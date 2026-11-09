<?php
session_start();

// 1. valido la sesión de secretario
if (
    empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'secretario'
) {
    header("Location: ../index.html?error=sesion_requerida");
    exit();
}

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/turnos.php';
actualizarTurnosPendientesVencidos($conexion);

$profesionalIdRecibido = $_GET['profesional_id'] ?? null;
$profesionalIdSeleccionado = is_scalar($profesionalIdRecibido)
    ? filter_var((string) $profesionalIdRecibido, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : null;
$profesionalIdSeleccionado = $profesionalIdSeleccionado === false ? null : $profesionalIdSeleccionado;

if (empty($_SESSION['csrf_excepciones'])) {
    $_SESSION['csrf_excepciones'] = bin2hex(random_bytes(32));
}

$profesionales = [];

try {
    // obtengo los profesionales de la base de datos
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
        $profesionalIdSeleccionado = $profesionales[0]['id'];
    }
} catch (Throwable $e) {
    error_log("Error al cargar profesionales en excepciones: " . $e->getMessage());
}

$mensajesErrorExcepciones = [
    'solicitud_invalida' => 'La solicitud venció o no es válida. Recargá la página e intentá nuevamente.',
    'datos_invalidos' => 'Revisá la fecha y el horario e intentá nuevamente.',
    'ocupado' => 'Ese horario tiene un turno reservado y no se puede bloquear.',
    'superpuesto' => 'La excepción se superpone con otra ya registrada.',
    'servidor' => 'No se pudo guardar el cambio. Intentá nuevamente.'
];
$codigoErrorExcepcion = is_string($_GET['error'] ?? null) ? $_GET['error'] : '';
$mensajeErrorExcepcion = $mensajesErrorExcepciones[$codigoErrorExcepcion] ?? null;
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="description" content="Gestiona excepciones en las agendas de los profesionales.">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excepciones | Pausa - Secretario</title>
    <link rel="stylesheet" href="../css/styles.css?v=<?php echo filemtime(__DIR__ . '/../css/styles.css'); ?>">
    <script src="../js/main.js" defer></script>
    <script src="../js/secretario.js?v=<?php echo filemtime(__DIR__ . '/../js/secretario.js'); ?>" defer></script>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_excepciones'], ENT_QUOTES, 'UTF-8'); ?>">
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
                <a href="../index.html" class="btn-logout">
                    <span>Cerrar sesión</span>
                </a>
            </div>

        </aside>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-secretario">

            <header class="header-dashboard">
                <div class="texto-bienvenida-secretario">
                    <h1>Gestión de Excepciones</h1>
                    <p>Registrá ausencias y cambios puntuales sin modificar los horarios base.</p>
                </div>
            </header>

            <?php if (isset($_GET['exito']) && $_GET['exito'] === '1'): ?>
                <p style="background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin: 0 0 20px 0;">El cambio de agenda se guardó correctamente.</p>
            <?php endif; ?>
            <?php if ($mensajeErrorExcepcion !== null): ?>
                <p style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin: 0 0 20px 0;"><?php echo htmlspecialchars($mensajeErrorExcepcion, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>

            <div class="contenedor-excepciones-general">

                <section class="tarjeta-blanca tarjeta-filtro-excepcion">
                    <div class="grupo-input">
                        <label for="profesional" class="label-filtro">Profesional</label>
                        <select name="profesional" id="profesional" class="input-form" onchange="window.location.href='secretario-excepciones.php?profesional_id=' + encodeURIComponent(this.value);">
                            <?php foreach ($profesionales as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo $profesionalIdSeleccionado == $p['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['nombre'] . ' ' . $p['apellido'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </section>

                <div class="grilla-excepciones-dos-columnas">
                    
                    <section class="tarjeta-blanca panel-calendario-excepcion">
                        <div class="cabecera-mes">
                            <div>
                                <p class="sobrelinea-excepcion">CALENDARIO DE EXCEPCIONES</p>
                                <h2>Cargando calendario...</h2>
                            </div>
                            <div class="nav-mes" aria-label="Navegar por meses">
                                <button type="button" class="btn-nav-mes" aria-label="Mes anterior">&lt;</button>
                                <button type="button" class="btn-nav-mes" aria-label="Mes siguiente">&gt;</button>
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

                        <div class="grilla-dias" aria-label="Calendario de excepciones"></div>

                        <div class="footer-calendario-excepcion">
                            <button type="button" class="btn-bloquear-dia-completo">Bloquear día completo</button>
                            <p class="estado-bloqueo-dia" id="estado-bloqueo-dia" role="status" aria-live="polite"></p>
                        </div>
                    </section>

                    <section class="tarjeta-blanca panel-horarios-excepcion">
                        <div class="cabecera-horarios-dia">
                            <div>
                                <h2>Seleccioná una fecha</h2>
                            </div>
                            <button type="button" class="btn-dar-turno-mini">+ Agregar turno extra</button>
                        </div>
                        <p class="resumen-dia-excepcion">Revisá los turnos del profesional y aplicá cambios puntuales.</p>

                        <div class="lista-franjas-excepcion" aria-live="polite">
                            <p class="mensaje-sin-franjas-excepcion">Seleccioná un día para consultar sus turnos.</p>
                        </div>
                    </section>

                </div>

            </div>

        </main>
    </div>

    <footer>
        <small>&copy; 2026 Pausa. Todos los derechos reservados.</small>
    </footer>

    <dialog id="modalCancelarTurnoExcepcion" class="modal-dialog modal-cancelacion-turno" aria-labelledby="titulo-modal-excepcion" aria-describedby="texto-modal-excepcion">
        <form method="dialog" class="modal-card">
            <div class="modal-icono-alerta" aria-hidden="true">!</div>
            <h2 class="modal-titulo" id="titulo-modal-excepcion">Avisar la cancelación del turno</h2>
            <p class="modal-subtitulo" id="texto-modal-excepcion">
                Se abrirá WhatsApp con un mensaje listo para <strong id="nombre-paciente-excepcion"></strong>.
                Revisalo y envialo para avisarle que su turno fue cancelado y que puede solicitar otro.
            </p>
            <div class="modal-acciones">
                <button type="submit" class="btn-secundario-modal">Volver</button>
                <a id="enlace-whatsapp-excepcion" class="btn-whatsapp-modal" href="#" target="_blank" rel="noopener noreferrer">Abrir WhatsApp</a>
            </div>
        </form>
    </dialog>

    <dialog id="modalBloquearDiaExcepcion" class="modal-dialog modal-cancelacion-turno modal-bloqueo-dia" aria-labelledby="titulo-modal-bloqueo" aria-describedby="texto-modal-bloqueo">
        <div class="modal-card">
            <div class="modal-icono-alerta" aria-hidden="true">!</div>
            <h2 class="modal-titulo" id="titulo-modal-bloqueo">Atención: este día tiene turnos ocupados</h2>
            <p class="modal-subtitulo" id="texto-modal-bloqueo">
                Si bloqueás el día completo, los siguientes pacientes se quedarán sin turno y deberán ser avisados:
            </p>
            <ul class="lista-pacientes-afectados" id="lista-pacientes-afectados"></ul>
            <div class="modal-acciones">
                <button type="button" class="btn-secundario-modal" id="cancelar-bloqueo-dia">Volver</button>
                <button type="button" class="btn-peligro-modal" id="confirmar-bloqueo-dia">Cancelar día</button>
            </div>
        </div>
    </dialog>

    <dialog id="modalConfirmarEstadoFranja" class="modal-dialog modal-cancelacion-turno modal-confirmacion-franja" aria-labelledby="titulo-confirmacion-franja" aria-describedby="texto-confirmacion-franja">
        <div class="modal-card">
            <div class="modal-icono-alerta" aria-hidden="true">!</div>
            <h2 class="modal-titulo" id="titulo-confirmacion-franja"></h2>
            <p class="modal-subtitulo" id="texto-confirmacion-franja"></p>
            <div class="modal-acciones">
                <button type="button" class="btn-secundario-modal" id="cancelar-confirmacion-franja">Volver</button>
                <button type="button" class="btn-peligro-modal" id="confirmar-estado-franja"></button>
            </div>
        </div>
    </dialog>

    <dialog id="modalTurnoExtra" class="modal-dialog modal-cancelacion-turno modal-turno-extra" aria-labelledby="titulo-turno-extra">
        <form id="formulario-turno-extra" class="modal-card modal-form-turno-extra">
            <div class="modal-icono-alerta" aria-hidden="true">+</div>
            <h2 class="modal-titulo" id="titulo-turno-extra">Agregar turno extra</h2>
            <p class="modal-subtitulo">Definí la hora de inicio de un turno de 60 minutos para <strong id="fecha-turno-extra"></strong>.</p>

            <div class="campos-horario-extra">
                <div class="campo-turno-extra">
                    <label for="hora-inicio-extra">Hora de inicio</label>
                    <input id="hora-inicio-extra" name="horaInicio" type="time" required>
                </div>
            </div>

            <div class="campo-turno-extra">
                <label for="estado-turno-extra">¿Cómo querés crear el turno?</label>
                <select id="estado-turno-extra" name="estado" required>
                    <option value="disponible" selected>Disponible para pacientes</option>
                    <option value="asignado">Asignarlo a un paciente</option>
                </select>
            </div>

            <div id="datos-paciente-turno-extra" class="datos-paciente-turno-extra" hidden>
                <div class="campo-turno-extra">
                    <label for="nombre-paciente-extra">Nombre y apellido</label>
                    <input id="nombre-paciente-extra" name="nombrePaciente" type="text" autocomplete="name" maxlength="100">
                </div>
                <div class="campo-turno-extra">
                    <label for="dni-paciente-extra">DNI </label>
                    <input id="dni-paciente-extra" name="dniPaciente" type="text" inputmode="numeric" maxlength="20">
                </div>
            </div>

            <p class="error-turno-extra" id="error-turno-extra" role="alert" hidden></p>
            <div class="modal-acciones">
                <button type="button" class="btn-secundario-modal" id="cerrar-modal-turno-extra">Volver</button>
                <button type="submit" class="btn-peligro-modal">Agregar turno</button>
            </div>
        </form>
    </dialog>

</body>
</html>