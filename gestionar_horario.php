<?php
require_once 'seguridad.php';
require_once 'conexion.php';

// Verificamos permisos y núcleo
$q_user = $conexion->prepare("SELECT rol, id_nucleo FROM usuarios WHERE usuario = :user");
$q_user->execute([':user' => $_SESSION['usuario']]);
$datos_usuario = $q_user->fetch(PDO::FETCH_ASSOC);

$mi_rol = $datos_usuario['rol'] ?? 'Admin_Sede';
$mi_nucleo = $datos_usuario['id_nucleo'];

// Verificar empleado
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id_empleado = (int)$_GET['id'];
$mensaje = "";
$tipo_mensaje = "";

// Seguridad: Que el empleado sea de su núcleo (Si no es SuperAdmin)
$filtro = ($mi_rol !== 'SuperAdmin') ? " AND id_nucleo = $mi_nucleo" : "";
$q_emp = $conexion->query("SELECT cedula, nombres, apellidos, modalidad FROM empleados WHERE id_empleado = $id_empleado $filtro");
$empleado = $q_emp->fetch(PDO::FETCH_ASSOC);

if (!$empleado) {
    header("Location: index.php");
    exit();
}

// 1. ELIMINAR BLOQUE DE HORARIO
if (isset($_GET['eliminar_horario'])) {
    $id_eliminar = (int)$_GET['eliminar_horario'];
    $conexion->query("DELETE FROM horarios_empleados WHERE id_horario = $id_eliminar AND id_empleado = $id_empleado");
    header("Location: gestionar_horario.php?id=$id_empleado&msg=eliminado");
    exit();
}

if (isset($_GET['msg']) && $_GET['msg'] == 'eliminado') {
    $mensaje = "✅ Bloque de horario eliminado correctamente.";
    $tipo_mensaje = "exito";
}

// 2. GUARDAR NUEVO BLOQUE DE HORARIO (PRG)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_horario'])) {
    $dia = $_POST['dia_semana'];
    $entrada = $_POST['hora_entrada'];
    $salida = $_POST['hora_salida'];

    if ($entrada >= $salida) {
        $mensaje = "❌ Error: La hora de entrada no puede ser mayor o igual a la salida.";
        $tipo_mensaje = "error";
    } else {
        $sql_in = "INSERT INTO horarios_empleados (id_empleado, dia_semana, hora_entrada, hora_salida) VALUES (:id_emp, :dia, :ent, :sal)";
        $stmt = $conexion->prepare($sql_in);
        if ($stmt->execute([':id_emp' => $id_empleado, ':dia' => $dia, ':ent' => $entrada, ':sal' => $salida])) {
            $mensaje = "✅ Bloque de horario asignado con éxito.";
            $tipo_mensaje = "exito";
        } else {
            $mensaje = "❌ Error al guardar en la base de datos.";
            $tipo_mensaje = "error";
        }
    }
}

// Obtener los horarios actuales de este empleado ordenados lógicamente
$dias_orden = "FIELD(dia_semana, 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo')";
$q_horarios = $conexion->query("SELECT * FROM horarios_empleados WHERE id_empleado = $id_empleado ORDER BY $dias_orden, hora_entrada ASC");
$horarios = $q_horarios->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIBIA - Horario Dinámico</title>
    <style>
        /* CSS BASE (Simplificado para el ejemplo, usa el mismo de SIBIA) */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
        body { background-color: #f5f5f5; color: #333; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        
        .header-perfil { background: #f0f8ff; padding: 15px; border-left: 5px solid #4a90d9; margin-bottom: 25px; border-radius: 4px; display: flex; justify-content: space-between; align-items: center;}
        .header-perfil h2 { color: #1e5799; font-size: 18px; }
        .badge-modalidad { background: #e67e22; color: white; padding: 5px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 15px; align-items: end; background: #fafafa; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 25px; }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group label { font-size: 12px; font-weight: bold; }
        .form-input { padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; }
        
        .btn { padding: 10px 15px; border: none; border-radius: 4px; color: white; font-weight: bold; cursor: pointer; text-decoration: none; font-size: 13px; transition: 0.3s;}
        .btn-add { background: #2ecc71; height: 35px; }
        .btn-add:hover { background: #27ae60; }
        .btn-back { background: #6c757d; }
        .btn-del { background: #e74c3c; padding: 5px 8px; font-size: 11px; }

        .table-horario { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 20px; }
        .table-horario th { background: #1e5799; color: white; padding: 10px; text-align: left; }
        .table-horario td { padding: 10px; border-bottom: 1px solid #eee; }
        .table-horario tr:hover { background-color: #f9f9f9; }

        .alert { padding: 12px; border-radius: 4px; margin-bottom: 20px; font-size: 13px; font-weight: bold; text-align: center; }
        .alert-exito { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <div style="margin-bottom: 20px;">
            <a href="index.php" class="btn btn-back">← Volver al Listado de Personal</a>
        </div>

        <div class="header-perfil">
            <div>
                <h2>⏱️ Configuración de Horario Analítico</h2>
                <p style="font-size: 13px; color: #666; margin-top: 5px;">
                    <strong>Empleado:</strong> <?= htmlspecialchars($empleado['cedula'] . " - " . $empleado['nombres'] . " " . $empleado['apellidos']) ?>
                </p>
            </div>
            <div>
                <span class="badge-modalidad">Modalidad: <?= htmlspecialchars($empleado['modalidad'] ?? 'No definida') ?></span>
            </div>
        </div>

        <?php if($mensaje != ""): ?>
            <div class="alert alert-<?= $tipo_mensaje ?>"><?= $mensaje ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="guardar_horario" value="1">
            <div class="form-grid">
                <div class="form-group">
                    <label>Día de la Semana:</label>
                    <select name="dia_semana" class="form-input" required>
                        <option value="Lunes">Lunes</option>
                        <option value="Martes">Martes</option>
                        <option value="Miércoles">Miércoles</option>
                        <option value="Jueves">Jueves</option>
                        <option value="Viernes">Viernes</option>
                        <option value="Sábado">Sábado</option>
                        <option value="Domingo">Domingo</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Hora de Entrada:</label>
                    <input type="time" name="hora_entrada" class="form-input" required>
                </div>
                <div class="form-group">
                    <label>Hora de Salida:</label>
                    <input type="time" name="hora_salida" class="form-input" required>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-add">➕ Agregar Bloque</button>
                </div>
            </div>
        </form>

        <h3 style="font-size: 15px; margin-bottom: 10px; color: #333;">Bloques de Horario Asignados</h3>
        <table class="table-horario">
            <thead>
                <tr>
                    <th>Día</th>
                    <th>Entrada</th>
                    <th>Salida</th>
                    <th>Total Horas</th>
                    <th style="text-align: center;">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($horarios) > 0): ?>
                    <?php foreach($horarios as $h): 
                        // Calcular diferencia de horas
                        $t1 = strtotime($h['hora_entrada']);
                        $t2 = strtotime($h['hora_salida']);
                        $diff = round(abs($t2 - $t1) / 3600, 2);
                    ?>
                    <tr>
                        <td><strong><?= $h['dia_semana'] ?></strong></td>
                        <td><?= date("h:i A", strtotime($h['hora_entrada'])) ?></td>
                        <td><?= date("h:i A", strtotime($h['hora_salida'])) ?></td>
                        <td><?= $diff ?> Hrs</td>
                        <td style="text-align: center;">
                            <a href="?id=<?= $id_empleado ?>&eliminar_horario=<?= $h['id_horario'] ?>" class="btn btn-del" onclick="return confirm('¿Quitar este bloque de horario?');">🗑️ Quitar</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 20px;">No hay horarios asignados. La IA no podrá analizar la asistencia de este empleado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>