<?php
session_start();
require_once __DIR__ . '/../config/csrf.php';

// valido que sea un profesional logueado
if (
    empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'profesional'
) {
    header("Location: ../index.html?error=sesion_requerida");
    exit();
}


require_once __DIR__ . '/../config/conexion.php';
$errorAlCargarProfesional = false;

try {
    $stmt = $conexion->prepare(
        'SELECT u.nombre, u.apellido, u.correo, p.especialidad 
         FROM usuarios u 
         JOIN profesionales p ON u.id = p.id 
         WHERE u.id = :id'
    );
    $stmt->execute([':id' => $_SESSION['usuario_id']]);
    $profesional = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profesional) {
        session_destroy();
        header("Location: ../index.html?error=usuario_no_encontrado");
        exit();
    }
} catch (Throwable $e) {
    error_log("Error al cargar configuración del profesional: " . $e->getMessage());
    http_response_code(500);
    $errorAlCargarProfesional = true;
    $profesional = null;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="description" content="Vista de la configuración del profesional.">
    <meta name="viewport" content="width=device-width, 
    initial-scale=1.0">
    <title> Pausa - Profesional </title>
    <link rel="icon" type="image/png" href="../img/favicon.png">
    <link rel="stylesheet" href="../css/styles.css?v=<?php echo filemtime(__DIR__ . '/../css/styles.css'); ?>"> <!-- Enlaza el archivo styles.css para agregar estilos -->
    <script src="../js/main.js?v=<?php echo filemtime(__DIR__ . '/../js/main.js'); ?>" defer></script>
    <script src="../js/profesional.js?v=profesional-configuracion-20261105-1" defer></script>
</head>

<body class="body-dashboard">

    <div class="layout-profesional">
        
        <!-- BARRA LATERAL (SIDEBAR) -->
        <aside class="sidebar-profesional">
            <div class="bloque-marca">
                <span class="logo-texto">Pausa</span>
                <span class="subtitulo-logo">Panel Profesional</span>
            </div>

            <!-- Navegación lateral -->
            <nav class="menu-lateral">
                <a href="profesional.php" class="item-menu">
                    <span>Inicio / Hoy</span>
                </a>
                <a href="profesional-pacientes.php" class="item-menu">
                    <span>Mis Pacientes</span>
                </a>
                <a href="profesional-agenda.php" class="item-menu">
                    <span>Agenda </span>
                </a>
                <a href="profesional-configuracion.php" class="item-menu activo">
                    <span>Configuración</span>
                </a>
            </nav>

        </aside>

        <main class="main-profesional-menu">

            <header class="header-dashboard">
                <div class="texto-bienvenida-prof">
                    <h1> Configuración </h1>
                </div>

                <div class="espaciador-header"></div>

                <a href="profesional.php" class="btn-volver-dashboard">
                    <span>Volver a Hoy</span>
                </a>
            </header>

            <!-- Avisos de éxito o error -->
            <?php
            $mensajesError = [
                'campos_vacios' => 'Ingresá un correo electrónico.',
                'correo_invalido' => 'Ingresá un correo electrónico válido.',
                'coincidencia' => 'Las contraseñas nuevas no coinciden.',
                'longitud' => 'La nueva contraseña debe tener al menos 8 caracteres.',
                'actual_incorrecta' => 'La contraseña actual no es correcta.',
                'password_igual' => 'La nueva contraseña debe ser diferente a la actual.',
                'solicitud_invalida' => 'La solicitud venció o no es válida. Recargá la página e intentá nuevamente.',
                'servidor' => 'No pudimos guardar los cambios. Intentá nuevamente más tarde.'
            ];
            if (isset($_GET['exito']) && $_GET['exito'] === '1'):
            ?>
                <p role="status" style="background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 20px;">Información actualizada con éxito.</p>
            <?php elseif (isset($_GET['password_exito']) && $_GET['password_exito'] === '1'): ?>
                <p role="status" style="background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 20px;">Contraseña actualizada con éxito.</p>
            <?php elseif (
                isset($_GET['error'])
                && is_string($_GET['error'])
                && isset($mensajesError[$_GET['error']])
            ): ?>
                <p role="alert" style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 20px;"><?php echo htmlspecialchars($mensajesError[$_GET['error']], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>

            <div class="grilla-perfil">

                <!-- TARJETA 1: INFORMACIÓN DE LA CUENTA -->
                <?php if ($errorAlCargarProfesional || !$profesional): ?>
                    <section class="tarjeta-blanca" role="alert">
                        <h2>No pudimos cargar tu configuración</h2>
                        <p>Ocurrió un problema al consultar tus datos. Intentá nuevamente más tarde.</p>
                    </section>
                <?php else: ?>
                <section class="tarjeta-blanca">
                    <h2>Información de la cuenta</h2>

                    <form class="form-perfil" action="../procesos/profesional/procesar-editar-perfil-profesional.php" method="POST">
                        <?php echo csrf_input(); ?>

                        <div class="grupo-campo">
                            <label for="nombre-profesional">Nombre y Apellido</label>
                            <input type="text" id="nombre-profesional" value="Lic. <?php echo htmlspecialchars($profesional['nombre'] . ' ' . $profesional['apellido']); ?>" disabled>
                        </div>

                        <div class="grupo-campo">
                            <label for="especialidad">Especialidad</label>
                            <input type="text" id="especialidad" value="<?php echo htmlspecialchars($profesional['especialidad']); ?>" disabled>
                        </div>

                        <div class="grupo-campo">
                            <label for="email">Correo electrónico de acceso</label>
                            <input type="email" id="email" name="correo" value="<?php echo htmlspecialchars($profesional['correo']); ?>" required>
                        </div>

                        <div class="footer-pasos">
                            <button type="submit" class="btn-siguiente-paso">Guardar cambios</button>
                        </div>

                    </form>
                </section>

                <!-- TARJETA 2: CAMBIAR CONTRASEÑA -->
                <section class="tarjeta-blanca">
                    <h2>Cambiar contraseña</h2>

                    <form class="form-perfil" action="../procesos/profesional/procesar-cambiar-password-profesional.php" method="POST">
                        <?php echo csrf_input(); ?>

                        <div class="grupo-campo">
                            <label for="contra-actual">Contraseña actual</label>
                            <input type="password" id="contra-actual" name="password_actual" placeholder="••••••••" data-password-toggle required>
                        </div>

                        <div class="grupo-campo">
                            <label for="contra-nueva">Nueva contraseña</label>
                            <input type="password" id="contra-nueva" name="password_nueva" placeholder="Ingresá la nueva contraseña" minlength="8" data-password-toggle required>
                        </div>

                        <div class="grupo-campo">
                            <label for="contra-confirmar">Confirmar nueva contraseña</label>
                            <input type="password" id="contra-confirmar" name="confirmar_password" placeholder="Repetí la nueva contraseña" minlength="8" data-password-toggle required>
                        </div>

                        <div class="footer-pasos">
                            <button type="submit" class="btn-siguiente-paso">Actualizar contraseña</button>
                        </div>

                    </form>
                </section>
                <?php endif; ?>
            </div>

        </main>
    </div>

    <footer>
        <small> &copy; 2026 Pausa. Todos los derechos reservados. </small>
    </footer>

</body>
</html>