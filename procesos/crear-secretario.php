// script para crear un secretario en la base de datos 
//con una contraseña temporal hasheada y marcar que debe cambiarla
// en su primer ingreso

<?php

require_once __DIR__ . '/../config/conexion.php';

// Datos del secretario
$dni = '47178132';
$nombre = 'Martina';
$apellido = 'Gassmann';
$fecha_nacimiento = '2006-02-15';
$telefono = '3431234567';
$correo = 'martinagassmann@gmail.com';
$password_temporal = 'secretaria123456'; 

try {
    // 1. Hasheo la contraseña usando la función de PHP
    $password_hash = password_hash($password_temporal, PASSWORD_DEFAULT);

    // 2. Preparo la consulta SQL segura para insertar al secretario con debe_cambiar_password = 1
    $sql = "INSERT INTO usuarios (dni, nombre, apellido, fecha_nacimiento, telefono, correo, password, rol, debe_cambiar_password) 
            VALUES (:dni, :nombre, :apellido, :fecha_nacimiento, :telefono, :correo, :password, 'secretario', 1)";
    
    $stmt = $conexion->prepare($sql);
    $stmt->execute([
        ':dni' => $dni,
        ':nombre' => $nombre,
        ':apellido' => $apellido,
        ':fecha_nacimiento' => $fecha_nacimiento,
        ':telefono' => $telefono,
        ':correo' => $correo,
        ':password' => $password_hash
    ]);

    echo "<h2>¡secretario creado y hasheado con exito</h2>";
    
} catch (Exception $e) {
    echo "Error al crear el secretario: " . $e->getMessage();
}
?>