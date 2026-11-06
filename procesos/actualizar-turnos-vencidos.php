<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/turnos.php';

$cantidad = actualizarTurnosPendientesVencidos($conexion);
fwrite(STDOUT, "Turnos marcados ausentes: {$cantidad}" . PHP_EOL);
