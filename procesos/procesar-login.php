<?php
session_start();
require_once __DIR__ . '/../config/csrf.php';

// Conexión a la base de datos
require_once __DIR__ . '/../config/conexion.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../index.html");
    exit();
}

// verifico el token CSRF (evita que otra página haga iniciar sesión al usuario con otra cuenta)
if (!csrf_es_valido()) {
    header("Location: ../index.html?error=solicitud_invalida");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $dni = is_string($_POST['dni'] ?? null) ? trim($_POST['dni']) : '';
    $password_ingresada = is_string($_POST['password'] ?? null) ? trim($_POST['password']) : '';

    try {
        // busco al usuario en la tabla 'usuarios' utilizando su DNI
        $sql = "SELECT * FROM usuarios WHERE dni = :dni";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([':dni' => $dni]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        // valido si el usuario existe y si la contraseña coincide con el hash de la BD
        if ($usuario && password_verify($password_ingresada, $usuario['password'])) {

            // genero un id de sesión nuevo al iniciar sesión para evitar
            // que alguien reutilice un id de sesión fijado de antemano
            session_regenerate_id(true);
            csrf_renovar();

            // guardo los datos esenciales en las variables de sesión
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['nombre'] = $usuario['nombre'];
            $_SESSION['apellido'] = $usuario['apellido'];
            $_SESSION['rol'] = $usuario['rol'];

            // todas las cuentas con contraseña temporal deben actualizarla en su primer ingreso.
            if (
                in_array($usuario['rol'], ['secretario', 'profesional', 'paciente', 'administrador'], true)
                && (int) $usuario['debe_cambiar_password'] === 1
            ) {
                header("Location: ../cambiar-password.html");
                exit();
            }

            // redireccionamiento automático según el rol registrado en la bd
            switch ($usuario['rol']) {
                case 'secretario':
                    header("Location: ../secretario/secretario.php");
                    break;
                case 'profesional':
                    header("Location: ../profesional/profesional.php");
                    break;
                case 'paciente':
                    header("Location: ../paciente/paciente.php");
                    break;
                case 'administrador':
                    header("Location: ../administrador/administrador.php");
                    break;
                default:
                    header("Location: ../index.html?error=rol_desconocido");
                    break;
            }
            exit();

        } else {
            // si el DNI no existe o la contraseña es incorrecta
            header("Location: ../index.html?error=credenciales_invalidas");
            exit();
        }

    } catch (Exception $e) {
        error_log("Error en el sistema de login: " . $e->getMessage());
        http_response_code(500);
        die("Error en el sistema de login. Intentá de nuevo más tarde.");
    }
}
?>