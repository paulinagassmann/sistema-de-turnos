<?php
require_once __DIR__ . '/../config/conexion.php';

$dni = '45758272';
$nombre = 'Administrador';
$apellido = 'General';
$fecha_nacimiento = '1990-01-01'; // fecha generica pq no es relevante
$telefono = '3430000000';
$correo = 'admin@pausa.com';
$password_temporal = 'admin123456'; // contra temporal

try {
    //hash de la contraseña
    $password_hash = password_hash($password_temporal, PASSWORD_DEFAULT);

    $sql = "INSERT INTO usuarios (dni, nombre, apellido, fecha_nacimiento, telefono, correo, password, rol, debe_cambiar_password) 
            VALUES (:dni, :nombre, :apellido, :fecha_nacimiento, :telefono, :correo, :password, 'administrador', 1)";
    
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

    echo "<h2>¡Administrador creado con éxito! Ya podés iniciar sesión.</h2>";
    
} catch (Exception $e) {
    echo "Error al crear el administrador: " . $e->getMessage();
}
?>