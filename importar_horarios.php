<?php
require_once 'seguridad.php';
require_once 'conexion.php';
require_once 'SimpleXLSX.php'; 

$q_user = $conexion->prepare("SELECT rol, id_nucleo FROM usuarios WHERE usuario = :user");
$q_user->execute([':user' => $_SESSION['usuario']]);
$datos_usuario = $q_user->fetch(PDO::FETCH_ASSOC);

$mi_rol = $datos_usuario['rol'] ?? 'Admin_Sede';
$mi_nucleo = $datos_usuario['id_nucleo'];

$mensaje = "";
$tipo_mensaje = "";
$resumen_importacion = []; 

$nombre_sede = "VISIÓN NACIONAL (Todas las Sedes)";
if ($mi_rol !== 'SuperAdmin' && $mi_nucleo) {
    $q_sede = $conexion->prepare("SELECT nombre_nucleo FROM nucleos WHERE id_nucleo = :id");
    $q_sede->execute([':id' => $mi_nucleo]);
    $res_sede = $q_sede->fetch(PDO::FETCH_ASSOC);
    if($res_sede) { $nombre_sede = strtoupper($res_sede['nombre_nucleo']); }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['archivos_excel'])) {
    
    $total_archivos = count($_FILES['archivos_excel']['name']);
    $registros_exitosos = 0;
    $registros_fallidos = 0;

    $filtro_sede = ($mi_rol !== 'SuperAdmin') ? " AND id_nucleo = $mi_nucleo" : "";
    
    $stmt_buscar_emp = $conexion->prepare("SELECT id_empleado, nombres, apellidos, cedula FROM empleados WHERE cedula LIKE :cedula $filtro_sede LIMIT 1");
    $stmt_insertar_horario = $conexion->prepare("INSERT INTO horarios_empleados (id_empleado, dia_semana, hora_entrada, hora_salida) VALUES (:id_emp, :dia, :ent, :sal)");

    $dias_validos = ['LUNES', 'MARTES', 'MIÉRCOLES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SÁBADO', 'SABADO', 'DOMINGO'];

    for ($i = 0; $i < $total_archivos; $i++) {
        $tmp_name = $_FILES['archivos_excel']['tmp_name'][$i];
        $nombre_archivo = $_FILES['archivos_excel']['name'][$i];

        if ($tmp_name != "") {
            if ( $xlsx = \Shuchkin\SimpleXLSX::parse($tmp_name) ) {
                $filas = $xlsx->rows();
                $dia_memoria = ""; 
                
                foreach ($filas as $indice => $fila) {
                    $texto_fila_completa = mb_strtoupper(implode(" ", $fila), 'UTF-8');
                    
                    $es_cabecera_dia = false;
                    foreach ($dias_validos as $d) {
                        if (strpos($texto_fila_completa, $d) !== false && empty(trim($fila[1] ?? ''))) {
                            $dia_memoria = ucfirst(strtolower($d));
                            if ($dia_memoria == 'Miércoles' || $dia_memoria == 'Miercoles') $dia_memoria = 'Miércoles';
                            if ($dia_memoria == 'Sábado' || $dia_memoria == 'Sabado') $dia_memoria = 'Sábado';
                            $es_cabecera_dia = true;
                            break;
                        }
                    }

                    if ($es_cabecera_dia) continue;

                    $cedula_bruta = trim($fila[1] ?? '');
                    $cedula_excel = preg_replace('/[^0-9]/', '', $cedula_bruta);
                    
                    if (!empty($cedula_excel) && is_numeric($cedula_excel) && $dia_memoria != "") {
                        
                        $inicio_str = trim($fila[4] ?? '');
                        $fin_str = trim($fila[5] ?? '');   
                        
                        $hora_inicio = is_numeric($inicio_str) ? gmdate("H:i:s", round($inicio_str * 86400)) : date("H:i:s", strtotime($inicio_str));
                        $hora_fin = is_numeric($fin_str) ? gmdate("H:i:s", round($fin_str * 86400)) : date("H:i:s", strtotime($fin_str));

                        if ($hora_inicio != "00:00:00" && $hora_fin != "00:00:00") {
                            
                            $stmt_buscar_emp->execute([':cedula' => '%' . $cedula_excel . '%']);
                            $empleado = $stmt_buscar_emp->fetch(PDO::FETCH_ASSOC);

                            if ($empleado) {
                                $check_dup = $conexion->prepare("SELECT COUNT(*) FROM horarios_empleados WHERE id_empleado = ? AND dia_semana = ? AND hora_entrada = ? AND hora_salida = ?");
                                $check_dup->execute([$empleado['id_empleado'], $dia_memoria, $hora_inicio, $hora_fin]);
                                
                                if ($check_dup->fetchColumn() == 0) {
                                    $exito = $stmt_insertar_horario->execute([
                                        ':id_emp' => $empleado['id_empleado'],
                                        ':dia' => $dia_memoria,
                                        ':ent' => $hora_inicio,
                                        ':sal' => $hora_fin
                                    ]);
                                    if($exito){ $registros_exitosos++; }
                                }
                            } else {
                                $error_msg = "Leí Cédula [ $cedula_excel ] el día $dia_memoria ($hora_inicio a $hora_fin), pero NO la encontré en la Base de Datos.";
                                if (!in_array($error_msg, $resumen_importacion)) {
                                    $resumen_importacion[] = $error_msg;
                                    $registros_fallidos++;
                                }
                            }
                        }
                    }
                }
            } else {
                $mensaje = "❌ Error al leer el archivo: $nombre_archivo. Verifique que sea un .xlsx válido.";
                $tipo_mensaje = "error";
            }
        }
    }

    if ($registros_exitosos > 0) {
        $mensaje = "✅ ¡Importación Exitosa! Se asignaron <b>$registros_exitosos bloques de horario</b> a los docentes.";
        $tipo_mensaje = "exito";
    } elseif ($mensaje == "") {
        $mensaje = "⚠️ No se importó ningún registro. Asegúrese de que las cédulas existan en el sistema.";
        $tipo_mensaje = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIBIA - Importar Horarios Docentes</title>
    
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

        /* MAGIA DE ANIMACIÓN DEL ACORDEÓN AQUÍ */
        .submenu { max-height: 0; overflow: hidden; background-color: #fafafa; transition: max-height 0.35s ease-in-out; }
        .submenu.open { max-height: 400px; }
        .submenu li a { padding: 10px 15px 10px 43px; font-size: 12px; color: #666; display: flex; align-items: center; text-decoration: none; }
        .submenu li a:hover { background-color: #f0f0f0; color: #4a90d9; }
        .bullet { width: 12px; height: 12px; background: linear-gradient(135deg, #8bc34a, #689f38); border-radius: 50%; margin-right: 8px; position: relative; }
        .bullet::after { content: ''; position: absolute; top: 3px; left: 4px; width: 4px; height: 4px; background: rgba(255,255,255,0.8); border-radius: 50%; }

        .content-area { flex: 1; display: flex; flex-direction: column; }
        .user-header { background: white; padding: 15px 20px; margin-bottom: 20px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        
        .record-section { background: white; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 25px; display: flex; flex-direction: column; }
        .record-title { text-align: center; font-size: 20px; font-weight: bold; color: #666; margin-bottom: 20px; font-style: italic; border-bottom: 2px solid #eee; padding-bottom: 10px;}

        .upload-box { border: 2px dashed #4a90d9; background-color: #f0f8ff; border-radius: 8px; padding: 40px; text-align: center; margin-bottom: 20px; transition: 0.3s; }
        .upload-box:hover { background-color: #e6f2ff; border-color: #2a6fb9; }
        
        .file-input { display: none; }
        .file-label { display: inline-block; background: linear-gradient(to bottom, #4a90d9, #2980b9); color: white; padding: 12px 25px; border-radius: 5px; font-weight: bold; font-size: 14px; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.2); transition: 0.3s;}
        .file-label:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.3); }

        .info-box { background: #fffdf5; border-left: 4px solid #f1c40f; padding: 15px; border-radius: 4px; font-size: 13px; color: #555; margin-bottom: 20px; line-height: 1.5; }

        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; font-weight: bold; text-align: center; }
        .alert-exito { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .btn-guardar { background: linear-gradient(to bottom, #7cb342, #558b2f); color: white; padding: 12px 30px; border: none; border-radius: 5px; font-weight: bold; font-size: 14px; cursor: pointer; width: 100%; margin-top: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); transition: 0.3s;}
        .btn-guardar:hover { background: linear-gradient(to bottom, #8bc34a, #689f38); transform: translateY(-2px); }
        
        #file-name-display { margin-top: 15px; font-size: 13px; color: #1e5799; font-weight: bold; }
        .error-list { background: #fff; padding: 15px; border: 1px solid #f5c6cb; border-radius: 4px; font-size: 12px; color: #721c24; max-height: 150px; overflow-y: auto; text-align: left;}

        .footer { text-align: center; padding: 20px; background: white; border-top: 1px solid #eee; margin-top: auto; }
        .footer p { font-size: 11px; color: #666; margin: 3px 0; }
        .footer strong { color: #333; font-weight: bold; }
    </style>
    
    <script>
        function updateFileName(input) {
            var display = document.getElementById('file-name-display');
            if (input.files.length > 1) {
                display.innerHTML = "📄 " + input.files.length + " sábana(s) académica(s) lista(s) para procesar.";
            } else if (input.files.length === 1) {
                display.innerHTML = "📄 " + input.files[0].name;
            } else {
                display.innerHTML = "";
            }
        }
    </script>
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
                            <a href="#" class="submenu-toggle active" style="font-weight: bold; color: #4a90d9; border-left: 4px solid #4a90d9; background-color: #f0f8ff;">
                                <span class="icon user-icon"></span><span class="text">Gestión de Personal</span>
                            </a>
                            <ul class="submenu open">
                                <li><a href="index.php"><span class="bullet"></span><span>Listado de Personal</span></a></li>
                                <li><a href="importar_horarios.php" style="font-weight: bold; color: #4a90d9;"><span class="bullet"></span><span>Importar Horarios (Excel)</span></a></li>
                            </ul>
                        </li>

                        <li class="menu-item">
                            <a href="#" class="submenu-toggle">
                                <span class="icon star-icon"></span><span class="text">Gestión Operativa</span>
                            </a>
                            <ul class="submenu">
                                <li><a href="alertas.php"><span class="bullet"></span><span>Panel de Alertas (IA)</span></a></li>
                                <li><a href="registrar_permiso.php"><span class="bullet"></span><span>Permisos y Feriados</span></a></li>
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
                    <h1 class="record-title">Sincronización Automática de Sábanas Docentes</h1>
                    
                    <?php if($mensaje != ""): ?>
                        <div class="alert alert-<?= $tipo_mensaje ?>"><?= $mensaje ?></div>
                    <?php endif; ?>

                    <div class="info-box">
                        <strong>💡 Inteligencia Artificial Activada:</strong><br>
                        El sistema SIBIA está configurado para leer el formato de listas (Sábanas) de la UNEFA.<br>
                        Asegúrese de que la primera columna sea el <strong>Día</strong> ("VIERNES", "SÁBADO", etc.) y debajo esté el listado con su <strong>Cédula en la Columna B</strong> y sus <strong>Horas en la E y F</strong>.
                    </div>

                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="upload-box">
                            <h2 style="color: #1e5799; font-size: 18px; margin-bottom: 10px;">Seleccione los archivos Excel (.xlsx) de los Coordinadores</h2>
                            <p style="font-size: 12px; color: #666; margin-bottom: 20px;">Puede seleccionar múltiples archivos al mismo tiempo.</p>
                            
                            <input type="file" name="archivos_excel[]" id="file" class="file-input" accept=".xlsx" multiple required onchange="updateFileName(this)">
                            <label for="file" class="file-label">📁 Buscar Sábanas Académicas</label>
                            <div id="file-name-display"></div>
                        </div>

                        <button type="submit" class="btn-guardar">⚡ Iniciar Escaneo y Asignación de Horarios</button>
                    </form>

                    <?php if(count($resumen_importacion) > 0): ?>
                        <div style="margin-top: 25px;">
                            <h3 style="font-size: 14px; color: #721c24; margin-bottom: 10px;">🛠️ MODO DIAGNÓSTICO (Lo que leyó la Inteligencia Artificial):</h3>
                            <div class="error-list">
                                <?php foreach($resumen_importacion as $err): ?>
                                    <p>• <?= $err ?></p>
                                <?php endforeach; ?>
                            </div>
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