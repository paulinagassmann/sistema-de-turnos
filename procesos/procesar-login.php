<?php
session_start();

// Conexión a la base de datos
require_once __DIR__ . '/../config/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dni = trim($_POST['dni']);
    $password_ingresada = trim($_POST['password']);

    try {
        // 1. Buscamos al usuario en la tabla 'usuarios' utilizando su DNI
        $sql = "SELECT * FROM usuarios WHERE dni = :dni";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([':dni' => $dni]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2. Validamos si el usuario existe y si la contraseña coincide con el hash de la BD
        if ($usuario && password_verify($password_ingresada, $usuario['password'])) {
            
            // Guardamos los datos esenciales en las variables de sesión
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['nombre'] = $usuario['nombre'];
            $_SESSION['apellido'] = $usuario['apellido'];
            $_SESSION['rol'] = $usuario['rol'];

            // Todas las cuentas con contraseña temporal deben actualizarla en su primer ingreso.
            if (
                in_array($usuario['rol'], ['secretario', 'profesional', 'paciente'], true)
                && (int) $usuario['debe_cambiar_password'] === 1
            ) {
                header("Location: ../cambiar-password.html");
                exit();
            }

            // 4. Redireccionamiento automático e inteligente según el rol registrado en la BD
            switch ($usuario['rol']) {
                case 'secretario':
                    header("Location: ../secretario/secretario.html");
                    break;
                case 'profesional':
                    header("Location: ../profesional/profesional.php");
                    break;
                case 'paciente':
                    header("Location: ../paciente/paciente.php");
                    break;
                default:
                    header("Location: ../index.html?error=rol_desconocido");
                    break;
            }
            exit();

        } else {
            // Si el DNI no existe o la contraseña es incorrecta
            header("Location: ../index.html?error=credenciales_invalidas");
            exit();
        }

    } catch (Exception $e) {
        die("Error en el sistema de login: " . $e->getMessage());
    }
}
?>