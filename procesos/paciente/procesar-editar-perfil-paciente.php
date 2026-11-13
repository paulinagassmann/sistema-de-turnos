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
) {
    header("Location: ../../paciente/perfil-paciente.php?error=campos_vacios");
    exit();
}

$correo = trim($correo);
$telefono = trim($telefono);
$obra_social = trim($obra_social);

if (filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
    header("Location: ../../paciente/perfil-paciente.php?error=correo_invalido");
    exit();
}

try {
    $conexion->beginTransaction();

    $stmtPaciente = $conexion->prepare('SELECT id FROM pacientes WHERE id = :id');
    $stmtPaciente->execute([':id' => $id_usuario]);
    if (!$stmtPaciente->fetchColumn()) {
        throw new RuntimeException('No se encontró el registro del paciente.');
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