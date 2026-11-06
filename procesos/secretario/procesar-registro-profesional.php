<?php
//conexión a la bd
require_once __DIR__ . '/../../config/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recogemos los datos enviados por el formulario
    $dni = trim($_POST['dni']);
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $fecha_nacimiento = trim($_POST['nacimiento']);
    $telefono = trim($_POST['telefono']);
    $correo = trim($_POST['email']);
    $especialidad = trim($_POST['especialidad']);
    $matricula = trim($_POST['matricula']);

    //le pongo una contraseña temporal, igual esta obligado a cambiarla
    $password_temporal = "pausa2026"; 
    
    // Hasheamos la contraseña para guardarla segura en la BD
    $password_hash = password_hash($password_temporal, PASSWORD_DEFAULT);

    try {
        $conexion->beginTransaction();

        // 1. Insertamos en la tabla 'usuarios' con rol 'profesional' y debe_cambiar_password = 1
        $sql_usuario = "INSERT INTO usuarios (dni, nombre, apellido, fecha_nacimiento, telefono, correo, password, rol, debe_cambiar_password) 
                        VALUES (:dni, :nombre, :apellido, :fecha_nacimiento, :telefono, :correo, :password, 'profesional', 1)";
        
        $stmt_usuario = $conexion->prepare($sql_usuario);
        $stmt_usuario->execute([
            ':dni' => $dni,
            ':nombre' => $nombre,
            ':apellido' => $apellido,
            ':fecha_nacimiento' => $fecha_nacimiento,
            ':telefono' => $telefono,
            ':correo' => $correo,
            ':password' => $password_hash
        ]);
        
        // Obtenemos el ID generado automáticamente para este usuario
        $usuario_id = $conexion->lastInsertId();

        // 2. Insertamos en la tabla 'profesionales' usando 'id' y 'nro_matricula'
        $sql_profesional = "INSERT INTO profesionales (id, especialidad, nro_matricula) VALUES (:id, :especialidad, :nro_matricula)";
        $stmt_profesional = $conexion->prepare($sql_profesional);
        $stmt_profesional->execute([
            ':id' => $usuario_id,
            ':especialidad' => $especialidad,
            ':nro_matricula' => $matricula
        ]);

        // 3. Confirmamos los cambios de forma definitiva
        $conexion->commit();

        // Redirigimos al formulario pasando el DNI y la contraseña temporal por URL para el cartelito de éxito
        header("Location: ../../secretario/secretario-registrar-profesional.html?exito=1&dni=" . urlencode($dni) . "&pass=" . urlencode($password_temporal));
        exit();

    } catch (Exception $e) {
        // Si algo falla, revertimos todo
        $conexion->rollBack();
        die("Error al registrar el profesional: " . $e->getMessage());
    }
}
?>