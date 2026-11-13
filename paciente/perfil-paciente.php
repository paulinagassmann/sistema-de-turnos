<?php
session_start();
require_once __DIR__ . '/../config/csrf.php';

// valido que sea un paciente logueado
if (
    empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'paciente'
) {
    header("Location: ../index.html?error=sesion_requerida");
    exit();
}

//conecto a la bd
require_once __DIR__ . '/../config/conexion.php';

try {
    // obtengo los datos del paciente 
    $stmt = $conexion->prepare(
        'SELECT u.correo, u.telefono, p.obra_social 
         FROM usuarios u 
         JOIN pacientes p ON u.id = p.id 
         WHERE u.id = :id'
    );
    $stmt->execute([':id' => $_SESSION['usuario_id']]);
    $paciente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$paciente) {
        session_destroy();
        header("Location: ../index.html?error=usuario_no_encontrado");
        exit();
    }
} catch (Throwable $e) {
    error_log("Error al cargar perfil del paciente: " . $e->getMessage());
    http_response_code(500);
?>

    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Pausa - Error al cargar perfil</title>
        <link rel="icon" type="image/png" href="../img/favicon.png">
        <link rel="stylesheet" href="../css/styles.css?v=<?php echo filemtime(__DIR__ . '/../css/styles.css'); ?>">
    </head>
    <body>
        <main class="main-perfil-paciente">
            <section class="tarjeta-blanca">
                <h1>No pudimos cargar tu perfil</h1>
                <p>Ocurrió un problema al consultar tus datos. Intentá nuevamente más tarde.</p>
                <a class="btn-siguiente-paso" href="paciente.php">Volver al inicio</a>
            </section>
        </main>
    </body>
    </html>
    <?php
    exit();
}

$mensajes = [
    'exito' => 'Tus datos se actualizaron correctamente.',
    'password_exito' => 'Tu contraseña se actualizó correctamente.',
    'campos_vacios' => 'Completá el correo electrónico y el teléfono.',
    'correo_invalido' => 'Ingresá un correo electrónico válido.',
    'campos_password' => 'Completá todos los campos de contraseña.',
    'coincidencia' => 'Las contraseñas nuevas no coinciden.',
    'longitud' => 'La nueva contraseña debe tener al menos 8 caracteres.',
    'actual_incorrecta' => 'La contraseña actual es incorrecta.',
    'solicitud_invalida' => 'La página venció. Recargala e intentá nuevamente.',
    'servidor' => 'No pudimos guardar los cambios. Intentá nuevamente.'
];
$tipoMensaje = null;
$mensaje = null;
if (isset($_GET['exito']) && $_GET['exito'] === '1') {
    $tipoMensaje = 'exito';
    $mensaje = $mensajes['exito'];
} elseif (isset($_GET['password_exito']) && $_GET['password_exito'] === '1') {
    $tipoMensaje = 'exito';
    $mensaje = $mensajes['password_exito'];
} elseif (
    isset($_GET['error'])
    && is_string($_GET['error'])
    && isset($mensajes[$_GET['error']])
) {
    $tipoMensaje = 'error';
    $mensaje = $mensajes[$_GET['error']];
}
?>

<!DOCTYPE html>
<html lang="es">

 <head>
    <meta charset="UTF-8">
    <meta name="description" content="Página para que el paciente pueda 
    editar su información personal en Pausa.">
    <meta name="viewport" content="width=device-width, 
    initial-scale=1.0">
    <title> Pausa - Editar Perfil </title>
    <link rel="stylesheet" href="../css/styles.css?v=<?php echo filemtime(__DIR__ . '/../css/styles.css'); ?>"> <!-- Enlaza el archivo styles.css para agregar estilos -->
     <script src="../js/main.js?v=<?php echo filemtime(__DIR__ . '/../js/main.js'); ?>" defer></script>
    <script src="../js/paciente.js?v=perfil-db-20261103-1" defer></script>
</head>

    <body>
    
        <header class = "header-paciente">
            <div class="bloque-marca">
                <span class="logo-texto">Pausa</span>
                <span class="subtitulo-logo">Clínica de Salud Mental</span>
            </div>
            <div class="usuario-header">
                <a href="paciente.php" class="btn-cancelar-paso">&larr; Volver al inicio</a>
            </div>
        </header>

        <main class = "main-perfil-paciente">
            <header class="encabezado-seccion">
                <h1> Mi perfil </h1>
                <p> Gestioná tus datos de contacto y la seguridad de tu cuenta. </p>
            </header>

            <?php if ($mensaje !== null): ?>
                <p class="mensaje-<?php echo htmlspecialchars($tipoMensaje, ENT_QUOTES, 'UTF-8'); ?>" role="<?php echo $tipoMensaje === 'error' ? 'alert' : 'status'; ?>">
                    <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                </p>
            <?php endif; ?>

            <div class = "grilla-perfil">

            <section class="tarjeta-blanca">
                <h2>Editar información personal</h2>

                <form class="form-perfil" action="../procesos/paciente/procesar-editar-perfil-paciente.php" method="POST">
                    <?php echo csrf_input(); ?>
                    <div class="grupo-campo">
                        <label for="email">Correo electrónico</label>
                        <input type="email" id="email" name="correo" value="<?php echo htmlspecialchars($paciente['correo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div class="grupo-campo">
                        <label for="telefono">Teléfono de contacto</label>
                        <input type="tel" id="telefono" name="telefono" value="<?php echo htmlspecialchars($paciente['telefono'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div class="grupo-campo">
                        <label for="obra-social">Obra Social / Prepaga</label>
                        <input type="text" id="obra-social" name="obra_social" value="<?php echo htmlspecialchars($paciente['obra_social'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="footer-pasos">
                        <button type="submit" class="btn-siguiente-paso">Guardar cambios</button>
                    </div>
                </form>
            </section>


            <section class="tarjeta-blanca">
                <h2>Cambiar contraseña</h2>

                <form class="form-perfil" action="../procesos/paciente/procesar-cambiar-password-paciente.php" method="POST">
                    <?php echo csrf_input(); ?>

                    <div class="grupo-campo">
                        <label for="contra-actual">Contraseña actual</label>
                        <input type="password" id="contra-actual" name="password_actual" placeholder="••••••••" data-password-toggle required>
                    </div>

                    <div class="grilla-seleccion-turno">
                        <div class="grupo-campo">
                            <label for="contra-nueva">Nueva contraseña</label>
                            <input type="password" id="contra-nueva" name="password_nueva" placeholder="Nueva contraseña" minlength="8" data-password-toggle required>
                        </div>

                        <div class="grupo-campo">
                            <label for="contra-confirmar">Confirmar nueva contraseña</label>
                            <input type="password" id="contra-confirmar" name="confirmar_password" placeholder="Repetir nueva contraseña" minlength="8" data-password-toggle required>
                        </div>
                    </div>

                    <div class="footer-pasos">
                        <button type="submit" class="btn-siguiente-paso">Actualizar contraseña</button>
                    </div>

                </form>
            </section>

            </div>

        </main>

        <footer>
            <small> &copy; 2026 Pausa. Todos los derechos reservados. </small>  
        </footer>

    </body>
</html>