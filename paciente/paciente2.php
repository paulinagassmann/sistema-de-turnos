<?php
session_start();

if (
    empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'paciente'
) {
    header("Location: ../index.html?error=sesion_requerida");
    exit();
}

$profesionalIdRecibido = $_GET['profesional_id'] ?? $_SESSION['profesional_id'] ?? null;
$profesionalId = is_scalar($profesionalIdRecibido)
    ? filter_var((string) $profesionalIdRecibido, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
    ])
    : false;
if ($profesionalId === false || $profesionalId === null) {
    header("Location: paciente1.php?error=profesional_requerido");
    exit();
}

$zonaHoraria = new DateTimeZone('America/Argentina/Buenos_Aires');
$ahora = new DateTimeImmutable('now', $zonaHoraria);

require_once __DIR__ . '/../config/conexion.php';

$profesional = null;
$agendas = [];
$turnosOcupados = [];
$errorAlCargarAgenda = false;

try {
    $stmtProfesional = $conexion->prepare(
        'SELECT u.nombre, u.apellido, p.especialidad
         FROM profesionales p
         JOIN usuarios u ON p.id = u.id
         WHERE p.id = :id AND u.rol = :rol'
    );
    $stmtProfesional->execute([
        ':id' => $profesionalId,
        ':rol' => 'profesional'
    ]);
    $profesional = $stmtProfesional->fetch(PDO::FETCH_ASSOC);

    if (!$profesional) {
        unset($_SESSION['profesional_id']);
        header("Location: paciente1.php?error=profesional_invalido");
        exit();
    }

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
} catch (Throwable $e) {
    error_log("Error al cargar agenda en paso 2: " . $e->getMessage());
    http_response_code(500);
    $errorAlCargarAgenda = true;
}

$profesionalId = (int) $profesionalId;
if (empty($errorAlCargarAgenda)) {
    $_SESSION['profesional_id'] = $profesionalId;
}

$horariosPorDia = [];
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
    return $diasSemana[$dia] ?? (ctype_digit($dia) && (int) $dia >= 1 && (int) $dia <= 7
        ? (int) $dia
        : null);
};

if (empty($errorAlCargarAgenda)) {
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
            $horariosPorDia[$dia][] = sprintf('%02d:%02d', intdiv($minuto, 60), $minuto % 60);
        }
    }
}

$ocupados = [];
foreach ($turnosOcupados ?? [] as $fechaHoraOcupada) {
    $ocupados[(string) $fechaHoraOcupada] = true;
}

$horariosPorFecha = [];
$ahora = new DateTimeImmutable('now', $zonaHoraria);
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

        $horariosPorFecha[$fechaFormateada][] = $horario;
    }
}

$datosHorarios = htmlspecialchars(
    json_encode($horariosPorFecha, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT),
    ENT_QUOTES,
    'UTF-8'
);
$mensajesError = [
    'ocupado' => 'Ese horario acaba de ser reservado. Elegí otro horario disponible.',
    'fecha_vencida' => 'La fecha seleccionada ya no está disponible. Elegí otra fecha.',
    'seleccion_invalida' => 'La selección ya no es válida. Elegí nuevamente fecha y horario.',
    'servidor' => 'No pudimos registrar el turno. Intentá nuevamente.'
];
$mensajeError = null;
if (isset($_GET['error']) && is_string($_GET['error']) && isset($mensajesError[$_GET['error']])) {
    $mensajeError = $mensajesError[$_GET['error']];
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="description" content="Segundo paso para agendar un turno en Pausa.">
    <meta name="viewport" content="width=device-width, 
    initial-scale=1.0">
    <title> Pausa - Agendar Turno </title>
    <link rel="stylesheet" href="../css/styles.css"> <!-- Enlaza el archivo styles.css para agregar estilos -->
    <script src="../js/main.js" defer></script>
    <script src="../js/paciente.js?v=booking-step-two-20261104-2" defer></script>
</head>

<body>
    
    <header class="header-paciente">
        <div class="bloque-marca">
            <span class="logo-texto">Pausa</span>
            <span class="subtitulo-logo">Clínica de Salud Mental</span>
        </div>
    </header>
     
    <main class="main-paciente-turno">
        <header class="encabezado-pasos">
            <h1> Fecha y Hora </h1>
            <p>
                Elegí la fecha y hora que mejor se adapte a tu agenda.
                <?php if ($profesional): ?>
                    Profesional: Lic. <?php echo htmlspecialchars($profesional['nombre'] . ' ' . $profesional['apellido'], ENT_QUOTES, 'UTF-8'); ?>.
                <?php endif; ?>
            </p>
        </header>
        <?php if ($mensajeError !== null): ?>
            <p class="aviso-turno-error" role="alert"><?php echo htmlspecialchars($mensajeError, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        
        <!-- Barra de progreso de 3 pasos -->
        <div class="indicador-pasos">
            <div class="paso">
                <span class="circulo-paso">1</span>
                <span class="etiqueta-paso">Profesional</span>
            </div>
            <div class="linea-paso"></div>
            <div class="paso activo"> <!-- para pintar este paso de azul -->
                <span class="circulo-paso">2</span>
                <span class="etiqueta-paso">Fecha y Hora</span>
            </div>
            <div class="linea-paso"></div>
            <div class="paso">
                <span class="circulo-paso">3</span>
                <span class="etiqueta-paso">Confirmación</span>
            </div>
        </div>

        <?php if ($errorAlCargarAgenda): ?>
            <section class="tarjeta-blanca">
                <p>No pudimos cargar los horarios. Intentá nuevamente más tarde.</p>
                <a href="paciente1.php" class="btn-cancelar-paso">Volver a profesionales</a>
            </section>
        <?php else: ?>
        <form action="paciente3.php" method="POST">
            <section class="tarjeta-blanca">
                <div class="grilla-seleccion-turno">
                    
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

                        <div class="grilla-dias" data-slots="<?php echo $datosHorarios; ?>"></div>
                    </div>

                    <!-- COLUMNA 2: HORARIOS DISPONIBLES SEGÚN LA AGENDA -->
                    <div class="panel-horarios">
                        <div class="cabecera-horarios">
                            <h3>Seleccioná una fecha disponible</h3>
                        </div>

                        <div class="lista-horas">
                            <p><?php echo empty($horariosPorFecha) ? 'Este profesional no tiene horarios disponibles en los próximos 90 días.' : 'Seleccioná una fecha para ver sus horarios.'; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Inputs ocultos para enviar la fecha y hora seleccionada al siguiente paso -->
                <input type="hidden" name="profesional_id" value="<?php echo $profesionalId; ?>">
                <input type="hidden" name="fecha_seleccionada" id="inputFechaSeleccionada" value="">
                <input type="hidden" name="hora_turno" id="inputHoraSeleccionada" value="">

                <footer class="footer-pasos">
                   <a href="paciente1.php" class="btn-cancelar-paso">Volver</a>
                   <button type="submit" class="btn-siguiente-paso">Siguiente &rarr;</button>
                </footer>
            </section>
        </form>
        <?php endif; ?>

    </main>

    <footer>
        <small> &copy; 2026 Pausa. Todos los derechos reservados. </small>  
    </footer>

</body>