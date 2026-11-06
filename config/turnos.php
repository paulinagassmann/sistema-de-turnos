<?php

function actualizarTurnosPendientesVencidos(PDO $conexion): int
{
    $zonaHoraria = new DateTimeZone('America/Argentina/Buenos_Aires');
    $ahora = new DateTimeImmutable('now', $zonaHoraria);
    $stmt = $conexion->prepare(
        "UPDATE turnos t
         SET t.estado = 'ausente'
         WHERE t.estado = 'pendiente'
           AND t.fecha_hora <= :ahora_inicio
           AND EXISTS (
               SELECT 1
               FROM agendas a
               WHERE a.profesional_id = t.profesional_id
                 AND a.duracion_turno BETWEEN 1 AND 480
                 AND TIME(t.fecha_hora) >= a.hora_inicio
                 AND TIME(t.fecha_hora) < a.hora_fin
                 AND CASE LOWER(TRIM(a.dia_semana))
                     WHEN 'lunes' THEN 0
                     WHEN 'lun' THEN 0
                     WHEN 'monday' THEN 0
                     WHEN '1' THEN 0
                     WHEN 'martes' THEN 1
                     WHEN 'mar' THEN 1
                     WHEN 'tuesday' THEN 1
                     WHEN '2' THEN 1
                     WHEN 'miércoles' THEN 2
                     WHEN 'miercoles' THEN 2
                     WHEN 'mie' THEN 2
                     WHEN 'wednesday' THEN 2
                     WHEN '3' THEN 2
                     WHEN 'jueves' THEN 3
                     WHEN 'jue' THEN 3
                     WHEN 'thursday' THEN 3
                     WHEN '4' THEN 3
                     WHEN 'viernes' THEN 4
                     WHEN 'vie' THEN 4
                     WHEN 'friday' THEN 4
                     WHEN '5' THEN 4
                     WHEN 'sábado' THEN 5
                     WHEN 'sabado' THEN 5
                     WHEN 'sab' THEN 5
                     WHEN 'saturday' THEN 5
                     WHEN '6' THEN 5
                     WHEN 'domingo' THEN 6
                     WHEN 'dom' THEN 6
                     WHEN 'sunday' THEN 6
                     WHEN '7' THEN 6
                 END = WEEKDAY(t.fecha_hora)
                 AND TIMESTAMPADD(MINUTE, a.duracion_turno, t.fecha_hora) <= :ahora_fin
           )"
    );
    $ahoraFormateado = $ahora->format('Y-m-d H:i:s');
    $stmt->execute([
        ':ahora_inicio' => $ahoraFormateado,
        ':ahora_fin' => $ahoraFormateado
    ]);

    return $stmt->rowCount();
}
