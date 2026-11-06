<?php
session_start();

// 1. valido que el usuario haya iniciado sesión y sea específicamente profesional
if (
    empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'profesional'
) {
    header("Location: ../index.html?error=sesion_requerida");
    exit();
}

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/turnos.php';
$errorAlCargarDatos = false;

try {
    actualizarTurnosPendientesVencidos($conexion);

    // 2. busco los datos del profesional logueado
    $stmtProf = $conexion->prepare(
        'SELECT u.nombre, u.apellido, p.especialidad 
         FROM usuarios u 
         JOIN profesionales p ON u.id = p.id 
         WHERE u.id = :id'
    );
    $stmtProf->execute([':id' => $_SESSION['usuario_id']]);
    $datosProfesional = $stmtProf->fetch(PDO::FETCH_ASSOC);

    if (!$datosProfesional) {
        session_destroy();
        header("Location: ../index.html?error=usuario_no_encontrado");
        exit();
    }

    // 3. busco los turnos de hoy para este profesional usando CURDATE() de MySQL
    $stmtTurnos = $conexion->prepare(
        "SELECT t.id, t.fecha_hora, t.estado, 
                u_pac.nombre AS pac_nombre, u_pac.apellido AS pac_apellido 
         FROM turnos t
         JOIN usuarios u_pac ON t.paciente_id = u_pac.id
         WHERE t.profesional_id = :profesional_id 
           AND DATE(t.fecha_hora) = CURDATE()
         ORDER BY t.fecha_hora ASC"
    );
    $stmtTurnos->execute([':profesional_id' => $_SESSION['usuario_id']]);
    $turnosHoy = $stmtTurnos->fetchAll(PDO::FETCH_ASSOC);

    // 4. calculo los contadores para el resumen del día
    $totalTurnos = count($turnosHoy);
    $confirmados = 0;
    $pendientes = 0;

    foreach ($turnosHoy as $turno) {
        if ($turno['estado'] === 'confirmado') {
            $confirmados++;
        } elseif ($turno['estado'] === 'pendiente') {
            $pendientes++;
        }
    }

} catch (Throwable $e) {
    error_log("Error al cargar el dashboard del profesional: " . $e->getMessage());
    http_response_code(500);
    $errorAlCargarDatos = true;
    $datosProfesional = ['nombre' => '', 'apellido' => ''];
    $turnosHoy = [];
    $totalTurnos = 0;
    $confirmados = 0;
    $pendientes = 0;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="description" content="Página principal de los profesionales de la salud mental, 
    organiza tus turnos en Pausa de manera sencilla.">
    <meta name="viewport" content="width=device-width, 
    initial-scale=1.0">
    <title> Pausa - Profesional </title>
    <link rel="stylesheet" href="../css/styles.css"> <!-- Enlaza el archivo styles.css para agregar estilos -->
    <script src="../js/main.js?v=password-modal-20261102-3" defer></script>
    <script src="../js/profesional.js?v=readonly-turno-status-20261104-2" defer></script>
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
                <a href="profesional.php" class="item-menu activo">
                    <span>Inicio / Hoy</span>
                </a>
                <a href="profesional-pacientes.php" class="item-menu">
                    <span>Mis Pacientes</span>
                </a>
                <a href="profesional-agenda.php" class="item-menu">
                    <span>Agenda </span>
                </a>
                <a href="profesional-configuracion.php" class="item-menu">
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
    

    <main class="main-profesional">
            
            <header class="header-dashboard">
                <div class="texto-bienvenida-prof">
                    <p><strong> Bienvenido, Lic. <?php echo htmlspecialchars($datosProfesional['nombre'] . ' ' . $datosProfesional['apellido']); ?></strong></p>
                    <h1> Tus turnos de hoy </h1>
                </div> 

                <div class="espaciador-header"></div>

                <div class="fecha-hoy">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-calendar"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12" /><path d="M16 3v4" /><path d="M8 3v4" /><path d="M4 11h16" /><path d="M11 15h1" /><path d="M12 15v3" /></svg>
                    <time datetime="<?php echo date('Y-m-d'); ?>"><?php echo date('d M, Y'); ?></time>
                </div>
            </header>

            <?php if ($errorAlCargarDatos): ?>
                <section class="tarjeta-blanca" role="alert">
                    <h2>No pudimos cargar tus turnos</h2>
                    <p>Ocurrió un problema al consultar la información. Intentá nuevamente más tarde.</p>
                </section>
            <?php else: ?>
            <div class="grilla-cuerpo-profesional">

                <section class="tarjeta-blanca">
                    <div
                        class="lista-turnos-hoy"
                        data-source="database"
                        data-status-endpoint="../procesos/profesional/obtener-turnos-hoy-profesional.php"
                    >
                        <?php if (!empty($turnosHoy)): ?>
                            <?php foreach ($turnosHoy as $turno): ?>
                                <article class="fila-turno" data-turno-id="<?php echo (int) $turno['id']; ?>">
                                    <div class="info-paciente-turno">
                                        <div>
                                            <strong><?php echo htmlspecialchars($turno['pac_nombre'] . ' ' . $turno['pac_apellido']); ?></strong>
                                        </div>
                                    </div>

                                    <div class="detalles-turno-badges">
                                        <span class="chip-horario"><?php echo date('H:i', strtotime($turno['fecha_hora'])); ?> hs</span>
                                        <span class="chip-estado estado-<?php echo htmlspecialchars($turno['estado']); ?>" role="status" aria-live="polite">
                                            <?php echo ucfirst(htmlspecialchars($turno['estado'])); ?>
                                        </span>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="text-align: center; color: #666; padding: 30px;">No tenés turnos programados para el día de hoy.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="resumen-dia">
                    <h3> Resumen del día </h3>

                    <div class="tarjeta-resumen">
                        <span class="titulo-resumen">Total Turnos</span>
                        <strong class="valor-resumen"><?php echo $totalTurnos; ?></strong>
                    </div>
                    <div class="tarjeta-resumen">
                        <span class="titulo-resumen">Confirmados</span>
                        <strong class="valor-resumen valor-confirmado"><?php echo $confirmados; ?></strong>
                    </div>
                    <div class="tarjeta-resumen">
                        <span class="titulo-resumen">Pendientes</span>
                        <strong class="valor-resumen valor-pendiente"><?php echo $pendientes; ?></strong>
                    </div>

                    <div class="bloque-agenda-lateral">
                        <a href="profesional-agenda.php" class="btn-siguiente-paso">
                            Consultar agenda
                        </a>
                    </div>
                </section>
            
            </div>
            <?php endif; ?>

        </main>
    </div>

    <footer>
        <small> &copy; 2026 Pausa. Todos los derechos reservados. </small>  
    </footer>

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