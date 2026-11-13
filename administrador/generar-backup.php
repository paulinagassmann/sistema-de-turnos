<?php

date_default_timezone_set('America/Argentina/Buenos_Aires');

session_start();
require_once __DIR__ . '/../config/csrf.php';

// valido que el usuario sea administrador
if (
    empty($_SESSION['usuario_id'])
    || !isset($_SESSION['rol'])
    || $_SESSION['rol'] !== 'administrador'
) {
    header("Location: ../index.html?error=sesion_requerida");
    exit();
}

// el backup solo se genera desde el botón del panel (POST), no abriendo la URL
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_es_valido()) {
    header("Location: administrador.php");
    exit();
}

require_once __DIR__ . '/../config/conexion.php';

try {
    // obtengo todas las tablas existentes en la bd
    $tablasBd = [];
    $stmt = $conexion->query("SHOW TABLES");
    while ($fila = $stmt->fetch(PDO::FETCH_NUM)) {
        $tablasBd[] = $fila[0];
    }

    // leo de la propia bd qué tabla depende de cuál (claves foráneas)
    // ej: pacientes depende de usuarios, turnos depende de pacientes y profesionales
    $dependencias = array_fill_keys($tablasBd, []);
    $stmtFk = $conexion->query(
        "SELECT TABLE_NAME, REFERENCED_TABLE_NAME
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE()
           AND REFERENCED_TABLE_SCHEMA = DATABASE()
           AND REFERENCED_TABLE_NAME IS NOT NULL"
    );
    while ($fk = $stmtFk->fetch(PDO::FETCH_ASSOC)) {
        if ($fk['TABLE_NAME'] !== $fk['REFERENCED_TABLE_NAME'] && isset($dependencias[$fk['TABLE_NAME']])) {
            $dependencias[$fk['TABLE_NAME']][] = $fk['REFERENCED_TABLE_NAME'];
        }
    }

    // ordeno las tablas de padres a hijas: una tabla va recién cuando ya están todas las que referencia
    // (usuarios -> pacientes/profesionales -> agendas/excepciones/turnos)
    $tablas = [];
    $pendientes = $tablasBd;
    while (!empty($pendientes)) {
        $avance = false;
        foreach ($pendientes as $i => $tabla) {
            if (empty(array_diff($dependencias[$tabla], $tablas))) {
                $tablas[] = $tabla;
                unset($pendientes[$i]);
                $avance = true;
            }
        }
        if (!$avance) {
            // dependencia circular: agrego las que quedan tal cual (FOREIGN_KEY_CHECKS = 0 las cubre)
            $tablas = array_merge($tablas, array_values($pendientes));
            break;
        }
    }

    $contenidoSql = "-- ==========================================\n";
    $contenidoSql .= "-- Respaldo de la Base de Datos: Pausa\n";
    $contenidoSql .= "-- Fecha y Hora: " . date('Y-m-d H:i:s') . "\n";
    $contenidoSql .= "-- Generado por: Administrador ID " . $_SESSION['usuario_id'] . "\n";
    $contenidoSql .= "-- ==========================================\n\n";

    $contenidoSql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    // primero borro todas las tablas de hijas a padres (turnos ... usuarios).
    // así el DROP de usuarios nunca falla por la clave foránea (#1451), aunque el programa
    // que importa el archivo (ej: phpMyAdmin) tenga la revisión de claves foráneas activada
    foreach (array_reverse($tablas) as $tabla) {
        $contenidoSql .= "DROP TABLE IF EXISTS `$tabla`;\n";
    }
    $contenidoSql .= "\n";

    // después creo y cargo las tablas de padres a hijas
    foreach ($tablas as $tabla) {
        // estructura de la tabla (CREATE TABLE)
        $stmtCreacion = $conexion->query("SHOW CREATE TABLE `$tabla`");
        $rowCreacion = $stmtCreacion->fetch(PDO::FETCH_NUM);
        $contenidoSql .= $rowCreacion[1] . ";\n\n";

        // datos de la tabla (INSERT INTO)
        $stmtDatos = $conexion->query("SELECT * FROM `$tabla`");
        $filasDatos = $stmtDatos->fetchAll(PDO::FETCH_ASSOC);

        if (count($filasDatos) > 0) {
            foreach ($filasDatos as $fila) {
                $columnas = array_keys($fila);
                $valores = array_values($fila);

                // escapar valores de manera segura mediante PDO
                // (los NULL se exportan como NULL; si no, quote() los convertiría en '')
                $valoresEscapados = array_map(function ($valor) use ($conexion) {
                    return $valor === null ? 'NULL' : $conexion->quote($valor);
                }, $valores);

                $contenidoSql .= "INSERT INTO `$tabla` (`" . implode("`, `", $columnas) . "`) VALUES (" . implode(", ", $valoresEscapados) . ");\n";
            }
            $contenidoSql .= "\n";
        }
    }

    $contenidoSql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    // defino el nombre del archivo descargable
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