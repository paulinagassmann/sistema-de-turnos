<?php
session_start();

// valido que sea un paciente con sesión activa
if (
    empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'paciente'
) {
    header("Location: ../index.html?error=sesion_requerida");
    exit();
}

// conecto a la bd
require_once __DIR__ . '/../config/conexion.php';

$profesionales = [];
$errorAlCargarProfesionales = false;

try {
    // consulto los profesionales de la bd porq en este paso se elige profesional
    $stmt = $conexion->prepare(
        'SELECT p.id, u.nombre, u.apellido, p.especialidad 
         FROM profesionales p 
         JOIN usuarios u ON p.id = u.id
         WHERE u.rol = :rol
         ORDER BY u.apellido, u.nombre'
    );
    $stmt->execute([':rol' => 'profesional']);
    $profesionales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log("Error al cargar profesionales para turnos: " . $e->getMessage());
    http_response_code(500);
    $errorAlCargarProfesionales = true;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="description" content="Primer paso para agendar un turno en Pausa.">
    <meta name="viewport" content="width=device-width, 
    initial-scale=1.0">
    <title> Pausa - Agendar Turno </title>
    <link rel="stylesheet" href="../css/styles.css"> <!-- Enlaza el archivo styles.css para agregar estilos -->
    <script src="../js/main.js" defer></script>
    <script src="../js/paciente.js?v=booking-step-two-20261104-2" defer></script>
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
            <h1> Agendar Turno </h1>
            <p> Siga los pasos para programar su sesión de terapia.</p>
        </header>
        
        <!-- Barra de progreso de 3 pasos -->
        <div class="indicador-pasos">
            <div class="paso activo"> <!-- para pintar este paso de azul -->
                <span class="circulo-paso">1</span>
                <span class="etiqueta-paso">Profesional</span>
            </div>
            <div class="linea-paso"></div>
            <div class="paso">
                <span class="circulo-paso">2</span>
                <span class="etiqueta-paso">Fecha y Hora</span>
            </div>
            <div class="linea-paso"></div>
            <div class="paso">
                <span class="circulo-paso">3</span>
                <span class="etiqueta-paso">Confirmación</span>
            </div>
        </div>

        <section class="tarjeta-blanca">

            <header>
                <h2> Seleccione un profesional </h2>
            </header>

            <!-- cajita de profesionales -->
            <div class="grilla-profesionales">
                <?php if ($errorAlCargarProfesionales): ?>
                    <p style="grid-column: 1 / -1; text-align: center; color: #666;">
                        No pudimos cargar los profesionales. Recargá la página para intentarlo nuevamente.
                    </p>
                <?php elseif (!empty($profesionales)): ?>
                    <?php foreach ($profesionales as $prof): ?>
                        <button
                            type="button"
                            class="tarjeta-profesional"
                            data-professional-id="<?php echo htmlspecialchars((string) $prof['id'], ENT_QUOTES, 'UTF-8'); ?>"
                            aria-pressed="false"
                        >
                            <span class="avatar-profesional avatar-profesional-placeholder" aria-hidden="true">P</span>
                            <div class="info-profesional">
                                <h3><?php echo htmlspecialchars($prof['nombre'] . ' ' . $prof['apellido'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                <p>Especialidad: <?php echo htmlspecialchars($prof['especialidad'], ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        </button>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="grid-column: 1 / -1; text-align: center; color: #666;">No hay profesionales disponibles en este momento.</p>
                <?php endif; ?>
            </div>

            <footer class ="footer-pasos">
               <a href="paciente.php" class="btn-cancelar-paso">Volver al inicio</a>
               <?php if (!$errorAlCargarProfesionales && !empty($profesionales)): ?>
                   <a href="paciente2.php" class="btn-siguiente-paso">Siguiente &rarr;</a>
               <?php endif; ?>
            </footer>

        </section>

    </main>

    <footer>
        <small> &copy; 2026 Pausa. Todos los derechos reservados. </small>  
    </footer>

</body>
