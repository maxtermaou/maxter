<?php
require_once 'conexion.php';

echo "<div style='font-family: Arial; padding: 20px; background: #fff; color: #333;'>";
echo "<h2 style='color: #dc3545;'>🔍 DIAGNÓSTICO DEL SISTEMA</h2>";

// 1. Verificamos la hora de PHP
$fecha_php = date('Y-m-d H:i:s');
$hoy_php = date('Y-m-d');
echo "<p>1. Fecha y Hora según PHP: <b>$fecha_php</b></p>";

// 2. Verificamos la hora de MySQL
$q_mysql = $conexion->query("SELECT NOW() as ahora, CURDATE() as hoy");
$res_mysql = $q_mysql->fetch(PDO::FETCH_ASSOC);
echo "<p>2. Fecha y Hora según MySQL: <b>{$res_mysql['ahora']}</b></p>";
echo "<hr>";

// 3. Buscamos TODOS los registros de asistencia de forma cruda para el día de hoy
echo "<h3>Buscando asistencias para la fecha: <span style='color:blue;'>$hoy_php</span></h3>";

$sql_crudo = "SELECT * FROM registros_asistencia WHERE DATE(fecha_hora) = '$hoy_php'";
$q_raw = $conexion->query($sql_crudo);
$registros = $q_raw->fetchAll(PDO::FETCH_ASSOC);

echo "<p>Registros físicos encontrados en la tabla hoy: <b>" . count($registros) . "</b></p>";

if (count($registros) > 0) {
    echo "<b>Detalle de los registros:</b><br>";
    echo "<pre style='background: #eee; padding: 10px; border-radius: 5px;'>";
    print_r($registros);
    echo "</pre>";
} else {
    echo "<p style='color: red;'>❌ No hay NINGÚN registro en la tabla 'registros_asistencia' que tenga la fecha $hoy_php.</p>";
}

echo "<hr>";
// 4. Verificamos si el empleado de esos registros realmente existe
if (count($registros) > 0) {
    $anviz_prueba = $registros[0]['anviz_id'];
    $q_emp = $conexion->query("SELECT * FROM empleados WHERE anviz_id = '$anviz_prueba'");
    $emp = $q_emp->fetch(PDO::FETCH_ASSOC);
    if ($emp) {
        echo "<p style='color: green;'>✅ El empleado con anviz_id $anviz_prueba SÍ existe en la tabla empleados (Cédula: {$emp['cedula']}).</p>";
    } else {
        echo "<p style='color: red;'>❌ ERROR GRAVE: Hay un registro con anviz_id $anviz_prueba, pero ESE EMPLEADO NO EXISTE en la tabla 'empleados'. Por eso el Dashboard marca 0 (porque usamos un JOIN).</p>";
    }
}
echo "</div>";
?>