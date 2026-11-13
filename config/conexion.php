<?php
$host = 'localhost';
$dbname = 'sistema_turnos';
$username = 'root'; // Usuario por defecto en XAMPP
$password = '';     // Contraseña por defecto

try {
    // Creo la conexión usando PDO
    $conexion = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password, [
        // lanza excepciones si algo falla
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        // consultas preparadas reales en el servidor MySQL: los datos viajan
        // siempre separados del SQL, así no se puede inyectar código
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

} catch (PDOException $e) {
    // guardo el detalle en el log del servidor y no se lo muestro al usuario
    // (el mensaje original puede revelar usuario, host o estructura de la BD)
    error_log("Error de conexión a la base de datos: " . $e->getMessage());
    http_response_code(500);
    die("No se pudo conectar con la base de datos. Intentá de nuevo más tarde.");
}
?>
