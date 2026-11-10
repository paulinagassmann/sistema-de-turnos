<?php
session_start();

// 1. Validamos que el usuario haya iniciado sesión y sea específicamente 'secretario'
if (
    empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'secretario'
) {
    header("Location: ../index.html?error=sesion_requerida");
    exit();
}

if (empty($_SESSION['csrf_secretario'])) {
    $_SESSION['csrf_secretario'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../config/conexion.php';
$errorAlCargarSecretario = false;

try {
    // 2. Buscamos los datos de la cuenta del secretario
    $stmt = $conexion->prepare(
        'SELECT nombre, apellido, correo, dni 
         FROM usuarios 
         WHERE id = :id AND rol = :rol'
    );
    $stmt->execute([
        ':id' => $_SESSION['usuario_id'],
        ':rol' => 'secretario'
    ]);
    $secretario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$secretario) {
        session_destroy();
        header("Location: ../index.html?error=usuario_no_encontrado");
        exit();
    }
} catch (Throwable $e) {
    error_log("Error al cargar configuración del secretario: " . $e->getMessage());
    http_response_code(500);
    $errorAlCargarSecretario = true;
    $secretario = null;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="description" content="Vista de secretario para configurar su cuenta en Pausa.">
    <meta name="viewport" content="width=device-width, 
    initial-scale=1.0">
    <title> Pausa - Secretario </title>
    <link rel="stylesheet" href="../css/styles.css"> <!-- Enlaza el archivo styles.css para agregar estilos -->
    <script src="../js/main.js?v=password-toggle-20261102" defer></script>
    <script src="../js/secretario.js?v=<?php echo filemtime(__DIR__ . '/../js/secretario.js'); ?>" defer></script>
</head>

<body class="body-dashboard">

    <div class="layout-secretario">
        
        <!-- BARRA LATERAL (SIDEBAR) -->
        <aside class="sidebar-secretario">
            <div class="bloque-marca">
                <span class="logo-texto">Pausa</span>
                <span class="subtitulo-logo">Panel Secretario</span>
            </div>

            <!-- Botón principal de acción rápida (dar turno) -->
            <div class="bloque-accion-sidebar">
                <a href="secretario-dar-turno.php" class="btn-dar-turno">+ Dar turno</a>
            </div>

            <!-- Navegación lateral -->
            <nav class="menu-lateral">
                <a href="secretario.php" class="item-menu">
                    <span>Inicio / Hoy</span>
                </a>
                <a href="secretario-pacientes.php" class="item-menu">
                    <span>Pacientes</span>
                </a>
                <a href="secretario-agendas.html" class="item-menu">
                    <span>Agendas </span>
                </a>
                <a href="secretario-registrar-profesional.html" class="item-menu">
                    <span>Alta Profesional </span>
                </a>
                <a href="secretario-configuracion.php" class="item-menu activo">
                    <span>Configuración</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="../index.html" class="btn-logout">
                    <span>Cerrar sesión</span>
                </a>
            </div>

        </aside>

        <main class="main-secretario">

            <header class="header-dashboard">
                <div class="texto-bienvenida-secretario">
                    <h1>Configuración</h1>
                    <p>Gestioná los datos de acceso a tu cuenta administrativa.</p>
                </div>
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
                <p role="status" style="background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 20px;">Correo actualizado con éxito.</p>
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

                <?php if ($errorAlCargarSecretario || !$secretario): ?>
                    <section class="tarjeta-blanca" role="alert">
                        <h2>No pudimos cargar tu configuración</h2>
                        <p>Ocurrió un problema al consultar tus datos. Intentá nuevamente más tarde.</p>
                    </section>
                <?php else: ?>


                <!-- TARJETA: CAMBIAR CONTRASEÑA -->
                <section class="tarjeta-blanca">
                    <h2>Seguridad y contraseña</h2>
                    <p class="descripcion-tarjeta-config">Actualizá tu contraseña periódicamente para mantener la seguridad.</p>

                    <form class="form-perfil" action="../procesos/secretario/procesar-cambiar-password-secretario.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_secretario'], ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="grupo-campo">
                            <label for="contra-actual">Contraseña actual</label>
                            <input type="password" id="contra-actual" name="password_actual" placeholder="••••••••" class="input-form" data-password-toggle required>
                        </div>

                        <div class="grupo-campo">
                            <label for="contra-nueva">Nueva contraseña</label>
                            <input type="password" id="contra-nueva" name="password_nueva" placeholder="Ingresá la nueva contraseña" class="input-form" data-password-toggle minlength="8" required>
                        </div>

                        <div class="grupo-campo">
                            <label for="contra-confirmar">Confirmar nueva contraseña</label>
                            <input type="password" id="contra-confirmar" name="confirmar_password" placeholder="Repetí la nueva contraseña" class="input-form" data-password-toggle minlength="8" required>
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