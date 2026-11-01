<?php
$host = 'localhost';
$dbname = 'sistema_turnos';
$username = 'root'; // Usuario por defecto en XAMPP
$password = '';     // Contraseña por defecto

try {
    // Creo la conexión usando PDO
    $conexion = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    
    // Configo el manejo de errores para que lance excepciones si algo falla
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch (PDOException $e) {
    // Si hay un error en la conexión, se muestra aca
    die("Error de conexión: " . $e->getMessage());
}
?>