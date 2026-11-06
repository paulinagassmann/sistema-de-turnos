<?php
session_start();

function volverAlCambioPassword($error)
{
    header("Location: ../cambiar-password.html?error=" . urlencode($error));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    volverAlCambioPassword('servidor');
}

// Solo se permite cambiar la contraseña a los roles válidos del sistema.
if (
    empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || !in_array($_SESSION['rol'], ['secretario', 'profesional', 'paciente'], true)
) {
    header("Location: ../index.html?error=sesion_requerida");
    exit();
}

$rutasPorRol = [
    'secretario' => '../secretario/secretario.php',
    'profesional' => '../profesional/profesional.php',
    'paciente' => '../paciente/paciente.php'
];
$destino = $rutasPorRol[$_SESSION['rol']];

$password_actual = $_POST['password_actual'] ?? null;
$password_nueva = $_POST['password_nueva'] ?? null;
$confirmar_password = $_POST['confirmar_password'] ?? null;

if (
    !is_string($password_actual)
    || !is_string($password_nueva)
    || !is_string($confirmar_password)
) {
    volverAlCambioPassword('servidor');
}

if ($password_nueva !== $confirmar_password) {
    volverAlCambioPassword('coincidencia');
}

$cantidad_caracteres = preg_match_all('/./us', $password_nueva, $coincidencias);
if ($cantidad_caracteres === false || $cantidad_caracteres < 8) {
    volverAlCambioPassword('longitud');
}

require_once __DIR__ . '/../config/conexion.php';

try {
    $consulta = $conexion->prepare(
        'SELECT password, debe_cambiar_password, rol FROM usuarios WHERE id = :id'
    );
    $consulta->execute([':id' => $_SESSION['usuario_id']]);
    $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$usuario || $usuario['rol'] !== $_SESSION['rol']) {
        header("Location: ../index.html?error=sesion_requerida");
        exit();
    }

    if ((int) $usuario['debe_cambiar_password'] !== 1) {
        header("Location: " . $destino);
        exit();
    }

    if (!password_verify($password_actual, $usuario['password'])) {
        volverAlCambioPassword('actual');
    }

    if ($password_nueva === $password_actual) {
        volverAlCambioPassword('distinta');
    }

    $password_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
    if ($password_hash === false) {
        throw new RuntimeException('No se pudo generar el hash de la contraseña.');
    }

    $actualizacion = $conexion->prepare(
        'UPDATE usuarios
         SET password = :password, debe_cambiar_password = 0
         WHERE id = :id AND debe_cambiar_password = 1'
    );
    $actualizacion->execute([
        ':password' => $password_hash,
        ':id' => $_SESSION['usuario_id']
    ]);

    if ($actualizacion->rowCount() !== 1) {
        header("Location: " . $destino);
        exit();
    }

    header("Location: " . $destino . "?password_actualizada=1");
    exit();
} catch (Throwable $error) {
    error_log('Error al cambiar la contraseña: ' . $error->getMessage());
    volverAlCambioPassword('servidor');
}
?>
