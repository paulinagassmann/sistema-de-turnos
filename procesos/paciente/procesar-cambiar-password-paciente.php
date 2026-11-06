<?php
session_start();

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'paciente'
) {
    header("Location: ../../index.html?error=sesion_requerida");
    exit();
}

require_once __DIR__ . '/../../config/conexion.php';

$password_actual = $_POST['password_actual'] ?? null;
$password_nueva = $_POST['password_nueva'] ?? null;
$confirmar_password = $_POST['confirmar_password'] ?? null;
$id_usuario = $_SESSION['usuario_id'];

if (
    !is_string($password_actual)
    || !is_string($password_nueva)
    || !is_string($confirmar_password)
) {
    header("Location: ../../paciente/perfil-paciente.php?error=campos_password");
    exit();
}

if ($password_nueva !== $confirmar_password) {
    header("Location: ../../paciente/perfil-paciente.php?error=coincidencia");
    exit();
}

if ($password_actual === '' || $password_nueva === '') {
    header("Location: ../../paciente/perfil-paciente.php?error=campos_password");
    exit();
}

$cantidadCaracteres = preg_match_all('/./us', $password_nueva, $coincidencias);
if ($cantidadCaracteres === false || $cantidadCaracteres < 8) {
    header("Location: ../../paciente/perfil-paciente.php?error=longitud");
    exit();
}

try {
    $stmt = $conexion->prepare('SELECT password FROM usuarios WHERE id = :id');
    $stmt->execute([':id' => $id_usuario]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario || !password_verify($password_actual, $usuario['password'])) {
        header("Location: ../../paciente/perfil-paciente.php?error=actual_incorrecta");
        exit();
    }

    $password_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
    if ($password_hash === false) {
        throw new RuntimeException('No se pudo generar el hash de la contraseña.');
    }

    $update = $conexion->prepare('UPDATE usuarios SET password = :password WHERE id = :id');
    $update->execute([':password' => $password_hash, ':id' => $id_usuario]);

    header("Location: ../../paciente/perfil-paciente.php?password_exito=1");
    exit();
} catch (Throwable $e) {
    error_log("Error al cambiar contraseña desde perfil: " . $e->getMessage());
    header("Location: ../../paciente/perfil-paciente.php?error=servidor");
    exit();
}