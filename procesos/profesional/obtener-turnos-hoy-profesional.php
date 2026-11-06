<?php
session_start();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (
    empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'profesional'
) {
    http_response_code(401);
    echo json_encode(['error' => 'sesion_requerida']);
    exit();
}

require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../config/turnos.php';

try {
    actualizarTurnosPendientesVencidos($conexion);

    $stmt = $conexion->prepare(
        'SELECT id, estado
         FROM turnos
         WHERE profesional_id = :profesional_id
           AND DATE(fecha_hora) = CURDATE()
         ORDER BY fecha_hora ASC'
    );
    $stmt->execute([':profesional_id' => $_SESSION['usuario_id']]);
    echo json_encode(
        ['turnos' => $stmt->fetchAll(PDO::FETCH_ASSOC)],
        JSON_THROW_ON_ERROR
    );
} catch (Throwable $e) {
    error_log('Error al actualizar los estados de turnos del profesional: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'error_servidor']);
}
