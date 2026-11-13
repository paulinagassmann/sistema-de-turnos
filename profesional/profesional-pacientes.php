<?php
session_start();

// 1. valido la sesión de profesional
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
$profesional_id = $_SESSION['usuario_id'];
$zonaHoraria = new DateTimeZone('America/Argentina/Buenos_Aires');
$ahora = new DateTimeImmutable('now', $zonaHoraria);

$listaPacientes = [];
$pacienteSeleccionado = null;
$proximosTurnosPaciente = [];
$historialTurnos = [];
$errorAlCargarPacientes = false;

try {
    actualizarTurnosPendientesVencidos($conexion);

    // 2. busco la lista de pacientes que se atienden con este profesional
    $stmtPacientes = $conexion->prepare(
        "SELECT DISTINCT pac.id, u.nombre, u.apellido, u.dni, u.correo, u.telefono, pac.obra_social
         FROM pacientes pac
         JOIN usuarios u ON pac.id = u.id
         JOIN turnos t ON t.paciente_id = pac.id
         WHERE t.profesional_id = :profesional_id
         ORDER BY u.apellido ASC, u.nombre ASC"
    );
    $stmtPacientes->execute([':profesional_id' => $profesional_id]);
    $listaPacientes = $stmtPacientes->fetchAll(PDO::FETCH_ASSOC);

    // 3. determino qué paciente mostrar en detalle (el de la URL o el primero de la lista por defecto)
    $idRecibido = $_GET['id'] ?? null;
    $pacienteIdSolicitado = is_scalar($idRecibido)
        ? filter_var((string) $idRecibido, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ])
        : false;
    $paciente_seleccionado_id = $pacienteIdSolicitado ?: ($listaPacientes[0]['id'] ?? null);

    if ($paciente_seleccionado_id) {
        // busco los datos específicos del paciente seleccionado
        foreach ($listaPacientes as $pac) {
            if ((int) $pac['id'] === (int) $paciente_seleccionado_id) {
                $pacienteSeleccionado = $pac;
                break;
            }
        }

        // Si no está en la lista del profesional (por seguridad), buscamos por bd si existe
        if (!$pacienteSeleccionado && !empty($listaPacientes)) {
            $pacienteSeleccionado = $listaPacientes[0];
            $paciente_seleccionado_id = $pacienteSeleccionado['id'];
        }

        if ($pacienteSeleccionado) {
            // 4. busco todos los turnos pendientes que no empezaron todavia
            $stmtProx = $conexion->prepare(
                "SELECT fecha_hora, estado
                 FROM turnos 
                 WHERE paciente_id = :paciente_id 
                   AND profesional_id = :profesional_id 
                   AND fecha_hora > :ahora
                   AND estado = 'pendiente'
                 ORDER BY fecha_hora ASC"
            );
            $stmtProx->execute([
                ':paciente_id' => $paciente_seleccionado_id,
                ':profesional_id' => $profesional_id,
                ':ahora' => $ahora->format('Y-m-d H:i:s')
            ]);
            $proximosTurnosPaciente = $stmtProx->fetchAll(PDO::FETCH_ASSOC);

            // 5. busco el historial de turnos pasados o cancelados/completados
            $stmtHistorial = $conexion->prepare(
                "SELECT fecha_hora, estado 
                 FROM turnos 
                 WHERE paciente_id = :paciente_id 
                   AND profesional_id = :profesional_id 
                 ORDER BY fecha_hora DESC"
            );
            $stmtHistorial->execute([
                ':paciente_id' => $paciente_seleccionado_id,
                ':profesional_id' => $profesional_id
            ]);
            $historialTurnos = $stmtHistorial->fetchAll(PDO::FETCH_ASSOC);
        }
    }

} catch (Throwable $e) {
    error_log("Error en lista de pacientes del profesional: " . $e->getMessage());
    http_response_code(500);
    $errorAlCargarPacientes = true;
    $listaPacientes = [];
    $pacienteSeleccionado = null;
    $proximosTurnosPaciente = [];
    $historialTurnos = [];
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="description" content="Vista de los pacientes atendidos por el profesional.">
    <meta name="viewport" content="width=device-width, 
    initial-scale=1.0">
    <title> Pausa - Profesional </title>
    <link rel="icon" type="image/png" href="../img/favicon.png">
    <link rel="stylesheet" href="../css/styles.css?v=<?php echo filemtime(__DIR__ . '/../css/styles.css'); ?>"> <!-- Enlaza el archivo styles.css para agregar estilos -->
    <script src="../js/main.js?v=<?php echo filemtime(__DIR__ . '/../js/main.js'); ?>" defer></script>
    <script src="../js/profesional.js?v=database-patients-20261105-1" defer></script>
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
                <a href="profesional-pacientes.php" class="item-menu activo">
                    <span>Mis Pacientes</span>
                </a>
                <a href="profesional-agenda.php" class="item-menu">
                    <span>Agenda </span>
                </a>
                <a href="profesional-configuracion.php" class="item-menu">
                    <span>Configuración</span>
                </a>
            </nav>

        </aside>

        <main class="main-profesional-menu">

            <header class="header-dashboard">
                <div class="texto-bienvenida-prof">
                    <h1> Lista de pacientes </h1>
                </div>

                <div class="espaciador-header"></div>

                <a href="profesional.php" class="btn-volver-dashboard">
                    <span>Volver a Hoy</span>
                </a>
            </header>

            <!-- CONTENEDOR DE LAS 2 TARJETAS -->
            <div class="contenedor-tarjetas-pacientes">

                <!-- TARJETA IZQUIERDA (LISTA DE LOS PACIENTES) -->
                <section class="tarjeta-blanca">

                    <div class="bloque-buscador-pacientes">
                        <input type="search" placeholder="Buscar por nombre o DNI..." class="input-busqueda-paciente">
                    </div>

                    <!-- Lista vertical de pacientes dinámica -->
                    <div class="lista-pacientes-resumen" data-source="database">
                        <?php if ($errorAlCargarPacientes): ?>
                            <p role="alert" style="text-align: center; color: #666; padding: 20px;">No pudimos cargar tus pacientes. Intentá nuevamente más tarde.</p>
                        <?php elseif (!empty($listaPacientes)): ?>
                            <?php foreach ($listaPacientes as $pac): ?>
                                <?php 
                                    $esSeleccionado = ($pac['id'] == ($pacienteSeleccionado['id'] ?? null));
                                    $inicial = mb_strtoupper(mb_substr($pac['nombre'], 0, 1));
                                ?>
                                <a href="profesional-pacientes.php?id=<?php echo (int) $pac['id']; ?>" 
                                   class="tarjeta-paciente-item <?php echo $esSeleccionado ? 'paciente-seleccionado' : ''; ?>" 
                                   style="text-decoration: none; color: inherit; display: flex; align-items: center;">
                                    <div class="avatar-paciente-mini"><?php echo htmlspecialchars($inicial); ?></div>
                                    <div class="info-paciente-mini">
                                        <strong><?php echo htmlspecialchars($pac['apellido'] . ', ' . $pac['nombre']); ?></strong>
                                        <span>DNI: <?php echo htmlspecialchars($pac['dni']); ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php elseif ($errorAlCargarPacientes): ?>
                            <p role="alert" style="text-align: center; color: #666; margin-top: 40px;">No pudimos cargar los datos del paciente. Intentá nuevamente más tarde.</p>
                        <?php else: ?>
                            <p style="text-align: center; color: #666; padding: 20px;">No tenés pacientes con turnos registrados.</p>
                        <?php endif; ?>
                    </div>

                </section>

                <!-- TARJETA DERECHA: DETALLE DEL PACIENTE SELECCIONADO -->
                <section class="tarjeta-blanca">
                    <h2>Detalle del paciente</h2>

                    <?php if ($pacienteSeleccionado): ?>
                        <!-- Datos básicos de contacto -->
                        <div class="ficha-datos-contacto">
                            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($pacienteSeleccionado['nombre'] . ' ' . $pacienteSeleccionado['apellido']); ?></p>
                            <p><strong>Correo:</strong> <?php echo htmlspecialchars($pacienteSeleccionado['correo']); ?></p>
                            <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($pacienteSeleccionado['telefono']); ?></p>
                            <p><strong>Obra social:</strong> <?php echo htmlspecialchars($pacienteSeleccionado['obra_social'] ?? 'No especificada'); ?></p>
                        </div>

                        <h3>Próximos turnos</h3>
                        <div>
                            <?php if (!empty($proximosTurnosPaciente)): ?>
                                <?php foreach ($proximosTurnosPaciente as $proximoTurnoPaciente): ?>
                                    <?php
                                        $fechaProx = new DateTimeImmutable($proximoTurnoPaciente['fecha_hora'], $zonaHoraria);
                                        $fechaProxFormateada = $fechaProx->format('d M, Y - H:i');
                                    ?>
                                    <div class="item-turno-agenda" style="margin-bottom: 8px;">
                                        <strong><?php echo htmlspecialchars($fechaProxFormateada); ?> hs</strong>
                                        <span class="chip-estado estado-pendiente">
                                            <?php echo ucfirst(htmlspecialchars($proximoTurnoPaciente['estado'])); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span style="color: #666; font-size: 0.9rem;">No tiene próximos turnos pendientes.</span>
                            <?php endif; ?>
                        </div>

                        <h3>Historial de turnos</h3>
                        <div class="lista-historial-paciente">
                            <?php if (!empty($historialTurnos)): ?>
                                <?php foreach ($historialTurnos as $hist): ?>
                                    <?php 
                                        $tsHist = strtotime($hist['fecha_hora']);
                                        $fechaHistFormateada = date('d M, Y - H:i', $tsHist);
                                    ?>
                                    <div class="item-turno-agenda" style="margin-bottom: 8px;">
                                        <span><?php echo htmlspecialchars($fechaHistFormateada); ?> hs</span>
                                        <span class="chip-estado estado-<?php echo htmlspecialchars($hist['estado']); ?>">
                                            <?php echo ucfirst(htmlspecialchars($hist['estado'])); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="color: #666; font-size: 0.9rem;">No hay registros previos en el historial.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; color: #666; margin-top: 40px;">Seleccioná un paciente de la lista de la izquierda para ver sus detalles.</p>
                    <?php endif; ?>

                </section>
            
            </div>
        
        </main>
    </div>

    <footer>
        <small> &copy; 2026 Pausa. Todos los derechos reservados. </small>  
    </footer>

</body>
</html>