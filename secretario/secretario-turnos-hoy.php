<?php
session_start();

// 1. Validamos sesión de secretario
if (
    empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'secretario'
) {
    header("Location: ../index.html?error=sesion_requerida");
    exit();
}

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/turnos.php';
$zonaHoraria = new DateTimeZone('America/Argentina/Buenos_Aires');
$fechaHoyObj = new DateTimeImmutable('today', $zonaHoraria);
$inicioDia = $fechaHoyObj->format('Y-m-d H:i:s');
$finDia = $fechaHoyObj->modify('+1 day')->format('Y-m-d H:i:s');

$estadoFiltro = $_GET['estado'] ?? 'todos';
if (!is_string($estadoFiltro) || !in_array($estadoFiltro, ['todos', 'pendiente', 'confirmado', 'asistido', 'ausente', 'cancelado'], true)) {
    $estadoFiltro = 'todos';
}
if ($estadoFiltro === 'asistido') {
    $estadoFiltro = 'confirmado';
}

$profesionalRecibido = $_GET['profesional'] ?? 'todos';
$profesionalFiltro = 'todos';
if (is_string($profesionalRecibido) && $profesionalRecibido !== 'todos') {
    $idProfesional = filter_var($profesionalRecibido, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);
    if ($idProfesional !== false) {
        $profesionalFiltro = (string) $idProfesional;
    }
}

$profesionales = [];
$turnosHoy = [];
$contadores = [
    'todos' => 0,
    'pendiente' => 0,
    'confirmado' => 0,
    'ausente' => 0,
    'cancelado' => 0
];
$errorAlCargarTurnos = false;
$erroresTurno = [
    'solicitud_invalida' => 'La solicitud no es válida. Actualizá la página e intentá nuevamente.',
    'turno_no_actualizable' => 'El turno ya no se puede actualizar; la lista puede haber cambiado.',
    'servidor' => 'No se pudo actualizar el estado del turno. Intentá nuevamente.'
];
$mensajeErrorTurno = isset($_GET['error']) && is_string($_GET['error'])
    ? ($erroresTurno[$_GET['error']] ?? null)
    : null;
if (empty($_SESSION['csrf_estado_turno'])) {
    $_SESSION['csrf_estado_turno'] = bin2hex(random_bytes(32));
}

try {
    actualizarTurnosPendientesVencidos($conexion);

    // 2. Obtenemos profesionales para el filtro superior
    $stmtProf = $conexion->prepare(
        "SELECT p.id, u.nombre, u.apellido
         FROM profesionales p
         JOIN usuarios u ON p.id = u.id AND u.rol = 'profesional'
         ORDER BY u.apellido ASC"
    );
    $stmtProf->execute();
    $profesionales = $stmtProf->fetchAll(PDO::FETCH_ASSOC);

    if (
        $profesionalFiltro !== 'todos'
        && !in_array((int) $profesionalFiltro, array_map('intval', array_column($profesionales, 'id')), true)
    ) {
        $profesionalFiltro = 'todos';
    }

    // 3. Contadores dinámicos para los filtros de estado del día
    $sqlCont = "SELECT estado, COUNT(*) as total
                FROM turnos
                WHERE fecha_hora >= :inicio AND fecha_hora < :fin";
    $paramsCont = [':inicio' => $inicioDia, ':fin' => $finDia];
    if ($profesionalFiltro !== 'todos') {
        $sqlCont .= " AND profesional_id = :prof_id";
        $paramsCont[':prof_id'] = (int) $profesionalFiltro;
    }
    $sqlCont .= " GROUP BY estado";
    $stmtCont = $conexion->prepare(
        $sqlCont
    );
    $stmtCont->execute($paramsCont);
    $rawContadores = $stmtCont->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rawContadores as $c) {
        $est = $c['estado'];
        $tot = (int)$c['total'];
        if (isset($contadores[$est])) {
            $contadores[$est] = $tot;
        }
        $contadores['todos'] += $tot;
    }

    // 4. Consulta principal de turnos de hoy con filtros opcionales
    $sql = "SELECT t.id, t.fecha_hora, t.estado, 
                   u_pac.nombre AS pac_nombre, u_pac.apellido AS pac_apellido, u_pac.dni AS pac_dni,
                   p_pac.obra_social,
                   u_prof.nombre AS prof_nombre, u_prof.apellido AS prof_apellido
            FROM turnos t
            JOIN usuarios u_pac ON t.paciente_id = u_pac.id AND u_pac.rol = 'paciente'
            LEFT JOIN pacientes p_pac ON t.paciente_id = p_pac.id
            JOIN profesionales p_prof ON t.profesional_id = p_prof.id
            JOIN usuarios u_prof ON p_prof.id = u_prof.id AND u_prof.rol = 'profesional'
            WHERE t.fecha_hora >= :inicio AND t.fecha_hora < :fin";

    $params = [':inicio' => $inicioDia, ':fin' => $finDia];

    if ($profesionalFiltro !== 'todos' && ctype_digit($profesionalFiltro)) {
        $sql .= " AND t.profesional_id = :prof_id";
        $params[':prof_id'] = (int)$profesionalFiltro;
    }

    if ($estadoFiltro !== 'todos') {
        $sql .= " AND t.estado = :estado";
        $params[':estado'] = $estadoFiltro;
    }

    $sql .= " ORDER BY t.fecha_hora ASC";

    $stmtTurnos = $conexion->prepare($sql);
    $stmtTurnos->execute($params);
    $turnosHoy = $stmtTurnos->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log("Error al cargar turnos de hoy: " . $e->getMessage());
    http_response_code(500);
    $errorAlCargarTurnos = true;
    $profesionales = [];
    $turnosHoy = [];
    $contadores = array_fill_keys(array_keys($contadores), 0);
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="description" content="Vista de los turnos del día actual para el secretario.">
    <meta name="viewport" content="width=device-width, 
    initial-scale=1.0">
    <title> Pausa - Secretario </title>
    <link rel="stylesheet" href="../css/styles.css"> <!-- Enlaza el archivo styles.css para agregar estilos -->
    <script src="../js/main.js" defer></script>
    <script src="../js/secretario.js?v=turnos-hoy-database-20261106-2" defer></script>
</head>

<body class="body-dashboard">

    <div class="layout-secretario">
        
        <!-- BARRA LATERAL (SIDEBAR) -->
        <aside class="sidebar-secretario">
            <div class="bloque-marca">
                <span class="logo-texto">Pausa</span>
                <span class="subtitulo-logo">Panel Secretario</span>
            </div>

            <!-- Botón principal de acción rápida (dar turno) -->
            <div class="bloque-accion-sidebar">
                <a href="secretario-dar-turno.php" class="btn-dar-turno">+ Dar turno</a>
            </div>

            <!-- Navegación lateral -->
            <nav class="menu-lateral">
                <a href="secretario.php" class="item-menu">
                    <span>Inicio / Hoy</span>
                </a>
                <a href="secretario-pacientes.php" class="item-menu">
                    <span>Pacientes</span>
                </a>
                <a href="secretario-agendas.html" class="item-menu">
                    <span>Agendas </span>
                </a>
                <a href="secretario-registrar-profesional.html" class="item-menu">
                    <span>Alta Profesional </span>
                </a>
                <a href="secretario-configuracion.php" class="item-menu">
                    <span>Configuración</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="../index.html" class="btn-logout">
                    <span>Cerrar sesión</span>
                </a>
            </div>

        </aside>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-secretario">

            <!-- CABECERA: TÍTULO, FECHA Y FILTRO DE PROFESIONAL -->
            <header class="header-gestion-turnos">
                <div class="titulo-con-fecha">
                    <h1>Turnos Hoy</h1>
                    <span class="badge-fecha-hoy"> <?php echo htmlspecialchars($fechaHoyObj->format('d/m/Y'), ENT_QUOTES, 'UTF-8'); ?> </span>
                </div>

                <div class="filtro-profesional-top">
                    <form method="GET" action="secretario-turnos-hoy.php" id="formFiltroProf">
                        <input type="hidden" name="estado" value="<?php echo htmlspecialchars($estadoFiltro, ENT_QUOTES, 'UTF-8'); ?>">
                        <select name="profesional" class="input-form select-profesional-top" onchange="this.form.submit()">
                            <option value="todos">Todos los Profesionales</option>
                            <?php foreach ($profesionales as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo $profesionalFiltro == $p['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['nombre'] . ' ' . $p['apellido']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </header>

            <?php if ($errorAlCargarTurnos): ?>
                <p role="alert" style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
                    No se pudieron cargar los turnos de hoy. Intentá nuevamente más tarde.
                </p>
            <?php endif; ?>
            <?php if ($mensajeErrorTurno !== null): ?>
                <p role="alert" style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
                    <?php echo htmlspecialchars($mensajeErrorTurno, ENT_QUOTES, 'UTF-8'); ?>
                </p>
            <?php endif; ?>

            <!-- GRILLA DE DOS COLUMNAS (ESTADOS A LA IZQUIERDA, LISTA A LA DERECHA) -->
            <div class="grilla-turnos-hoy">

                <!-- COLUMNA 1: FILTROS POR ESTADO -->
                <aside class="tarjeta-blanca tarjeta-filtros-estado">
                    <h3 class="titulo-filtro-estado">ESTADO</h3>
                    
                    <div class="lista-botones-estado">
                        <a href="secretario-turnos-hoy.php?estado=todos&amp;profesional=<?php echo rawurlencode($profesionalFiltro); ?>" class="btn-estado-filtro <?php echo $estadoFiltro === 'todos' ? 'estado-activo' : ''; ?>" style="text-decoration: none;">
                            <span>Todos</span>
                            <span class="contador-estado"><?php echo $contadores['todos']; ?></span>
                        </a>
                        <a href="secretario-turnos-hoy.php?estado=pendiente&amp;profesional=<?php echo rawurlencode($profesionalFiltro); ?>" class="btn-estado-filtro <?php echo $estadoFiltro === 'pendiente' ? 'estado-activo' : ''; ?>" style="text-decoration: none;">
                            <span>Pendientes</span>
                            <span class="contador-estado"><?php echo $contadores['pendiente']; ?></span>
                        </a>
                        <a href="secretario-turnos-hoy.php?estado=confirmado&amp;profesional=<?php echo rawurlencode($profesionalFiltro); ?>" class="btn-estado-filtro <?php echo $estadoFiltro === 'confirmado' ? 'estado-activo' : ''; ?>" style="text-decoration: none;">
                            <span>Confirmados</span>
                            <span class="contador-estado"><?php echo $contadores['confirmado']; ?></span>
                        </a>
                        <a href="secretario-turnos-hoy.php?estado=ausente&amp;profesional=<?php echo rawurlencode($profesionalFiltro); ?>" class="btn-estado-filtro <?php echo $estadoFiltro === 'ausente' ? 'estado-activo' : ''; ?>" style="text-decoration: none;">
                            <span>Ausente</span>
                            <span class="contador-estado"><?php echo $contadores['ausente']; ?></span>
                        </a>
                        <a href="secretario-turnos-hoy.php?estado=cancelado&amp;profesional=<?php echo rawurlencode($profesionalFiltro); ?>" class="btn-estado-filtro texto-alerta <?php echo $estadoFiltro === 'cancelado' ? 'estado-activo' : ''; ?>" style="text-decoration: none;">
                            <span>Cancelados</span>
                            <span class="contador-estado"><?php echo $contadores['cancelado']; ?></span>
                        </a>
                    </div>
                </aside>

                <!-- COLUMNA 2: AGENDA DEL DÍA (LISTA DE TURNOS) -->
                <section class="tarjeta-blanca tarjeta-agenda-dia">
                    <div class="cabecera-agenda-dia">
                        <h2>Agenda del Día</h2>
                        <div class="acciones-agenda">
                            <button type="button" class="btn-icono-agenda" title="Actualizar" onclick="window.location.reload();">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-refresh"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4" /><path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4" /></svg>
                            </button>
                            <button type="button" class="btn-icono-agenda" title="Imprimir" onclick="window.print();">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-printer"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M17 17h2a2 2 0 0 0 2 -2v-4a2 2 0 0 0 -2 -2h-14a2 2 0 0 0 -2 2v4a2 2 0 0 0 2 2h2" /><path d="M17 9v-4a2 2 0 0 0 -2 -2h-6a2 2 0 0 0 -2 2v4" /><path d="M7 15a2 2 0 0 1 2 -2h6a2 2 0 0 1 2 2v4a2 2 0 0 1 -2 2h-6a2 2 0 0 1 -2 -2l0 -4" /></svg>
                            </button>
                        </div>
                    </div>

                    <div class="lista-turnos-operativa" data-source="database">
                        <?php if (!empty($turnosHoy)): ?>
                            <?php foreach ($turnosHoy as $t): ?>
                                <?php 
                                    $fechaHoraTurno = new DateTimeImmutable($t['fecha_hora'], $zonaHoraria);
                                    $hora = $fechaHoraTurno->format('H:i');
                                    $iniciales = mb_strtoupper(mb_substr($t['pac_nombre'], 0, 1) . mb_substr($t['pac_apellido'], 0, 1));
                                    $estado = $t['estado'];
                                    $etiquetaEstado = mb_strtoupper($estado);
                                    
                                    $bordeClase = match($estado) {
                                        'pendiente' => 'borde-pendiente',
                                        'cancelado' => 'borde-cancelado',
                                        'confirmado' => 'borde-asistio',
                                        'ausente' => 'borde-ausente',
                                        default => 'borde-pendiente'
                                    };
                                    $chipClase = match($estado) {
                                        'pendiente' => 'estado-pendiente',
                                        'cancelado' => 'estado-cancelado',
                                        'confirmado' => 'estado-asistio',
                                        'ausente' => 'estado-ausente',
                                        default => 'estado-pendiente'
                                    };
                                    $articuloClase = match($estado) {
                                        'cancelado' => 'item-turno-operativo turno-cancelado-op',
                                        'confirmado' => 'item-turno-operativo turno-asistido-op',
                                        'ausente' => 'item-turno-operativo turno-ausente-op',
                                        default => 'item-turno-operativo'
                                    };
                                ?>
                                <article class="<?php echo $articuloClase; ?>">
                                    <div class="bloque-hora-estado <?php echo $bordeClase; ?>">
                                        <span class="hora-turno-op"><?php echo $hora; ?></span>
                                        <span class="chip-estado <?php echo $chipClase; ?>"><?php echo htmlspecialchars($etiquetaEstado, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>

                                    <div class="avatar-mini-sec"><?php echo htmlspecialchars($iniciales, ENT_QUOTES, 'UTF-8'); ?></div>

                                    <div class="detalles-turno-op">
                                        <strong class="nombre-paciente-op"><?php echo htmlspecialchars($t['pac_apellido'] . ', ' . $t['pac_nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <span class="info-secundaria-op">
                                            DNI: <?php echo htmlspecialchars($t['pac_dni'], ENT_QUOTES, 'UTF-8'); ?> • <?php echo htmlspecialchars($t['prof_nombre'] . ' ' . $t['prof_apellido'], ENT_QUOTES, 'UTF-8'); ?>
                                            <?php if (!empty($t['obra_social'])): ?>
                                                • <span class="badge-obra-social"><?php echo htmlspecialchars($t['obra_social'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </div>

                                    <div class="botones-accion-turno">
                                        <?php if ($estado === 'pendiente'): ?>
                                            <form action="../procesos/secretario/cambiar-estado-turno.php" method="POST" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_estado_turno'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="turno_id" value="<?php echo (int) $t['id']; ?>">
                                                <input type="hidden" name="estado" value="cancelado">
                                                <input type="hidden" name="filtro_estado" value="<?php echo htmlspecialchars($estadoFiltro, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="filtro_profesional" value="<?php echo htmlspecialchars($profesionalFiltro, ENT_QUOTES, 'UTF-8'); ?>">
                                                <button type="submit" class="btn-accion-turno btn-cancelar-turno" title="Cancelar turno" aria-label="Cancelar turno">✕</button>
                                            </form>
                                            <form action="../procesos/secretario/cambiar-estado-turno.php" method="POST" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_estado_turno'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="turno_id" value="<?php echo (int) $t['id']; ?>">
                                                <input type="hidden" name="estado" value="confirmado">
                                                <input type="hidden" name="filtro_estado" value="<?php echo htmlspecialchars($estadoFiltro, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="filtro_profesional" value="<?php echo htmlspecialchars($profesionalFiltro, ENT_QUOTES, 'UTF-8'); ?>">
                                                <button type="submit" class="btn-accion-turno btn-asistio-turno" title="Confirmar turno">✓</button>
                                            </form>
                                        <?php elseif (in_array($estado, ['confirmado', 'cancelado'], true)): ?>
                                            <form action="../procesos/secretario/cambiar-estado-turno.php" method="POST" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_estado_turno'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="turno_id" value="<?php echo (int) $t['id']; ?>">
                                                <input type="hidden" name="estado" value="pendiente">
                                                <input type="hidden" name="filtro_estado" value="<?php echo htmlspecialchars($estadoFiltro, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="filtro_profesional" value="<?php echo htmlspecialchars($profesionalFiltro, ENT_QUOTES, 'UTF-8'); ?>">
                                                <button type="submit" class="btn-deshacer-turno">Deshacer</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="texto-ausente">Ausente</span>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="text-align: center; color: #666; padding: 40px;">No hay turnos registrados para el día de hoy con los filtros seleccionados.</p>
                        <?php endif; ?>
                    </div>
                </section>

            </div>

        </main>
    </div>

    <footer>
        <small>&copy; 2026 Pausa. Todos los derechos reservados.</small>
    </footer>

</body>
</html>