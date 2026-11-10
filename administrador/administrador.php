<?php
session_start();

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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pausa - Panel de Administración</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body> 

    <header class="header-administrador">
        <div class="bloque-marca">
            <span class="logo-texto">Pausa</span>
            <span class="subtitulo-logo">Panel de Administración</span>
        </div>
        <a href="../index.html" class="btn-logout">Cerrar sesión</a>
    </header>

    <main class="main-administrador">
        <section class="seccion-turnos">
            
            <header class="encabezado-seccion">
                <h1>Panel de Control</h1>
                <p>Bienvenido, <?php echo htmlspecialchars($datosAdmin['nombre'] . ' ' . $datosAdmin['apellido']); ?>. Desde aquí puedes gestionar las cuentas administrativas y los respaldos del sistema.</p>
            </header>

            <?php if (isset($_GET['exito']) && $_GET['exito'] === 'secretario_creado'): ?>
                <p class="aviso-turno-agendado" role="status">¡El secretario se ha registrado correctamente con contraseña temporal!</p>
            <?php endif; ?>

            <!-- Botones de acciones del Administrador -->
            <a href="alta-secretario.php" class="btn-agendar">
                <span>Registrar Nuevo Secretario</span>
            </a>

            <!-- Botón para generar el respaldo -->
            <a href="generar-backup.php" class="btn-secundario-modal">
                <span>Generar backup</span>
            </a>

        </section>
    </main>

    <footer>
        <small>&copy; 2026 Pausa. Todos los derechos reservados.</small>
    </footer>

</body>
</html>