<?php
session_start();

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'secretario'
) {
    header("Location: ../../index.html");
    exit();
}

$csrfToken = $_POST['csrf_token'] ?? null;
if (
    !is_string($csrfToken)
    || empty($_SESSION['csrf_secretario'])
    || !hash_equals($_SESSION['csrf_secretario'], $csrfToken)
) {
    header("Location: ../../secretario/secretario-configuracion.php?error=solicitud_invalida");
    exit();
}

$passwordActual = $_POST['password_actual'] ?? null;
$passwordNueva = $_POST['password_nueva'] ?? null;
$confirmarPassword = $_POST['confirmar_password'] ?? null;
if (
    !is_string($passwordActual)
    || !is_string($passwordNueva)
    || !is_string($confirmarPassword)
) {
    header("Location: ../../secretario/secretario-configuracion.php?error=solicitud_invalida");
    exit();
}

if ($passwordNueva !== $confirmarPassword) {
    header("Location: ../../secretario/secretario-configuracion.php?error=coincidencia");
    exit();
}

$cantidadCaracteres = preg_match_all('/./us', $passwordNueva, $coincidencias);
if ($cantidadCaracteres === false || $cantidadCaracteres < 8) {
    header("Location: ../../secretario/secretario-configuracion.php?error=longitud");
    exit();
}

if ($passwordNueva === $passwordActual) {
    header("Location: ../../secretario/secretario-configuracion.php?error=password_igual");
    exit();
}

require_once __DIR__ . '/../../config/conexion.php';

try {
    $stmt = $conexion->prepare(
        "SELECT password
         FROM usuarios
         WHERE id = :id AND rol = 'secretario'"
    );
    $stmt->execute([':id' => $_SESSION['usuario_id']]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario || !password_verify($passwordActual, $usuario['password'])) {
        header("Location: ../../secretario/secretario-configuracion.php?error=actual_incorrecta");
        exit();
    }

    $passwordHash = password_hash($passwordNueva, PASSWORD_DEFAULT);
    if ($passwordHash === false) {
        throw new RuntimeException('No se pudo generar el hash de la contraseña.');
    }

    $update = $conexion->prepare(
        "UPDATE usuarios
         SET password = :password
         WHERE id = :id AND rol = 'secretario'"
    );
    $update->execute([
        ':password' => $passwordHash,
        ':id' => $_SESSION['usuario_id']
    ]);

    unset($_SESSION['csrf_secretario']);
    header("Location: ../../secretario/secretario-configuracion.php?password_exito=1");
    exit();
} catch (Throwable $e) {
    error_log("Error al cambiar contraseña del secretario: " . $e->getMessage());
    header("Location: ../../secretario/secretario-configuracion.php?error=servidor");
    exit();
}