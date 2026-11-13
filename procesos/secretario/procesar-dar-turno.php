<?php
session_start();
require_once __DIR__ . '/../../config/csrf.php';

$rutaFormulario = '../../secretario/secretario-dar-turno.php';
if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'secretario'
) {
    header('Location: ../../index.html?error=sesion_requerida');
    exit();
}

if (
    !csrf_es_valido()
) {
    header("Location: {$rutaFormulario}?error=solicitud_invalida");
    exit();
}

$pacienteId = filter_var($_POST['paciente_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
$profesionalId = filter_var($_POST['profesional_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
$fecha = $_POST['fecha_seleccionada'] ?? null;
$hora = $_POST['hora_turno'] ?? null;
$profesionalQuery = $profesionalId === false
    ? '?'
    : '?profesional_id=' . $profesionalId . '&';

if (
    $pacienteId === false
    || $profesionalId === false
    || !is_string($fecha)
    || !is_string($hora)
    || preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) !== 1
    || preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hora) !== 1
) {
    header("Location: {$rutaFormulario}{$profesionalQuery}error=campos_vacios");
    exit();
}

$partesFecha = explode('-', $fecha);
if (
    count($partesFecha) !== 3
    || !checkdate((int) $partesFecha[1], (int) $partesFecha[2], (int) $partesFecha[0])
) {
    header("Location: {$rutaFormulario}{$profesionalQuery}error=campos_vacios");
    exit();
}

$zonaHoraria = new DateTimeZone('America/Argentina/Buenos_Aires');
$inicioTurno = DateTimeImmutable::createFromFormat('!Y-m-d H:i', "{$fecha} {$hora}", $zonaHoraria);
$ahora = new DateTimeImmutable('now', $zonaHoraria);
if (!$inicioTurno || $inicioTurno <= $ahora) {
    header("Location: {$rutaFormulario}{$profesionalQuery}error=fecha_vencida");
    exit();
}

require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../config/turnos.php';

$bloqueoAdquirido = false;
$nombreBloqueo = hash('sha256', $profesionalId . '|' . $fecha . '|' . $hora);
$liberarBloqueo = static function () use (&$bloqueoAdquirido, $conexion, $nombreBloqueo): void {
    if (!$bloqueoAdquirido) {
        return;
    }

    try {
        $conexion->prepare('SELECT RELEASE_LOCK(:nombre)')->execute([':nombre' => $nombreBloqueo]);
    } catch (Throwable $e) {
        error_log("Error al liberar el bloqueo del turno: " . $e->getMessage());
    }
    $bloqueoAdquirido = false;
};

try {
    actualizarTurnosPendientesVencidos($conexion);

    $stmtPaciente = $conexion->prepare(
        "SELECT 1
         FROM pacientes p
         JOIN usuarios u ON u.id = p.id AND u.rol = 'paciente'
         WHERE p.id = :id"
    );
    $stmtPaciente->execute([':id' => $pacienteId]);
    $pacienteValido = (bool) $stmtPaciente->fetchColumn();

    $stmtProfesional = $conexion->prepare(
        "SELECT 1
         FROM profesionales p
         JOIN usuarios u ON u.id = p.id AND u.rol = 'profesional'
         WHERE p.id = :id"
    );
    $stmtProfesional->execute([':id' => $profesionalId]);
    $profesionalValido = (bool) $stmtProfesional->fetchColumn();

    if (!$pacienteValido || !$profesionalValido) {
        header("Location: {$rutaFormulario}{$profesionalQuery}error=solicitud_invalida");
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
        header("Location: {$rutaFormulario}{$profesionalQuery}error=horario_invalido");
        exit();
    }

    $stmtBloqueo = $conexion->prepare('SELECT GET_LOCK(:nombre, 5)');
    $stmtBloqueo->execute([':nombre' => $nombreBloqueo]);
    $bloqueoAdquirido = (int) $stmtBloqueo->fetchColumn() === 1;
    if (!$bloqueoAdquirido) {
        header("Location: {$rutaFormulario}{$profesionalQuery}error=ocupado");
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
        $liberarBloqueo();
        header("Location: {$rutaFormulario}{$profesionalQuery}error=horario_invalido");
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
        $liberarBloqueo();
        header("Location: {$rutaFormulario}{$profesionalQuery}error=ocupado");
        exit();
    }

    $stmtInsertar = $conexion->prepare(
        "INSERT INTO turnos (paciente_id, profesional_id, fecha_hora, estado, creado_por)
         VALUES (:paciente_id, :profesional_id, :fecha_hora, 'pendiente', 'secretario')"
    );
    $stmtInsertar->execute([
        ':paciente_id' => $pacienteId,
        ':profesional_id' => $profesionalId,
        ':fecha_hora' => $inicioTurno->format('Y-m-d H:i:s')
    ]);
    $conexion->commit();

    $liberarBloqueo();
    header("Location: {$rutaFormulario}?profesional_id={$profesionalId}&exito=1");
    exit();
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    $liberarBloqueo();
    error_log("Error al dar turno desde secretaría: " . $e->getMessage());
    header("Location: {$rutaFormulario}{$profesionalQuery}error=servidor");
    exit();
}
