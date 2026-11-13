<?php
// Devuelve (una sola vez) el DNI y la contraseña temporal de la última cuenta
// registrada por el secretario, para mostrarlas en el cartel de éxito.
// Se piden por POST y se borran de la sesión apenas se entregan.
session_start();
require_once __DIR__ . '/../../config/csrf.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'secretario'
) {
    http_response_code(401);
    echo json_encode(['error' => 'sesion_requerida']);
    exit();
}

if (!csrf_es_valido()) {
    http_response_code(400);
    echo json_encode(['error' => 'solicitud_invalida']);
    exit();
}

$credenciales = $_SESSION['credenciales_registro'] ?? null;
unset($_SESSION['credenciales_registro']);

if (!is_array($credenciales)) {
    http_response_code(404);
    echo json_encode(['error' => 'sin_credenciales']);
    exit();
}

echo json_encode([
    'dni' => (string) ($credenciales['dni'] ?? ''),
    'pass' => (string) ($credenciales['pass'] ?? ''),
]);
