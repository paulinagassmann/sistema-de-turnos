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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../paciente/paciente.php?error=solicitud_invalida");
    exit();
}

$csrfToken = $_POST['csrf_token'] ?? null;
if (
    !is_string($csrfToken)
    || empty($_SESSION['csrf_cancelar_turno'])
    || !hash_equals($_SESSION['csrf_cancelar_turno'], $csrfToken)
) {
    header("Location: ../paciente/paciente.php?error=solicitud_invalida");
    exit();
}

$turnoId = filter_var($_POST['turno_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
if ($turnoId === false || $turnoId === null) {
    header("Location: ../paciente/paciente.php?error=solicitud_invalida");
    exit();
}

require_once __DIR__ . '/../config/conexion.php';

try {
    $conexion->beginTransaction();

    $stmtTurno = $conexion->prepare(
        "SELECT id
         FROM turnos
         WHERE id = :turno_id
           AND paciente_id = :paciente_id
           AND fecha_hora >= NOW()
           AND estado IN ('pendiente', 'confirmado')
         FOR UPDATE"
    );
    $stmtTurno->execute([
        ':turno_id' => $turnoId,
        ':paciente_id' => $_SESSION['usuario_id']
    ]);

    if (!$stmtTurno->fetchColumn()) {
        $conexion->rollBack();
        header("Location: ../paciente/paciente.php?error=sin_turno");
        exit();
    }

    $stmtCancelar = $conexion->prepare(
        "UPDATE turnos
         SET estado = 'cancelado'
         WHERE id = :turno_id
           AND paciente_id = :paciente_id
           AND fecha_hora >= NOW()
           AND estado IN ('pendiente', 'confirmado')"
    );
    $stmtCancelar->execute([
        ':turno_id' => $turnoId,
        ':paciente_id' => $_SESSION['usuario_id']
    ]);

    if ($stmtCancelar->rowCount() !== 1) {
        $conexion->rollBack();
        header("Location: ../paciente/paciente.php?error=sin_turno");
        exit();
    }

    $conexion->commit();
    unset($_SESSION['csrf_cancelar_turno']);
    header("Location: ../paciente/paciente.php?turno_cancelado=1");
    exit();
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    error_log("Error al cancelar el turno del paciente: " . $e->getMessage());
    header("Location: ../paciente/paciente.php?error=servidor");
    exit();
}
