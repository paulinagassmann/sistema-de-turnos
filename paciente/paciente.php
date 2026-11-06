<?php
session_start();

// Validamos que el usuario haya iniciado sesión y sea específicamente 'paciente'
if (
    empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'paciente'
) {
    header("Location: ../index.html?error=sesion_requerida");
    exit();
}

if (empty($_SESSION['csrf_cancelar_turno'])) {
    $_SESSION['csrf_cancelar_turno'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/turnos.php';

try {
    actualizarTurnosPendientesVencidos($conexion);

    // 1. Buscamos los datos del usuario y su extensión de paciente (obra social, teléfono, correo, etc.)
    $stmtUsuario = $conexion->prepare(
        'SELECT u.nombre, u.apellido, u.correo, u.telefono, u.dni, p.obra_social 
        FROM usuarios u 
        JOIN pacientes p ON u.id = p.id 
        WHERE u.id = :id'
    );
    $stmtUsuario->execute([':id' => $_SESSION['usuario_id']]);
    $datosPaciente = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

    if (!$datosPaciente) {
        session_destroy();
        header("Location: ../index.html?error=usuario_no_encontrado");
        exit();
    }

    // 2. Solo mostramos como próxima sesión un turno que siga pendiente
    $stmtTurno = $conexion->prepare(
        "SELECT t.id, t.fecha_hora, t.estado,
                u_prof.nombre AS prof_nombre, u_prof.apellido AS prof_apellido, 
                pr.especialidad 
         FROM turnos t
         JOIN profesionales pr ON t.profesional_id = pr.id
         JOIN usuarios u_prof ON pr.id = u_prof.id
         JOIN pacientes pa ON t.paciente_id = pa.id
         WHERE pa.id = :paciente_id 
           AND t.fecha_hora >= NOW() 
           AND t.estado = 'pendiente'
         ORDER BY t.fecha_hora ASC 
         LIMIT 1"
    );
    $stmtTurno->execute([':paciente_id' => $_SESSION['usuario_id']]);
    $proximoTurno = $stmtTurno->fetch(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log("Error al cargar el dashboard del paciente: " . $e->getMessage());
    http_response_code(500);
    ?>

    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Pausa - Error al cargar</title>
        <link rel="stylesheet" href="../css/styles.css">
    </head>
    <body class="body-dashboard-paciente">
        <main class="main-paciente">
            <section class="seccion-turnos">
                <h1>No pudimos cargar tu información</h1>
                <p>Ocurrió un problema al consultar tus datos. Intentá nuevamente más tarde.</p>
                <a class="btn-agendar" href="../index.html">Volver al inicio</a>
            </section>
        </main>
    </body>
    </html>
    <?php
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">

 <head>
    <meta charset="UTF-8">
    <meta name="description" content="Página principal de pacientes, organiza 
    tus turnos en Pausa de manera sencilla.">
    <meta name="viewport" content="width=device-width, 
    initial-scale=1.0">
    <title> Pausa - Paciente </title>
    <link rel="stylesheet" href="../css/styles.css"> <!-- Enlaza el archivo styles.css para agregar estilos -->
     <script src="../js/main.js" defer></script>
    <script src="../js/paciente.js?v=booking-step-two-20261104-2" defer></script>
</head>

    <body class="body-dashboard-paciente">
    
        <header class = "header-paciente">
            <div class="bloque-marca">
                <span class="logo-texto">Pausa</span>
                <span class="subtitulo-logo">Clínica de Salud Mental</span>
            </div>
            <a href="../index.html" class="btn-logout">Cerrar sesión</a>
        </header>

        <main class = "main-paciente">
            <section class = "seccion-turnos">

                <header class="encabezado-seccion">
                    <h1> Bienvenido, <?php echo htmlspecialchars($datosPaciente['nombre']); ?> </h1>
                    <p> Aquí puedes gestionar tus turnos de terapia y acceder a tu información personal. </p>
                </header>
                <?php if (isset($_GET['turno_agendado']) && $_GET['turno_agendado'] === '1'): ?>
                    <p class="aviso-turno-agendado" role="status">Tu turno se reservó correctamente.</p>
                <?php endif; ?>

                <?php if (isset($_GET['turno_cancelado']) && $_GET['turno_cancelado'] === '1'): ?>
                    <p class="aviso-turno-agendado" role="status">Tu turno fue cancelado correctamente.</p>
                <?php endif; ?>
                <?php
                $mensajesErrorCancelacion = [
                    'sin_turno' => 'No tenés un turno próximo disponible para cancelar.',
                    'solicitud_invalida' => 'La solicitud de cancelación no es válida. Intentá nuevamente.',
                    'servidor' => 'No pudimos cancelar el turno. Intentá nuevamente más tarde.'
                ];
                if (
                    isset($_GET['error'])
                    && is_string($_GET['error'])
                    && isset($mensajesErrorCancelacion[$_GET['error']])
                ):
                ?>
                    <p class="aviso-turno-error" role="alert"><?php echo htmlspecialchars($mensajesErrorCancelacion[$_GET['error']], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>

                <!-- Tarjeta de próximo turno -->
                <article class="tarjeta-turnos" data-source="database">
                    <h2> Próxima sesión </h2>
                    <?php if ($proximoTurno): ?>
                    <?php 
                        // Formateamos la fecha y hora de la base de datos de manera amigable
                        $timestamp = strtotime($proximoTurno['fecha_hora']);
                        $fechaFormateada = date('d M', $timestamp);
                        $horaFormateada = date('H:i', $timestamp);
                    ?>
                    <p> <strong>Profesional: Lic. <?php echo htmlspecialchars($proximoTurno['prof_nombre'] . ' ' . $proximoTurno['prof_apellido']); ?></strong> </p>
                    <p> Especialidad: <?php echo htmlspecialchars($proximoTurno['especialidad']); ?> </p>
 
                    <div class="contenedor-chips">
                        <span class="chip-info">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-calendar"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12" /><path d="M16 3v4" /><path d="M8 3v4" /><path d="M4 11h16" /><path d="M11 15h1" /><path d="M12 15v3" /></svg>
                            <?php echo htmlspecialchars($fechaFormateada); ?>
                        </span>

                        <span class="chip-info">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-clock"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" /><path d="M12 7v5l3 3" /></svg>
                            <?php echo htmlspecialchars($horaFormateada); ?> hs
                        </span>
                    </div>

                    <!-- Botón para abrir el modal de cancelación -->
                    <button type="button" class="btn-cancelar" data-modal-open="modalCancelar">Cancelar turno</button>
                    <?php else: ?>
                        <p style="margin: 20px 0; color: #666;">No tenés ninguna sesión próxima agendada.</p>
                    <?php endif; ?>
                </article>

                <a href="paciente1.php" class="btn-agendar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-plus"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                    <span>Agendar nuevo turno</span>
                </a>

            </section>

            <section class = "seccion-perfil">

                <article class="tarjeta-perfil">
                    <h2> Mi perfil </h2>

                    <p class="perfil-nombre"> <?php echo htmlspecialchars($datosPaciente['nombre'] . ' ' . $datosPaciente['apellido']); ?> </p>
                    <p class="perfil-dni">DNI: <?php echo htmlspecialchars($datosPaciente['dni']); ?></p>

                    <div class="campo-perfil">
                    <label for="obra-social">Obra social:</label>
                    <input type="text" id="obra-social" value="<?php echo htmlspecialchars($datosPaciente['obra_social'] ?? 'No especificada'); ?>" readonly>
                    </div>

                    <div class="campo-perfil">
                    <label for="telefono">Teléfono:</label>
                    <input type="tel" id="telefono" value="<?php echo htmlspecialchars($datosPaciente['telefono']); ?>" readonly>
                    </div>

                    <div class="campo-perfil">
                    <label for="email">Correo electrónico:</label>
                    <input type="email" id="email" value="<?php echo htmlspecialchars($datosPaciente['correo']); ?>" readonly>
                    </div>

                    <div class="campo-perfil">
                    <label for="password">Contraseña:</label>
                    <input type="password" id="password" value="********" readonly>
                    </div>

                    <a href="perfil-paciente.php" class="btn-editar">
                        <!-- SVG del lápiz -->
                        <span>Editar información</span>
                    </a>
                </article>

            </section>
        </main>

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

<?php if ($proximoTurno): ?>
<!-- MODAL 1: PREGUNTA DE CONFIRMACIÓN -->
<div id="modalCancelar" class="modal-fondo">
    <div class="modal-caja">
        <div class="modal-icono-alerta">⚠️</div>
        <h3>¿Cancelar turno?</h3>
        <p>¿Estás seguro de que querés cancelar tu sesión? Esta acción no se puede deshacer.</p>
        
        <form action="../procesos/paciente/procesar-cancelar-turno-paciente.php" method="POST" class="modal-acciones">
            <input type="hidden" name="turno_id" value="<?php echo htmlspecialchars((string) $proximoTurno['id'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_cancelar_turno'], ENT_QUOTES, 'UTF-8'); ?>">
            <button type="button" class="btn-secundario-modal" data-modal-close="modalCancelar">No, mantener turno</button>
            <button type="submit" class="btn-peligro-modal">Sí, cancelar</button>
        </form>
    </div>
</div>
<?php endif; ?>


</body>
</html>