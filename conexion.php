<?php
// 1. Sincronizamos el reloj de PHP con la hora de Venezuela
date_default_timezone_set('America/Caracas');

// Configuración de la base de datos
$host = 'localhost';
$dbname = 'unefa_rrhh';
$username = 'root'; // Usuario por defecto en XAMPP
$password = ''; // Contraseña por defecto en XAMPP (suele estar vacía)

try {
    // Se establece la conexión con PDO
    $conexion = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    
    // Configurar PDO para que reporte cualquier error de manera estricta (muy útil para depurar)
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 2. MAGIA: Sincronizamos el reloj interno de MySQL con Venezuela
    $conexion->exec("SET time_zone = '-04:00'");
    
} catch(PDOException $e) {
    // Si la conexión falla, el sistema se detiene y muestra el error exacto
    die("Error al conectar con la base de datos: " . $e->getMessage());
}
?>