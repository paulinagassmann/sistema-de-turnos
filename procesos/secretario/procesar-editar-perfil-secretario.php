<?php
session_start();
require_once __DIR__ . '/../../config/csrf.php';

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'secretario'
) {
    header("Location: ../../index.html");
    exit();
}

if (
    !csrf_es_valido()
) {
    header("Location: ../../secretario/secretario-configuracion.php?error=solicitud_invalida");
    exit();
}

$correoRecibido = $_POST['correo'] ?? null;
if (!is_string($correoRecibido)) {
    header("Location: ../../secretario/secretario-configuracion.php?error=correo_invalido");
    exit();
}

$correo = trim($correoRecibido);

if ($correo === '') {
    header("Location: ../../secretario/secretario-configuracion.php?error=campos_vacios");
    exit();
}

require_once __DIR__ . '/../../config/validaciones.php';

if (!es_correo_valido($correo)) {
    header("Location: ../../secretario/secretario-configuracion.php?error=correo_invalido");
    exit();
}

require_once __DIR__ . '/../../config/conexion.php';

try {
    $stmt = $conexion->prepare(
        "UPDATE usuarios
         SET correo = :correo
         WHERE id = :id
           AND rol = 'secretario'"
    );
    $stmt->execute([
        ':correo' => $correo,
        ':id' => $_SESSION['usuario_id']
    ]);

    header("Location: ../../secretario/secretario-configuracion.php?exito=1");
    exit();
} catch (Throwable $e) {
    error_log("Error al actualizar perfil del secretario: " . $e->getMessage());
    header("Location: ../../secretario/secretario-configuracion.php?error=servidor");
    exit();
}