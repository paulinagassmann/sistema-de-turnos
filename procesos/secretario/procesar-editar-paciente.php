<?php
session_start();
require_once __DIR__ . '/../../config/csrf.php';

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'secretario'
) {
    header("Location: ../../index.html?error=sesion_requerida");
    exit();
}

$idRecibido = $_POST['paciente_id'] ?? null;
$paciente_id = is_scalar($idRecibido)
    ? filter_var((string) $idRecibido, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ])
    : false;
if ($paciente_id === false) {
    header("Location: ../../secretario/secretario-pacientes.php?error=paciente_invalido");
    exit();
}

$urlEdicion = '../../secretario/secretario-editar-perfil-paciente.php?id=' . $paciente_id;
if (
    !csrf_es_valido()
) {
    header("Location: " . $urlEdicion . "&error=solicitud_invalida");
    exit();
}

$telefonoRecibido = $_POST['telefono'] ?? null;
$correoRecibido = $_POST['correo'] ?? null;
if (!is_string($telefonoRecibido) || !is_string($correoRecibido)) {
    header("Location: " . $urlEdicion . "&error=campos_vacios");
    exit();
}

$telefono = trim($telefonoRecibido);
$correo = trim($correoRecibido);
if ($telefono === '' || $correo === '') {
    header("Location: " . $urlEdicion . "&error=campos_vacios");
    exit();
}
if (filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
    header("Location: " . $urlEdicion . "&error=correo_invalido");
    exit();
}

require_once __DIR__ . '/../../config/conexion.php';

try {
    $stmtPaciente = $conexion->prepare(
        "SELECT p.id
         FROM pacientes p
         JOIN usuarios u ON u.id = p.id AND u.rol = 'paciente'
         WHERE p.id = :id"
    );
    $stmtPaciente->execute([':id' => $paciente_id]);
    if (!$stmtPaciente->fetchColumn()) {
        header("Location: ../../secretario/secretario-pacientes.php?error=no_encontrado");
        exit();
    }

    $stmt = $conexion->prepare(
        "UPDATE usuarios SET telefono = :telefono, correo = :correo WHERE id = :id AND rol = 'paciente'"
    );
    $stmt->execute([
        ':telefono' => $telefono,
        ':correo' => $correo,
        ':id' => $paciente_id
    ]);

    header("Location: " . $urlEdicion . "&exito=1");
    exit();
} catch (Throwable $e) {
    error_log("Error al actualizar paciente desde secretaría: " . $e->getMessage());
    header("Location: " . $urlEdicion . "&error=servidor");
    exit();
}