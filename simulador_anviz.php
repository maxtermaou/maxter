<?php
require_once 'seguridad.php';
require_once 'conexion.php';

// Verificación extra de seguridad (opcional, por si quieres que solo tu usuario entre)
// if ($_SESSION['usuario'] !== 'admin') { die("Acceso denegado."); }

$mensaje = "";
$tipo_mensaje = "";

// ==========================================
// PROCESAR LA HUELLA FANTASMA
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $anviz_id = $_POST['anviz_id'];
    $fecha = $_POST['fecha'];
    $hora = $_POST['hora'];
    $tipo_marca = $_POST['tipo_marca'];

    // Unimos la fecha y la hora para el formato de la base de datos
    $fecha_hora = $fecha . ' ' . $hora;

    try {
        $stmt_insert = $conexion->prepare("INSERT INTO registros_asistencia (anviz_id, fecha_hora, tipo_marca) VALUES (:anv, :fh, :tipo)");
        $exito = $stmt_insert->execute([
            ':anv' => $anviz_id, 
            ':fh' => $fecha_hora, 
            ':tipo' => $tipo_marca
        ]);

        if ($exito) {
            $mensaje = "✅ ¡BIP! Marcaje simulado con éxito a las " . date("h:i A", strtotime($hora)) . ". La base de datos lo registró como real.";
            $tipo_mensaje = "exito";
        }
    } catch(PDOException $e) {
        $mensaje = "❌ Error al simular la huella: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// Obtener lista de empleados para facilitar la simulación
$q_emp = $conexion->query("SELECT anviz_id, cedula, nombres, apellidos FROM empleados WHERE estado = 'Activo' ORDER BY nombres ASC");
$empleados = $q_emp->fetchAll(PDO::FETCH_ASSOC);

// Fecha actual por defecto para el formulario
$fecha_hoy = date('Y-m-d');
$hora_actual = date('H:i');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIBIA - DEV TOOLS (Simulador)</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Courier New', Courier, monospace; }
        body { background-color: #1a1a1a; color: #f5f5f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        
        .simulator-container { background: #2d2d2d; width: 100%; max-width: 600px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border: 1px solid #444; overflow: hidden; }
        
        .sim-header { background: #000; padding: 20px; text-align: center; border-bottom: 3px solid #2ecc71; }
        .sim-header h1 { color: #2ecc71; font-size: 22px; text-transform: uppercase; letter-spacing: 2px;}
        .sim-header p { color: #888; font-size: 12px; margin-top: 5px; }

        .sim-body { padding: 30px; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; color: #aaa; font-size: 13px; margin-bottom: 8px; font-weight: bold; }
        
        .form-input, .form-select { width: 100%; padding: 12px; background: #1a1a1a; border: 1px solid #444; color: #2ecc71; border-radius: 5px; font-size: 15px; outline: none; transition: 0.3s; }
        .form-input:focus, .form-select:focus { border-color: #2ecc71; box-shadow: 0 0 10px rgba(46, 204, 113, 0.2); }
        
        .btn-simular { background: #2ecc71; color: #000; width: 100%; padding: 15px; border: none; border-radius: 5px; font-size: 16px; font-weight: bold; text-transform: uppercase; cursor: pointer; transition: 0.3s; letter-spacing: 1px;}
        .btn-simular:hover { background: #27ae60; box-shadow: 0 0 15px rgba(46, 204, 113, 0.4); transform: translateY(-2px); }

        .btn-volver { display: block; text-align: center; margin-top: 20px; color: #888; text-decoration: none; font-size: 12px; transition: 0.3s; }
        .btn-volver:hover { color: #fff; }

        .alert { padding: 15px; border-radius: 5px; margin-bottom: 25px; text-align: center; font-weight: bold; font-size: 14px;}
        .alert-exito { background: rgba(46, 204, 113, 0.1); border: 1px solid #2ecc71; color: #2ecc71; }
        .alert-error { background: rgba(231, 76, 60, 0.1); border: 1px solid #e74c3c; color: #e74c3c; }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    </style>
</head>
<body>

    <div class="simulator-container">
        <div class="sim-header">
            <h1>⚙️ Simulador Anviz Biométrico</h1>
            <p>MODO DESARROLLADOR / TESIS</p>
        </div>

        <div class="sim-body">
            <?php if($mensaje != ""): ?>
                <div class="alert alert-<?= $tipo_mensaje ?>"><?= $mensaje ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                
                <div class="form-group">
                    <label>▶ Seleccione el Empleado / Docente:</label>
                    <select name="anviz_id" class="form-select" required>
                        <option value="" disabled selected>-- Buscar en la base de datos --</option>
                        <?php foreach($empleados as $emp): ?>
                            <option value="<?= $emp['anviz_id'] ?>">
                                [ID: <?= $emp['anviz_id'] ?>] - <?= htmlspecialchars($emp['nombres'] . ' ' . $emp['apellidos']) ?> (C.I. <?= $emp['cedula'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>▶ Fecha del Marcaje:</label>
                        <input type="date" name="fecha" class="form-input" value="<?= $fecha_hoy ?>" required>
                    </div>

                    <div class="form-group">
                        <label>▶ Hora Exacta:</label>
                        <input type="time" name="hora" class="form-input" value="<?= $hora_actual ?>" required step="60">
                    </div>
                </div>

                <div class="form-group">
                    <label>▶ Tipo de Registro:</label>
                    <select name="tipo_marca" class="form-select" required>
                        <option value="Entrada">Entrada (Llegada al núcleo)</option>
                        <option value="Salida">Salida (Retiro del núcleo)</option>
                    </select>
                </div>

                <button type="submit" class="btn-simular">👆 Colocar Huella Digital</button>
            </form>

            <a href="dashboard.php" class="btn-volver">← Volver al Dashboard del SIBIA</a>
        </div>
    </div>

</body>
</html>