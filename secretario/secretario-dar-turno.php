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

$profesionalIdRecibido = $_GET['profesional_id'] ?? null;
$profesionalId = is_scalar($profesionalIdRecibido)
    ? filter_var((string) $profesionalIdRecibido, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;

$profesionales = [];
$pacientes = [];
$profesional = null;
$agendas = [];
$turnosOcupados = [];
$excepciones = [];
$errorAlCargarAgenda = false;

try {
    actualizarTurnosPendientesVencidos($conexion);

    // Obtenemos todos los profesionales para el Paso 2
    $stmtProfList = $conexion->prepare(
        "SELECT p.id, u.nombre, u.apellido, p.especialidad 
         FROM profesionales p 
         JOIN usuarios u ON p.id = u.id AND u.rol = 'profesional'
         ORDER BY u.apellido ASC"
    );
    $stmtProfList->execute();
    $profesionales = $stmtProfList->fetchAll(PDO::FETCH_ASSOC);

    // Obtenemos todos los pacientes para el Paso 1
    $stmtPacList = $conexion->prepare(
        "SELECT pac.id, u.nombre, u.apellido, u.dni 
         FROM pacientes pac 
         JOIN usuarios u ON pac.id = u.id AND u.rol = 'paciente'
         ORDER BY u.apellido ASC"
    );
    $stmtPacList->execute();
    $pacientes = $stmtPacList->fetchAll(PDO::FETCH_ASSOC);

    // Si hay un profesional seleccionado, cargamos su agenda y turnos ocupados para el Paso 3
    if ($profesionalId) {
        $stmtProf = $conexion->prepare(
            'SELECT u.nombre, u.apellido, p.especialidad
             FROM profesionales p
             JOIN usuarios u ON p.id = u.id
             WHERE p.id = :id AND u.rol = :rol'
        );
        $stmtProf->execute([':id' => $profesionalId, ':rol' => 'profesional']);
        $profesional = $stmtProf->fetch(PDO::FETCH_ASSOC);

        if ($profesional) {
            $stmtAgenda = $conexion->prepare(
                'SELECT dia_semana, hora_inicio, hora_fin, duracion_turno
                 FROM agendas
                 WHERE profesional_id = :profesional_id'
            );
            $stmtAgenda->execute([':profesional_id' => $profesionalId]);
            $agendas = $stmtAgenda->fetchAll(PDO::FETCH_ASSOC);

            $stmtTurnos = $conexion->prepare(
                "SELECT DATE_FORMAT(fecha_hora, '%Y-%m-%d %H:%i') AS fecha_hora
                 FROM turnos
                 WHERE profesional_id = :profesional_id
                   AND fecha_hora >= :ahora
                   AND estado IN ('pendiente', 'confirmado')"
            );
            $stmtTurnos->execute([
                ':profesional_id' => $profesionalId,
                ':ahora' => $ahora->format('Y-m-d H:i:s')
            ]);
            $turnosOcupados = $stmtTurnos->fetchAll(PDO::FETCH_COLUMN);

            $stmtExcepciones = $conexion->prepare(
                'SELECT tipo, fecha_hora_inicio, fecha_hora_fin
                 FROM excepciones
                 WHERE profesional_id = :profesional_id
                   AND fecha_hora_inicio < :hasta
                   AND fecha_hora_fin >= :desde'
            );
            $stmtExcepciones->execute([
                ':profesional_id' => $profesionalId,
                ':desde' => $ahora->format('Y-m-d H:i:s'),
                ':hasta' => $ahora->modify('+90 days')->format('Y-m-d H:i:s')
            ]);
            $excepciones = $stmtExcepciones->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Throwable $e) {
    error_log("Error al cargar datos en dar turno secretario: " . $e->getMessage());
    http_response_code(500);
    $errorAlCargarAgenda = true;
}

if ($profesionalId && !$profesional) {
    $profesionalId = false;
}

// Lógica de cálculo de horarios disponibles (idéntica al flujo del paciente)
$horariosPorDia = [];
$duracionesPorDia = [];
$diasSemana = [
    'lunes' => 1, 'lun' => 1, 'monday' => 1,
    'martes' => 2, 'mar' => 2, 'tuesday' => 2,
    'miercoles' => 3, 'mie' => 3, 'wednesday' => 3,
    'jueves' => 4, 'jue' => 4, 'thursday' => 4,
    'viernes' => 5, 'vie' => 5, 'friday' => 5,
    'sabado' => 6, 'sab' => 6, 'saturday' => 6,
    'domingo' => 7, 'dom' => 7, 'sunday' => 7
];
$normalizarDia = static function ($dia) use ($diasSemana) {
    $dia = strtolower(trim((string) $dia));
    $dia = strtr($dia, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
    return $diasSemana[$dia] ?? (ctype_digit($dia) && (int) $dia >= 1 && (int) $dia <= 7 ? (int) $dia : null);
};

if (!empty($agendas)) {
    foreach ($agendas as $agenda) {
        $dia = $normalizarDia($agenda['dia_semana'] ?? '');
        $inicio = DateTimeImmutable::createFromFormat('!H:i:s', (string) $agenda['hora_inicio'])
            ?: DateTimeImmutable::createFromFormat('!H:i', (string) $agenda['hora_inicio']);
        $fin = DateTimeImmutable::createFromFormat('!H:i:s', (string) $agenda['hora_fin'])
            ?: DateTimeImmutable::createFromFormat('!H:i', (string) $agenda['hora_fin']);
        $duracion = filter_var($agenda['duracion_turno'], FILTER_VALIDATE_INT);

        if ($dia === null || !$inicio || !$fin || $duracion === false || $duracion < 1 || $duracion > 480) {
            continue;
        }

        $inicioMinutos = ((int) $inicio->format('H') * 60) + (int) $inicio->format('i');
        $finMinutos = ((int) $fin->format('H') * 60) + (int) $fin->format('i');
        for ($minuto = $inicioMinutos; $minuto + $duracion <= $finMinutos; $minuto += $duracion) {
            $horario = sprintf('%02d:%02d', intdiv($minuto, 60), $minuto % 60);
            $horariosPorDia[$dia][] = $horario;
            $duracionesPorDia[$dia][$horario] = $duracion;
        }
    }
}

$ocupados = [];
foreach ($turnosOcupados ?? [] as $fechaHoraOcupada) {
    $ocupados[(string) $fechaHoraOcupada] = true;
}

$horariosPorFecha = [];
$fechaInicio = $ahora->setTime(0, 0);
$fechaHoy = $ahora->format('Y-m-d');
$horaActual = $ahora->format('H:i:s');

for ($diaOffset = 0; $diaOffset < 90; $diaOffset++) {
    $fecha = $fechaInicio->modify("+{$diaOffset} days");
    $fechaFormateada = $fecha->format('Y-m-d');
    $numeroDia = (int) $fecha->format('N');

    foreach (array_unique($horariosPorDia[$numeroDia] ?? []) as $horario) {
        $claveOcupado = $fechaFormateada . ' ' . $horario;
        if (isset($ocupados[$claveOcupado])) {
            continue;
        }
        if ($fechaFormateada === $fechaHoy && $horario <= $horaActual) {
            continue;
        }

        $inicioSlot = $claveOcupado . ':00';
        $duracionSlot = $duracionesPorDia[$numeroDia][$horario] ?? 30;
        $finSlot = (new DateTimeImmutable($inicioSlot, $zonaHoraria))
            ->modify("+{$duracionSlot} minutes")
            ->format('Y-m-d H:i:s');
        $bloqueado = false;
        foreach ($excepciones as $excepcion) {
            if (
                $excepcion['tipo'] === 'bloqueo'
                && $excepcion['fecha_hora_inicio'] < $finSlot
                && $excepcion['fecha_hora_fin'] > $inicioSlot
            ) {
                $bloqueado = true;
                break;
            }
        }
        if ($bloqueado) {
            continue;
        }

        $horariosPorFecha[$fechaFormateada][] = $horario;
    }

    foreach ($excepciones as $excepcion) {
        if ($excepcion['tipo'] !== 'extra' || substr($excepcion['fecha_hora_inicio'], 0, 10) !== $fechaFormateada) {
            continue;
        }
        $horarioExtra = substr($excepcion['fecha_hora_inicio'], 11, 5);
        $claveExtra = $fechaFormateada . ' ' . $horarioExtra;
        $bloqueado = false;
        foreach ($excepciones as $otraExcepcion) {
            if (
                $otraExcepcion['tipo'] === 'bloqueo'
                && $otraExcepcion['fecha_hora_inicio'] < $excepcion['fecha_hora_fin']
                && $otraExcepcion['fecha_hora_fin'] > $excepcion['fecha_hora_inicio']
            ) {
                $bloqueado = true;
                break;
            }
        }
        if (!$bloqueado && !isset($ocupados[$claveExtra]) && ($fechaFormateada !== $fechaHoy || $horarioExtra > $horaActual)) {
            $horariosPorFecha[$fechaFormateada][] = $horarioExtra;
        }
    }

    if (isset($horariosPorFecha[$fechaFormateada])) {
        $horariosPorFecha[$fechaFormateada] = array_values(array_unique($horariosPorFecha[$fechaFormateada]));
        sort($horariosPorFecha[$fechaFormateada]);
    }
}

$datosHorarios = htmlspecialchars(
    json_encode($horariosPorFecha, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT),
    ENT_QUOTES,
    'UTF-8'
);

$erroresTurno = [
    'campos_vacios' => 'Completá todos los datos del turno.',
    'solicitud_invalida' => 'La solicitud venció o no es válida. Recargá la página e intentá nuevamente.',
    'fecha_vencida' => 'La fecha y hora del turno deben ser futuras.',
    'horario_invalido' => 'El horario seleccionado no está disponible en la agenda del profesional.',
    'ocupado' => 'Ese horario ya está ocupado. Elegí otro.',
    'servidor' => 'No se pudo registrar el turno. Intentá nuevamente.'
];
$errorTurno = is_string($_GET['error'] ?? null)
    ? ($erroresTurno[$_GET['error']] ?? null)
    : null;
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="description" content="Vista de secretario para dar turnos a pacientes.">
    <meta name="viewport" content="width=device-width, 
    initial-scale=1.0">
    <title> Pausa - Secretario </title>
    <link rel="icon" type="image/png" href="../img/favicon.png">
    <link rel="stylesheet" href="../css/styles.css?v=<?php echo filemtime(__DIR__ . '/../css/styles.css'); ?>"> <!-- Enlaza el archivo styles.css para agregar estilos -->
    <script src="../js/main.js?v=<?php echo filemtime(__DIR__ . '/../js/main.js'); ?>" defer></script>
    <script src="../js/secretario.js?v=<?php echo filemtime(__DIR__ . '/../js/secretario.js'); ?>" defer></script>
    <script src="../js/secretario-dar-turno.js" defer></script>
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
                <form action="../procesos/cerrar-sesion.php" method="POST" class="form-logout">
                    <?php echo csrf_input(); ?>
                    <button type="submit" class="btn-logout">
                        <span>Cerrar sesión</span>
                    </button>
                </form>
            </div>

        </aside>

        <main class="main-secretario">

            <header class="header-dashboard">
                <div class="texto-bienvenida-secretario">
                    <h1>Nuevo Turno</h1>
                </div>
            </header>

            <?php if (isset($_GET['exito']) && $_GET['exito'] === '1'): ?>
                <p style="background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px;">¡Turno asignado con éxito desde secretaría!</p>
            <?php endif; ?>
            <?php if ($errorAlCargarAgenda): ?>
                <p role="alert" style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px;">No se pudieron cargar los datos del turno. Recargá la página o intentá más tarde.</p>
            <?php elseif ($errorTurno !== null): ?>
                <p role="alert" style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px;"><?php echo htmlspecialchars($errorTurno, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>

            <form action="../procesos/secretario/procesar-dar-turno.php" method="POST">
                <?php echo csrf_input(); ?>
                <div class="grilla-dar-turno">

                    <!-- PASO 1: PACIENTE -->
                    <section class="tarjeta-blanca paso-turno">
                        <div class="header-paso">
                            <span class="badge-numero">1</span>
                            <h2>¿Quién es el paciente?</h2>
                        </div>

                        <div class="grupo-campo" style="margin-top: 16px;">
                            <label for="paciente_id">Seleccionar Paciente</label>
                            <select name="paciente_id" id="paciente_id" class="input-form" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px;">
                                <option value="">-- Buscá o elegí un paciente --</option>
                                <?php foreach ($pacientes as $pac): ?>
                                    <option value="<?php echo (int) $pac['id']; ?>">
                                        <?php echo htmlspecialchars($pac['apellido'] . ', ' . $pac['nombre'] . ' (DNI: ' . $pac['dni'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </section>

                    <!-- PASO 2: ESPECIALISTA -->
                    <section class="tarjeta-blanca paso-turno">
                        <div class="header-paso">
                            <span class="badge-numero">2</span>
                            <h2>¿Qué especialista?</h2>
                        </div>

                        <!-- Lista dinámica de profesionales en círculo -->
                        <div class="lista-especialistas-rapida" style="margin-top: 16px; display: flex; gap: 10px; flex-wrap: wrap;">
                            <?php foreach ($profesionales as $prof): ?>
                                <?php 
                                    $esSeleccionado = (int) $prof['id'] === $profesionalId;
                                    $iniciales = htmlspecialchars(mb_strtoupper(mb_substr($prof['nombre'], 0, 1) . mb_substr($prof['apellido'], 0, 1)), ENT_QUOTES, 'UTF-8');
                                ?>
                                <a href="secretario-dar-turno.php?profesional_id=<?php echo (int) $prof['id']; ?>" 
                                   class="item-especialista-btn <?php echo $esSeleccionado ? 'activo' : ''; ?>" 
                                   style="text-decoration: none; display: flex; align-items: center; gap: 8px; padding: 8px 12px; border: 1px solid <?php echo $esSeleccionado ? '#0056b3' : '#ccc'; ?>; border-radius: 30px; background: <?php echo $esSeleccionado ? '#e7f1ff' : '#fff'; ?>; color: #333;">
                                    <div class="avatar-esp" style="width: 32px; height: 32px; background: #0056b3; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.85rem;"><?php echo $iniciales; ?></div>
                                    <span style="font-size: 0.9rem; font-weight: 500;">Lic. <?php echo htmlspecialchars($prof['apellido'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <!-- PASO 3: MOMENTO (CALENDARIO Y HORARIOS) -->
                    <section class="tarjeta-blanca paso-turno paso-ancho-completo">
                        <div class="header-paso">
                            <span class="badge-numero">3</span>
                            <h2>Selecciona el momento</h2>
                        </div>

                        <?php if (!$profesionalId): ?>
                            <p style="margin-top: 16px; color: #666; font-style: italic;">Seleccioná primero un especialista en el Paso 2 para ver su calendario y horarios disponibles.</p>
                        <?php else: ?>
                            <!-- Contenedor de las dos columnas idéntico al flujo del paciente -->
                            <div class="grilla-seleccion-turno" style="margin-top: 16px;">
                                
                                <!-- COLUMNA 1: CALENDARIO -->
                                <div class="panel-calendario">
                                    <div class="cabecera-mes">
                                        <h3></h3>
                                        <div class="nav-mes">
                                            <button type="button" class="btn-nav-mes">&lt;</button>
                                            <button type="button" class="btn-nav-mes">&gt;</button>
                                        </div>
                                    </div>

                                    <div class="dias-semana">
                                        <span>Dom</span><span>Lun</span><span>Mar</span><span>Mié</span><span>Jue</span><span>Vie</span><span>Sáb</span>
                                    </div>

                                    <div class="grilla-dias" data-hoy="<?php echo $ahora->format('Y-m-d'); ?>" data-slots="<?php echo $datosHorarios; ?>"></div>
                                </div>

                                <!-- COLUMNA 2: HORARIOS DISPONIBLES -->
                                <div class="panel-horarios">
                                    <div class="cabecera-horarios">
                                        <h3>Seleccioná una fecha disponible</h3>
                                    </div>

                                    <div class="lista-horas">
                                        <p><?php echo empty($horariosPorFecha) ? 'Este profesional no tiene horarios disponibles en los próximos 90 días.' : 'Seleccioná una fecha para ver sus horarios.'; ?></p>
                                    </div>
                                </div>

                            </div>
                        <?php endif; ?>
                    </section>
                </div>

                <!-- Inputs ocultos para enviar los datos seleccionados -->
                <input type="hidden" name="profesional_id" value="<?php echo $profesional ? (int) $profesionalId : ''; ?>">
                <input type="hidden" name="fecha_seleccionada" id="inputFechaSeleccionada" value="">
                <input type="hidden" name="hora_turno" id="inputHoraSeleccionada" value="">

                <!-- BOTÓN DE CONFIRMACIÓN AL PIE -->
                <div class="footer-confirmar-turno">
                    <button type="submit" class="btn-siguiente-paso" style="max-width: 300px; margin: 30px auto 0 auto; display: block;">
                        Confirmar Turno &rarr;
                    </button>
                </div>
            </form>

        </main>
    </div>

    <footer>
        <small>&copy; 2026 Pausa. Todos los derechos reservados.</small>
    </footer>

</body>
</html>