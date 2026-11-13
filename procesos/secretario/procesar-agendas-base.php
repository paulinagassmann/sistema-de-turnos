<?php
session_start();
require_once __DIR__ . '/../../config/csrf.php';

$rutaFormulario = '../../secretario/secretario-agendas-base.php';

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'secretario'
) {
    header("Location: ../../index.html?error=sesion_requerida");
    exit();
}

require_once __DIR__ . '/../../config/conexion.php';

$profesionalIdRecibido = $_POST['profesional_id'] ?? null;
$profesionalId = is_scalar($profesionalIdRecibido)
    ? filter_var((string) $profesionalIdRecibido, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if (
    !csrf_es_valido()
) {
    header("Location: {$rutaFormulario}?error=solicitud_invalida");
    exit();
}

$profesionalQuery = $profesionalId ? '?profesional_id=' . $profesionalId . '&' : '?';
if (!$profesionalId) {
    header("Location: {$rutaFormulario}?error=profesional_invalido");
    exit();
}

$diasPermitidos = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes'];
$diasEnviados = $_POST['dias'] ?? [];
$agendasNuevas = [];
$formularioValido = is_array($diasEnviados);
$errorValidacion = 'datos_invalidos';

if ($formularioValido) {
    foreach ($diasEnviados as $dia => $infoDia) {
        if (!in_array($dia, $diasPermitidos, true) || !is_array($infoDia)) {
            $formularioValido = false;
            break;
        }
    }
}

if ($formularioValido) {
    foreach ($diasPermitidos as $dia) {
        $infoDia = $diasEnviados[$dia] ?? [];
        if (!is_array($infoDia)) {
            $formularioValido = false;
            break;
        }

        if (!isset($infoDia['activo'])) {
            continue;
        }
        if (!is_string($infoDia['activo']) || $infoDia['activo'] !== '1') {
            $formularioValido = false;
            break;
        }

        $inicios = $infoDia['inicio'] ?? null;
        $fines = $infoDia['fin'] ?? null;
        if (!is_array($inicios) || !is_array($fines) || count($inicios) === 0 || count($inicios) !== count($fines)) {
            $formularioValido = false;
            $errorValidacion = 'franjas_incompletas';
            break;
        }

        $franjas = [];
        foreach ($inicios as $indice => $inicio) {
            $fin = $fines[$indice] ?? null;
            if (
                !is_string($inicio)
                || !is_string($fin)
                || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $inicio)
                || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $fin)
                || $fin <= $inicio
            ) {
                $formularioValido = false;
                $errorValidacion = 'horario_invalido';
                break 2;
            }

            $franjas[] = ['inicio' => $inicio, 'fin' => $fin];
        }

        usort($franjas, static fn(array $a, array $b): int => strcmp($a['inicio'], $b['inicio']));
        foreach ($franjas as $indice => $franja) {
            if ($indice > 0 && $franja['inicio'] < $franjas[$indice - 1]['fin']) {
                $formularioValido = false;
                $errorValidacion = 'franjas_superpuestas';
                break 2;
            }
        }

        foreach ($franjas as $franja) {
            $agendasNuevas[] = [
                'dia' => $dia,
                'inicio' => $franja['inicio'] . ':00',
                'fin' => $franja['fin'] . ':00'
            ];
        }
    }
}

if (!$formularioValido) {
    header("Location: {$rutaFormulario}{$profesionalQuery}error={$errorValidacion}");
    exit();
}

try {
    $stmtProfesional = $conexion->prepare(
        "SELECT 1
         FROM profesionales p
         JOIN usuarios u ON u.id = p.id AND u.rol = 'profesional'
         WHERE p.id = :profesional_id"
    );
    $stmtProfesional->execute([':profesional_id' => $profesionalId]);
    if (!$stmtProfesional->fetchColumn()) {
        header("Location: {$rutaFormulario}?error=profesional_invalido");
        exit();
    }

    $conexion->beginTransaction();

    $stmtDel = $conexion->prepare("DELETE FROM agendas WHERE profesional_id = :profesional_id");
    $stmtDel->execute([':profesional_id' => $profesionalId]);

    $stmtIns = $conexion->prepare(
        "INSERT INTO agendas (profesional_id, dia_semana, hora_inicio, hora_fin, duracion_turno) 
         VALUES (:profesional_id, :dia, :inicio, :fin, 60)"
    );
    foreach ($agendasNuevas as $agenda) {
        // Mapeamos el día para asegurarnos de que se guarde prolijo (con tilde y mayúscula)
        $diaFormateado = match($agenda['dia']) {
            'lunes' => 'Lunes',
            'martes' => 'Martes',
            'miercoles' => 'Miércoles',
            'jueves' => 'Jueves',
            'viernes' => 'Viernes',
            default => ucfirst($agenda['dia'])
        };

        $stmtIns->execute([
            ':profesional_id' => $profesionalId,
            ':dia' => $diaFormateado,
            ':inicio' => $agenda['inicio'],
            ':fin' => $agenda['fin']
        ]);
    }

    $conexion->commit();
    header("Location: {$rutaFormulario}?profesional_id={$profesionalId}&exito=1");
    exit();
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    error_log("Error al procesar agendas: " . $e->getMessage());
    header("Location: {$rutaFormulario}{$profesionalQuery}error=servidor");
    exit();
}