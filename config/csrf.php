<?php
// Protección CSRF (Cross-Site Request Forgery) compartida por todo el sistema.
//
// Cada sesión tiene un único token secreto y aleatorio. Todo formulario o fetch
// que envía datos por POST debe incluirlo en el campo "csrf_token", y todo proceso
// que recibe un POST lo compara con el de la sesión. Otra página web puede hacer
// que el navegador del usuario envíe un formulario a este sistema, pero no puede
// leer el token, así que ese envío falso se rechaza.
//
/*
 * Uso:
 *   - en una página:  echo csrf_input();  dentro del <form method="POST">
 *                     o csrf_token() para pasarlo a JavaScript (data-csrf-token / meta)
 *   - en un proceso:  if (!csrf_es_valido()) { ...rechazar... }
 */

function csrf_iniciar_sesion(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

// Devuelve el token de la sesión (lo crea si todavía no existe).
function csrf_token(): string
{
    csrf_iniciar_sesion();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Campo oculto listo para pegar dentro de un formulario.
function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

// Verifica que el POST traiga el mismo token que la sesión.
function csrf_es_valido(): bool
{
    csrf_iniciar_sesion();
    $recibido = $_POST['csrf_token'] ?? null;
    return is_string($recibido)
        && !empty($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $recibido);
}

// Genera un token nuevo (se usa al iniciar sesión, junto con session_regenerate_id).
function csrf_renovar(): void
{
    csrf_iniciar_sesion();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
