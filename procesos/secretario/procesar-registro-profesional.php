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
    header("Location: ../../secretario/secretario-registrar-profesional.html?error=solicitud_invalida");
    exit();
}

//conexión a la bd
require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../config/validaciones.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recogemos los datos enviados por el formulario
    $dni = trim((string) ($_POST['dni'] ?? ''));
    $nombre = capitalizar_nombre((string) ($_POST['nombre'] ?? ''));
    $apellido = capitalizar_nombre((string) ($_POST['apellido'] ?? ''));
    $fecha_nacimiento = trim((string) ($_POST['nacimiento'] ?? ''));
    $telefono = normalizar_telefono(trim((string) ($_POST['telefono'] ?? '')));
    $correo = trim((string) ($_POST['email'] ?? ''));
    $especialidad = trim((string) ($_POST['especialidad'] ?? ''));
    $matricula = trim((string) ($_POST['matricula'] ?? ''));

    // Especialidades del desplegable; con "otro" se usa la que escribió el secretario.
    $especialidades_validas = ['psicologia', 'psiquiatria', 'familiar', 'infantil'];
    $especialidad_valida = in_array($especialidad, $especialidades_validas, true);
    if ($especialidad === 'otro') {
        $especialidad = capitalizar_nombre((string) ($_POST['especialidad_otra'] ?? ''));
        $especialidad_valida = es_nombre_valido($especialidad) && mb_strlen($especialidad, 'UTF-8') <= 150;
    }

    if (
        !es_dni_valido($dni)
        || !es_nombre_valido($nombre)
        || !es_nombre_valido($apellido)
        || !es_fecha_valida($fecha_nacimiento, '1900-01-01', date('Y-m-d'))
        || $telefono === null
        || !es_correo_valido($correo)
        || !$especialidad_valida
        || !es_matricula_valida($matricula)
    ) {
        header("Location: ../../secretario/secretario-registrar-profesional.html?error=datos_invalidos");
        exit();
    }

    //le pongo una contraseña temporal, igual esta obligado a cambiarla
    $password_temporal = "pausa2026"; 
    
    // Hasheamos la contraseña para guardarla segura en la BD
    $password_hash = password_hash($password_temporal, PASSWORD_DEFAULT);

    try {
        $conexion->beginTransaction();

        // 0. La matrícula no puede repetirse entre profesionales.
        // (FOR UPDATE bloquea la lectura para que dos altas simultáneas no carguen la misma)
        $stmt_matricula = $conexion->prepare(
            "SELECT 1 FROM profesionales WHERE nro_matricula = :nro_matricula LIMIT 1 FOR UPDATE"
        );
        $stmt_matricula->execute([':nro_matricula' => $matricula]);
        if ($stmt_matricula->fetchColumn()) {
            $conexion->rollBack();
            header("Location: ../../secretario/secretario-registrar-profesional.html?error=matricula_repetida");
            exit();
        }

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

        // guardo las credenciales en la sesión (no en la URL) para el cartelito de éxito
        $_SESSION['credenciales_registro'] = ['dni' => $dni, 'pass' => $password_temporal];
        header("Location: ../../secretario/secretario-registrar-profesional.html?exito=1");
        exit();

    } catch (Exception $e) {
        // Si algo falla, revertimos todo
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        error_log("Error al registrar el profesional: " . $e->getMessage());
        header("Location: ../../secretario/secretario-registrar-profesional.html?error=duplicado");
        exit();
    }
}
?>