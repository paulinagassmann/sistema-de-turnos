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

require_once __DIR__ . '/../../config/validaciones.php';

$telefono = trim($telefonoRecibido);
$correo = trim($correoRecibido);
if ($telefono === '' || $telefono === '+54' || $correo === '') {
    header("Location: " . $urlEdicion . "&error=campos_vacios");
    exit();
}

// Teléfono: solo números (10 a 13 después del +54), en el formato único del sistema.
$telefono = normalizar_telefono($telefono);
if ($telefono === null) {
    header("Location: " . $urlEdicion . "&error=telefono_invalido");
    exit();
}

// Correo: sin espacios, con @ y un dominio con punto (ej: .com, .com.ar).
if (!es_correo_valido($correo)) {
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

    // El correo es único: aviso claro si ya lo usa otra cuenta.
    $stmtCorreo = $conexion->prepare("SELECT 1 FROM usuarios WHERE correo = :correo AND id <> :id LIMIT 1");
    $stmtCorreo->execute([':correo' => $correo, ':id' => $paciente_id]);
    if ($stmtCorreo->fetchColumn()) {
        header("Location: " . $urlEdicion . "&error=correo_repetido");
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