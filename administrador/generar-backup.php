<?php

date_default_timezone_set('America/Argentina/Buenos_Aires');

session_start();

// valido que el usuario sea administrador
if (
    empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'administrador'
) {
    header("Location: ../index.html?error=sesion_requerida");
    exit();
}

require_once __DIR__ . '/../config/conexion.php';

try {
    // obtengo todas las tablas de la bd
    $tablas = [];
    $stmt = $conexion->query("SHOW TABLES");
    while ($fila = $stmt->fetch(PDO::FETCH_NUM)) {
        $tablas[] = $fila[0];
    }

    $contenidoSql = "-- ==========================================\n";
    $contenidoSql .= "-- Respaldo de la Base de Datos: Pausa\n";
    $contenidoSql .= "-- Fecha y Hora: " . date('Y-m-d H:i:s') . "\n";
    $contenidoSql .= "-- Generado por: Administrador ID " . $_SESSION['usuario_id'] . "\n";
    $contenidoSql .= "-- ==========================================\n\n";
    
    $contenidoSql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    // recorro tabla por tabla exportando estructura y registros
    foreach ($tablas as $tabla) {
        // estructura de la tabla (CREATE TABLE)
        $stmtCreacion = $conexion->query("SHOW CREATE TABLE `$tabla`");
        $rowCreacion = $stmtCreacion->fetch(PDO::FETCH_NUM);
        $contenidoSql .= "DROP TABLE IF EXISTS `$tabla`;\n";
        $contenidoSql .= $rowCreacion[1] . ";\n\n";

        // datos de la tabla (INSERT INTO)
        $stmtDatos = $conexion->query("SELECT * FROM `$tabla`");
        $filasDatos = $stmtDatos->fetchAll(PDO::FETCH_ASSOC);

        if (count($filasDatos) > 0) {
            foreach ($filasDatos as $fila) {
                $columnas = array_keys($fila);
                $valores = array_values($fila);

                // escapar valores de manera segura mediante PDO
                $valoresEscapados = array_map([$conexion, 'quote'], $valores);

                $contenidoSql .= "INSERT INTO `$tabla` (`" . implode("`, `", $columnas) . "`) VALUES (" . implode(", ", $valoresEscapados) . ");\n";
            }
            $contenidoSql .= "\n";
        }
    }

    $contenidoSql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    //defino el nombre del archivo descargable
    $nombreArchivo = 'backup_pausa_' . date('Y-m-d_H-i-s') . '.sql';

    // fuerzo la descarga directa en el navegador del usuario
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('Content-Length: ' . strlen($contenidoSql));
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Expires: 0');

    echo $contenidoSql;
    exit();

} catch (Throwable $e) {
    error_log("Error crítico al generar el respaldo de la base de datos: " . $e->getMessage());
    header("Location: administrador.php?error=backup_fallido");
    exit();
}
?>