<?php
require_once 'seguridad.php';
require_once 'conexion.php';

// ==========================================
// 1. CEREBRO MULTI-SEDE Y VARIABLES
// ==========================================
$q_user = $conexion->prepare("SELECT rol, id_nucleo FROM usuarios WHERE usuario = :user");
$q_user->execute([':user' => $_SESSION['usuario']]);
$datos_usuario = $q_user->fetch(PDO::FETCH_ASSOC);

$mi_rol = $datos_usuario['rol'] ?? 'Admin_Sede';
$mi_nucleo = $datos_usuario['id_nucleo'];

$filtro_sede = "";
$parametros_sede = [];
if ($mi_rol !== 'SuperAdmin') {
    $filtro_sede = " WHERE id_nucleo = :id_nucleo";
    $parametros_sede[':id_nucleo'] = $mi_nucleo;
}

$nombre_sede = "VISIÓN NACIONAL (Todas las Sedes)";
if ($mi_rol !== 'SuperAdmin' && $mi_nucleo) {
    $q_sede = $conexion->prepare("SELECT nombre_nucleo FROM nucleos WHERE id_nucleo = :id");
    $q_sede->execute([':id' => $mi_nucleo]);
    $res_sede = $q_sede->fetch(PDO::FETCH_ASSOC);
    if($res_sede) {
        $nombre_sede = strtoupper($res_sede['nombre_nucleo']);
    }
}

// ==========================================
// NUEVO: MOTOR DE ELIMINACIÓN (BORRADO SEGURO)
// ==========================================

// Eliminar Feriado
if (isset($_GET['del_feriado']) && is_numeric($_GET['del_feriado'])) {
    $id_feriado = (int)$_GET['del_feriado'];
    try {
        $conexion->query("DELETE FROM dias_feriados WHERE id_feriado = $id_feriado");
        $_SESSION['mensaje_permiso'] = "✅ Día Feriado eliminado correctamente. La IA volverá a exigir asistencia en esas fechas.";
        $_SESSION['tipo_mensaje_permiso'] = "exito";
    } catch(PDOException $e) {
        $_SESSION['mensaje_permiso'] = "❌ Error al eliminar el feriado: " . $e->getMessage();
        $_SESSION['tipo_mensaje_permiso'] = "error";
    }
    header("Location: registrar_permiso.php");
    exit();
}

// Eliminar Permiso / Retardo Individual
if (isset($_GET['del_permiso']) && is_numeric($_GET['del_permiso'])) {
    $id_permiso = (int)$_GET['del_permiso'];
    try {
        $conexion->query("DELETE FROM justificaciones WHERE id_justificacion = $id_permiso");
        $_SESSION['mensaje_permiso'] = "✅ Justificación eliminada correctamente del historial del empleado.";
        $_SESSION['tipo_mensaje_permiso'] = "exito";
    } catch(PDOException $e) {
        $_SESSION['mensaje_permiso'] = "❌ Error al eliminar la justificación: " . $e->getMessage();
        $_SESSION['tipo_mensaje_permiso'] = "error";
    }
    header("Location: registrar_permiso.php");
    exit();
}

// ==========================================
// 2. PATRÓN PRG PARA PERMISOS INDIVIDUALES Y RETARDOS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_permiso'])) {
    $id_empleado = $_POST['id_empleado'];
    $tipo = $_POST['tipo'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    
    $motivo = "[$tipo] - " . trim($_POST['motivo']);

    if ($fecha_inicio > $fecha_fin) {
        $_SESSION['mensaje_permiso'] = "❌ Error: La fecha de inicio no puede ser mayor a la fecha de fin.";
        $_SESSION['tipo_mensaje_permiso'] = "error";
    } else {
        $sql_insert = "INSERT INTO justificaciones (id_empleado, tipo, fecha_inicio, fecha_fin, motivo) 
                       VALUES (:id_emp, :tipo, :inicio, :fin, :motivo)";
        $stmt_insert = $conexion->prepare($sql_insert);
        
        $exito = $stmt_insert->execute([
            ':id_emp' => $id_empleado, ':tipo' => $tipo, ':inicio' => $fecha_inicio, 
            ':fin' => $fecha_fin, ':motivo' => $motivo
        ]);

        if ($exito) {
            $_SESSION['mensaje_permiso'] = "✅ ¡Justificación individual registrada exitosamente para la IA!";
            $_SESSION['tipo_mensaje_permiso'] = "exito";
        } else {
            $_SESSION['mensaje_permiso'] = "❌ Error al guardar en la base de datos.";
            $_SESSION['tipo_mensaje_permiso'] = "error";
        }
    }
    header("Location: registrar_permiso.php");
    exit();
}

// ==========================================
// 3. PATRÓN PRG PARA DÍAS FERIADOS / MASIVOS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_feriado'])) {
    $motivo_feriado = trim($_POST['motivo_feriado']);
    $fecha_inicio_feriado = $_POST['fecha_inicio_feriado'];
    $fecha_fin_feriado = $_POST['fecha_fin_feriado'];
    
    $nucleo_feriado = ($mi_rol === 'SuperAdmin') ? ($_POST['alcance'] ?? NULL) : $mi_nucleo;

    if ($fecha_inicio_feriado > $fecha_fin_feriado) {
        $_SESSION['mensaje_permiso'] = "❌ Error en Feriado: La fecha de inicio no puede ser mayor a la de fin.";
        $_SESSION['tipo_mensaje_permiso'] = "error";
    } else {
        $sql_feriado = "INSERT INTO dias_feriados (motivo, fecha_inicio, fecha_fin, id_nucleo) 
                        VALUES (:motivo, :inicio, :fin, :nucleo)";
        $stmt_feriado = $conexion->prepare($sql_feriado);
        $exito_feriado = $stmt_feriado->execute([
            ':motivo' => $motivo_feriado, ':inicio' => $fecha_inicio_feriado, 
            ':fin' => $fecha_fin_feriado, ':nucleo' => $nucleo_feriado
        ]);

        if ($exito_feriado) {
            $_SESSION['mensaje_permiso'] = "✅ ¡Día Feriado o Suspensión Masiva registrada con éxito!";
            $_SESSION['tipo_mensaje_permiso'] = "exito";
        } else {
            $_SESSION['mensaje_permiso'] = "❌ Error al guardar el feriado.";
            $_SESSION['tipo_mensaje_permiso'] = "error";
        }
    }
    header("Location: registrar_permiso.php");
    exit();
}

// Leer mensajes
$mensaje = "";
$tipo_mensaje = "";
if (isset($_SESSION['mensaje_permiso'])) {
    $mensaje = $_SESSION['mensaje_permiso'];
    $tipo_mensaje = $_SESSION['tipo_mensaje_permiso'];
    unset($_SESSION['mensaje_permiso']);
    unset($_SESSION['tipo_mensaje_permiso']);
}

// ==========================================
// 4. OBTENER DATOS PARA LAS VISTAS
// ==========================================
// Empleados
$q_empleados = $conexion->prepare("SELECT id_empleado, cedula, nombres, apellidos FROM empleados $filtro_sede ORDER BY nombres ASC");
$q_empleados->execute($parametros_sede);
$empleados = $q_empleados->fetchAll(PDO::FETCH_ASSOC);

// Núcleos para el SuperAdmin
$nucleos = [];
if ($mi_rol === 'SuperAdmin') {
    $q_nucs = $conexion->query("SELECT * FROM nucleos ORDER BY nombre_nucleo ASC");
    $nucleos = $q_nucs->fetchAll(PDO::FETCH_ASSOC);
}

// Historial Pagina
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$registros_por_pagina = 10;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

$filtro_historial = ($mi_rol !== 'SuperAdmin') ? " WHERE e.id_nucleo = $mi_nucleo " : "";
$q_count = $conexion->query("SELECT COUNT(*) as total FROM justificaciones j JOIN empleados e ON j.id_empleado = e.id_empleado $filtro_historial");
$total_registros = $q_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Historial de Permisos (Asumiendo que tu Primary Key es id_justificacion)
$q_historial = $conexion->query("
    SELECT j.*, e.cedula, e.nombres, e.apellidos 
    FROM justificaciones j 
    JOIN empleados e ON j.id_empleado = e.id_empleado 
    $filtro_historial
    ORDER BY j.fecha_registro DESC LIMIT $offset, $registros_por_pagina
");
$ultimos_permisos = $q_historial->fetchAll(PDO::FETCH_ASSOC);

// Historial de Feriados (Asumiendo que tu Primary Key es id_feriado)
$filtro_feriados = ($mi_rol !== 'SuperAdmin') ? " WHERE id_nucleo = $mi_nucleo OR id_nucleo IS NULL " : "";
$q_feriados = $conexion->query("SELECT * FROM dias_feriados $filtro_feriados ORDER BY fecha_inicio DESC LIMIT 5");
$ultimos_feriados = $q_feriados->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIBIA - Permisos y Feriados</title>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; background-color: #f5f5f5; color: #333; }
        .page-container { min-height: 100vh; display: flex; flex-direction: column; }
        
        .header-banner { background: linear-gradient(135deg, #4a90d9 0%, #6bb3ff 50%, #4a90d9 100%); position: relative; overflow: hidden; }
        .header-banner::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-image: linear-gradient(45deg, rgba(255,255,255,0.1) 25%, transparent 25%), linear-gradient(-45deg, rgba(255,255,255,0.1) 25%, transparent 25%), linear-gradient(45deg, transparent 75%, rgba(255,255,255,0.1) 75%), linear-gradient(-45deg, transparent 75%, rgba(255,255,255,0.1) 75%); background-size: 40px 40px; opacity: 0.3; }
        .banner-content { display: flex; align-items: center; justify-content: space-between; max-width: 1200px; margin: 0 auto; padding: 10px 20px; position: relative; z-index: 1; }
        .logo-left img, .logo-right img { height: 120px; width: auto; }
        .banner-center { flex: 1; display: flex; flex-direction: column; align-items: center; position: relative; }
        .photo-group { width: 350px; height: 180px; border-radius: 50%; overflow: hidden; border: 4px solid rgba(255,255,255,0.5); box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .photo-group img { width: 100%; height: 100%; object-fit: cover; }
        .banner-text { background: linear-gradient(to bottom, #5a9fd9, #3a7bc9); padding: 8px 25px; border-radius: 20px; margin-top: -25px; box-shadow: 0 2px 8px rgba(0,0,0,0.3); position: relative; z-index: 2; }
        .banner-text span { color: white; font-size: 14px; font-weight: bold; text-shadow: 1px 1px 2px rgba(0,0,0,0.3); letter-spacing: 1px; }

        .main-content { display: flex; flex: 1; max-width: 1200px; margin: 0 auto; width: 100%; padding: 20px; gap: 20px; }
        .sidebar { flex: 0 0 220px; background: white; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); height: fit-content; }
        .menu ul { list-style: none; }
        .menu-item { border-bottom: 1px solid #eee; position: relative; }
        .menu-item:last-child { border-bottom: none; }
        .menu-item > a { display: flex; align-items: center; padding: 12px 15px; text-decoration: none; color: #666; font-size: 13px; transition: background-color 0.2s; }
        .menu-item > a:hover { background-color: #f9f9f9; }
        .menu-item > a.active { background-color: #f0f8ff; color: #4a90d9; font-weight: bold; border-left: 4px solid #4a90d9;}

        .icon { width: 18px; height: 18px; margin-right: 10px; display: inline-block; position: relative; }
        .home-icon { background: linear-gradient(135deg, #e74c3c, #c0392b); border-radius: 3px; }
        .home-icon::before { content: ''; position: absolute; top: 4px; left: 50%; transform: translateX(-50%); width: 0; height: 0; border-left: 5px solid transparent; border-right: 5px solid transparent; border-bottom: 6px solid white; }
        .home-icon::after { content: ''; position: absolute; bottom: 3px; left: 50%; transform: translateX(-50%); width: 8px; height: 6px; background: white; border-radius: 1px; }
        .star-icon { background: #8bc34a; border-radius: 50%; }
        .star-icon::before { content: '★'; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-size: 10px; }
        .user-icon { background: linear-gradient(135deg, #ff9800, #f57c00); border-radius: 50%; }
        .user-icon::before { content: ''; position: absolute; top: 4px; left: 50%; transform: translateX(-50%); width: 6px; height: 6px; background: white; border-radius: 50%; }
        .user-icon::after { content: ''; position: absolute; bottom: 3px; left: 50%; transform: translateX(-50%); width: 10px; height: 5px; background: white; border-radius: 5px 5px 0 0; }

        .submenu { max-height: 0; overflow: hidden; background-color: #fafafa; transition: max-height 0.35s ease-in-out; }
        .submenu.open { max-height: 400px; }
        .submenu li a { padding: 10px 15px 10px 43px; font-size: 12px; color: #666; display: flex; align-items: center; text-decoration: none; }
        .submenu li a:hover { background-color: #f0f0f0; color: #4a90d9; }
        .bullet { width: 12px; height: 12px; background: linear-gradient(135deg, #8bc34a, #689f38); border-radius: 50%; margin-right: 8px; position: relative; }
        .bullet::after { content: ''; position: absolute; top: 3px; left: 4px; width: 4px; height: 4px; background: rgba(255,255,255,0.8); border-radius: 50%; }

        .content-area { flex: 1; display: flex; flex-direction: column; }
        .user-header { background: white; padding: 15px 20px; margin-bottom: 20px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        
        .record-section { background: white; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 30px; display: flex; flex-direction: column; margin-bottom: 20px;}
        .record-title { text-align: center; font-size: 20px; font-weight: bold; color: #666; margin-bottom: 25px; font-style: italic; border-bottom: 2px solid #eee; padding-bottom: 10px;}

        .split-container { display: flex; gap: 20px; margin-bottom: 20px; }
        .form-box { flex: 1; background: #fafafa; border: 1px solid #ddd; padding: 20px; border-radius: 5px; }
        .form-box h3 { font-size: 15px; color: #4a90d9; margin-bottom: 15px; border-bottom: 1px solid #ccc; padding-bottom: 5px;}
        .form-box.feriado h3 { color: #e67e22; }
        @media (max-width: 900px) { .split-container { flex-direction: column; } }

        .form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 15px;}
        .form-group label { font-size: 12px; font-weight: bold; color: #333; }
        .form-group label span { color: #c0392b; } 
        
        .form-input, .form-select, .form-textarea { width: 100%; padding: 8px 10px; font-size: 12px; border: 1px solid #ccc; border-radius: 4px; background-color: #fff; transition: border-color 0.3s; }
        .form-textarea { resize: vertical; min-height: 60px; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: #4a90d9; }

        .btn-guardar { background: linear-gradient(to bottom, #7cb342, #558b2f); color: white; padding: 10px 20px; border: none; border-radius: 5px; font-weight: bold; font-size: 12px; cursor: pointer; text-transform: uppercase; transition: 0.3s; box-shadow: 0 2px 4px rgba(0,0,0,0.2); width: 100%;}
        .btn-guardar:hover { background: linear-gradient(to bottom, #8bc34a, #689f38); transform: translateY(-2px); }
        .btn-feriado { background: linear-gradient(to bottom, #e67e22, #d35400); }
        .btn-feriado:hover { background: linear-gradient(to bottom, #f39c12, #e67e22); }

        .table-container { overflow-x: auto; border: 1px solid #ccc; border-radius: 5px; background: white; margin-bottom: 20px;}
        .grades-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .grades-table thead { background-color: #d3d3d3; }
        .grades-table th { padding: 10px 8px; text-align: left; font-weight: bold; color: #333; border-bottom: 2px solid #999; text-transform: uppercase; }
        .grades-table td { padding: 10px 8px; text-align: left; border-bottom: 1px solid #ddd; }
        .grades-table tbody tr:nth-child(odd) { background-color: #e8f5e9; }
        .grades-table tbody tr:nth-child(even) { background-color: #c8e6c9; }
        .grades-table tbody tr:hover { background-color: #a5d6a7; }
        
        .badge-permiso { background-color: #f39c12; color: white; padding: 4px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; }
        .badge-reposo { background-color: #e74c3c; color: white; padding: 4px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; }
        .badge-feriado { background-color: #8e44ad; color: white; padding: 4px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; }
        .badge-retardo { background-color: #2980b9; color: white; padding: 4px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; }

        /* ESTILO DEL BOTÓN ELIMINAR */
        .btn-eliminar { background: #e74c3c; color: white; padding: 5px 10px; border-radius: 3px; text-decoration: none; font-size: 11px; font-weight: bold; transition: 0.3s; display: inline-block;}
        .btn-eliminar:hover { background: #c0392b; transform: scale(1.05); }

        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; font-weight: bold; text-align: center; }
        .alert-exito { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .pagination { display: flex; justify-content: flex-end; align-items: center; gap: 5px; padding: 10px; background-color: #e0e0e0; border-radius: 0 0 5px 5px; }
        .page-link, .page-number { color: #666; text-decoration: none; padding: 5px 8px; font-size: 12px; background: white; border: 1px solid #ccc; border-radius: 3px;}
        .page-link:hover, .page-number:hover { color: #333; background: #eee; }
        .page-number.active { color: white; background-color: #333; font-weight: bold; border-color: #333;}
        .page-disabled { color: #aaa; background: #f9f9f9; border: 1px solid #ddd; padding: 5px 8px; font-size: 12px; border-radius: 3px; cursor: not-allowed; }

        .footer { text-align: center; padding: 20px; background: white; border-top: 1px solid #eee; margin-top: auto; }
        .footer p { font-size: 11px; color: #666; margin: 3px 0; }
        .footer strong { color: #333; font-weight: bold; }
    </style>
</head>
<body>
    <div class="page-container">
        <header class="header-banner">
            <div class="banner-content">
                <div class="logo-left"><img src="logo_gobierno.png" alt="Gobierno"></div>
                <div class="banner-center">
                    <div class="photo-group"><img src="fondo_unefa.jpg" alt="Estudiantes"></div>
                    <div class="banner-text"><span>EXCELENCIA EDUCATIVA ABIERTA AL PUEBLO</span></div>
                </div>
                <div class="logo-right"><img src="logo_unefa.png" alt="Logo SIBIA"></div>
            </div>
        </header>

        <main class="main-content">
            <aside class="sidebar">
                <nav class="menu">
                    <ul>
                        <li class="menu-item"><a href="dashboard.php"><span class="icon home-icon"></span><span class="text">Inicio (Tablero)</span></a></li>
                        
                        <li class="menu-item">
                            <a href="#" class="submenu-toggle">
                                <span class="icon user-icon"></span><span class="text">Gestión de Personal</span>
                            </a>
                            <ul class="submenu">
                                <li><a href="index.php"><span class="bullet"></span><span>Listado de Personal</span></a></li>
                                <li><a href="importar_horarios.php"><span class="bullet"></span><span>Importar Horarios (Excel)</span></a></li>
                            </ul>
                        </li>

                        <li class="menu-item">
                            <a href="#" class="submenu-toggle active" style="font-weight: bold; color: #4a90d9; border-left: 4px solid #4a90d9; background-color: #f0f8ff;">
                                <span class="icon star-icon"></span><span class="text">Gestión Operativa</span>
                            </a>
                            <ul class="submenu open">
                                <li><a href="alertas.php"><span class="bullet"></span><span>Panel de Alertas (IA)</span></a></li>
                                <li><a href="registrar_permiso.php" style="font-weight: bold; color: #4a90d9;"><span class="bullet"></span><span>Permisos y Feriados</span></a></li>
                                <li><a href="historial_asistencia.php"><span class="bullet"></span><span>Historial de Asistencias</span></a></li>
                            </ul>
                        </li>
                        
                        <li class="menu-item"><a href="logout.php"><span class="icon" style="background: linear-gradient(135deg, #e74c3c, #c0392b); border-radius: 50%;"></span><b style="color: #c0392b;">Cerrar Sesión</b></a></li>
                    </ul>
                </nav>
            </aside>

            <section class="content-area">
                <div class="user-header">
                    <div style="text-align: left;">
                        <span style="font-size: 14px; font-weight: bold; color: #1e5799;">NÚCLEO/EXTENSIÓN:</span><br>
                        <span style="font-size: 12px; color: #555; font-weight: bold;"><?= htmlspecialchars($nombre_sede) ?></span>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 14px; font-weight: bold; color: #333; display: block;"><?= strtoupper(htmlspecialchars($_SESSION['nombre_completo'])) ?></span>
                        <span style="font-size: 11px; color: #888;">Rol: <?= htmlspecialchars($mi_rol) ?></span>
                    </div>
                </div>

                <div class="record-section">
                    <h1 class="record-title">Gestión de Ausencias y Feriados</h1>
                    
                    <?php if($mensaje != ""): ?>
                        <div class="alert alert-<?= $tipo_mensaje ?>"><?= $mensaje ?></div>
                    <?php endif; ?>

                    <div style="background: #e8f4fd; border-left: 4px solid #4a90d9; padding: 15px; border-radius: 4px; font-size: 13px; color: #333; margin-bottom: 20px; line-height: 1.5;">
                        <strong>🛡️ Auditado por Inteligencia Artificial:</strong><br>
                        Al registrar un <strong>Retardo Justificado</strong>, si el docente marca su huella tarde, la IA omitirá la penalización roja y dejará constancia en verde del motivo especificado.
                    </div>

                    <div class="split-container">
                        <div class="form-box">
                            <h3>👤 Registro Individual (Permiso/Retardo)</h3>
                            <form method="POST" action="">
                                <input type="hidden" name="guardar_permiso" value="1">
                                
                                <div class="form-group">
                                    <label><span>*</span> Empleado:</label>
                                    <select name="id_empleado" class="form-select" required>
                                        <option value="" disabled selected>Seleccione...</option>
                                        <?php foreach($empleados as $emp): ?>
                                            <option value="<?= $emp['id_empleado'] ?>"><?= htmlspecialchars($emp['cedula'] . " - " . $emp['nombres'] . " " . $emp['apellidos']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label><span>*</span> Tipo de Evento:</label>
                                    <select name="tipo" class="form-select" required>
                                        <option value="" disabled selected>Seleccione la categoría...</option>
                                        <option value="Retardo">Retardo Justificado (Llegada tarde avisada)</option>
                                        <option value="Permiso Personal">Permiso Personal (Día completo)</option>
                                        <option value="Reposo Médico">Reposo Médico</option>
                                        <option value="Institucional">Comisión de Servicio / Institucional</option>
                                        <option value="Vacaciones">Vacaciones</option>
                                    </select>
                                </div>
                                <div style="display: flex; gap: 10px;">
                                    <div class="form-group" style="flex: 1;">
                                        <label><span>*</span> Desde:</label>
                                        <input type="date" name="fecha_inicio" class="form-input" required>
                                    </div>
                                    <div class="form-group" style="flex: 1;">
                                        <label><span>*</span> Hasta (Mismo día si es Retardo):</label>
                                        <input type="date" name="fecha_fin" class="form-input" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Motivo Detallado:</label>
                                    <textarea name="motivo" class="form-textarea" placeholder="Ej: Se quedó sin gasolina en la autopista..."></textarea>
                                </div>
                                <button type="submit" class="btn-guardar">Guardar Justificación</button>
                            </form>
                        </div>

                        <div class="form-box feriado">
                            <h3 style="color: #d35400;">🌴 Día Feriado / Suspensión Masiva</h3>
                            <form method="POST" action="">
                                <input type="hidden" name="guardar_feriado" value="1">
                                
                                <div class="form-group">
                                    <label><span>*</span> Motivo (Ej: Semana Santa, 19 de Abril):</label>
                                    <input type="text" name="motivo_feriado" class="form-input" required placeholder="Motivo del Feriado...">
                                </div>
                                
                                <?php if($mi_rol === 'SuperAdmin'): ?>
                                <div class="form-group">
                                    <label><span>*</span> Alcance:</label>
                                    <select name="alcance" class="form-select" required>
                                        <option value="">Toda VENEZUELA (Nacional)</option>
                                        <?php foreach($nucleos as $n): ?>
                                            <option value="<?= $n['id_nucleo'] ?>">Solo <?= htmlspecialchars($n['nombre_nucleo']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>

                                <div style="display: flex; gap: 10px;">
                                    <div class="form-group" style="flex: 1;">
                                        <label><span>*</span> Inicia:</label>
                                        <input type="date" name="fecha_inicio_feriado" class="form-input" required>
                                    </div>
                                    <div class="form-group" style="flex: 1;">
                                        <label><span>*</span> Termina:</label>
                                        <input type="date" name="fecha_fin_feriado" class="form-input" required>
                                    </div>
                                </div>
                                <div style="margin-top: 15px;">
                                    <p style="font-size: 11px; color: #777; margin-bottom: 10px; font-style: italic;">Nota: Esto justificará automáticamente la ausencia de todo el personal seleccionado durante estas fechas.</p>
                                    <button type="submit" class="btn-guardar btn-feriado">Declarar Feriado</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="record-section">
                    <h2 style="font-size: 16px; color: #333; margin-bottom: 15px; border-bottom: 2px solid #eee; padding-bottom: 10px;">Días Feriados Próximos / Activos</h2>
                    <div class="table-container">
                        <table class="grades-table">
                            <thead>
                                <tr>
                                    <th>Motivo del Feriado</th>
                                    <th>Desde</th>
                                    <th>Hasta</th>
                                    <th>Alcance</th>
                                    <th style="text-align: center;">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($ultimos_feriados) > 0): ?>
                                    <?php foreach ($ultimos_feriados as $f): ?>
                                        <tr>
                                            <td><span class="badge-feriado">Feriado</span> <strong><?= htmlspecialchars($f['motivo']) ?></strong></td>
                                            <td><?= date("d/m/Y", strtotime($f['fecha_inicio'])) ?></td>
                                            <td><?= date("d/m/Y", strtotime($f['fecha_fin'])) ?></td>
                                            <td><?= is_null($f['id_nucleo']) ? 'Nacional (Toda Venezuela)' : 'Sede Actual' ?></td>
                                            <td style="text-align: center;">
                                                <a href="?del_feriado=<?= $f['id_feriado'] ?>" class="btn-eliminar" onclick="return confirm('¿Seguro que deseas eliminar este Día Feriado? La IA volverá a auditar estas fechas.');">🗑️ Eliminar</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" style="text-align: center; padding: 15px;">No hay feriados registrados recientemente.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <h2 style="font-size: 16px; color: #333; margin-bottom: 15px; margin-top: 30px; border-bottom: 2px solid #eee; padding-bottom: 10px;">Registro Histórico de Permisos y Retardos</h2>
                    <div class="table-container">
                        <table class="grades-table">
                            <thead>
                                <tr>
                                    <th>Empleado</th>
                                    <th>Tipo</th>
                                    <th>Desde</th>
                                    <th>Hasta</th>
                                    <th>Motivo Detallado</th>
                                    <th style="text-align: center;">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($ultimos_permisos) > 0): ?>
                                    <?php foreach ($ultimos_permisos as $p): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($p['cedula']) ?></strong><br><?= htmlspecialchars($p['nombres'] . " " . $p['apellidos']) ?></td>
                                            <td>
                                                <?php 
                                                $es_reposo = (strpos($p['tipo'], 'Reposo') !== false || strpos($p['motivo'], '[Reposo') !== false);
                                                $es_retardo = (strpos($p['tipo'], 'Retardo') !== false || strpos($p['motivo'], '[Retardo]') !== false);
                                                
                                                if ($es_reposo): ?>
                                                    <span class="badge-reposo"><?= htmlspecialchars($p['tipo'] ?: 'Reposo Médico') ?></span>
                                                <?php elseif ($es_retardo): ?>
                                                    <span class="badge-retardo">Retardo</span>
                                                <?php else: ?>
                                                    <span class="badge-permiso"><?= htmlspecialchars($p['tipo'] ?: 'Permiso Personal') ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date("d/m/Y", strtotime($p['fecha_inicio'])) ?></td>
                                            <td><?= date("d/m/Y", strtotime($p['fecha_fin'])) ?></td>
                                            <td><?= htmlspecialchars($p['motivo'] ?? 'N/A') ?></td>
                                            <td style="text-align: center;">
                                                <a href="?del_permiso=<?= $p['id_justificacion'] ?>" class="btn-eliminar" onclick="return confirm('¿Seguro que deseas eliminar este Permiso? Si la fecha ya pasó, la IA detectará una inasistencia.');">🗑️ Eliminar</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" style="text-align: center; padding: 20px;">No hay justificaciones registradas.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_paginas > 1): ?>
                    <div class="pagination">
                        <?php if ($pagina_actual > 1): ?>
                            <a href="?pagina=1" class="page-link">Primero</a>
                            <a href="?pagina=<?= $pagina_actual - 1 ?>" class="page-link">Anterior</a>
                        <?php else: ?>
                            <span class="page-disabled">Primero</span>
                            <span class="page-disabled">Anterior</span>
                        <?php endif; ?>

                        <?php for ($i = max(1, $pagina_actual - 2); $i <= min($total_paginas, $pagina_actual + 2); $i++): ?>
                            <a href="?pagina=<?= $i ?>" class="page-number <?= ($pagina_actual == $i) ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($pagina_actual < $total_paginas): ?>
                            <a href="?pagina=<?= $pagina_actual + 1 ?>" class="page-link">Siguiente</a>
                            <a href="?pagina=<?= $total_paginas ?>" class="page-link">Último</a>
                        <?php else: ?>
                            <span class="page-disabled">Siguiente</span>
                            <span class="page-disabled">Último</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>

        <footer class="footer">
            <p>Todos los logos en este sitio son propiedad, y están registrados, por sus respectivos dueños.</p>
            <p>Todo lo demás tiene <strong>Derechos de Propiedad © 2013-2026</strong> por la <strong>Universidad Nacional Experimental Politécnica de la Fuerza Armada</strong></p>
            <p>Todos los derechos reservados. Módulo SIBIA.</p>
        </footer>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const toggles = document.querySelectorAll(".submenu-toggle");
            toggles.forEach(toggle => {
                toggle.addEventListener("click", function(e) {
                    e.preventDefault();
                    const submenu = this.nextElementSibling;
                    document.querySelectorAll(".submenu").forEach(sub => {
                        if(sub !== submenu) sub.classList.remove("open");
                    });
                    submenu.classList.toggle("open");
                });
            });
        });
    </script>
</body>
</html>