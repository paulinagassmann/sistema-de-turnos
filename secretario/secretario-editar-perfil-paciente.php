<?php
session_start();
require_once __DIR__ . '/../config/csrf.php';

// 1. Validamos sesión de secretario
if (
    empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'secretario'
) {
    header("Location: ../index.html?error=sesion_requerida");
    exit();
}

require_once __DIR__ . '/../config/conexion.php';

$idRecibido = $_GET['id'] ?? null;
$dniRecibido = $_GET['dni'] ?? null;
$paciente_id = $idRecibido === null
    ? null
    : (is_scalar($idRecibido)
        ? filter_var((string) $idRecibido, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
        ])
        : false);
$dni = is_string($dniRecibido) ? trim($dniRecibido) : '';
if ($paciente_id === false || ($paciente_id === null && $dni === '')) {
    header("Location: secretario-pacientes.php?error=paciente_invalido");
    exit();
}


$paciente = null;

try {
    // 2. Buscamos los datos actuales del paciente
    if ($paciente_id !== null) {
        $stmt = $conexion->prepare(
            "SELECT u.id, u.nombre, u.apellido, u.telefono, u.correo
             FROM usuarios u
             JOIN pacientes p ON u.id = p.id
             WHERE u.id = :id AND u.rol = 'paciente'"
        );
        $stmt->execute([':id' => $paciente_id]);
    } else {
        $stmt = $conexion->prepare(
            "SELECT u.id, u.nombre, u.apellido, u.telefono, u.correo
             FROM usuarios u
             JOIN pacientes p ON u.id = p.id
             WHERE u.dni = :dni AND u.rol = 'paciente'"
        );
        $stmt->execute([':dni' => $dni]);
    }
    $paciente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$paciente) {
        header("Location: secretario-pacientes.php?error=no_encontrado");
        exit();
    }
} catch (Throwable $e) {
    error_log("Error al cargar edición de paciente: " . $e->getMessage());
    header("Location: secretario-pacientes.php?error=servidor");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="description" content="Vista de secretario para editar el perfil de un paciente.">
    <meta name="viewport" content="width=device-width, 
    initial-scale=1.0">
    <title> Pausa - Secretario </title>
    <link rel="icon" type="image/png" href="../img/favicon.png">
    <link rel="stylesheet" href="../css/styles.css?v=<?php echo filemtime(__DIR__ . '/../css/styles.css'); ?>"> <!-- Enlaza el archivo styles.css para agregar estilos -->
    <script src="../js/main.js?v=<?php echo filemtime(__DIR__ . '/../js/main.js'); ?>" defer></script>
    <script src="../js/secretario.js?v=<?php echo filemtime(__DIR__ . '/../js/secretario.js'); ?>" defer></script>
    <script src="../js/validaciones-formulario.js?v=<?php echo filemtime(__DIR__ . '/../js/validaciones-formulario.js'); ?>" defer></script>
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
                <a href="secretario-configuracion.php" class="item-menu">
                    <span>Configuración</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <form action="../procesos/cerrar-sesion.php" method="POST" class="form-logout">
                    <?php echo csrf_input(); ?>
                    <button type="submit" class="btn-logout">
                        <span>Cerrar sesión</span>
                    </button>
                </form>
            </div>

        </aside>

        <main class="main-secretario">

            <header class="header-dashboard">
                <div class="texto-bienvenida-secretario">
                    <h1>Editar perfil del paciente (<?php echo htmlspecialchars($paciente['nombre'] . ' ' . $paciente['apellido'], ENT_QUOTES, 'UTF-8'); ?>)</h1>
                </div>
            </header>

            <?php if (isset($_GET['exito']) && $_GET['exito'] === '1'): ?>
                <p style="background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px;">Datos del paciente actualizados con éxito.</p>
            <?php endif; ?>
            <?php
            $mensajesError = [
                'campos_vacios' => 'Completá el teléfono y el correo electrónico.',
                'telefono_invalido' => 'El teléfono solo puede contener números: entre 10 y 13 después del +54. Ej: +543436985148',
                'correo_invalido' => 'Ingresá un correo electrónico válido (ej: nombre@dominio.com).',
                'correo_repetido' => 'Ese correo electrónico ya está registrado en otra cuenta.',
                'solicitud_invalida' => 'La página venció. Recargala e intentá nuevamente.',
            ];
            $codigoError = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : null;
            ?>
            <?php if ($codigoError !== null): ?>
                <p style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px;" role="alert"><?php echo htmlspecialchars($mensajesError[$codigoError] ?? 'Ocurrió un error al actualizar los datos.', ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>

            <div class="grilla-perfil">

                <!-- TARJETA 1: EDITAR DATOS -->
                <section class="tarjeta-blanca">
                    
                    <form class="form-perfil" action="../procesos/secretario/procesar-editar-paciente.php" method="POST">

                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="paciente_id" value="<?php echo (int) $paciente['id']; ?>">

                        <h2> Teléfono </h2>
                        <div class="grupo-campo">
                            <label for="telefono-actual">Teléfono actual</label>
                            <input type="tel" id="telefono-actual" name="telefono" value="<?php echo htmlspecialchars(($paciente['telefono'] ?? '') !== '' ? $paciente['telefono'] : '+54', ENT_QUOTES, 'UTF-8'); ?>" class="input-form"
                                   placeholder="+543436985148" inputmode="tel" autocomplete="off" maxlength="16"
                                   data-validar="telefono" aria-describedby="ayuda-telefono" required>
                            <small id="ayuda-telefono" class="ayuda-campo">Ej: +543436985148</small>
                        </div>

                        <h2> Correo electrónico </h2>
                        <div class="grupo-campo">
                            <label for="correo-actual">Correo actual</label>
                            <input type="email" id="correo-actual" name="correo" value="<?php echo htmlspecialchars($paciente['correo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="input-form"
                                   placeholder="correo@ejemplo.com" maxlength="150" autocomplete="off" data-validar="correo"
                                   pattern="[A-Za-z0-9._%+\-]+@[A-Za-z0-9\-]+(\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,}"
                                   title="Ingresá un correo válido, por ejemplo: nombre@dominio.com" required>
                        </div>

                        <div class="footer-pasos" style="display: flex; gap: 10px; align-items: center; margin-top: 20px;">
                            <a href="secretario-pacientes.php?id=<?php echo (int) $paciente['id']; ?>" class="btn-cancelar-form" style="text-decoration: none; padding: 10px 20px;">Volver</a>
                            <button type="submit" class="btn-siguiente-paso">Actualizar</button>
                        </div>

                    </form>

                </section>

            </div>

        </main>
    </div>

    <footer>
        <small> &copy; 2026 Pausa. Todos los derechos reservados. </small>
    </footer>

</body>
</html>