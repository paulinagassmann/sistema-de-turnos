<?php
// Cierra la sesión de cualquier usuario (paciente, profesional, secretario o administrador).
// Se llama solo por POST y con token CSRF, desde el botón "Cerrar sesión" de cada panel.
session_start();
require_once __DIR__ . '/../config/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_es_valido()) {
    header("Location: ../index.html");
    exit();
}

// 1. vacío todos los datos guardados en la sesión
$_SESSION = [];

// 2. borro la cookie de sesión del navegador
if (ini_get('session.use_cookies')) {
    $parametros = session_get_cookie_params();
    $opcionesCookie = [
        'expires' => time() - 42000,
        'path' => $parametros['path'],
        'domain' => $parametros['domain'],
        'secure' => $parametros['secure'],
        'httponly' => $parametros['httponly'],
    ];
    if (!empty($parametros['samesite'])) {
        $opcionesCookie['samesite'] = $parametros['samesite'];
    }
    setcookie(session_name(), '', $opcionesCookie);
}

// 3. elimino la sesión del servidor
session_destroy();

header("Location: ../index.html");
exit();
