<?php
session_start();
require_once __DIR__ . '/../../config/csrf.php';

header('Content-Type: application/json; charset=utf-8');

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_SESSION['usuario_id'])
    || ($_SESSION['rol'] ?? null) !== 'secretario'
) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'sesion_requerida']);
    exit();
}

if (
    !csrf_es_valido()
) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'solicitud_invalida']);
    exit();
}

require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../config/turnos.php';

try {
    $cantidad = actualizarTurnosPendientesVencidos($conexion);
    echo json_encode(['ok' => true, 'actualizados' => $cantidad]);
} catch (Throwable $e) {
    error_log('Error al actualizar turnos vencidos: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'servidor']);
}
