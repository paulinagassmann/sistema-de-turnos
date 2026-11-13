<?php
session_start();
require_once __DIR__ . '/../config/csrf.php';

if (
    empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'administrador'
) {
    header("Location: ../index.html?error=sesion_requerida");
    exit();
}

require_once __DIR__ . '/../config/conexion.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_es_valido()) {
    $error = 'La página venció. Recargala e intentá nuevamente.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $password_temporal = trim($_POST['password_temporal'] ?? '');

    if (empty($dni) || empty($nombre) || empty($apellido) || empty($correo) || empty($password_temporal)) {
        $error = 'Por favor, completa todos los campos obligatorios.';
    } else {
        try {
            $password_hash = password_hash($password_temporal, PASSWORD_DEFAULT);

            $sql = "INSERT INTO usuarios (dni, nombre, apellido, fecha_nacimiento, telefono, correo, password, rol, debe_cambiar_password) 
                    VALUES (:dni, :nombre, :apellido, :fecha_nacimiento, :telefono, :correo, :password, 'secretario', 1)";
            
            $stmt = $conexion->prepare($sql);
            $stmt->execute([
                ':dni' => $dni,
                ':nombre' => $nombre,
                ':apellido' => $apellido,
                ':fecha_nacimiento' => $fecha_nacimiento ?: null,
                ':telefono' => $telefono,
                ':correo' => $correo,
                ':password' => $password_hash
            ]);

            header("Location: administrador.php?exito=secretario_creado");
            exit();

        } catch (Exception $e) {
            $error = 'Error al registrar el secretario (es posible que el DNI o correo ya existan).';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pausa - Registrar Secretario</title>
    <link rel="icon" type="image/png" href="../img/favicon.png">
    <link rel="stylesheet" href="../css/styles.css?v=<?php echo filemtime(__DIR__ . '/../css/styles.css'); ?>">
    <script src="../js/main.js?v=<?php echo filemtime(__DIR__ . '/../js/main.js'); ?>" defer></script>
</head>
<body class="body-dashboard-paciente">

    <header class="header-paciente">
        <div class="bloque-marca">
            <span class="logo-texto">Pausa</span>
            <span class="subtitulo-logo">Panel de Administración</span>
        </div>
        <a href="administrador.php" class="btn-logout">Volver al panel</a>
    </header>

    <main class="main-paciente">
        <section class="seccion-turnos">
            
            <h2>Alta de Nuevo Secretario</h2>
            
            <?php if (!empty($error)): ?>
                <p class="aviso-turno-error" role="alert"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <form action="alta-secretario.php" method="POST" class="formulario-registro">
                <?php echo csrf_input(); ?>
                <div class="campo-perfil">
                    <label for="dni">DNI:</label>
                    <input type="text" id="dni" name="dni" required>
                </div>

                <div class="campo-perfil">
                    <label for="nombre">Nombre:</label>
                    <input type="text" id="nombre" name="nombre" required>
                </div>

                <div class="campo-perfil">
                    <label for="apellido">Apellido:</label>
                    <input type="text" id="apellido" name="apellido" required>
                </div>

                <div class="campo-perfil">
                    <label for="fecha_nacimiento">Fecha de Nacimiento:</label>
                    <input type="date" id="fecha_nacimiento" name="fecha_nacimiento">
                </div>

                <div class="campo-perfil">
                    <label for="telefono">Teléfono:</label>
                    <input type="tel" id="telefono" name="telefono">
                </div>

                <div class="campo-perfil">
                    <label for="correo">Correo Electrónico:</label>
                    <input type="email" id="correo" name="correo" required>
                </div>

                <div class="campo-perfil">
                    <label for="password_temporal">Contraseña Temporal:</label>
                    <input type="password" id="password_temporal" name="password_temporal" data-password-toggle required>
                </div>

                <button type="submit" class="btn-agendar">
                    <span>Crear cuenta de secretario</span>
                </button>
            </form>

        </section>
    </main>

    <footer>
        <small>&copy; 2026 Pausa. Todos los derechos reservados.</small>
    </footer>

</body>
</html>