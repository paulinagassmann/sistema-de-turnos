<?php
session_start();

$pacienteId = filter_var($_POST['paciente_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
$urlRetorno = '../../secretario/secretario-pacientes.php?'
    . ($pacienteId !== false && $pacienteId !== null ? 'id=' . $pacienteId . '&' : '');

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'secretario'
) {
    header('Location: ../../index.html?error=sesion_requerida');
    exit();
}

$csrfToken = $_POST['csrf_token'] ?? null;
if (
    !is_string($csrfToken)
    || empty($_SESSION['csrf_secretario_cancelar_turno'])
    || !hash_equals($_SESSION['csrf_secretario_cancelar_turno'], $csrfToken)
    || $pacienteId === false
    || $pacienteId === null
) {
    header("Location: {$urlRetorno}error=solicitud_invalida");
    exit();
}

$turnoId = filter_var($_POST['turno_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
if ($turnoId === false || $turnoId === null) {
    header("Location: {$urlRetorno}error=solicitud_invalida");
    exit();
}

require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../config/turnos.php';

$zonaHoraria = new DateTimeZone('America/Argentina/Buenos_Aires');
$ahora = new DateTimeImmutable('now', $zonaHoraria);
$resultado = 'turno_cancelado=1';
$nombreBloqueo = null;
$bloqueoAdquirido = false;

try {
    actualizarTurnosPendientesVencidos($conexion);

    $stmtTurno = $conexion->prepare(
        "SELECT profesional_id, fecha_hora
         FROM turnos
         WHERE id = :turno_id
           AND paciente_id = :paciente_id
           AND fecha_hora >= :ahora
           AND estado IN ('pendiente', 'confirmado')"
    );
    $stmtTurno->execute([
        ':turno_id' => $turnoId,
        ':paciente_id' => $pacienteId,
        ':ahora' => $ahora->format('Y-m-d H:i:s')
    ]);
    $turno = $stmtTurno->fetch(PDO::FETCH_ASSOC);
    if (!$turno) {
        throw new DomainException('turno_no_cancelable');
    }

    $fechaHora = new DateTimeImmutable($turno['fecha_hora'], $zonaHoraria);
    $nombreBloqueo = hash(
        'sha256',
        (int) $turno['profesional_id'] . '|' . $fechaHora->format('Y-m-d') . '|' . $fechaHora->format('H:i')
    );
    $stmtBloqueo = $conexion->prepare('SELECT GET_LOCK(:nombre, 5)');
    $stmtBloqueo->execute([':nombre' => $nombreBloqueo]);
    $bloqueoAdquirido = (int) $stmtBloqueo->fetchColumn() === 1;
    if (!$bloqueoAdquirido) {
        throw new DomainException('ocupado');
    }

    $conexion->beginTransaction();
    $stmtRevalidar = $conexion->prepare(
        "SELECT id
         FROM turnos
         WHERE id = :turno_id
           AND paciente_id = :paciente_id
           AND profesional_id = :profesional_id
           AND fecha_hora = :fecha_hora
           AND fecha_hora >= :ahora
           AND estado IN ('pendiente', 'confirmado')
         FOR UPDATE"
    );
    $stmtRevalidar->execute([
        ':turno_id' => $turnoId,
        ':paciente_id' => $pacienteId,
        ':profesional_id' => $turno['profesional_id'],
        ':fecha_hora' => $turno['fecha_hora'],
        ':ahora' => (new DateTimeImmutable('now', $zonaHoraria))->format('Y-m-d H:i:s')
    ]);
    if (!$stmtRevalidar->fetchColumn()) {
        $conexion->rollBack();
        throw new DomainException('turno_no_cancelable');
    }

    $stmtCancelar = $conexion->prepare(
        "UPDATE turnos
         SET estado = 'cancelado'
         WHERE id = :turno_id
           AND paciente_id = :paciente_id
           AND fecha_hora >= :ahora
           AND estado IN ('pendiente', 'confirmado')"
    );
    $stmtCancelar->execute([
        ':turno_id' => $turnoId,
        ':paciente_id' => $pacienteId,
        ':ahora' => (new DateTimeImmutable('now', $zonaHoraria))->format('Y-m-d H:i:s')
    ]);
    if ($stmtCancelar->rowCount() !== 1) {
        $conexion->rollBack();
        throw new DomainException('turno_no_cancelable');
    }

    $conexion->commit();
} catch (DomainException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    $resultado = 'error=' . rawurlencode($e->getMessage());
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    error_log('Error al cancelar el turno del paciente desde secretaría: ' . $e->getMessage());
    $resultado = 'error=servidor';
}

if ($bloqueoAdquirido && $nombreBloqueo !== null) {
    try {
        $conexion->prepare('SELECT RELEASE_LOCK(:nombre)')->execute([':nombre' => $nombreBloqueo]);
    } catch (Throwable $e) {
        error_log('No se pudo liberar el bloqueo de cancelación del turno: ' . $e->getMessage());
    }
}

header("Location: {$urlRetorno}{$resultado}");
exit();
