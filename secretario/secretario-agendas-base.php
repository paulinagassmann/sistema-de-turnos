<?php
session_start();

// valido sesión de secretario
if (
    empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'secretario'
) {
    header("Location: ../index.html?error=sesion_requerida");
    exit();
}

require_once __DIR__ . '/../config/conexion.php';

$profesionalIdRecibido = $_GET['profesional_id'] ?? null;
$profesionalIdSeleccionado = is_scalar($profesionalIdRecibido)
    ? filter_var((string) $profesionalIdRecibido, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : null;
$profesionalIdSeleccionado = $profesionalIdSeleccionado === false ? null : $profesionalIdSeleccionado;

if (empty($_SESSION['csrf_agendas_base'])) {
    $_SESSION['csrf_agendas_base'] = bin2hex(random_bytes(32));
}

$profesionales = [];
$agendasActuales = [];

try {
    // obtengo todos los profesionales de la base de datos
    $stmtProf = $conexion->prepare(
        "SELECT p.id, u.nombre, u.apellido, p.especialidad 
         FROM profesionales p 
         JOIN usuarios u ON p.id = u.id AND u.rol = 'profesional'
         ORDER BY u.apellido ASC"
    );
    $stmtProf->execute();
    $profesionales = $stmtProf->fetchAll(PDO::FETCH_ASSOC);

    $profesionalEncontrado = false;
    if ($profesionalIdSeleccionado !== null) {
        foreach ($profesionales as $profesional) {
            if ((int) $profesional['id'] === (int) $profesionalIdSeleccionado) {
                $profesionalEncontrado = true;
                break;
            }
        }
    }

    if (!$profesionalEncontrado && !empty($profesionales)) {
        $profesionalIdSeleccionado = $profesionales[0]['id'];
        $profesionalEncontrado = true;
    }

    if ($profesionalEncontrado) {
        $stmtAgenda = $conexion->prepare(
            "SELECT dia_semana, hora_inicio, hora_fin, duracion_turno 
             FROM agendas 
             WHERE profesional_id = :prof_id"
        );
        $stmtAgenda->execute([':prof_id' => $profesionalIdSeleccionado]);
        $agendasActuales = $stmtAgenda->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    error_log("Error al cargar agendas en secretario: " . $e->getMessage());
}

// convierto las agendas de la BD a un formato clave-valor rápido (ej: $mapeoAgendas['lunes'] = ['inicio' => '08:00', 'fin' => '12:00'])
$mapeoAgendas = [];
foreach ($agendasActuales as $ag) {
    $diaLower = strtolower(strtr(trim($ag['dia_semana']), [
        'Á' => 'a', 'á' => 'a',
        'É' => 'e', 'é' => 'e',
        'Í' => 'i', 'í' => 'i',
        'Ó' => 'o', 'ó' => 'o',
        'Ú' => 'u', 'ú' => 'u'
    ]));
    $mapeoAgendas[$diaLower][] = [
        'inicio' => substr($ag['hora_inicio'], 0, 5),
        'fin' => substr($ag['hora_fin'], 0, 5)
    ];
}

$diasSemanaList = [
    'lunes' => 'Lunes',
    'martes' => 'Martes',
    'miercoles' => 'Miércoles',
    'jueves' => 'Jueves',
    'viernes' => 'Viernes'
];

$mensajesErrorAgendas = [
    'solicitud_invalida' => 'El formulario venció o la solicitud no es válida. Recargá la página e intentá nuevamente.',
    'profesional_invalido' => 'Seleccioná un profesional válido antes de guardar los horarios.',
    'datos_invalidos' => 'Revisá los horarios: cada franja debe tener inicio y fin válidos, sin superponerse.',
    'franjas_incompletas' => 'No se recibieron todas las franjas horarias. Recargá la página e intentá guardar nuevamente.',
    'horario_invalido' => 'Revisá el horario: cada franja debe tener una hora de inicio y fin válidas, con el fin posterior al inicio.',
    'franjas_superpuestas' => 'Hay franjas horarias superpuestas en uno de los días. Ajustalas antes de guardar.',
    'servidor' => 'No se pudieron guardar los horarios por un error del servidor. No se aplicaron cambios.'
];
$codigoErrorAgenda = is_string($_GET['error'] ?? null) ? $_GET['error'] : '';
$mensajeErrorAgenda = $mensajesErrorAgendas[$codigoErrorAgenda]
    ?? ($codigoErrorAgenda !== '' ? 'Ocurrió un error al guardar los horarios.' : null);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="description" content="Vista de secretario para gestionar las agendas de los profesionales,
    en específico los horarios base.">
    <meta name="viewport" content="width=device-width, 
    initial-scale=1.0">
    <title> Pausa - Secretario </title>
    <link rel="stylesheet" href="../css/styles.css"> <!-- Enlaza el archivo styles.css para agregar estilos -->
    <script src="../js/main.js" defer></script>
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

            <header class="header-dashboard">
                <div class="texto-bienvenida-secretario">
                    <h1>Configurar horarios base</h1>
                    <p>Define los horarios estándar para los profesionales desde hoy hasta tres meses en el futuro.</p>
                </div>
            </header>

            <?php if (isset($_GET['exito']) && $_GET['exito'] === '1'): ?>
                <p style="background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin: 0 0 20px 0;">¡Horarios base guardados y actualizados con éxito!</p>
            <?php endif; ?>
            <?php if ($mensajeErrorAgenda !== null): ?>
                <p style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin: 0 0 20px 0;"><?php echo htmlspecialchars($mensajeErrorAgenda, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>

            <form action="../procesos/secretario/procesar-agendas-base.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_agendas_base'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="profesional_id" value="<?php echo htmlspecialchars((string) ($profesionalIdSeleccionado ?? ''), ENT_QUOTES, 'UTF-8'); ?>">

                <div class="contenedor-config-agenda">

                    <!-- TARJETA 1: SELECTOR DE PROFESIONAL -->
                    <section class="tarjeta-blanca tarjeta-seleccion-prof">
                        <div class="grupo-input">
                            <label for="filtro-profesional" class="label-filtro">PROFESIONAL</label>
                            <select id="filtro-profesional" class="input-form" onchange="window.location.href='secretario-agendas-base.php?profesional_id=' + encodeURIComponent(this.value);">
                                <?php foreach ($profesionales as $p): ?>
                                    <option value="<?php echo $p['id']; ?>" <?php echo $profesionalIdSeleccionado == $p['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['nombre'] . ' ' . $p['apellido']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </section>

                    <!-- TARJETA 2: LISTA DE DIAS Y HORARIOS -->
                    <section class="tarjeta-blanca tarjeta-dias-semana">
                        
                        <?php 
                        $contadorDias = 0;
                        foreach ($diasSemanaList as $keyDia => $nombreDia): 
                            $contadorDias++;
                            $franjasDia = $mapeoAgendas[$keyDia] ?? [];
                            $tieneHorarios = !empty($franjasDia);
                            $checked = $tieneHorarios ? 'checked' : '';
                            $claseFila = $tieneHorarios ? 'fila-horario-dia' : 'fila-horario-dia dia-inactivo';
                        ?>
                            <div class="<?php echo $claseFila; ?>">
                                <div class="dia-info-switch">
                                    <label class="switch-toggle">
                                        <input type="checkbox" name="dias[<?php echo $keyDia; ?>][activo]" value="1" <?php echo $checked; ?> onchange="this.closest('.fila-horario-dia').classList.toggle('dia-inactivo', !this.checked)">
                                        <span class="slider-toggle"></span>
                                    </label>
                                    <span class="nombre-dia"><?php echo $nombreDia; ?></span>
                                </div>
                                <div class="franjas-horarias-container">
                                    <?php if ($tieneHorarios): ?>
                                        <?php foreach ($franjasDia as $idx => $franja): ?>
                                            <div class="franja-item">
                                                <input type="time" name="dias[<?php echo $keyDia; ?>][inicio][]" value="<?php echo $franja['inicio']; ?>" class="input-time">
                                                <span class="separador-a">a</span>
                                                <input type="time" name="dias[<?php echo $keyDia; ?>][fin][]" value="<?php echo $franja['fin']; ?>" class="input-time">
                                                <button type="button" class="btn-accion-horario" title="Franja horaria">➕</button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="franja-item">
                                            <input type="time" name="dias[<?php echo $keyDia; ?>][inicio][]" value="08:00" class="input-time">
                                            <span class="separador-a">a</span>
                                            <input type="time" name="dias[<?php echo $keyDia; ?>][fin][]" value="12:00" class="input-time">
                                            <button type="button" class="btn-accion-horario" title="Franja horaria">➕</button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($contadorDias < count($diasSemanaList)): ?>
                                <hr class="divisor-horarios">
                            <?php endif; ?>
                        <?php endforeach; ?>

                    </section>

                    <!-- BOTÓN DE GUARDAR PLANTILLA AL PIE -->
                    <div class="contenedor-guardar-horarios">
                        <button type="submit" class="btn-siguiente-paso">Guardar horarios base</button>
                    </div>

                </div>
            </form>

        </main>
    </div>

    <footer>
        <small>&copy; 2026 Pausa. Todos los derechos reservados.</small>
    </footer>

</body>
</html>