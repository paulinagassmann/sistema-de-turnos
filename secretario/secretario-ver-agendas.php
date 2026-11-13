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

$profesionales = [];
$profesionalIdRecibido = $_GET['profesional_id'] ?? null;
$profesionalIdSeleccionado = is_scalar($profesionalIdRecibido)
    ? filter_var((string) $profesionalIdRecibido, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : null;
$profesionalIdSeleccionado = $profesionalIdSeleccionado === false ? null : $profesionalIdSeleccionado;
$errorAlCargarProfesionales = false;


try {
    // 2. Obtenemos los profesionales reales de la base de datos
    $stmtProf = $conexion->prepare(
        "SELECT p.id, u.nombre, u.apellido, p.especialidad 
         FROM profesionales p 
         JOIN usuarios u ON p.id = u.id AND u.rol = 'profesional' 
         ORDER BY u.apellido ASC"
    );
    $stmtProf->execute();
    $profesionales = $stmtProf->fetchAll(PDO::FETCH_ASSOC);

    $profesionalEncontrado = false;
    foreach ($profesionales as $profesional) {
        if ((int) $profesional['id'] === (int) $profesionalIdSeleccionado) {
            $profesionalEncontrado = true;
            break;
        }
    }

    if (!$profesionalEncontrado && !empty($profesionales)) {
        $profesionalIdSeleccionado = (int) $profesionales[0]['id'];
    }
} catch (Throwable $e) {
    error_log("Error al cargar profesionales en ver agendas: " . $e->getMessage());
    $errorAlCargarProfesionales = true;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="description" content="Permite consultar las agendas filtrando por profesional.">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Agendas | Pausa - Secretario</title>
    <link rel="icon" type="image/png" href="../img/favicon.png">
    <link rel="stylesheet" href="../css/styles.css?v=<?php echo filemtime(__DIR__ . '/../css/styles.css'); ?>">
    <script src="../js/main.js?v=<?php echo filemtime(__DIR__ . '/../js/main.js'); ?>" defer></script>
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

        <main class="main-secretario vista-agendas" data-csrf-token="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <header class="header-dashboard">
                <div class="texto-bienvenida-secretario">
                    <h1>Ver agendas</h1>
                    <p>Consultá la disponibilidad y los turnos de cada profesional.</p>
                </div>
            </header>

            <section class="tarjeta-blanca filtro-agendas" aria-label="Filtro de agendas">
                <label for="profesional-agenda">Profesional</label>
                <select id="profesional-agenda" data-source="database" <?php echo empty($profesionales) ? 'disabled' : ''; ?>>
                    <?php foreach ($profesionales as $p): ?>
                        <option value="<?php echo (int) $p['id']; ?>" <?php echo (int) $profesionalIdSeleccionado === (int) $p['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['nombre'] . ' ' . $p['apellido'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if (empty($profesionales)): ?>
                        <option value="" selected><?php echo $errorAlCargarProfesionales ? 'No se pudieron cargar los profesionales' : 'No hay profesionales registrados'; ?></option>
                    <?php endif; ?>
                </select>
            </section>

            <?php if ($errorAlCargarProfesionales): ?>
                <p role="alert" class="mensaje-agenda-error">No se pudieron cargar los profesionales. Recargá la página para intentarlo nuevamente.</p>
            <?php endif; ?>

            <div class="grilla-consulta-agendas">
                <section class="tarjeta-blanca panel-calendario-agendas" aria-labelledby="titulo-calendario-agendas">
                    <div class="cabecera-mes">
                        <h2 id="titulo-calendario-agendas" aria-live="polite"></h2>
                        <div class="nav-mes">
                            <button type="button" class="btn-nav-mes" id="mes-anterior" aria-label="Mes anterior">&lt;</button>
                            <button type="button" class="btn-nav-mes" id="mes-siguiente" aria-label="Mes siguiente">&gt;</button>
                        </div>
                    </div>

                    <div class="dias-semana" aria-hidden="true">
                        <span>Dom</span>
                        <span>Lun</span>
                        <span>Mar</span>
                        <span>Mié</span>
                        <span>Jue</span>
                        <span>Vie</span>
                        <span>Sáb</span>
                    </div>
                    <div class="grilla-dias" id="dias-agenda" role="group" aria-label="Días del mes"></div>
                </section>

                <section class="tarjeta-blanca panel-horarios-agendas" aria-labelledby="fecha-agenda-seleccionada">
                    <div class="cabecera-horarios-dia">
                        <div>
                            <p class="sobrelinea-agendas">AGENDA DEL DÍA</p>
                            <h2 id="fecha-agenda-seleccionada" aria-live="polite"></h2>
                        </div>
                    </div>
                    <div class="lista-franjas-excepcion" id="horarios-agenda" aria-live="polite" aria-busy="false"></div>
                </section>
            </div>
        </main>
    </div>

    <footer>
        <small>&copy; 2026 Pausa. Todos los derechos reservados.</small>
    </footer>

</body>
</html>