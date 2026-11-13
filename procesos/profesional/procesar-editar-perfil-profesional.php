<?php
session_start();
require_once __DIR__ . '/../../config/csrf.php';

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'profesional'
) {
    header("Location: ../../index.html");
    exit();
}

if (
    !csrf_es_valido()
) {
    header("Location: ../../profesional/profesional-configuracion.php?error=solicitud_invalida");
    exit();
}

$correoRecibido = $_POST['correo'] ?? null;
if (!is_string($correoRecibido)) {
    header("Location: ../../profesional/profesional-configuracion.php?error=correo_invalido");
    exit();
}

$correo = trim($correoRecibido);
if ($correo === '') {
    header("Location: ../../profesional/profesional-configuracion.php?error=campos_vacios");
    exit();
}

if (filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
    header("Location: ../../profesional/profesional-configuracion.php?error=correo_invalido");
    exit();
}

require_once __DIR__ . '/../../config/conexion.php';

try {
    $stmt = $conexion->prepare(
        "UPDATE usuarios
         SET correo = :correo
         WHERE id = :id
           AND rol = 'profesional'"
    );
    $stmt->execute([':correo' => $correo, ':id' => $_SESSION['usuario_id']]);

    header("Location: ../../profesional/profesional-configuracion.php?exito=1");
    exit();
} catch (Throwable $e) {
    error_log("Error al actualizar perfil del profesional: " . $e->getMessage());
    header("Location: ../../profesional/profesional-configuracion.php?error=servidor");
    exit();
}