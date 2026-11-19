<?php
session_start();
require_once __DIR__ . '/../config/csrf.php';

// Valido que el usuario haya iniciado sesión y sea administrador
if (
    empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'administrador'
) {
    header("Location: ../index.html?error=sesion_requerida");
    exit();
}

require_once __DIR__ . '/../config/conexion.php';

try {
    $stmtUsuario = $conexion->prepare('SELECT nombre, apellido FROM usuarios WHERE id = :id');
    $stmtUsuario->execute([':id' => $_SESSION['usuario_id']]);
    $datosAdmin = $stmtUsuario->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $datosAdmin = ['nombre' => 'Administrador', 'apellido' => ''];
}

// Credenciales del secretario recién registrado (se muestran una sola vez en el cartel).
$credencialesSecretario = $_SESSION['credenciales_secretario'] ?? null;
unset($_SESSION['credenciales_secretario']);
if (!is_array($credencialesSecretario)) {
    $credencialesSecretario = null;
}

// El panel puede mostrar una contraseña temporal: que el navegador no lo guarde en caché
// (así no reaparece al usar el botón "Atrás").
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pausa - Panel de Administración</title>
    <link rel="icon" type="image/png" href="../img/favicon.png">
    <link rel="stylesheet" href="../css/styles.css?v=<?php echo filemtime(__DIR__ . '/../css/styles.css'); ?>">
    <script src="../js/main.js?v=<?php echo filemtime(__DIR__ . '/../js/main.js'); ?>" defer></script>
</head>
<body> 

    <header class="header-administrador">
        <div class="bloque-marca">
            <span class="logo-texto">Pausa</span>
            <span class="subtitulo-logo">Panel de Administración</span>
        </div>
        <form action="../procesos/cerrar-sesion.php" method="POST" class="form-logout">
            <?php echo csrf_input(); ?>
            <button type="submit" class="btn-logout">Cerrar sesión</button>
        </form>
    </header>

    <main class="main-administrador">
        <section class="seccion-turnos">
            
            <header class="encabezado-seccion">
                <h1>Panel de Control</h1>
                <p>Bienvenido, <?php echo htmlspecialchars($datosAdmin['nombre'] . ' ' . $datosAdmin['apellido']); ?>. Desde aquí puedes gestionar las cuentas administrativas y los respaldos del sistema.</p>
            </header>

            <?php if (!$credencialesSecretario && isset($_GET['exito']) && $_GET['exito'] === 'secretario_creado'): ?>
                <p class="aviso-turno-agendado" role="status">¡El secretario se ha registrado correctamente con contraseña temporal!</p>
            <?php endif; ?>

            <!-- Botones de acciones del Administrador -->
            <a href="alta-secretario.php" class="btn-agendar">
                <span>Registrar Nuevo Secretario</span>
            </a>

            <!-- Botón para generar el respaldo -->
            <form action="generar-backup.php" method="POST">
                <?php echo csrf_input(); ?>
                <button type="submit" class="btn-secundario-modal">
                    <span>Generar backup</span>
                </button>
            </form>

        </section>
    </main>

    <footer>
        <small>&copy; 2026 Pausa. Todos los derechos reservados.</small>
    </footer>

    <?php if ($credencialesSecretario): ?>
    <!-- Cartel de éxito al registrar un secretario: usuario y contraseña temporal (con ojito). Lo abre js/main.js -->
    <dialog id="modalExitoSecretario" class="modal-dialog" data-abrir-al-cargar
        aria-labelledby="titulo-exito-secretario" aria-describedby="texto-exito-secretario">
        <div class="modal-card">
            <div class="modal-icono-exito" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12l5 5l10 -10" />
                </svg>
            </div>

            <h2 class="modal-titulo" id="titulo-exito-secretario">¡Secretario registrado con éxito!</h2>
            <p class="modal-subtitulo" id="texto-exito-secretario">Credenciales temporales de acceso (El sistema exigirá cambio de contraseña en su primer ingreso):</p>

            <div class="modal-credenciales-box">
                <div class="fila-credencial">
                    <span class="label-cred">USUARIO (DNI)</span>
                    <span class="valor-cred"><?php echo htmlspecialchars((string) ($credencialesSecretario['dni'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="divisor-cred"></div>
                <div class="fila-credencial">
                    <span class="label-cred">CONTRASEÑA TEMPORAL</span>
                    <span class="valor-cred-password">
                        <span class="valor-cred" id="modal-sec-pass"
                              data-password-oculta="<?php echo htmlspecialchars((string) ($credencialesSecretario['pass'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">********</span>
                        <button type="button" class="boton-ver-password" aria-controls="modal-sec-pass"
                                aria-label="Mostrar contraseña" title="Mostrar contraseña"></button>
                    </span>
                </div>
            </div>

            <form class="modal-acciones" method="dialog">
                <button class="btn-finalizar-modal" type="submit">Finalizar</button>
            </form>
        </div>
    </dialog>
    <?php endif; ?>

    <!-- Cartel de éxito tras cambiar la contraseña temporal en el primer ingreso (lo abre js/main.js) -->
    <dialog id="modalPasswordActualizada" class="modal-dialog modal-confirmacion-password"
        aria-labelledby="titulo-password-actualizada" aria-describedby="texto-password-actualizada">
        <div class="modal-card">
            <div class="modal-icono-exito" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12l5 5l10 -10" />
                </svg>
            </div>
            <h2 class="modal-titulo" id="titulo-password-actualizada">¡Contraseña actualizada con éxito!</h2>
            <p class="modal-subtitulo" id="texto-password-actualizada">
                Ya podés continuar usando tu cuenta con tu nueva contraseña.
            </p>
            <form class="modal-acciones" method="dialog">
                <button class="btn-finalizar-modal" type="submit">Continuar</button>
            </form>
        </div>
    </dialog>

</body>
</html>