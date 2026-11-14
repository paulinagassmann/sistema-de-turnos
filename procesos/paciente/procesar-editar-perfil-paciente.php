<?php
session_start();
require_once __DIR__ . '/../../config/csrf.php';

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'paciente'
) {
    header("Location: ../../index.html?error=sesion_requerida");
    exit();
}

// verifico el token CSRF (que el envío venga de un formulario de este sistema)
if (!csrf_es_valido()) {
    header("Location: ../../paciente/perfil-paciente.php?error=solicitud_invalida");
    exit();
}

require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../config/validaciones.php';

$correo = $_POST['correo'] ?? null;
$telefono = $_POST['telefono'] ?? null;
$obra_social = $_POST['obra_social'] ?? '';
$id_usuario = $_SESSION['usuario_id'];

if (
    !is_string($correo)
    || !is_string($telefono)
    || !is_string($obra_social)
    || trim($correo) === ''
    || trim($telefono) === ''
    || trim($telefono) === '+54'
) {
    header("Location: ../../paciente/perfil-paciente.php?error=campos_vacios");
    exit();
}

$correo = trim($correo);
$telefono = trim($telefono);
$obra_social = trim($obra_social);

if (!es_correo_valido($correo)) {
    header("Location: ../../paciente/perfil-paciente.php?error=correo_invalido");
    exit();
}

// Obra social (opcional): letras, números y espacios, sin caracteres especiales.
$obra_social = preg_replace('/\s+/u', ' ', $obra_social);
if (
    $obra_social !== ''
    && (!es_alfanumerico_valido($obra_social) || mb_strlen($obra_social, 'UTF-8') > 150)
) {
    header("Location: ../../paciente/perfil-paciente.php?error=obra_social_invalida");
    exit();
}

// Teléfono: solo números (10 a 13 después del +54), en el formato único del sistema.
$telefono = normalizar_telefono($telefono);
if ($telefono === null) {
    header("Location: ../../paciente/perfil-paciente.php?error=telefono_invalido");
    exit();
}

try {
    $conexion->beginTransaction();

    $stmtPaciente = $conexion->prepare('SELECT id FROM pacientes WHERE id = :id');
    $stmtPaciente->execute([':id' => $id_usuario]);
    if (!$stmtPaciente->fetchColumn()) {
        throw new RuntimeException('No se encontró el registro del paciente.');
    }

    // El correo es único: aviso claro si ya lo usa otra cuenta.
    $stmtCorreo = $conexion->prepare('SELECT 1 FROM usuarios WHERE correo = :correo AND id <> :id LIMIT 1');
    $stmtCorreo->execute([':correo' => $correo, ':id' => $id_usuario]);
    if ($stmtCorreo->fetchColumn()) {
        $conexion->rollBack();
        header("Location: ../../paciente/perfil-paciente.php?error=correo_repetido");
        exit();
    }

    $stmtUsuario = $conexion->prepare(
        'UPDATE usuarios SET correo = :correo, telefono = :telefono WHERE id = :id'
    );
    $stmtUsuario->execute([
        ':correo' => $correo,
        ':telefono' => $telefono,
        ':id' => $id_usuario
    ]);

    $stmtPaciente = $conexion->prepare(
        'UPDATE pacientes SET obra_social = :obra_social WHERE id = :id'
    );
    $stmtPaciente->execute([
        ':obra_social' => $obra_social !== '' ? $obra_social : null,
        ':id' => $id_usuario
    ]);

    $conexion->commit();

    header("Location: ../../paciente/perfil-paciente.php?exito=1");
    exit();
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    error_log("Error al actualizar perfil: " . $e->getMessage());
    header("Location: ../../paciente/perfil-paciente.php?error=servidor");
    exit();
}