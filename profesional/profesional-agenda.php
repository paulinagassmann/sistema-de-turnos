<?php
session_start();

// 1. Validamos sesión de profesional
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
$fechaHoy = new DateTimeImmutable('today', $zonaHoraria);
$fechaRecibida = $_GET['fecha'] ?? $fechaHoy->format('Y-m-d');
$fechaSeleccionada = is_string($fechaRecibida)
    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaRecibida) === 1
    && DateTimeImmutable::createFromFormat('!Y-m-d', $fechaRecibida, $zonaHoraria)?->format('Y-m-d') === $fechaRecibida
        ? $fechaRecibida
        : $fechaHoy->format('Y-m-d');
$errorAlCargarAgenda = false;
$datosProfesional = null;

try {
    actualizarTurnosPendientesVencidos($conexion);

    // 2. Buscamos los datos de la cuenta profesional para mostrar su nombre
    $stmtProf = $conexion->prepare(
        "SELECT u.nombre, u.apellido
         FROM usuarios u
         JOIN profesionales p ON p.id = u.id
         WHERE u.id = :id AND u.rol = 'profesional'"
    );
    $stmtProf->execute([':id' => $profesional_id]);
    $datosProfesional = $stmtProf->fetch(PDO::FETCH_ASSOC);
    if (!$datosProfesional) {
        session_destroy();
        header("Location: ../index.html?error=usuario_no_encontrado");
        exit();
    }

    // 4. Buscamos todos los turnos de este profesional para marcar los días con actividad en el calendario
    $stmtTodosTurnos = $conexion->prepare(
        "SELECT DATE(fecha_hora) as fecha, COUNT(*) as total 
         FROM turnos 
         WHERE profesional_id = :profesional_id 
           AND estado IN ('pendiente', 'confirmado', 'ausente')
         GROUP BY DATE(fecha_hora)"
    );
    $stmtTodosTurnos->execute([':profesional_id' => $profesional_id]);
    $turnosPorDiaRaw = $stmtTodosTurnos->fetchAll(PDO::FETCH_ASSOC);
    
    $mapaTurnos = [];
    foreach ($turnosPorDiaRaw as $row) {
        $mapaTurnos[$row['fecha']] = $row['total'];
    }

    // 5. Buscamos los turnos específicos del día seleccionado para mostrar en la tarjeta derecha
    $stmtTurnosDia = $conexion->prepare(
        "SELECT t.fecha_hora, t.estado, u_pac.nombre AS pac_nombre, u_pac.apellido AS pac_apellido 
         FROM turnos t
         JOIN usuarios u_pac ON t.paciente_id = u_pac.id
         WHERE t.profesional_id = :profesional_id 
           AND DATE(t.fecha_hora) = :fecha
         ORDER BY t.fecha_hora ASC"
    );
    $stmtTurnosDia->execute([
        ':profesional_id' => $profesional_id,
        ':fecha' => $fechaSeleccionada
    ]);
    $turnosDelDia = $stmtTurnosDia->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log("Error en agenda del profesional: " . $e->getMessage());
    http_response_code(500);
    $errorAlCargarAgenda = true;
    $datosProfesional = $datosProfesional ?? ['nombre' => '', 'apellido' => ''];
    $mapaTurnos = [];
    $turnosDelDia = [];
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="description" content="Vista de la agenda completa de los 
    profesionales de Pausa.">
    <meta name="viewport" content="width=device-width, 
    initial-scale=1.0">
    <title> Pausa - Profesional </title>
    <link rel="stylesheet" href="../css/styles.css"> <!-- Enlaza el archivo styles.css para agregar estilos -->
    <script src="../js/main.js" defer></script>
    <script src="../js/profesional.js" defer></script>
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
                <a href="profesional-agenda.php" class="item-menu activo">
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
                <p>Lic. <?php echo htmlspecialchars($datosProfesional['nombre'] . ' ' . $datosProfesional['apellido']); ?></p>
                <h1> Mi agenda </h1>
            </div>

            <div class="espaciador-header"></div>

            <a href="profesional.php" class="btn-volver-dashboard">
                <span>Volver a Hoy</span>
            </a>
        </header>

        <?php if ($errorAlCargarAgenda): ?>
            <section class="tarjeta-blanca" role="alert">
                <h2>No pudimos cargar tu agenda</h2>
                <p>Ocurrió un problema al consultar los turnos. Intentá nuevamente más tarde.</p>
            </section>
        <?php else: ?>
        <div class="grilla-agenda-profesional">

        <!-- TARJETA 1 (IZQUIERDA)-->
        <section class="tarjeta-blanca">
            <div class="cabecera-mes">
                <?php
                    $fechaBaseCalendario = DateTimeImmutable::createFromFormat(
                        '!Y-m-d',
                        $fechaSeleccionada,
                        $zonaHoraria
                    );
                    $primerDiaMes = $fechaBaseCalendario->modify('first day of this month');
                    $nombresMeses = [
                        'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
                        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
                    ];
                    $nombreMes = $nombresMeses[(int) $primerDiaMes->format('n') - 1];
                ?>
                <h3><?php echo htmlspecialchars(ucfirst($nombreMes) . ' ' . $primerDiaMes->format('Y'), ENT_QUOTES, 'UTF-8'); ?></h3>
                
                <!-- Botones para navegar entre los meses -->
                <div class="nav-mes">
                    <?php 
                        $mesAnterior = $primerDiaMes->modify('-1 month')->format('Y-m-d');
                        $mesSiguiente = $primerDiaMes->modify('+1 month')->format('Y-m-d');
                    ?>
                    <a href="profesional-agenda.php?fecha=<?php echo $mesAnterior; ?>" class="btn-nav-mes" aria-label="Mes anterior" style="text-decoration: none; text-align: center; line-height: 28px;">&lt;</a>
                    <a href="profesional-agenda.php?fecha=<?php echo $mesSiguiente; ?>" class="btn-nav-mes" aria-label="Mes siguiente" style="text-decoration: none; text-align: center; line-height: 28px;">&gt;</a>
                </div>
            </div>

            <div class="dias-semana">
                <span>Dom</span><span>Lun</span><span>Mar</span><span>Mié</span><span>Jue</span><span>Vie</span><span>Sáb</span>
            </div>

            <div class="grilla-dias-agenda" data-source="database">
                <?php 
                    $offsetInicio = (int) $primerDiaMes->format('w');
                    $totalDiasMes = (int) $primerDiaMes->format('t');

                    // Espacios vacíos previos al inicio del mes
                    for ($i = 0; $i < $offsetInicio; $i++):
                ?>
                    <span class="dia-vacio"></span>
                <?php endfor; ?>

                <?php for ($dia = 1; $dia <= $totalDiasMes; $dia++): ?>
                    <?php 
                        $fechaIterada = $primerDiaMes
                            ->setDate(
                                (int) $primerDiaMes->format('Y'),
                                (int) $primerDiaMes->format('n'),
                                $dia
                            )
                            ->format('Y-m-d');
                        $tieneTurnos = isset($mapaTurnos[$fechaIterada]);
                        $cantTurnos = $mapaTurnos[$fechaIterada] ?? 0;
                        $esSeleccionado = ($fechaIterada === $fechaSeleccionada);
                    ?>
                    <a href="profesional-agenda.php?fecha=<?php echo htmlspecialchars($fechaIterada, ENT_QUOTES, 'UTF-8'); ?>" 
                       class="btn-dia <?php echo $esSeleccionado ? 'dia-seleccionado' : ''; ?>" 
                       style="text-decoration: none; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                        <span class="num-dia"><?php echo $dia; ?></span>
                        <?php if ($tieneTurnos): ?>
                            <span class="badge-turnos-dia"><?php echo $cantTurnos; ?> turno<?php echo $cantTurnos > 1 ? 's' : ''; ?></span>
                        <?php endif; ?>
                    </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </section>

        <!-- TARJETA 2 (DERECHA): DETALLE DE TURNOS DEL DÍA SELECCIONADO -->
        <section class="tarjeta-blanca">
            <div class="encabezado-detalle-dia">
                <h2><?php echo date('d \d\e F, Y', strtotime($fechaSeleccionada)); ?></h2>
                <span class="subtitulo-detalle"><?php echo count($turnosDelDia); ?> turno<?php echo count($turnosDelDia) !== 1 ? 's' : ''; ?> programado<?php echo count($turnosDelDia) !== 1 ? 's' : ''; ?></span>
            </div>

            <div class="lista-turnos-dia-agenda">
                <?php if (!empty($turnosDelDia)): ?>
                    <?php foreach ($turnosDelDia as $t): ?>
                        <article class="item-turno-agenda">
                            <div class="hora-turno-agenda">
                                <strong><?php echo date('H:i', strtotime($t['fecha_hora'])); ?> hs</strong>
                            </div>

                            <div class="datos-paciente-agenda">
                                <span class="nombre-paciente"><?php echo htmlspecialchars($t['pac_nombre'] . ' ' . $t['pac_apellido']); ?></span>
                                <span class="chip-estado estado-<?php echo htmlspecialchars($t['estado']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($t['estado'])); ?>
                                </span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 30px;">No hay turnos programados para este día.</p>
                <?php endif; ?>
            </div>
        </section>

        </div>

    </main>

    </div>

<footer>
    <small> &copy; 2026 Pausa. Todos los derechos reservados. </small>
</footer>

</body>
</html>