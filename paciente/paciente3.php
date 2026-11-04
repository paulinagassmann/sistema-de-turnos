<?php
session_start();

if (
    empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'paciente'
) {
    header("Location: ../index.html?error=sesion_requerida");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: paciente1.php");
    exit();
}

$profesionalIdPost = $_POST['profesional_id'] ?? null;
$fechaPost = $_POST['fecha_seleccionada'] ?? null;
$horaPost = $_POST['hora_turno'] ?? null;
$profesionalId = filter_var($profesionalIdPost, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
$fechaValida = is_string($fechaPost)
    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaPost) === 1
    && DateTimeImmutable::createFromFormat('!Y-m-d', $fechaPost)?->format('Y-m-d') === $fechaPost;
$horaValida = is_string($horaPost)
    && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $horaPost) === 1;

if (
    $profesionalId === false
    || $profesionalId === null
    || (int) $profesionalId !== (int) ($_SESSION['profesional_id'] ?? 0)
    || !$fechaValida
    || !$horaValida
) {
    header("Location: paciente1.php?error=seleccion_invalida");
    exit();
}

$profesional_id = (int) $profesionalId;
$fecha = $fechaPost;
$hora = $horaPost;
$_SESSION['fecha_turno'] = $fecha;
$_SESSION['hora_turno'] = $hora;
if (empty($_SESSION['csrf_reservar_turno'])) {
    $_SESSION['csrf_reservar_turno'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_reservar_turno'];

require_once __DIR__ . '/../config/conexion.php';

try {
    $stmt = $conexion->prepare(
        'SELECT u.nombre, u.apellido, p.especialidad
         FROM profesionales p
         JOIN usuarios u ON p.id = u.id
         WHERE p.id = :id AND u.rol = :rol'
    );
    $stmt->execute([':id' => $profesional_id, ':rol' => 'profesional']);
    $profesional = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profesional) {
        unset($_SESSION['profesional_id'], $_SESSION['fecha_turno'], $_SESSION['hora_turno']);
        header("Location: paciente1.php?error=profesional_invalido");
        exit();
    }
} catch (Throwable $e) {
    error_log("Error al cargar resumen de turno: " . $e->getMessage());
    http_response_code(500);
    $errorAlCargarProfesional = true;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="description" content="Tercer paso para agendar un turno en Pausa.">
    <meta name="viewport" content="width=device-width, 
    initial-scale=1.0">
    <title> Pausa - Agendar Turno </title>
    <link rel="stylesheet" href="../css/styles.css"> <!-- Enlaza el archivo styles.css para agregar estilos -->
</head>

<body>
    
    <header class="header-paciente">
        <div class="bloque-marca">
            <span class="logo-texto">Pausa</span>
            <span class="subtitulo-logo">Clínica de Salud Mental</span>
        </div>
    </header>
     
    <main class="main-paciente-turno">
        <header class="encabezado-pasos">
            <h1> Confirmar turno </h1>
            <p> Revisá los detalles del turno antes de confirmarlo.</p>
        </header>
        
        <!-- Barra de progreso de 3 pasos -->
        <div class="indicador-pasos">
            <div class="paso">
                <span class="circulo-paso">1</span>
                <span class="etiqueta-paso">Profesional</span>
            </div>
            <div class="linea-paso"></div>
            <div class="paso">
                <span class="circulo-paso">2</span>
                <span class="etiqueta-paso">Fecha y Hora</span>
            </div>
            <div class="linea-paso"></div>
            <div class="paso activo"> <!-- para pintar este paso de azul -->
                <span class="circulo-paso">3</span>
                <span class="etiqueta-paso">Confirmación</span>
            </div>
        </div>


        <section class="tarjeta-blanca">
            
            <!-- Formulario que envía la confirmación final al procesador -->
            <?php if (!empty($errorAlCargarProfesional)): ?>
                <p>No pudimos cargar los datos del profesional. Volvé a intentarlo más tarde.</p>
                <a href="paciente1.php" class="btn-cancelar-paso">Volver a profesionales</a>
            <?php else: ?>
            <form action="../procesos/procesar-reservar-turno-paciente.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="profesional_id" value="<?php echo $profesional_id; ?>">
                <input type="hidden" name="fecha_seleccionada" value="<?php echo htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="hora_turno" value="<?php echo htmlspecialchars($hora, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="grilla-confirmacion">
                    
                    <!-- COLUMNA IZQUIERDA: DETALLES DE LA RESERVA -->
                    <div class="panel-resumen">
                        <h2>Detalles del turno</h2>
                        
                        <div class="item-resumen">
                            <div class="texto-resumen">
                                <span class="subtitulo-campo">PROFESIONAL</span>
                                <strong>Lic. <?php echo htmlspecialchars($profesional['nombre'] . ' ' . $profesional['apellido'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <p style="font-size: 0.85rem; color: #666; margin: 2px 0 0 0;"><?php echo htmlspecialchars($profesional['especialidad'], ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        </div>

                        <div class="item-resumen">
                            <div class="texto-resumen">
                                <span class="subtitulo-campo">FECHA</span>
                                <strong><?php echo htmlspecialchars((new DateTimeImmutable($fecha))->format('d/m/Y'), ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                        </div>

                        <div class="item-resumen">
                            <div class="texto-resumen">
                                <span class="subtitulo-campo">HORA</span>
                                <strong><?php echo htmlspecialchars($hora); ?> hs</strong>
                            </div>
                        </div>
                    </div>

                    <!-- COLUMNA DERECHA: INFORMACIÓN IMPORTANTE -->
                    <div class="panel-aviso-importante">
                        <div class="cabecera-aviso">
                            <h3>Información Importante</h3>
                        </div>
                        <ul class="lista-avisos">
                            <li>Por favor, presentate <strong>5 minutos antes</strong> del horario acordado.</li>
                            <li>Las cancelaciones deben realizarse con al menos <strong>24 horas de anticipación.</strong></li>
                            <li>En caso de cancelación dentro de las 24 horas previas al turno, <strong>se deberá abonar una penalidad.</strong></li>
                        </ul>
                    </div>

                </div>

                <footer class="footer-pasos">
                    <a href="paciente2.php" class="btn-cancelar-paso">Volver</a>
                    <button type="submit" class="btn-siguiente-paso">Confirmar sesión &rarr;</button>
                </footer>
            </form>
            <?php endif; ?>

        </section>

    </main>

    <footer>
        <small> &copy; 2026 Pausa. Todos los derechos reservados. </small>  
    </footer>

</body>