<?php
// Entrega el token CSRF de la sesión a las páginas .html estáticas, que no pueden
// generarlo con PHP. js/main.js lo pide al cargar y lo pone en sus formularios.
// Otro sitio web no puede leer esta respuesta (el navegador lo bloquea por ser
// de otro origen), así que no le sirve para falsificar envíos.
require_once __DIR__ . '/../config/csrf.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'metodo_no_permitido']);
    exit();
}

echo json_encode(['csrf_token' => csrf_token()]);
