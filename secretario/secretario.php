<?php
session_start();

// 1. valido la sesion del secretario
if (
    empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'secretario'
) {
    header("Location: ../index.html?error=sesion_requerida");
    exit();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/turnos.php';

$zonaHoraria = new DateTimeZone('America/Argentina/Buenos_Aires');
$fechaHoy = new DateTimeImmutable('today', $zonaHoraria);
$inicioDia = $fechaHoy->format('Y-m-d H:i:s');
$finDia = $fechaHoy->modify('+1 day')->format('Y-m-d H:i:s');

$totalTurnosHoy = 0;
$totalPendientesHoy = 0;
$errorMetricas = false;

try {
    actualizarTurnosPendientesVencidos($conexion);

    $stmtTurnos = $conexion->prepare(
        "SELECT
            COUNT(*) AS total_turnos,
            COALESCE(SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END), 0) AS total_pendientes
         FROM turnos
         WHERE fecha_hora >= :inicio_dia
           AND fecha_hora < :fin_dia"
    );
    $stmtTurnos->execute([
        ':inicio_dia' => $inicioDia,
        ':fin_dia' => $finDia
    ]);
    $metricas = $stmtTurnos->fetch(PDO::FETCH_ASSOC);
    $totalTurnosHoy = (int) $metricas['total_turnos'];
    $totalPendientesHoy = (int) $metricas['total_pendientes'];
} catch (Throwable $e) {
    error_log("Error al cargar métricas del secretario: " . $e->getMessage());
    $errorMetricas = true;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="description" content="Vista principal de secretario, organiza los 
    turnos en Pausa de manera sencilla.">
    <meta name="viewport" content="width=device-width, 
    initial-scale=1.0">
    <title> Pausa - Secretario </title>
    <link rel="stylesheet" href="../css/styles.css"> <!-- Enlaza el archivo styles.css para agregar estilos -->
    <script src="../js/main.js?v=password-modal-20261102-3" defer></script>
    <script src="../js/secretario.js?v=secretario-metricas-bd-20261106" defer></script>
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
                <a href="secretario.php" class="item-menu activo">
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

            <!-- Botón de cerrar sesión al pie del menú -->
            <div class="sidebar-footer">
                <a href="../index.html" class="btn-logout">
                    <span>Cerrar sesión</span>
                </a>
            </div>

        </aside>

        <main class="main-secretario">

            <header class="header-dashboard">

                <div class ="texto-bienvenida-secretario">
                    <h1> Gestión del día </h1>
                </div>

                <div class="fecha-hoy">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-calendar"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12" /><path d="M16 3v4" /><path d="M8 3v4" /><path d="M4 11h16" /><path d="M11 15h1" /><path d="M12 15v3" /></svg>
                    <time datetime="<?php echo $fechaHoy->format('Y-m-d'); ?>"><?php echo $fechaHoy->format('d M, Y'); ?></time>
                </div>

            </header>

            <!-- CONTENEDOR DE TARJETAS -->
            <div class="grilla-dashboard-secretario">

                <!-- FILA SUPERIOR: TARJETAS DE RESUMEN Y ACCESOS RÁPIDOS -->
                <div class="contenedor-tarjetas-resumen-sec">

                    <!-- Tarjeta 1: Resumen de turnos de hoy (dinámico) -->
                    <a href="secretario-turnos-hoy.php" class="tarjeta-blanca tarjeta-metrica-azul enlace-tarjeta-metrica">
                        <div class="header-metrica">
                            <h3>TURNOS HOY</h3>
                        </div>
                        <div class="cuerpo-metrica">
                            <?php if ($errorMetricas): ?>
                                <span class="numero-grande" aria-label="No disponible">—</span>
                                <span class="subtexto-metrica">No se pudieron cargar los datos</span>
                            <?php else: ?>
                                <span class="numero-grande"><?php echo $totalTurnosHoy; ?></span>
                                <span class="subtexto-metrica"><?php echo $totalPendientesHoy; ?> pendiente<?php echo $totalPendientesHoy !== 1 ? 's' : ''; ?></span>
                            <?php endif; ?>
                        </div>
                    </a>

                    <!-- Tarjeta 2: Acceso rápido a Registrar Paciente -->
                    <a href="secretario-registrar-paciente.html" class="tarjeta-blanca tarjeta-accion enlace-tarjeta-metrica">
                        <div class="info-accion">
                            <h3>REGISTRAR PACIENTE</h3>
                            <p>Dar de alta un nuevo paciente.</p>
                        </div>
                    </a>

                    <!-- Tarjeta 3: Acceso rápido a Agendas -->
                    <a href="secretario-agendas.html" class="tarjeta-blanca tarjeta-accion enlace-tarjeta-metrica">
                        <div class="info-accion">
                            <h3>GESTIONAR AGENDAS</h3>
                            <p>Ver horarios de profesionales.</p>
                        </div>
                    </a>
                
                </div>

            </div>

        </main>
    </div>

    <footer>
        <small>&copy; 2026 Pausa. Todos los derechos reservados.</small>
    </footer>

</body>
</html>