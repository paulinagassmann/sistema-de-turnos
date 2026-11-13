<?php
session_start();
require_once __DIR__ . '/../../config/csrf.php';

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'paciente'
) {
    header("Location: ../../index.html?error=sesion_requerida");
    exit();
}

if (
    !csrf_es_valido()
) {
    header("Location: ../../paciente/paciente1.php?error=sesion_vencida");
    exit();
}

$profesionalId = filter_var($_POST['profesional_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
$profesionalIdSesion = filter_var($_SESSION['profesional_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
$fecha = $_POST['fecha_seleccionada'] ?? null;
$hora = $_POST['hora_turno'] ?? null;

if (
    $profesionalId === false
    || $profesionalIdSesion === false
    || $profesionalId !== $profesionalIdSesion
    || !is_string($fecha)
    || !is_string($hora)
    || preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) !== 1
    || preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hora) !== 1
) {
    header("Location: ../../paciente/paciente1.php?error=seleccion_invalida");
    exit();
}

$fechaPartes = explode('-', $fecha);
if (
    count($fechaPartes) !== 3
    || !checkdate((int) $fechaPartes[1], (int) $fechaPartes[2], (int) $fechaPartes[0])
) {
    header("Location: ../../paciente/paciente2.php?profesional_id=" . $profesionalId . "&error=seleccion_invalida");
    exit();
}

$zonaHoraria = new DateTimeZone('America/Argentina/Buenos_Aires');
$inicioTurno = DateTimeImmutable::createFromFormat(
    '!Y-m-d H:i',
    $fecha . ' ' . $hora,
    $zonaHoraria
);
$ahora = new DateTimeImmutable('now', $zonaHoraria);
$fechaLimite = $ahora->modify('+89 days');
if (
    !$inicioTurno
    || $inicioTurno <= $ahora
    || $inicioTurno->format('Y-m-d') > $fechaLimite->format('Y-m-d')
) {
    header("Location: ../../paciente/paciente2.php?profesional_id=" . $profesionalId . "&error=fecha_vencida");
    exit();
}

require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../config/turnos.php';

$nombreBloqueo = hash('sha256', $profesionalId . '|' . $fecha . '|' . $hora);
$bloqueoAdquirido = false;

try {
    actualizarTurnosPendientesVencidos($conexion);

    $stmtBloqueo = $conexion->prepare('SELECT GET_LOCK(:nombre, 5)');
    $stmtBloqueo->execute([':nombre' => $nombreBloqueo]);
    $bloqueoAdquirido = (int) $stmtBloqueo->fetchColumn() === 1;

    if (!$bloqueoAdquirido) {
        header("Location: ../../paciente/paciente2.php?profesional_id=" . $profesionalId . "&error=ocupado");
        exit();
    }

    $stmtAgenda = $conexion->prepare(
        'SELECT dia_semana, hora_inicio, hora_fin, duracion_turno
         FROM agendas
         WHERE profesional_id = :profesional_id'
    );
    $stmtAgenda->execute([':profesional_id' => $profesionalId]);
    $agendas = $stmtAgenda->fetchAll(PDO::FETCH_ASSOC);

    $diasSemana = [
        'lunes' => 1, 'lun' => 1, 'monday' => 1,
        'martes' => 2, 'mar' => 2, 'tuesday' => 2,
        'miercoles' => 3, 'mie' => 3, 'wednesday' => 3,
        'jueves' => 4, 'jue' => 4, 'thursday' => 4,
        'viernes' => 5, 'vie' => 5, 'friday' => 5,
        'sabado' => 6, 'sab' => 6, 'saturday' => 6,
        'domingo' => 7, 'dom' => 7, 'sunday' => 7
    ];
    $numeroDiaSolicitado = (int) $inicioTurno->format('N');
    $minutoSolicitado = ((int) $inicioTurno->format('H') * 60) + (int) $inicioTurno->format('i');
    $horarioValido = false;
    $duracionTurno = null;

    foreach ($agendas as $agenda) {
        $diaNombre = strtolower(trim((string) $agenda['dia_semana']));
        $diaNombre = strtr($diaNombre, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
        $diaAgenda = $diasSemana[$diaNombre] ?? (
            ctype_digit($diaNombre) && (int) $diaNombre >= 1 && (int) $diaNombre <= 7
                ? (int) $diaNombre
                : null
        );
        $inicioAgenda = DateTimeImmutable::createFromFormat('!H:i:s', (string) $agenda['hora_inicio'])
            ?: DateTimeImmutable::createFromFormat('!H:i', (string) $agenda['hora_inicio']);
        $finAgenda = DateTimeImmutable::createFromFormat('!H:i:s', (string) $agenda['hora_fin'])
            ?: DateTimeImmutable::createFromFormat('!H:i', (string) $agenda['hora_fin']);
        $duracion = filter_var($agenda['duracion_turno'], FILTER_VALIDATE_INT);

        if (
            $diaAgenda !== $numeroDiaSolicitado
            || !$inicioAgenda
            || !$finAgenda
            || $duracion === false
            || $duracion < 1
            || $duracion > 480
        ) {
            continue;
        }

        $minutoInicioAgenda = ((int) $inicioAgenda->format('H') * 60) + (int) $inicioAgenda->format('i');
        $minutoFinAgenda = ((int) $finAgenda->format('H') * 60) + (int) $finAgenda->format('i');
        if (
            $minutoSolicitado >= $minutoInicioAgenda
            && $minutoSolicitado + $duracion <= $minutoFinAgenda
            && ($minutoSolicitado - $minutoInicioAgenda) % $duracion === 0
        ) {
            $horarioValido = true;
            $duracionTurno = $duracion;
            break;
        }
    }

    if (!$horarioValido) {
        $stmtTurnoExtra = $conexion->prepare(
            "SELECT TIMESTAMPDIFF(MINUTE, fecha_hora_inicio, fecha_hora_fin)
             FROM excepciones
             WHERE profesional_id = :profesional_id
               AND tipo = 'extra'
               AND fecha_hora_inicio = :fecha_hora_inicio
               AND fecha_hora_fin > fecha_hora_inicio
             LIMIT 1"
        );
        $stmtTurnoExtra->execute([
            ':profesional_id' => $profesionalId,
            ':fecha_hora_inicio' => $inicioTurno->format('Y-m-d H:i:s')
        ]);
        $duracionExtra = filter_var($stmtTurnoExtra->fetchColumn(), FILTER_VALIDATE_INT);
        if ($duracionExtra !== false && $duracionExtra >= 1 && $duracionExtra <= 480) {
            $horarioValido = true;
            $duracionTurno = $duracionExtra;
        }
    }

    if ($horarioValido && $duracionTurno !== null) {
        $finTurno = $inicioTurno->modify("+{$duracionTurno} minutes");
        $stmtBloqueoAgenda = $conexion->prepare(
            "SELECT 1
             FROM excepciones
             WHERE profesional_id = :profesional_id
               AND tipo = 'bloqueo'
               AND fecha_hora_inicio < :fecha_hora_fin
               AND fecha_hora_fin > :fecha_hora_inicio
             LIMIT 1"
        );
        $stmtBloqueoAgenda->execute([
            ':profesional_id' => $profesionalId,
            ':fecha_hora_inicio' => $inicioTurno->format('Y-m-d H:i:s'),
            ':fecha_hora_fin' => $finTurno->format('Y-m-d H:i:s')
        ]);
        if ($stmtBloqueoAgenda->fetchColumn()) {
            $horarioValido = false;
        }
    }

    if (!$horarioValido) {
        $conexion->prepare('SELECT RELEASE_LOCK(:nombre)')->execute([':nombre' => $nombreBloqueo]);
        $bloqueoAdquirido = false;
        header("Location: ../../paciente/paciente2.php?profesional_id=" . $profesionalId . "&error=seleccion_invalida");
        exit();
    }

    $conexion->beginTransaction();
    $stmtBloqueoFinal = $conexion->prepare(
        "SELECT id
         FROM excepciones
         WHERE profesional_id = :profesional_id
           AND tipo = 'bloqueo'
           AND fecha_hora_inicio < :fecha_hora_fin
           AND fecha_hora_fin > :fecha_hora_inicio
         LIMIT 1
         FOR UPDATE"
    );
    $stmtBloqueoFinal->execute([
        ':profesional_id' => $profesionalId,
        ':fecha_hora_inicio' => $inicioTurno->format('Y-m-d H:i:s'),
        ':fecha_hora_fin' => $finTurno->format('Y-m-d H:i:s')
    ]);
    if ($stmtBloqueoFinal->fetchColumn()) {
        $conexion->rollBack();
        $conexion->prepare('SELECT RELEASE_LOCK(:nombre)')->execute([':nombre' => $nombreBloqueo]);
        $bloqueoAdquirido = false;
        header("Location: ../../paciente/paciente2.php?profesional_id=" . $profesionalId . "&error=seleccion_invalida");
        exit();
    }

    $stmtOcupado = $conexion->prepare(
        "SELECT id
         FROM turnos
         WHERE profesional_id = :profesional_id
           AND fecha_hora = :fecha_hora
           AND estado IN ('pendiente', 'confirmado')
         LIMIT 1
         FOR UPDATE"
    );
    $stmtOcupado->execute([
        ':profesional_id' => $profesionalId,
        ':fecha_hora' => $inicioTurno->format('Y-m-d H:i:s')
    ]);

    if ($stmtOcupado->fetchColumn()) {
        $conexion->rollBack();
        $conexion->prepare('SELECT RELEASE_LOCK(:nombre)')->execute([':nombre' => $nombreBloqueo]);
        $bloqueoAdquirido = false;
        header("Location: ../../paciente/paciente2.php?profesional_id=" . $profesionalId . "&error=ocupado");
        exit();
    }

    $stmtInsertar = $conexion->prepare(
        "INSERT INTO turnos (paciente_id, profesional_id, fecha_hora, estado, creado_por)
         VALUES (:paciente_id, :profesional_id, :fecha_hora, 'pendiente', 'paciente')"
    );
    $stmtInsertar->execute([
        ':paciente_id' => $_SESSION['usuario_id'],
        ':profesional_id' => $profesionalId,
        ':fecha_hora' => $inicioTurno->format('Y-m-d H:i:s')
    ]);
    $conexion->commit();

    $conexion->prepare('SELECT RELEASE_LOCK(:nombre)')->execute([':nombre' => $nombreBloqueo]);
    $bloqueoAdquirido = false;
    unset(
        $_SESSION['profesional_id'],
        $_SESSION['fecha_turno'],
        $_SESSION['hora_turno']
    );
    header("Location: ../../paciente/paciente.php?turno_agendado=1");
    exit();
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    if ($bloqueoAdquirido) {
        $conexion->prepare('SELECT RELEASE_LOCK(:nombre)')->execute([':nombre' => $nombreBloqueo]);
    }
    error_log("Error al registrar el turno: " . $e->getMessage());
    header("Location: ../../paciente/paciente2.php?profesional_id=" . $profesionalId . "&error=servidor");
    exit();
}
