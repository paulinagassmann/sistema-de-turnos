<?php

require_once __DIR__ . '/../../config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Obtengo los datos del formulario
    $nombre = $_POST['nombre'];
    $apellido = $_POST['apellido'];
    $dni = $_POST['dni'];
    $fecha_nacimiento = $_POST['nacimiento']; 
    $telefono = $_POST['telefono'];
    $correo = $_POST['email'];

    // Obra social
    $tiene_cobertura = $_POST['tiene_cobertura'];
    $obra_social = ($tiene_cobertura === 'si' && !empty($_POST['obra_social'])) ? trim($_POST['obra_social']) : null;

    try {
        // extraigo el año de nacimiento para usarlo de contraseña inicial
        $anio_nacimiento = date('Y', strtotime($fecha_nacimiento));
        $password_hash = password_hash($anio_nacimiento, PASSWORD_DEFAULT);

        // 1. Iniciamos una transacción (por seguridad, para guardar todo junto o nada)
        $conexion->beginTransaction();

        // 2. Insertamos en la tabla central 'usuarios' con cambio obligatorio de contraseña al primer ingreso.
        $sql_usuario = "INSERT INTO usuarios (dni, nombre, apellido, fecha_nacimiento, telefono, correo, password, rol, debe_cambiar_password) 
                        VALUES (:dni, :nombre, :apellido, :fecha_nacimiento, :telefono, :correo, :password, 'paciente', 1)";

        $stmt = $conexion->prepare($sql_usuario);
        $stmt->execute([
            ':dni' => $dni,
            ':nombre' => $nombre,
            ':apellido' => $apellido,
            ':fecha_nacimiento' => $fecha_nacimiento,
            ':telefono' => $telefono,
            ':correo' => $correo,
            ':password' => $password_hash
        ]);

        // 3. Obtenemos el ID que se le asignó automáticamente a este usuario
        $usuario_id = $conexion->lastInsertId();

        // 4. Insertamos en la tabla 'pacientes' vinculando ese ID y la obra social
        $sql_paciente = "INSERT INTO pacientes (id, obra_social) VALUES (:id, :obra_social)";
        $stmt_paciente = $conexion->prepare($sql_paciente);
        $stmt_paciente->execute([
            ':id' => $usuario_id,
            ':obra_social' => $obra_social
        ]);

        // 5. Si todo salió bien, confirmamos los cambios en la base de datos
        $conexion->commit();

        // Éxito: Redirigimos al formulario pasando el DNI y la contraseña (año) por la URL de manera limpia
        header("Location: ../../secretario/secretario-registrar-paciente.html?exito=1&dni=" . urlencode($dni) . "&pass=" . urlencode($anio_nacimiento));
        exit();

    } catch (Exception $e) {
        // Si ocurre algún error (ej: DNI repetido), deshacemos todo lo que intentó guardar
        $conexion->rollBack();
        die("Error al registrar el paciente: " . $e->getMessage());
    }
}

?>