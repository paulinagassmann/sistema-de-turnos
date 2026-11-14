<?php
session_start();
require_once __DIR__ . '/../../config/csrf.php';

// solo un secretario con sesión iniciada puede registrar cuentas, y solo por POST
if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'secretario'
) {
    header("Location: ../../index.html?error=sesion_requerida");
    exit();
}

// verifico el token CSRF (que el envío venga de un formulario de este sistema)
if (!csrf_es_valido()) {
    header("Location: ../../secretario/secretario-registrar-paciente.html?error=solicitud_invalida");
    exit();
}

require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../config/validaciones.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Obtengo los datos del formulario
    $nombre = capitalizar_nombre((string) ($_POST['nombre'] ?? ''));
    $apellido = capitalizar_nombre((string) ($_POST['apellido'] ?? ''));
    $dni = trim((string) ($_POST['dni'] ?? ''));
    $fecha_nacimiento = trim((string) ($_POST['nacimiento'] ?? ''));
    $telefono = normalizar_telefono(trim((string) ($_POST['telefono'] ?? '')));
    $correo = trim((string) ($_POST['email'] ?? ''));

    // Obra social: solo si tiene cobertura; letras, números y espacios.
    $tiene_cobertura = (string) ($_POST['tiene_cobertura'] ?? 'no');
    $obra_social = null;
    $obra_social_valida = true;
    if ($tiene_cobertura === 'si') {
        $obra_social = preg_replace('/\s+/u', ' ', trim((string) ($_POST['obra_social'] ?? '')));
        $obra_social_valida = es_alfanumerico_valido($obra_social) && mb_strlen($obra_social, 'UTF-8') <= 150;
    }

    if (
        !es_dni_valido($dni)
        || !es_nombre_valido($nombre)
        || !es_nombre_valido($apellido)
        || !es_fecha_valida($fecha_nacimiento, '1900-01-01', date('Y-m-d'))
        || $telefono === null
        || !es_correo_valido($correo)
        || !$obra_social_valida
    ) {
        header("Location: ../../secretario/secretario-registrar-paciente.html?error=datos_invalidos");
        exit();
    }

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

        // Éxito: guardo las credenciales en la sesión (no en la URL, para que la
        // contraseña no quede en el historial del navegador ni en los logs del servidor)
        $_SESSION['credenciales_registro'] = ['dni' => $dni, 'pass' => $anio_nacimiento];
        header("Location: ../../secretario/secretario-registrar-paciente.html?exito=1");
        exit();

    } catch (Exception $e) {
        // Si ocurre algún error (ej: DNI repetido), deshacemos todo lo que intentó guardar
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        error_log("Error al registrar el paciente: " . $e->getMessage());
        header("Location: ../../secretario/secretario-registrar-paciente.html?error=duplicado");
        exit();
    }
}

?>