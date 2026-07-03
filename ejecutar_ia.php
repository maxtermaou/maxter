<?php
require_once 'seguridad.php'; 
// ejecutar_ia.php

// 1. Obtenemos la ruta exacta de tu carpeta (sin importar dónde esté instalado XAMPP)
$ruta_proyecto = dirname(__FILE__);

// 2. Le decimos a Windows: "Ve a esta carpeta EXACTA, y LUEGO ejecuta el motor"
$comando = 'cd /d "' . $ruta_proyecto . '" && motor_ia.bat 2>&1';

// 3. Ejecutamos el comando y capturamos TODO lo que la consola responda
$salida_consola = shell_exec($comando);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ejecutando IA...</title>
    <style>
        body { font-family: sans-serif; background: #f0f2f5; padding: 20px; }
        .consola { background-color: #1e1e1e; color: #00ff00; padding: 20px; border-radius: 8px; font-family: monospace; font-size: 14px; overflow-x: auto; box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
        .btn-volver { display: inline-block; margin-top: 20px; background-color: #6f42c1; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold; }
    </style>
</head>
<body>
    <h2>🤖 Ejecutando Motor de Inteligencia Artificial...</h2>
    <p>Esto es lo que el servidor está procesando por debajo de la mesa:</p>
    
    <div class="consola">
        <pre><?= htmlspecialchars($salida_consola) ?></pre>
    </div>

    <a href="dashboard.php?analisis=exito" class="btn-volver">Continuar al Dashboard ➔</a>
</body>
</html>