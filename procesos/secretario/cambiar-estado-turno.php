<?php
session_start();

$rutaPagina = '../../secretario/secretario-turnos-hoy.php';
if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'secretario'
) {
    header("Location: ../../index.html?error=sesion_requerida");
    exit();
}

$filtroEstado = $_POST['filtro_estado'] ?? 'todos';
if (!is_string($filtroEstado) || !in_array($filtroEstado, ['todos', 'pendiente', 'confirmado', 'asistido', 'ausente', 'cancelado'], true)) {
    $filtroEstado = 'todos';
}
if ($filtroEstado === 'asistido') {
    $filtroEstado = 'confirmado';
}

$filtroProfesional = $_POST['filtro_profesional'] ?? 'todos';
if (
    !is_string($filtroProfesional)
    || (
        $filtroProfesional !== 'todos'
        && filter_var($filtroProfesional, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]) === false
    )
) {
    $filtroProfesional = 'todos';
}
$filtroQuery = http_build_query([
    'estado' => $filtroEstado,
    'profesional' => $filtroProfesional
]);
$urlRetorno = $rutaPagina . '?' . $filtroQuery;

$csrfToken = $_POST['csrf_token'] ?? null;
if (
    !is_string($csrfToken)
    || empty($_SESSION['csrf_estado_turno'])
    || !hash_equals($_SESSION['csrf_estado_turno'], $csrfToken)
) {
    header("Location: {$urlRetorno}&error=solicitud_invalida");
    exit();
}

$turnoId = filter_var($_POST['turno_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
$nuevoEstado = $_POST['estado'] ?? null;
if (
    $turnoId === false
    || $turnoId === null
    || !is_string($nuevoEstado)
    || !in_array($nuevoEstado, ['pendiente', 'confirmado', 'cancelado'], true)
) {
    header("Location: {$urlRetorno}&error=solicitud_invalida");
    exit();
}

$zonaHoraria = new DateTimeZone('America/Argentina/Buenos_Aires');
$hoy = new DateTimeImmutable('today', $zonaHoraria);
$inicioDia = $hoy->format('Y-m-d H:i:s');
$finDia = $hoy->modify('+1 day')->format('Y-m-d H:i:s');
require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../config/turnos.php';

try {
    actualizarTurnosPendientesVencidos($conexion);

    $conexion->beginTransaction();
    $stmtTurno = $conexion->prepare(
        "SELECT estado
         FROM turnos
         WHERE id = :id
           AND fecha_hora >= :inicio
           AND fecha_hora < :fin
         FOR UPDATE"
    );
    $stmtTurno->execute([
        ':id' => $turnoId,
        ':inicio' => $inicioDia,
        ':fin' => $finDia
    ]);
    $estadoActual = $stmtTurno->fetchColumn();

    $transicionValida = $nuevoEstado === 'pendiente'
        ? in_array($estadoActual, ['confirmado', 'cancelado'], true)
        : $estadoActual === 'pendiente';
    if (!$transicionValida) {
        $conexion->rollBack();
        header("Location: {$urlRetorno}&error=turno_no_actualizable");
        exit();
    }

    $stmtActualizar = $conexion->prepare(
        "UPDATE turnos
         SET estado = :estado
         WHERE id = :id
           AND fecha_hora >= :inicio
           AND fecha_hora < :fin"
    );
    $stmtActualizar->execute([
        ':estado' => $nuevoEstado,
        ':id' => $turnoId,
        ':inicio' => $inicioDia,
        ':fin' => $finDia
    ]);
    if ($stmtActualizar->rowCount() !== 1) {
        $conexion->rollBack();
        header("Location: {$urlRetorno}&error=turno_no_actualizable");
        exit();
    }

    $conexion->commit();
    unset($_SESSION['csrf_estado_turno']);
    header("Location: {$urlRetorno}");
    exit();
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    error_log("Error al cambiar estado del turno desde secretaría: " . $e->getMessage());
    header("Location: {$urlRetorno}&error=servidor");
    exit();
}