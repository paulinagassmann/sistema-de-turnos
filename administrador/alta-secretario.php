<?php
session_start();
require_once __DIR__ . '/../config/csrf.php';

if (
    empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'administrador'
) {
    header("Location: ../index.html?error=sesion_requerida");
    exit();
}

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/validaciones.php';

$error = '';
$errores = [];

// Valores para volver a mostrar en el formulario si hay errores.
$dni = '';
$nombre = '';
$apellido = '';
$fecha_nacimiento = '';
$telefono = '+54';
$correo = '';

$fecha_maxima = date('Y-m-d');
$fecha_minima = '1900-01-01';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_es_valido()) {
    $error = 'La página venció. Recargala e intentá nuevamente.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim((string) ($_POST['dni'] ?? ''));
    $nombre = capitalizar_nombre((string) ($_POST['nombre'] ?? ''));
    $apellido = capitalizar_nombre((string) ($_POST['apellido'] ?? ''));
    $fecha_nacimiento = trim((string) ($_POST['fecha_nacimiento'] ?? ''));
    $telefono = trim((string) ($_POST['telefono'] ?? ''));
    $correo = trim((string) ($_POST['correo'] ?? ''));
    $password_temporal = trim((string) ($_POST['password_temporal'] ?? ''));

    // 1. DNI: exactamente 8 dígitos.
    if (!es_dni_valido($dni)) {
        $errores[] = 'El DNI debe tener exactamente 8 números, sin puntos, letras ni espacios.';
    }

    // 2 y 3. Nombre y apellido: solo letras (se permiten espacios entre palabras).
    if (!es_nombre_valido($nombre)) {
        $errores[] = 'El nombre solo puede contener letras.';
    }
    if (!es_nombre_valido($apellido)) {
        $errores[] = 'El apellido solo puede contener letras.';
    }

    // 4. Fecha de nacimiento (opcional): formato AAAA-MM-DD real, año de 4 dígitos y no futura.
    if ($fecha_nacimiento !== '' && !es_fecha_valida($fecha_nacimiento, $fecha_minima, $fecha_maxima)) {
        $errores[] = 'La fecha de nacimiento no es válida.';
    }

    // 5. Teléfono (opcional): +54 opcional y luego entre 10 y 13 números.
    if ($telefono === '+54') {
        $telefono = '';
    }
    if ($telefono !== '') {
        $telefono_normalizado = normalizar_telefono($telefono);
        if ($telefono_normalizado === null) {
            $errores[] = 'El teléfono solo puede contener números (entre 10 y 13, sin contar el +54). Ejemplo: +543436985148';
        } else {
            $telefono = $telefono_normalizado;
        }
    }

    // 6. Correo: sin espacios, con @ y un dominio con punto (ej: .com, .com.ar).
    if (!es_correo_valido($correo)) {
        $errores[] = 'Ingresá un correo electrónico válido (ej: nombre@dominio.com).';
    }

    // 7. Contraseña temporal: mínimo 8 caracteres.
    if (mb_strlen($password_temporal, 'UTF-8') < 8) {
        $errores[] = 'La contraseña temporal debe tener al menos 8 caracteres.';
    }

    if (!empty($errores)) {
        $error = implode("\n", $errores);
    } else {
        try {
            $password_hash = password_hash($password_temporal, PASSWORD_DEFAULT);

            $sql = "INSERT INTO usuarios (dni, nombre, apellido, fecha_nacimiento, telefono, correo, password, rol, debe_cambiar_password) 
                    VALUES (:dni, :nombre, :apellido, :fecha_nacimiento, :telefono, :correo, :password, 'secretario', 1)";
            
            $stmt = $conexion->prepare($sql);
            $stmt->execute([
                ':dni' => $dni,
                ':nombre' => $nombre,
                ':apellido' => $apellido,
                ':fecha_nacimiento' => $fecha_nacimiento ?: null,
                ':telefono' => $telefono,
                ':correo' => $correo,
                ':password' => $password_hash
            ]);

            header("Location: administrador.php?exito=secretario_creado");
            exit();

        } catch (Exception $e) {
            $error = 'Error al registrar el secretario (es posible que el DNI o correo ya existan).';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pausa - Registrar Secretario</title>
    <link rel="icon" type="image/png" href="../img/favicon.png">
    <link rel="stylesheet" href="../css/styles.css?v=<?php echo filemtime(__DIR__ . '/../css/styles.css'); ?>">
    <script src="../js/main.js?v=<?php echo filemtime(__DIR__ . '/../js/main.js'); ?>" defer></script>
    <script src="../js/validaciones-formulario.js?v=<?php echo filemtime(__DIR__ . '/../js/validaciones-formulario.js'); ?>" defer></script>
</head>
<body class="body-dashboard-paciente">

    <header class="header-paciente">
        <div class="bloque-marca">
            <span class="logo-texto">Pausa</span>
            <span class="subtitulo-logo">Panel de Administración</span>
        </div>
        <a href="administrador.php" class="btn-logout">Volver al panel</a>
    </header>

    <main class="main-paciente">
        <section class="seccion-turnos">
            
            <h2>Alta de Nuevo Secretario</h2>
            
            <?php if (!empty($error)): ?>
                <p class="aviso-turno-error" role="alert"><?php echo nl2br(htmlspecialchars($error, ENT_QUOTES, 'UTF-8')); ?></p>
            <?php endif; ?>

            <form action="alta-secretario.php" method="POST" class="formulario-registro" id="form-alta-secretario">
                <?php echo csrf_input(); ?>
                <div class="campo-perfil">
                    <label for="dni">DNI:</label>
                    <input type="text" id="dni" name="dni" inputmode="numeric" autocomplete="off"
                           minlength="8" maxlength="8" pattern="[0-9]{8}" data-validar="dni"
                           title="El DNI debe tener exactamente 8 números."
                           value="<?php echo htmlspecialchars($dni, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="campo-perfil">
                    <label for="nombre">Nombre:</label>
                    <input type="text" id="nombre" name="nombre" maxlength="100" autocomplete="off"
                           pattern="\p{L}+( \p{L}+)*" data-validar="letras"
                           title="El nombre solo puede contener letras."
                           value="<?php echo htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="campo-perfil">
                    <label for="apellido">Apellido:</label>
                    <input type="text" id="apellido" name="apellido" maxlength="100" autocomplete="off"
                           pattern="\p{L}+( \p{L}+)*" data-validar="letras"
                           title="El apellido solo puede contener letras."
                           value="<?php echo htmlspecialchars($apellido, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="campo-perfil">
                    <label for="fecha_nacimiento">Fecha de Nacimiento:</label>
                    <input type="date" id="fecha_nacimiento" name="fecha_nacimiento"
                           min="<?php echo $fecha_minima; ?>" max="<?php echo $fecha_maxima; ?>"
                           value="<?php echo htmlspecialchars($fecha_nacimiento, ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="campo-perfil">
                    <label for="telefono">Teléfono:</label>
                    <input type="tel" id="telefono" name="telefono" inputmode="tel" autocomplete="off"
                           maxlength="16" data-validar="telefono" placeholder="+543436985148" aria-describedby="ayuda-telefono"
                           value="<?php echo htmlspecialchars($telefono !== '' ? $telefono : '+54', ENT_QUOTES, 'UTF-8'); ?>">
                    <small id="ayuda-telefono" class="ayuda-campo">Ej: +543436985148</small>
                </div>

                <div class="campo-perfil">
                    <label for="correo">Correo Electrónico:</label>
                    <input type="email" id="correo" name="correo" maxlength="150" data-validar="correo" autocomplete="off"
                           pattern="[A-Za-z0-9._%+\-]+@[A-Za-z0-9\-]+(\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,}"
                           title="Ingresá un correo válido, por ejemplo: nombre@dominio.com"
                           value="<?php echo htmlspecialchars($correo, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="campo-perfil">
                    <label for="password_temporal">Contraseña Temporal:</label>
                    <input type="password" id="password_temporal" name="password_temporal" data-password-toggle
                           minlength="8" autocomplete="new-password"
                           title="La contraseña temporal debe tener al menos 8 caracteres."
                           aria-describedby="ayuda-password" required>
                    <small id="ayuda-password" class="ayuda-campo">Mínimo 8 caracteres.</small>
                </div>

                <button type="submit" class="btn-agendar">
                    <span>Crear cuenta de secretario</span>
                </button>
            </form>

        </section>
    </main>

    <footer>
        <small>&copy; 2026 Pausa. Todos los derechos reservados.</small>
    </footer>

</body>
</html>