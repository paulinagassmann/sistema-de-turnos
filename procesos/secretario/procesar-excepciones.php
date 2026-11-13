<?php
session_start();
require_once __DIR__ . '/../../config/csrf.php';

header('Content-Type: application/json; charset=utf-8');

$responder = static function (int $codigo, array $respuesta): void {
    http_response_code($codigo);
    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit();
};

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'secretario'
) {
    $responder(403, ['ok' => false, 'error' => 'sesion_requerida']);
}

if (
    !csrf_es_valido()
) {
    $responder(400, ['ok' => false, 'error' => 'solicitud_invalida']);
}

require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../config/turnos.php';
actualizarTurnosPendientesVencidos($conexion);

$profesionalIdRecibido = $_POST['profesional_id'] ?? null;
$profesionalId = is_scalar($profesionalIdRecibido)
    ? filter_var((string) $profesionalIdRecibido, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
$accion = $_POST['accion'] ?? null;
$mes = $_POST['mes'] ?? null;
$fecha = $accion === 'cargar_mes'
    ? (is_string($mes) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes) ? $mes . '-01' : null)
    : ($_POST['fecha'] ?? null);
$zonaHoraria = new DateTimeZone('America/Argentina/Buenos_Aires');
$fechaValida = is_string($fecha)
    ? DateTimeImmutable::createFromFormat('!Y-m-d', $fecha, $zonaHoraria)
    : false;

if (
    !$profesionalId
    || !is_string($accion)
    || !$fechaValida
    || $fechaValida->format('Y-m-d') !== $fecha
) {
    $responder(400, ['ok' => false, 'error' => 'datos_invalidos']);
}

$accionesConFechaFutura = [
    'bloquear_franja',
    'bloquear_dia',
    'crear_extra',
    'desbloquear_franja',
    'desbloquear_dia',
    'cancelar_turno'
];
if (
    in_array($accion, $accionesConFechaFutura, true)
    && $fechaValida < new DateTimeImmutable('today', $zonaHoraria)
) {
    $responder(400, ['ok' => false, 'error' => 'fecha_pasada']);
}

$bloqueoTurnoAdquirido = false;
$nombreBloqueoTurno = null;

try {
    $stmtProfesional = $conexion->prepare(
        "SELECT 1
         FROM profesionales p
         JOIN usuarios u ON u.id = p.id AND u.rol = 'profesional'
         WHERE p.id = :id"
    );
    $stmtProfesional->execute([':id' => $profesionalId]);
    if (!$stmtProfesional->fetchColumn()) {
        $responder(404, ['ok' => false, 'error' => 'profesional_invalido']);
    }

    $inicioDia = $fecha . ' 00:00:00';
    $finDia = $fecha . ' 23:59:59';
    $diasSemana = [
        'lunes' => 1, 'lun' => 1, 'monday' => 1,
        'martes' => 2, 'mar' => 2, 'tuesday' => 2,
        'miercoles' => 3, 'mie' => 3, 'wednesday' => 3,
        'jueves' => 4, 'jue' => 4, 'thursday' => 4,
        'viernes' => 5, 'vie' => 5, 'friday' => 5,
        'sabado' => 6, 'sab' => 6, 'saturday' => 6,
        'domingo' => 7, 'dom' => 7, 'sunday' => 7
    ];
    $normalizarDia = static function ($dia) use ($diasSemana): ?int {
        $dia = strtolower(trim((string) $dia));
        $dia = strtr($dia, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
        if (isset($diasSemana[$dia])) {
            return $diasSemana[$dia];
        }
        return ctype_digit($dia) && (int) $dia >= 1 && (int) $dia <= 7
            ? (int) $dia
            : null;
    };

    if ($accion === 'cargar_mes') {
        $inicioMes = $fecha . ' 00:00:00';
        $finMes = $fechaValida->modify('+1 month')->format('Y-m-d') . ' 00:00:00';

        $stmtAgendas = $conexion->prepare(
            'SELECT dia_semana, hora_inicio, hora_fin, duracion_turno
             FROM agendas
             WHERE profesional_id = :profesional_id'
        );
        $stmtAgendas->execute([':profesional_id' => $profesionalId]);
        $agendas = $stmtAgendas->fetchAll(PDO::FETCH_ASSOC);

        $stmtTurnos = $conexion->prepare(
            "SELECT DATE_FORMAT(fecha_hora, '%Y-%m-%d') AS fecha,
                    TIME_FORMAT(fecha_hora, '%H:%i') AS hora
             FROM turnos
             WHERE profesional_id = :profesional_id
               AND fecha_hora >= :inicio
               AND fecha_hora < :fin
               AND estado IN ('pendiente', 'confirmado')"
        );
        $stmtTurnos->execute([
            ':profesional_id' => $profesionalId,
            ':inicio' => $inicioMes,
            ':fin' => $finMes
        ]);
        $turnosPorFecha = [];
        foreach ($stmtTurnos->fetchAll(PDO::FETCH_ASSOC) as $turno) {
            $turnosPorFecha[$turno['fecha']][$turno['hora']] = true;
        }

        $stmtExcepciones = $conexion->prepare(
            'SELECT tipo, fecha_hora_inicio, fecha_hora_fin
             FROM excepciones
             WHERE profesional_id = :profesional_id
               AND fecha_hora_inicio < :fin_mes
               AND fecha_hora_fin >= :inicio_mes'
        );
        $stmtExcepciones->execute([
            ':profesional_id' => $profesionalId,
            ':inicio_mes' => $inicioMes,
            ':fin_mes' => $finMes
        ]);
        $excepciones = $stmtExcepciones->fetchAll(PDO::FETCH_ASSOC);

        $ahora = new DateTimeImmutable('now', $zonaHoraria);
        $diasConTurnos = [];
        $primerDiaMes = new DateTimeImmutable($inicioMes, $zonaHoraria);
        $cantidadDias = (int) $primerDiaMes->format('t');
        for ($numeroDiaMes = 1; $numeroDiaMes <= $cantidadDias; $numeroDiaMes++) {
            $fechaDia = $primerDiaMes->setDate(
                (int) $primerDiaMes->format('Y'),
                (int) $primerDiaMes->format('n'),
                $numeroDiaMes
            );
            $fechaTexto = $fechaDia->format('Y-m-d');
            $numeroDiaSemana = (int) $fechaDia->format('N');
            $excepcionesDia = array_values(array_filter(
                $excepciones,
                static fn(array $excepcion): bool =>
                    substr($excepcion['fecha_hora_inicio'], 0, 10) <= $fechaTexto
                    && substr($excepcion['fecha_hora_fin'], 0, 10) >= $fechaTexto
            ));
            $turnosDia = $turnosPorFecha[$fechaTexto] ?? [];
            $hayTurnoDisponibleOTomado = !empty($turnosDia);

            foreach ($agendas as $agenda) {
                if ($hayTurnoDisponibleOTomado) {
                    break;
                }
                if ($normalizarDia($agenda['dia_semana']) !== $numeroDiaSemana) {
                    continue;
                }

                $inicio = DateTimeImmutable::createFromFormat('!H:i:s', (string) $agenda['hora_inicio'], $zonaHoraria)
                    ?: DateTimeImmutable::createFromFormat('!H:i', (string) $agenda['hora_inicio'], $zonaHoraria);
                $fin = DateTimeImmutable::createFromFormat('!H:i:s', (string) $agenda['hora_fin'], $zonaHoraria)
                    ?: DateTimeImmutable::createFromFormat('!H:i', (string) $agenda['hora_fin'], $zonaHoraria);
                $duracion = filter_var($agenda['duracion_turno'], FILTER_VALIDATE_INT);
                if (!$inicio || !$fin || $duracion === false || $duracion < 1 || $duracion > 480) {
                    continue;
                }

                $minutoInicio = ((int) $inicio->format('H') * 60) + (int) $inicio->format('i');
                $minutoFin = ((int) $fin->format('H') * 60) + (int) $fin->format('i');
                for ($minuto = $minutoInicio; $minuto + $duracion <= $minutoFin; $minuto += $duracion) {
                    $horaInicio = sprintf('%02d:%02d', intdiv($minuto, 60), $minuto % 60);
                    $inicioFranja = new DateTimeImmutable($fechaTexto . ' ' . $horaInicio . ':00', $zonaHoraria);
                    $finFranja = $inicioFranja->modify('+' . $duracion . ' minutes');
                    $bloqueada = false;

                    foreach ($excepcionesDia as $excepcion) {
                        if (
                            $excepcion['tipo'] === 'bloqueo'
                            && $inicioFranja < new DateTimeImmutable($excepcion['fecha_hora_fin'], $zonaHoraria)
                            && $finFranja > new DateTimeImmutable($excepcion['fecha_hora_inicio'], $zonaHoraria)
                        ) {
                            $bloqueada = true;
                            break;
                        }
                    }

                    if (isset($turnosDia[$horaInicio]) || (!$bloqueada && $inicioFranja > $ahora)) {
                        $hayTurnoDisponibleOTomado = true;
                        break 2;
                    }
                }
            }

            if (!$hayTurnoDisponibleOTomado) {
                foreach ($excepcionesDia as $excepcion) {
                    if ($excepcion['tipo'] !== 'extra') {
                        continue;
                    }

                    $horaInicio = substr($excepcion['fecha_hora_inicio'], 11, 5);
                    $inicioExtra = new DateTimeImmutable($excepcion['fecha_hora_inicio'], $zonaHoraria);
                    $finExtra = new DateTimeImmutable($excepcion['fecha_hora_fin'], $zonaHoraria);
                    $bloqueada = false;

                    foreach ($excepcionesDia as $otraExcepcion) {
                        if (
                            $otraExcepcion['tipo'] === 'bloqueo'
                            && $inicioExtra < new DateTimeImmutable($otraExcepcion['fecha_hora_fin'], $zonaHoraria)
                            && $finExtra > new DateTimeImmutable($otraExcepcion['fecha_hora_inicio'], $zonaHoraria)
                        ) {
                            $bloqueada = true;
                            break;
                        }
                    }

                    if (!$bloqueada && (isset($turnosDia[$horaInicio]) || $inicioExtra > $ahora)) {
                        $hayTurnoDisponibleOTomado = true;
                        break;
                    }
                }
            }

            if ($hayTurnoDisponibleOTomado) {
                $diasConTurnos[] = $fechaTexto;
            }
        }

        $responder(200, ['ok' => true, 'data' => ['dias' => $diasConTurnos]]);
    }

    if ($accion === 'cargar_dia') {
        $stmtAgendas = $conexion->prepare(
            'SELECT id, dia_semana, hora_inicio, hora_fin, duracion_turno
             FROM agendas
             WHERE profesional_id = :profesional_id'
        );
        $stmtAgendas->execute([':profesional_id' => $profesionalId]);
        $agendas = $stmtAgendas->fetchAll(PDO::FETCH_ASSOC);

        $numeroDia = (int) $fechaValida->format('N');
        $franjas = [];

        foreach ($agendas as $agenda) {
            if ($normalizarDia($agenda['dia_semana']) !== $numeroDia) {
                continue;
            }

            $inicio = DateTimeImmutable::createFromFormat('!H:i:s', (string) $agenda['hora_inicio'], $zonaHoraria)
                ?: DateTimeImmutable::createFromFormat('!H:i', (string) $agenda['hora_inicio'], $zonaHoraria);
            $fin = DateTimeImmutable::createFromFormat('!H:i:s', (string) $agenda['hora_fin'], $zonaHoraria)
                ?: DateTimeImmutable::createFromFormat('!H:i', (string) $agenda['hora_fin'], $zonaHoraria);
            $duracion = filter_var($agenda['duracion_turno'], FILTER_VALIDATE_INT);
            if (!$inicio || !$fin || $duracion === false || $duracion < 1 || $duracion > 480) {
                continue;
            }

            $minutoInicio = ((int) $inicio->format('H') * 60) + (int) $inicio->format('i');
            $minutoFin = ((int) $fin->format('H') * 60) + (int) $fin->format('i');
            for ($minuto = $minutoInicio; $minuto + $duracion <= $minutoFin; $minuto += $duracion) {
                $franjas[] = [
                    'inicio' => sprintf('%02d:%02d', intdiv($minuto, 60), $minuto % 60),
                    'fin' => sprintf('%02d:%02d', intdiv($minuto + $duracion, 60), ($minuto + $duracion) % 60),
                    'estado' => 'disponible',
                    'detalle' => 'Turno estándar',
                    'extra' => false,
                    'excepcionId' => null,
                    'turnoId' => null,
                    'paciente' => '',
                    'dni' => '',
                    'telefono' => ''
                ];
            }
        }

        $stmtTurnos = $conexion->prepare(
            "SELECT t.id, TIME_FORMAT(t.fecha_hora, '%H:%i') AS hora_inicio,
                    u.nombre, u.apellido, u.dni, u.telefono
             FROM turnos t
             JOIN pacientes p ON p.id = t.paciente_id
             JOIN usuarios u ON u.id = p.id
             WHERE t.profesional_id = :profesional_id
               AND t.fecha_hora >= :inicio
               AND t.fecha_hora <= :fin
               AND t.estado IN ('pendiente', 'confirmado')
             ORDER BY t.fecha_hora"
        );
        $stmtTurnos->execute([
            ':profesional_id' => $profesionalId,
            ':inicio' => $inicioDia,
            ':fin' => $finDia
        ]);
        $turnosPorHora = [];
        foreach ($stmtTurnos->fetchAll(PDO::FETCH_ASSOC) as $turno) {
            $turnosPorHora[$turno['hora_inicio']] = $turno;
        }

        $stmtExcepciones = $conexion->prepare(
            'SELECT id, tipo, fecha_hora_inicio, fecha_hora_fin, motivo
             FROM excepciones
             WHERE profesional_id = :profesional_id
               AND fecha_hora_inicio <= :fin_dia
               AND fecha_hora_fin >= :inicio_dia
             ORDER BY fecha_hora_inicio'
        );
        $stmtExcepciones->execute([
            ':profesional_id' => $profesionalId,
            ':inicio_dia' => $inicioDia,
            ':fin_dia' => $finDia
        ]);
        $excepciones = $stmtExcepciones->fetchAll(PDO::FETCH_ASSOC);
        $bloqueadoDia = false;
        $ahora = new DateTimeImmutable('now', $zonaHoraria);

        foreach ($franjas as &$franja) {
            $inicioFranja = new DateTimeImmutable($fecha . ' ' . $franja['inicio'] . ':00', $zonaHoraria);
            $finFranja = new DateTimeImmutable($fecha . ' ' . $franja['fin'] . ':00', $zonaHoraria);

            foreach ($excepciones as $excepcion) {
                $inicioExcepcion = new DateTimeImmutable($excepcion['fecha_hora_inicio'], $zonaHoraria);
                $finExcepcion = new DateTimeImmutable($excepcion['fecha_hora_fin'], $zonaHoraria);
                if ($excepcion['tipo'] === 'bloqueo' && $inicioFranja < $finExcepcion && $finFranja > $inicioExcepcion) {
                    $franja['estado'] = 'bloqueado';
                    $franja['detalle'] = $excepcion['motivo'] ?: 'Por excepción manual';
                    $franja['excepcionId'] = (int) $excepcion['id'];
                }
            }

            $turno = $turnosPorHora[$franja['inicio']] ?? null;
            if ($turno) {
                $franja['estado'] = 'ocupado';
                $franja['turnoId'] = (int) $turno['id'];
                $franja['paciente'] = trim($turno['nombre'] . ' ' . $turno['apellido']);
                $franja['dni'] = (string) $turno['dni'];
                $franja['telefono'] = (string) $turno['telefono'];
                $franja['detalle'] = $franja['paciente'];
            } elseif ($franja['estado'] !== 'bloqueado' && $inicioFranja <= $ahora) {
                $franja['estado'] = 'pasado';
                $franja['detalle'] = 'Horario no disponible';
            }
        }
        unset($franja);

        foreach ($excepciones as $excepcion) {
            if ($excepcion['tipo'] !== 'extra') {
                if (
                    $excepcion['fecha_hora_inicio'] <= $inicioDia
                    && $excepcion['fecha_hora_fin'] >= $finDia
                ) {
                    $bloqueadoDia = true;
                }
                continue;
            }

            $horaInicio = substr($excepcion['fecha_hora_inicio'], 11, 5);
            $horaFin = substr($excepcion['fecha_hora_fin'], 11, 5);
            $inicioExtra = new DateTimeImmutable($excepcion['fecha_hora_inicio'], $zonaHoraria);
            $finExtra = new DateTimeImmutable($excepcion['fecha_hora_fin'], $zonaHoraria);
            $bloqueoExtra = null;
            foreach ($excepciones as $otraExcepcion) {
                if ($otraExcepcion['tipo'] !== 'bloqueo') {
                    continue;
                }
                $inicioBloqueo = new DateTimeImmutable($otraExcepcion['fecha_hora_inicio'], $zonaHoraria);
                $finBloqueo = new DateTimeImmutable($otraExcepcion['fecha_hora_fin'], $zonaHoraria);
                if ($inicioExtra < $finBloqueo && $finExtra > $inicioBloqueo) {
                    $bloqueoExtra = $otraExcepcion;
                    break;
                }
            }

            $turno = $turnosPorHora[$horaInicio] ?? null;
            $estadoExtra = $bloqueoExtra
                ? 'bloqueado'
                : ($turno ? 'ocupado' : ($inicioExtra <= $ahora ? 'pasado' : 'disponible'));
            $franjas[] = [
                'inicio' => $horaInicio,
                'fin' => $horaFin,
                'estado' => $estadoExtra,
                'detalle' => $bloqueoExtra
                    ? ($bloqueoExtra['motivo'] ?: 'Por excepción manual')
                    : ($turno
                        ? trim($turno['nombre'] . ' ' . $turno['apellido'])
                        : ($estadoExtra === 'pasado'
                            ? 'Horario no disponible'
                            : ($excepcion['motivo'] ?: 'Turno agregado fuera del horario base'))),
                'extra' => true,
                'excepcionId' => (int) ($bloqueoExtra['id'] ?? $excepcion['id']),
                'turnoId' => $turno ? (int) $turno['id'] : null,
                'paciente' => $turno ? trim($turno['nombre'] . ' ' . $turno['apellido']) : '',
                'dni' => $turno ? (string) $turno['dni'] : '',
                'telefono' => $turno ? (string) $turno['telefono'] : ''
            ];
        }

        foreach ($excepciones as $excepcion) {
            if (
                $excepcion['tipo'] !== 'bloqueo'
                || $excepcion['motivo'] !== 'Turno cancelado y bloqueado por secretaría'
            ) {
                continue;
            }

            $extraConservado = false;
            foreach ($excepciones as $otraExcepcion) {
                if (
                    $otraExcepcion['tipo'] === 'extra'
                    && $otraExcepcion['fecha_hora_inicio'] === $excepcion['fecha_hora_inicio']
                    && $otraExcepcion['fecha_hora_fin'] === $excepcion['fecha_hora_fin']
                ) {
                    $extraConservado = true;
                    break;
                }
            }
            if ($extraConservado) {
                continue;
            }

            $franjaBaseExistente = false;
            foreach ($franjas as $franjaExistente) {
                if (
                    $franjaExistente['inicio'] === substr($excepcion['fecha_hora_inicio'], 11, 5)
                    && $franjaExistente['fin'] === substr($excepcion['fecha_hora_fin'], 11, 5)
                    && empty($franjaExistente['extra'])
                ) {
                    $franjaBaseExistente = true;
                    break;
                }
            }
            if ($franjaBaseExistente) {
                continue;
            }

            $franjas[] = [
                'inicio' => substr($excepcion['fecha_hora_inicio'], 11, 5),
                'fin' => substr($excepcion['fecha_hora_fin'], 11, 5),
                'estado' => 'bloqueado',
                'detalle' => $excepcion['motivo'],
                'extra' => true,
                'excepcionId' => (int) $excepcion['id'],
                'turnoId' => null,
                'paciente' => '',
                'dni' => '',
                'telefono' => ''
            ];
        }

        $turnosCancelados = [];
        if ($bloqueadoDia) {
            $stmtTurnosCancelados = $conexion->prepare(
                "SELECT u.nombre, u.apellido, u.telefono,
                        TIME_FORMAT(t.fecha_hora, '%H:%i') AS hora
                 FROM turnos t
                 JOIN pacientes p ON p.id = t.paciente_id
                 JOIN usuarios u ON u.id = p.id
                 WHERE t.profesional_id = :profesional_id
                   AND t.fecha_hora >= :inicio
                   AND t.fecha_hora <= :fin
                   AND t.estado = 'cancelado'
                 ORDER BY t.fecha_hora"
            );
            $stmtTurnosCancelados->execute([
                ':profesional_id' => $profesionalId,
                ':inicio' => $inicioDia,
                ':fin' => $finDia
            ]);
            $turnosCancelados = $stmtTurnosCancelados->fetchAll(PDO::FETCH_ASSOC);
        }

        usort($franjas, static fn(array $a, array $b): int => strcmp($a['inicio'], $b['inicio']));
        $responder(200, [
            'ok' => true,
            'data' => [
                'franjas' => $franjas,
                'bloqueadoDia' => $bloqueadoDia,
                'turnosCancelados' => $turnosCancelados,
                'textoBloqueo' => $bloqueadoDia ? 'Este día está bloqueado por una excepción.' : ''
            ]
        ]);
    }

    $horaInicio = $_POST['hora_inicio'] ?? null;
    $horaFin = $_POST['hora_fin'] ?? null;
    $formatoHora = '/^(?:[01]\d|2[0-3]):[0-5]\d$/';
    $inicioMinuto = is_string($horaInicio) && preg_match($formatoHora, $horaInicio)
        ? ((int) substr($horaInicio, 0, 2) * 60) + (int) substr($horaInicio, 3, 2)
        : null;
    if ($accion === 'crear_extra' && $inicioMinuto !== null && $inicioMinuto <= 1379) {
        $finMinuto = $inicioMinuto + 60;
        $horaFin = sprintf('%02d:%02d', intdiv($finMinuto, 60), $finMinuto % 60);
    } else {
        $finMinuto = is_string($horaFin) && preg_match($formatoHora, $horaFin)
        ? ((int) substr($horaFin, 0, 2) * 60) + (int) substr($horaFin, 3, 2)
        : null;
    }

    if ($accion === 'desbloquear_franja') {
        $excepcionId = filter_var($_POST['excepcion_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$excepcionId) {
            $responder(400, ['ok' => false, 'error' => 'datos_invalidos']);
        }
        $stmtExcepcion = $conexion->prepare(
            'SELECT tipo, motivo, fecha_hora_inicio, fecha_hora_fin
             FROM excepciones
             WHERE id = :id
               AND profesional_id = :profesional_id
               AND fecha_hora_inicio >= :inicio_dia
               AND fecha_hora_fin <= :fin_dia'
        );
        $stmtExcepcion->execute([
            ':id' => $excepcionId,
            ':profesional_id' => $profesionalId,
            ':inicio_dia' => $inicioDia,
            ':fin_dia' => $finDia
        ]);
        $excepcion = $stmtExcepcion->fetch(PDO::FETCH_ASSOC);
        if (!$excepcion || $excepcion['tipo'] !== 'bloqueo') {
            $responder(404, ['ok' => false, 'error' => 'excepcion_no_encontrada']);
        }

        if ($excepcion['motivo'] === 'Turno cancelado y bloqueado por secretaría') {
            $stmtExtra = $conexion->prepare(
                "SELECT 1
                 FROM excepciones
                 WHERE profesional_id = :profesional_id
                   AND tipo = 'extra'
                   AND fecha_hora_inicio = :fecha_hora_inicio
                   AND fecha_hora_fin = :fecha_hora_fin
                 LIMIT 1"
            );
            $stmtExtra->execute([
                ':profesional_id' => $profesionalId,
                ':fecha_hora_inicio' => $excepcion['fecha_hora_inicio'],
                ':fecha_hora_fin' => $excepcion['fecha_hora_fin']
            ]);
            $extraConservado = (bool) $stmtExtra->fetchColumn();
            $esFranjaBase = false;
            if (!$extraConservado) {
                $inicioBloqueo = new DateTimeImmutable($excepcion['fecha_hora_inicio'], $zonaHoraria);
                $minutoInicioBloqueo = ((int) $inicioBloqueo->format('H') * 60) + (int) $inicioBloqueo->format('i');
                $duracionBloqueoSegundos =
                    (new DateTimeImmutable($excepcion['fecha_hora_fin'], $zonaHoraria))->getTimestamp()
                    - $inicioBloqueo->getTimestamp()
                ;
                $stmtAgendas = $conexion->prepare(
                    'SELECT dia_semana, hora_inicio, hora_fin, duracion_turno
                     FROM agendas
                     WHERE profesional_id = :profesional_id'
                );
                $stmtAgendas->execute([':profesional_id' => $profesionalId]);
                foreach ($stmtAgendas->fetchAll(PDO::FETCH_ASSOC) as $agenda) {
                    if ($normalizarDia($agenda['dia_semana']) !== (int) $inicioBloqueo->format('N')) {
                        continue;
                    }
                    $inicioAgenda = DateTimeImmutable::createFromFormat('!H:i:s', (string) $agenda['hora_inicio'], $zonaHoraria)
                        ?: DateTimeImmutable::createFromFormat('!H:i', (string) $agenda['hora_inicio'], $zonaHoraria);
                    $finAgenda = DateTimeImmutable::createFromFormat('!H:i:s', (string) $agenda['hora_fin'], $zonaHoraria)
                        ?: DateTimeImmutable::createFromFormat('!H:i', (string) $agenda['hora_fin'], $zonaHoraria);
                    $duracionAgenda = filter_var($agenda['duracion_turno'], FILTER_VALIDATE_INT);
                    if (!$inicioAgenda || !$finAgenda || $duracionAgenda === false || $duracionAgenda < 1) {
                        continue;
                    }
                    $minutoInicioAgenda = ((int) $inicioAgenda->format('H') * 60) + (int) $inicioAgenda->format('i');
                    $minutoFinAgenda = ((int) $finAgenda->format('H') * 60) + (int) $finAgenda->format('i');
                    if (
                        $minutoInicioBloqueo >= $minutoInicioAgenda
                        && $minutoInicioBloqueo + intdiv($duracionBloqueoSegundos, 60) <= $minutoFinAgenda
                        && $minutoInicioBloqueo - $minutoInicioAgenda >= 0
                        && ($minutoInicioBloqueo - $minutoInicioAgenda) % $duracionAgenda === 0
                        && $duracionBloqueoSegundos === $duracionAgenda * 60
                    ) {
                        $esFranjaBase = true;
                        break;
                    }
                }
            }
            if (!$extraConservado && !$esFranjaBase) {
                $stmtRestaurarExtra = $conexion->prepare(
                    "UPDATE excepciones
                     SET tipo = 'extra', motivo = 'Turno agregado fuera del horario base'
                     WHERE id = :id AND profesional_id = :profesional_id AND tipo = 'bloqueo'"
                );
                $stmtRestaurarExtra->execute([
                    ':id' => $excepcionId,
                    ':profesional_id' => $profesionalId
                ]);
                $responder(200, ['ok' => true]);
            }
        }

        $stmtEliminar = $conexion->prepare(
            "DELETE FROM excepciones
             WHERE id = :id
               AND profesional_id = :profesional_id
               AND tipo = 'bloqueo'
               AND fecha_hora_inicio >= :inicio_dia
               AND fecha_hora_fin <= :fin_dia"
        );
        $stmtEliminar->execute([
            ':id' => $excepcionId,
            ':profesional_id' => $profesionalId,
            ':inicio_dia' => $inicioDia,
            ':fin_dia' => $finDia
        ]);
        if ($stmtEliminar->rowCount() !== 1) {
            $responder(404, ['ok' => false, 'error' => 'excepcion_no_encontrada']);
        }
        $responder(200, ['ok' => true]);
    }

    if ($accion === 'desbloquear_dia') {
        $stmtEliminarDia = $conexion->prepare(
            "DELETE FROM excepciones
             WHERE profesional_id = :profesional_id
               AND tipo = 'bloqueo'
               AND fecha_hora_inicio <= :inicio_dia
               AND fecha_hora_fin >= :fin_dia"
        );
        $stmtEliminarDia->execute([
            ':profesional_id' => $profesionalId,
            ':inicio_dia' => $inicioDia,
            ':fin_dia' => $finDia
        ]);
        if ($stmtEliminarDia->rowCount() < 1) {
            $responder(404, ['ok' => false, 'error' => 'excepcion_no_encontrada']);
        }
        $responder(200, ['ok' => true]);
    }

    if ($accion === 'cancelar_turno') {
        $turnoId = filter_var($_POST['turno_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$turnoId) {
            $responder(400, ['ok' => false, 'error' => 'datos_invalidos']);
        }
        $stmtHoraTurno = $conexion->prepare(
            "SELECT fecha_hora
             FROM turnos
             WHERE id = :id
               AND profesional_id = :profesional_id
               AND fecha_hora >= :inicio_dia
               AND fecha_hora <= :fin_dia
               AND estado IN ('pendiente', 'confirmado')"
        );
        $stmtHoraTurno->execute([
            ':id' => $turnoId,
            ':profesional_id' => $profesionalId,
            ':inicio_dia' => $inicioDia,
            ':fin_dia' => $finDia
        ]);
        $fechaHoraTurno = $stmtHoraTurno->fetchColumn();
        if (!$fechaHoraTurno) {
            $responder(404, ['ok' => false, 'error' => 'turno_no_encontrado']);
        }

        $inicioTurno = new DateTimeImmutable($fechaHoraTurno, $zonaHoraria);
        $nombreBloqueo = hash(
            'sha256',
            $profesionalId . '|' . $inicioTurno->format('Y-m-d') . '|' . $inicioTurno->format('H:i')
        );
        $nombreBloqueoTurno = $nombreBloqueo;
        $stmtAdquirirBloqueo = $conexion->prepare('SELECT GET_LOCK(:nombre, 5)');
        $stmtAdquirirBloqueo->execute([':nombre' => $nombreBloqueo]);
        if ((int) $stmtAdquirirBloqueo->fetchColumn() !== 1) {
            $responder(409, ['ok' => false, 'error' => 'ocupado']);
        }
        $bloqueoTurnoAdquirido = true;

        $conexion->beginTransaction();
        $stmtTurno = $conexion->prepare(
            "SELECT fecha_hora
             FROM turnos
             WHERE id = :id
               AND profesional_id = :profesional_id
               AND fecha_hora = :fecha_hora
               AND estado IN ('pendiente', 'confirmado')
             FOR UPDATE"
        );
        $stmtTurno->execute([
            ':id' => $turnoId,
            ':profesional_id' => $profesionalId,
            ':fecha_hora' => $fechaHoraTurno
        ]);
        $fechaHoraTurno = $stmtTurno->fetchColumn();
        if (!$fechaHoraTurno) {
            $conexion->rollBack();
            $conexion->prepare('SELECT RELEASE_LOCK(:nombre)')->execute([':nombre' => $nombreBloqueo]);
            $bloqueoTurnoAdquirido = false;
            $responder(404, ['ok' => false, 'error' => 'turno_no_encontrado']);
        }

        $inicioTurno = new DateTimeImmutable($fechaHoraTurno, $zonaHoraria);
        $inicioBloqueo = $inicioTurno->format('Y-m-d H:i:s');
        $stmtExtra = $conexion->prepare(
            "SELECT id, fecha_hora_fin
             FROM excepciones
             WHERE profesional_id = :profesional_id
               AND tipo = 'extra'
               AND fecha_hora_inicio = :fecha_hora_inicio
             LIMIT 1
             FOR UPDATE"
        );
        $stmtExtra->execute([
            ':profesional_id' => $profesionalId,
            ':fecha_hora_inicio' => $inicioBloqueo
        ]);
        $turnoExtra = $stmtExtra->fetch(PDO::FETCH_ASSOC);

        if ($turnoExtra) {
            $finBloqueo = (string) $turnoExtra['fecha_hora_fin'];
            $stmtBloqueoExtraExistente = $conexion->prepare(
                "SELECT id
                 FROM excepciones
                 WHERE profesional_id = :profesional_id
                   AND tipo = 'bloqueo'
                   AND fecha_hora_inicio = :fecha_hora_inicio
                   AND fecha_hora_fin = :fecha_hora_fin
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmtBloqueoExtraExistente->execute([
                ':profesional_id' => $profesionalId,
                ':fecha_hora_inicio' => $inicioBloqueo,
                ':fecha_hora_fin' => $finBloqueo
            ]);
            if (!$stmtBloqueoExtraExistente->fetchColumn()) {
                $stmtBloquearExtra = $conexion->prepare(
                    "INSERT INTO excepciones
                        (profesional_id, tipo, fecha_hora_inicio, fecha_hora_fin, motivo)
                     VALUES
                        (:profesional_id, 'bloqueo', :fecha_hora_inicio, :fecha_hora_fin,
                         'Turno cancelado y bloqueado por secretaría')"
                );
                $stmtBloquearExtra->execute([
                    ':profesional_id' => $profesionalId,
                    ':fecha_hora_inicio' => $inicioBloqueo,
                    ':fecha_hora_fin' => $finBloqueo
                ]);
            }
        } else {
            $numeroDiaTurno = (int) $inicioTurno->format('N');
            $minutoTurno = ((int) $inicioTurno->format('H') * 60) + (int) $inicioTurno->format('i');
            $duracionTurno = null;
            $stmtAgendas = $conexion->prepare(
                'SELECT dia_semana, hora_inicio, hora_fin, duracion_turno
                 FROM agendas
                 WHERE profesional_id = :profesional_id'
            );
            $stmtAgendas->execute([':profesional_id' => $profesionalId]);
            foreach ($stmtAgendas->fetchAll(PDO::FETCH_ASSOC) as $agenda) {
                if ($normalizarDia($agenda['dia_semana']) !== $numeroDiaTurno) {
                    continue;
                }
                $horaAgendaInicio = DateTimeImmutable::createFromFormat('!H:i:s', (string) $agenda['hora_inicio'], $zonaHoraria)
                    ?: DateTimeImmutable::createFromFormat('!H:i', (string) $agenda['hora_inicio'], $zonaHoraria);
                $horaAgendaFin = DateTimeImmutable::createFromFormat('!H:i:s', (string) $agenda['hora_fin'], $zonaHoraria)
                    ?: DateTimeImmutable::createFromFormat('!H:i', (string) $agenda['hora_fin'], $zonaHoraria);
                $duracion = filter_var($agenda['duracion_turno'], FILTER_VALIDATE_INT);
                if (!$horaAgendaInicio || !$horaAgendaFin || $duracion === false || $duracion < 1 || $duracion > 480) {
                    continue;
                }
                $minutoAgendaInicio = ((int) $horaAgendaInicio->format('H') * 60) + (int) $horaAgendaInicio->format('i');
                $minutoAgendaFin = ((int) $horaAgendaFin->format('H') * 60) + (int) $horaAgendaFin->format('i');
                if (
                    $minutoTurno >= $minutoAgendaInicio
                    && $minutoTurno + $duracion <= $minutoAgendaFin
                    && ($minutoTurno - $minutoAgendaInicio) % $duracion === 0
                ) {
                    $duracionTurno = $duracion;
                    break;
                }
            }
            if ($duracionTurno === null) {
                $conexion->rollBack();
                $conexion->prepare('SELECT RELEASE_LOCK(:nombre)')->execute([':nombre' => $nombreBloqueo]);
                $bloqueoTurnoAdquirido = false;
                $responder(409, ['ok' => false, 'error' => 'horario_invalido']);
            }
            $finBloqueo = $inicioTurno->modify("+{$duracionTurno} minutes")->format('Y-m-d H:i:s');
            $stmtBloqueoExistente = $conexion->prepare(
                "SELECT id
                 FROM excepciones
                 WHERE profesional_id = :profesional_id
                   AND tipo = 'bloqueo'
                   AND fecha_hora_inicio < :fecha_fin
                   AND fecha_hora_fin > :fecha_inicio
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmtBloqueoExistente->execute([
                ':profesional_id' => $profesionalId,
                ':fecha_inicio' => $inicioBloqueo,
                ':fecha_fin' => $finBloqueo
            ]);
            if (!$stmtBloqueoExistente->fetchColumn()) {
                $stmtInsertarBloqueo = $conexion->prepare(
                    "INSERT INTO excepciones
                        (profesional_id, tipo, fecha_hora_inicio, fecha_hora_fin, motivo)
                     VALUES
                        (:profesional_id, 'bloqueo', :inicio, :fin, 'Turno cancelado y bloqueado por secretaría')"
                );
                $stmtInsertarBloqueo->execute([
                    ':profesional_id' => $profesionalId,
                    ':inicio' => $inicioBloqueo,
                    ':fin' => $finBloqueo
                ]);
            }
        }

        $stmtCancelar = $conexion->prepare(
            "UPDATE turnos
             SET estado = 'cancelado'
             WHERE id = :id
               AND profesional_id = :profesional_id
               AND estado IN ('pendiente', 'confirmado')"
        );
        $stmtCancelar->execute([':id' => $turnoId, ':profesional_id' => $profesionalId]);
        if ($stmtCancelar->rowCount() !== 1) {
            $conexion->rollBack();
            $conexion->prepare('SELECT RELEASE_LOCK(:nombre)')->execute([':nombre' => $nombreBloqueo]);
            $bloqueoTurnoAdquirido = false;
            $responder(404, ['ok' => false, 'error' => 'turno_no_encontrado']);
        }
        $conexion->commit();
        $conexion->prepare('SELECT RELEASE_LOCK(:nombre)')->execute([':nombre' => $nombreBloqueo]);
        $bloqueoTurnoAdquirido = false;
        $responder(200, ['ok' => true]);
    }

    if (!in_array($accion, ['bloquear_franja', 'bloquear_dia', 'crear_extra'], true)) {
        $responder(400, ['ok' => false, 'error' => 'accion_invalida']);
    }
    if (
        $accion === 'crear_extra'
        && !in_array($_POST['estado'] ?? 'disponible', ['disponible', 'asignado'], true)
    ) {
        $responder(400, ['ok' => false, 'error' => 'datos_invalidos']);
    }

    if ($accion === 'bloquear_dia') {
        $inicioFechaHora = $inicioDia;
        $finFechaHora = $finDia;
        $motivo = 'Bloqueo de agenda por excepción';
    } else {
        if (
            $inicioMinuto === null
            || $finMinuto === null
            || $finMinuto <= $inicioMinuto
        ) {
            $responder(400, ['ok' => false, 'error' => 'datos_invalidos']);
        }
        $inicioFechaHora = $fecha . ' ' . $horaInicio . ':00';
        $finFechaHora = $fecha . ' ' . $horaFin . ':00';
        $motivo = $accion === 'crear_extra'
            ? 'Turno agregado fuera del horario base'
            : 'Por excepción manual';
    }

    if ($accion === 'crear_extra') {
        $inicioExtra = new DateTimeImmutable($inicioFechaHora, $zonaHoraria);
        $finExtra = new DateTimeImmutable($finFechaHora, $zonaHoraria);
        $ahora = new DateTimeImmutable('now', $zonaHoraria);
        if (
            $inicioExtra <= $ahora
            || $inicioExtra > $ahora->modify('+89 days')
            || ($finExtra->getTimestamp() - $inicioExtra->getTimestamp()) !== 60 * 60
        ) {
            $responder(400, ['ok' => false, 'error' => 'datos_invalidos']);
        }

        $stmtAgendasExtra = $conexion->prepare(
            'SELECT dia_semana, hora_inicio, hora_fin
             FROM agendas
             WHERE profesional_id = :profesional_id'
        );
        $stmtAgendasExtra->execute([':profesional_id' => $profesionalId]);
        $numeroDiaExtra = (int) $fechaValida->format('N');
        foreach ($stmtAgendasExtra->fetchAll(PDO::FETCH_ASSOC) as $agenda) {
            if ($normalizarDia($agenda['dia_semana']) !== $numeroDiaExtra) {
                continue;
            }
            $inicioAgenda = DateTimeImmutable::createFromFormat('!H:i:s', (string) $agenda['hora_inicio'], $zonaHoraria)
                ?: DateTimeImmutable::createFromFormat('!H:i', (string) $agenda['hora_inicio'], $zonaHoraria);
            $finAgenda = DateTimeImmutable::createFromFormat('!H:i:s', (string) $agenda['hora_fin'], $zonaHoraria)
                ?: DateTimeImmutable::createFromFormat('!H:i', (string) $agenda['hora_fin'], $zonaHoraria);
            if (!$inicioAgenda || !$finAgenda) {
                continue;
            }
            $inicioAgendaMinuto = ((int) $inicioAgenda->format('H') * 60) + (int) $inicioAgenda->format('i');
            $finAgendaMinuto = ((int) $finAgenda->format('H') * 60) + (int) $finAgenda->format('i');
            if ($inicioMinuto < $finAgendaMinuto && $finMinuto > $inicioAgendaMinuto) {
                $responder(409, ['ok' => false, 'error' => 'dentro_horario_base']);
            }
        }
    }

    if ($accion === 'bloquear_franja') {
        $stmtAgendas = $conexion->prepare(
            'SELECT dia_semana, hora_inicio, hora_fin, duracion_turno
             FROM agendas
             WHERE profesional_id = :profesional_id'
        );
        $stmtAgendas->execute([':profesional_id' => $profesionalId]);
        $esFranjaValida = false;
        $numeroDiaSolicitado = (int) $fechaValida->format('N');
        foreach ($stmtAgendas->fetchAll(PDO::FETCH_ASSOC) as $agenda) {
            $diaAgenda = $normalizarDia($agenda['dia_semana']);
            $inicioAgenda = DateTimeImmutable::createFromFormat('!H:i:s', (string) $agenda['hora_inicio'], $zonaHoraria)
                ?: DateTimeImmutable::createFromFormat('!H:i', (string) $agenda['hora_inicio'], $zonaHoraria);
            $finAgenda = DateTimeImmutable::createFromFormat('!H:i:s', (string) $agenda['hora_fin'], $zonaHoraria)
                ?: DateTimeImmutable::createFromFormat('!H:i', (string) $agenda['hora_fin'], $zonaHoraria);
            $duracion = (int) $agenda['duracion_turno'];
            if ($diaAgenda !== $numeroDiaSolicitado || !$inicioAgenda || !$finAgenda || $duracion < 1 || $duracion > 480) {
                continue;
            }
            $inicioAgendaMinuto = ((int) $inicioAgenda->format('H') * 60) + (int) $inicioAgenda->format('i');
            $finAgendaMinuto = ((int) $finAgenda->format('H') * 60) + (int) $finAgenda->format('i');
            if (
                $inicioMinuto >= $inicioAgendaMinuto
                && $finMinuto <= $finAgendaMinuto
                && $finMinuto - $inicioMinuto === $duracion
                && ($inicioMinuto - $inicioAgendaMinuto) % $duracion === 0
            ) {
                $esFranjaValida = true;
                break;
            }
        }
        if (!$esFranjaValida) {
            $stmtExtra = $conexion->prepare(
                "SELECT 1
                 FROM excepciones
                 WHERE profesional_id = :profesional_id
                   AND tipo = 'extra'
                   AND fecha_hora_inicio = :fecha_inicio
                   AND fecha_hora_fin = :fecha_fin
                 LIMIT 1"
            );
            $stmtExtra->execute([
                ':profesional_id' => $profesionalId,
                ':fecha_inicio' => $inicioFechaHora,
                ':fecha_fin' => $finFechaHora
            ]);
            $esFranjaValida = (bool) $stmtExtra->fetchColumn();
        }
        if (!$esFranjaValida) {
            $responder(400, ['ok' => false, 'error' => 'datos_invalidos']);
        }
    }

    $conexion->beginTransaction();
    $stmtSuperposicion = $conexion->prepare(
        'SELECT id, tipo, fecha_hora_inicio, fecha_hora_fin
         FROM excepciones
         WHERE profesional_id = :profesional_id
           AND fecha_hora_inicio < :fecha_fin
           AND fecha_hora_fin > :fecha_inicio
           AND (:accion <> \'bloquear_dia\' OR (tipo = \'bloqueo\' AND fecha_hora_inicio <= :inicio_dia AND fecha_hora_fin >= :fin_dia))
         LIMIT 1
         FOR UPDATE'
    );
    $stmtSuperposicion->execute([
        ':profesional_id' => $profesionalId,
        ':accion' => $accion,
        ':fecha_inicio' => $inicioFechaHora,
        ':fecha_fin' => $finFechaHora,
        ':inicio_dia' => $inicioDia,
        ':fin_dia' => $finDia
    ]);
    $excepcionesSuperpuestas = $stmtSuperposicion->fetchAll(PDO::FETCH_ASSOC);
    $extraABloquear = null;
    if (
        $accion === 'bloquear_franja'
        && count($excepcionesSuperpuestas) === 1
        && $excepcionesSuperpuestas[0]['tipo'] === 'extra'
        && $excepcionesSuperpuestas[0]['fecha_hora_inicio'] === $inicioFechaHora
        && $excepcionesSuperpuestas[0]['fecha_hora_fin'] === $finFechaHora
    ) {
        $extraABloquear = $excepcionesSuperpuestas[0]['id'];
    } elseif ($excepcionesSuperpuestas) {
        $conexion->rollBack();
        $responder(409, ['ok' => false, 'error' => 'superpuesto']);
    }

    $stmtOcupado = $conexion->prepare(
        "SELECT id
         FROM turnos
         WHERE profesional_id = :profesional_id
           AND fecha_hora >= :fecha_inicio
           AND fecha_hora < :fecha_fin
           AND estado IN ('pendiente', 'confirmado')
         LIMIT 1
         FOR UPDATE"
    );
    $stmtOcupado->execute([
        ':profesional_id' => $profesionalId,
        ':fecha_inicio' => $inicioFechaHora,
        ':fecha_fin' => $finFechaHora
    ]);
    $turnoOcupado = $stmtOcupado->fetchColumn();

    if ($accion !== 'bloquear_dia' && $turnoOcupado) {
        $conexion->rollBack();
        $responder(409, ['ok' => false, 'error' => 'ocupado']);
    }

    if ($extraABloquear !== null) {
        $stmtBloquearExtra = $conexion->prepare(
            "INSERT INTO excepciones
                (profesional_id, tipo, fecha_hora_inicio, fecha_hora_fin, motivo)
             VALUES
                (:profesional_id, 'bloqueo', :fecha_hora_inicio, :fecha_hora_fin, :motivo)"
        );
        $stmtBloquearExtra->execute([
            ':profesional_id' => $profesionalId,
            ':fecha_hora_inicio' => $inicioFechaHora,
            ':fecha_hora_fin' => $finFechaHora,
            ':motivo' => $motivo
        ]);
        $excepcionId = (int) $conexion->lastInsertId();
    } else {
        $stmtInsertar = $conexion->prepare(
            'INSERT INTO excepciones
                (profesional_id, tipo, fecha_hora_inicio, fecha_hora_fin, motivo)
             VALUES
                (:profesional_id, :tipo, :fecha_hora_inicio, :fecha_hora_fin, :motivo)'
        );
        $stmtInsertar->execute([
            ':profesional_id' => $profesionalId,
            ':tipo' => $accion === 'crear_extra' ? 'extra' : 'bloqueo',
            ':fecha_hora_inicio' => $inicioFechaHora,
            ':fecha_hora_fin' => $finFechaHora,
            ':motivo' => $motivo
        ]);
        $excepcionId = (int) $conexion->lastInsertId();
    }

    if ($accion === 'bloquear_dia') {
        $stmtCancelar = $conexion->prepare(
            "UPDATE turnos
             SET estado = 'cancelado'
             WHERE profesional_id = :profesional_id
               AND fecha_hora >= :inicio
               AND fecha_hora <= :fin
               AND estado IN ('pendiente', 'confirmado')"
        );
        $stmtCancelar->execute([
            ':profesional_id' => $profesionalId,
            ':inicio' => $inicioFechaHora,
            ':fin' => $finFechaHora
        ]);
    }

    if ($accion === 'crear_extra' && ($_POST['estado'] ?? 'disponible') === 'asignado') {
        $dniRecibido = $_POST['dni_paciente'] ?? null;
        $dni = is_string($dniRecibido) ? preg_replace('/\D+/', '', $dniRecibido) : '';
        $nombreRecibido = $_POST['nombre_paciente'] ?? null;
        $nombre = is_string($nombreRecibido) ? trim($nombreRecibido) : '';
        if ($dni === '' || $nombre === '') {
            $conexion->rollBack();
            $responder(400, ['ok' => false, 'error' => 'paciente_invalido']);
        }

        $stmtPaciente = $conexion->prepare(
            "SELECT p.id
             FROM pacientes p
             JOIN usuarios u ON u.id = p.id AND u.rol = 'paciente'
             WHERE REPLACE(REPLACE(REPLACE(u.dni, '.', ''), '-', ''), ' ', '') = :dni
               AND LOWER(CONCAT(TRIM(u.nombre), ' ', TRIM(u.apellido))) = LOWER(:nombre)
             LIMIT 1"
        );
        $stmtPaciente->execute([':dni' => $dni, ':nombre' => $nombre]);
        $pacienteId = $stmtPaciente->fetchColumn();
        if (!$pacienteId) {
            $conexion->rollBack();
            $responder(400, ['ok' => false, 'error' => 'paciente_invalido']);
        }

        $stmtTurnoExtra = $conexion->prepare(
            "INSERT INTO turnos (paciente_id, profesional_id, fecha_hora, estado, creado_por)
             VALUES (:paciente_id, :profesional_id, :fecha_hora, 'pendiente', 'secretario')"
        );
        $stmtTurnoExtra->execute([
            ':paciente_id' => $pacienteId,
            ':profesional_id' => $profesionalId,
            ':fecha_hora' => $inicioFechaHora
        ]);
    }

    $conexion->commit();
    $responder(200, ['ok' => true, 'excepcion_id' => $excepcionId]);
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    if ($bloqueoTurnoAdquirido && is_string($nombreBloqueoTurno)) {
        $conexion->prepare('SELECT RELEASE_LOCK(:nombre)')->execute([':nombre' => $nombreBloqueoTurno]);
    }
    error_log('Error al procesar excepción de agenda: ' . $e->getMessage());
    $responder(500, ['ok' => false, 'error' => 'servidor']);
}
