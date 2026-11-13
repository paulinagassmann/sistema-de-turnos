<?php
// Este script crea el administrador inicial. Solo se puede ejecutar desde la
// consola (php administrador/crear-administrador.php), nunca desde el navegador:
// si estuviera accesible por URL, cualquiera podría crearse una cuenta de administrador.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

require_once __DIR__ . '/../config/conexion.php';

$dni = '45758272';
$nombre = 'Administrador';
$apellido = 'General';
$fecha_nacimiento = '1990-01-01'; // fecha generica pq no es relevante
$telefono = '3430000000';
$correo = 'admin@pausa.com';
$password_temporal = 'admin123456'; // contra temporal (se obliga a cambiarla al primer ingreso)

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

    fwrite(STDOUT, "Administrador creado con éxito. Ya podés iniciar sesión." . PHP_EOL);

} catch (Exception $e) {
    fwrite(STDERR, "Error al crear el administrador: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
