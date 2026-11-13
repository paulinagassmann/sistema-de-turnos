<?php
session_start();
require_once __DIR__ . '/../config/csrf.php';

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
$ahora = new DateTimeImmutable('now', $zonaHoraria);
$listaPacientes = [];
$pacienteSeleccionado = null;
$proximosTurnos = [];
$historialTurnos = [];
$profesionales = [];
$errorAlCargarPacientes = false;
$pacienteNoEncontrado = false;

try {
    actualizarTurnosPendientesVencidos($conexion);

    // 2. Obtenemos la lista de profesionales para el filtro opcional
    $stmtProf = $conexion->prepare(
        "SELECT p.id, u.nombre, u.apellido
         FROM profesionales p
         JOIN usuarios u ON p.id = u.id AND u.rol = 'profesional'
         ORDER BY u.apellido ASC"
    );
    $stmtProf->execute();
    $profesionales = $stmtProf->fetchAll(PDO::FETCH_ASSOC);

    // 3. Obtenemos todos los pacientes registrados en la clínica
    $stmtPac = $conexion->prepare(
        "SELECT pac.id, u.nombre, u.apellido, u.dni, u.fecha_nacimiento, u.telefono, u.correo, pac.obra_social
         FROM pacientes pac
         JOIN usuarios u ON pac.id = u.id AND u.rol = 'paciente'
         ORDER BY u.apellido ASC, u.nombre ASC"
    );
    $stmtPac->execute();
    $listaPacientes = $stmtPac->fetchAll(PDO::FETCH_ASSOC);

    // 4. Determinamos el paciente seleccionado (por URL o el primero por defecto)
    $idRecibido = $_GET['id'] ?? null;
    $pacienteSeleccionadoId = $idRecibido === null
        ? ($listaPacientes[0]['id'] ?? null)
        : (is_scalar($idRecibido)
            ? filter_var((string) $idRecibido, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1]
            ])
            : false);

    if ($pacienteSeleccionadoId === false) {
        $pacienteNoEncontrado = true;
    } elseif ($pacienteSeleccionadoId !== null) {
        foreach ($listaPacientes as $pac) {
            if ((int) $pac['id'] === (int) $pacienteSeleccionadoId) {
                $pacienteSeleccionado = $pac;
                break;
            }
        }
        if (!$pacienteSeleccionado) {
            $pacienteNoEncontrado = true;
        }

        if ($pacienteSeleccionado) {
            // 5. Buscamos los próximos turnos activos de este paciente
            $stmtProx = $conexion->prepare(
                "SELECT t.id, t.fecha_hora, t.estado, u_prof.nombre AS prof_nombre, u_prof.apellido AS prof_apellido
                 FROM turnos t
                 JOIN profesionales p ON t.profesional_id = p.id
                 JOIN usuarios u_prof ON p.id = u_prof.id AND u_prof.rol = 'profesional'
                 WHERE t.paciente_id = :paciente_id
                   AND t.fecha_hora >= :ahora
                   AND t.estado IN ('pendiente', 'confirmado')
                 ORDER BY t.fecha_hora ASC"
            );
            $stmtProx->execute([
                ':paciente_id' => $pacienteSeleccionadoId,
                ':ahora' => $ahora->format('Y-m-d H:i:s')
            ]);
            $proximosTurnos = $stmtProx->fetchAll(PDO::FETCH_ASSOC);

            // 6. Buscamos el historial completo de turnos del paciente
            $stmtHist = $conexion->prepare(
                "SELECT t.fecha_hora, t.estado, u_prof.nombre AS prof_nombre, u_prof.apellido AS prof_apellido
                 FROM turnos t
                 JOIN profesionales p ON t.profesional_id = p.id
                 JOIN usuarios u_prof ON p.id = u_prof.id AND u_prof.rol = 'profesional'
                 WHERE t.paciente_id = :paciente_id
                 ORDER BY t.fecha_hora DESC"
            );
            $stmtHist->execute([':paciente_id' => $pacienteSeleccionadoId]);
            $historialTurnos = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Throwable $e) {
    error_log("Error al cargar la sección de pacientes del secretario: " . $e->getMessage());
    http_response_code(500);
    $errorAlCargarPacientes = true;
    $listaPacientes = [];
    $pacienteSeleccionado = null;
    $proximosTurnos = [];
    $historialTurnos = [];
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="description" content="Listado de todos los pacientes de Pausa.">
    <meta name="viewport" content="width=device-width, 
    initial-scale=1.0">
    <title> Pausa - Secretario </title>
    <link rel="icon" type="image/png" href="../img/favicon.png">
    <link rel="stylesheet" href="../css/styles.css?v=<?php echo filemtime(__DIR__ . '/../css/styles.css'); ?>">
    <script src="../js/main.js?v=<?php echo filemtime(__DIR__ . '/../js/main.js'); ?>" defer></script>
    <script src="../js/secretario.js?v=<?php echo filemtime(__DIR__ . '/../js/secretario.js'); ?>" defer></script>
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
                <a href="secretario-pacientes.php" class="item-menu activo">
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
                <form action="../procesos/cerrar-sesion.php" method="POST" class="form-logout">
                    <?php echo csrf_input(); ?>
                    <button type="submit" class="btn-logout">
                        <span>Cerrar sesión</span>
                    </button>
                </form>
            </div>

        </aside>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-secretario">

            <!-- TÍTULO DE LA SECCIÓN -->
            <header class="header-dashboard">
                <div class="texto-bienvenida-secretario">
                    <h1>Pacientes</h1>
                </div>
            </header>

            <?php if (($_GET['turno_cancelado'] ?? null) === '1'): ?>
                <p role="status" class="aviso-cancelacion-paciente">Turno cancelado. El horario quedó disponible para que otros pacientes lo soliciten.</p>
            <?php elseif (in_array($_GET['error'] ?? null, ['turno_no_cancelable', 'ocupado', 'servidor'], true)): ?>
                <p role="alert" class="error-cancelacion-paciente">No se pudo cancelar el turno. Actualizá la página e intentá nuevamente.</p>
            <?php endif; ?>

            <?php if ($errorAlCargarPacientes): ?>
                <p role="alert" style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
                    No se pudieron cargar los datos de pacientes. Intentá nuevamente más tarde.
                </p>
            <?php elseif ($pacienteNoEncontrado): ?>
                <p role="alert" style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
                    El paciente solicitado no existe o ya no está registrado.
                </p>
            <?php endif; ?>

            <!-- GRILLA DE DOS COLUMNAS (LISTA + DETALLE) -->
            <div class="grilla-gestion-pacientes">

                <!-- COLUMNA IZQUIERDA: BUSCADOR Y LISTA -->
                <section class="tarjeta-blanca">
                    
                    <!-- Buscador de pacientes -->
                    <div class="bloque-buscador-pacientes contenedor-buscador-sec" style="margin-top: 10px;">
                        <input type="search" placeholder="Buscar por nombre o DNI..." class="input-busqueda-paciente">
                    </div>

                    <!-- Lista de pacientes dinámica -->
                    <div class="lista-pacientes-lateral" data-source="database" style="margin-top: 15px;">
                        <?php if (!empty($listaPacientes)): ?>
                            <?php foreach ($listaPacientes as $pac): ?>
                                <?php 
                                    $esSeleccionado = (int) $pac['id'] === (int) ($pacienteSeleccionado['id'] ?? 0);
                                    $iniciales = htmlspecialchars(mb_strtoupper(mb_substr($pac['nombre'], 0, 1) . mb_substr($pac['apellido'], 0, 1)), ENT_QUOTES, 'UTF-8');
                                ?>
                                <a href="secretario-pacientes.php?id=<?php echo (int) $pac['id']; ?>" 
                                   class="item-paciente-lista <?php echo $esSeleccionado ? 'item-seleccionado-sec' : ''; ?>" 
                                   style="text-decoration: none; color: inherit; display: block; margin-bottom: 8px;">
                                    <div class="info-item-lista">
                                        <div class="avatar-mini-sec <?php echo $esSeleccionado ? 'avatar-activo' : ''; ?>"><?php echo $iniciales; ?></div>
                                        <div>
                                            <strong class="nombre-paciente-lista"><?php echo htmlspecialchars($pac['apellido'] . ', ' . $pac['nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                <span class="dni-paciente-lista">DNI: <?php echo htmlspecialchars($pac['dni'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php elseif (!$errorAlCargarPacientes && !$pacienteNoEncontrado): ?>
                            <p style="text-align: center; color: #666; padding: 20px;">No hay pacientes registrados en el sistema.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- COLUMNA DERECHA: DETALLE COMPLETO DEL PACIENTE -->
                <section class="tarjeta-blanca">
                    <h3 class="titulo-seccion-detalle">Detalle administrativo</h3>

                    <?php if ($pacienteSeleccionado): ?>
                        <!-- Datos personales expandidos -->
                        <div class="panel-datos-administrativos">
                            <div class="campo-detalle-admin">
                                <span class="label-admin">NOMBRE COMPLETO</span>
                                <span class="valor-admin"><?php echo htmlspecialchars($pacienteSeleccionado['nombre'] . ' ' . $pacienteSeleccionado['apellido'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="campo-detalle-admin">
                                <span class="label-admin">DNI / DOCUMENTO</span>
                                <span class="valor-admin"><?php echo htmlspecialchars($pacienteSeleccionado['dni'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="campo-detalle-admin">
                                <span class="label-admin">FECHA DE NACIMIENTO</span>
                                <span class="valor-admin"><?php echo !empty($pacienteSeleccionado['fecha_nacimiento']) ? date('d/m/Y', strtotime($pacienteSeleccionado['fecha_nacimiento'])) : 'No especificada'; ?></span>
                            </div>
                            <div class="campo-detalle-admin">
                                <span class="label-admin">TELÉFONO</span>
                                <span class="valor-admin"><?php echo htmlspecialchars($pacienteSeleccionado['telefono'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="campo-detalle-admin">
                                <span class="label-admin">CORREO ELECTRÓNICO</span>
                                <span class="valor-admin"><?php echo htmlspecialchars($pacienteSeleccionado['correo'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="campo-detalle-admin">
                                <span class="label-admin">OBRA SOCIAL</span>
                                <span class="valor-admin"><?php echo htmlspecialchars($pacienteSeleccionado['obra_social'] ?? 'Particular / Sin cobertura', ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>

                        <!-- Botón para editar perfil del paciente seleccionado -->
                        <a href="secretario-editar-perfil-paciente.php?id=<?php echo $pacienteSeleccionado['id']; ?>" class="btn-editar-perfil">Editar perfil</a>

                        <!-- Próximos turnos -->
                        <h4 class="subtitulo-detalle">Próximos turnos agendados</h4>
                        <div class="lista-turnos-proximos-sec">
                            <?php if (!empty($proximosTurnos)): ?>
                                <?php foreach ($proximosTurnos as $turnoProximo): ?>
                                    <?php
                                        $fechaProxFormateada = (new DateTimeImmutable($turnoProximo['fecha_hora'], $zonaHoraria))->setTimezone($zonaHoraria)->format('d M, Y - H:i');
                                    ?>
                                    <div class="item-turno-proximo-sec">
                                        <span class="texto-turno-sec"><?php echo htmlspecialchars($fechaProxFormateada, ENT_QUOTES, 'UTF-8'); ?> hs (Lic. <?php echo htmlspecialchars($turnoProximo['prof_apellido'], ENT_QUOTES, 'UTF-8'); ?>)</span>
                                        <button
                                            type="button"
                                            class="btn-cancelar-turno-paciente"
                                            data-turno-id="<?php echo (int) $turnoProximo['id']; ?>"
                                            data-turno-fecha="<?php echo htmlspecialchars($fechaProxFormateada, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-profesional="<?php echo htmlspecialchars($turnoProximo['prof_apellido'], ENT_QUOTES, 'UTF-8'); ?>"
                                        >Cancelar turno</button>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span style="color: #666; font-size: 0.9rem;">El paciente no tiene turnos pendientes próximos.</span>
                            <?php endif; ?>
                        </div>

                        <!-- Historial de turnos -->
                        <h4 class="subtitulo-detalle">Historial de turnos</h4>
                        <div class="lista-historial-sec">
                            <?php if (!empty($historialTurnos)): ?>
                                <?php foreach ($historialTurnos as $hist): ?>
                                    <?php 
                                        $fechaHistFormateada = (new DateTimeImmutable($hist['fecha_hora'], $zonaHoraria))->setTimezone($zonaHoraria)->format('d M, Y - H:i');
                                        $claseEstado = match($hist['estado']) {
                                            'confirmado' => 'texto-atendido',
                                            'ausente' => 'texto-ausente',
                                            'cancelado' => 'texto-alerta',
                                            default => 'texto-pendiente'
                                        };
                                    ?>
                                    <div class="item-historial-sec" style="margin-bottom: 6px;">
                                        <span><?php echo htmlspecialchars($fechaHistFormateada, ENT_QUOTES, 'UTF-8'); ?> hs (Lic. <?php echo htmlspecialchars($hist['prof_apellido'], ENT_QUOTES, 'UTF-8'); ?>)</span>
                                        <span class="<?php echo $claseEstado; ?>"><?php echo htmlspecialchars(ucfirst($hist['estado']), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="color: #666; font-size: 0.9rem;">No hay registros previos en el historial.</p>
                            <?php endif; ?>
                        </div>
                    <?php elseif (!$errorAlCargarPacientes && !$pacienteNoEncontrado): ?>
                        <p style="text-align: center; color: #666; margin-top: 40px;">Seleccioná un paciente de la lista de la izquierda para ver sus detalles.</p>
                    <?php elseif ($pacienteNoEncontrado): ?>
                        <p style="text-align: center; color: #666; margin-top: 40px;">Elegí un paciente válido de la lista para ver sus detalles.</p>
                    <?php endif; ?>
                </section>

            </div>

        </main>
    </div>

    <footer>
        <small>&copy; 2026 Pausa. Todos los derechos reservados.</small>
    </footer>

    <?php if ($pacienteSeleccionado): ?>
        <dialog id="modalCancelarTurnoPaciente" class="modal-dialog modal-cancelacion-turno" aria-labelledby="titulo-cancelar-turno-paciente" aria-describedby="texto-cancelar-turno-paciente">
            <form method="POST" action="../procesos/secretario/procesar-cancelar-turno-paciente.php" class="modal-card">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="turno_id" id="turno-paciente-id">
                <input type="hidden" name="paciente_id" value="<?php echo (int) $pacienteSeleccionado['id']; ?>">
                <div class="modal-icono-alerta" aria-hidden="true">!</div>
                <h2 class="modal-titulo" id="titulo-cancelar-turno-paciente">¿Cancelar este turno?</h2>
                <p class="modal-subtitulo" id="texto-cancelar-turno-paciente">
                    El turno de <strong id="fecha-cancelar-turno-paciente"></strong> se quitará de este paciente y quedará disponible para que lo soliciten otros pacientes.
                </p>
                <div class="modal-acciones">
                    <button type="button" class="btn-secundario-modal" id="cerrar-modal-cancelar-turno-paciente">Volver</button>
                    <button type="submit" class="btn-peligro-modal">Cancelar turno</button>
                </div>
            </form>
        </dialog>
    <?php endif; ?>

</body>
</html>