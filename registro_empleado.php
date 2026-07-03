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

$mensaje = "";
$tipo_mensaje = "";

// Obtener título de la sede para la cabecera
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
// 2. PROCESAR EL FORMULARIO (GUARDAR)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cedula = trim($_POST['cedula']);
    $nombres = trim(strtoupper($_POST['nombres']));
    $apellidos = trim(strtoupper($_POST['apellidos']));
    $anviz_id = trim($_POST['anviz_id']);
    $id_departamento = $_POST['id_departamento'];
    $cargo = trim(strtoupper($_POST['cargo']));
    $modalidad = $_POST['modalidad']; // AQUI CAMBIAMOS A MODALIDAD
    $contrato = $_POST['contrato'];
    
    // Si es SuperAdmin, toma el núcleo del selector. Si es Admin normal, usa su propio núcleo.
    $nucleo_asignado = ($mi_rol === 'SuperAdmin') ? $_POST['id_nucleo'] : $mi_nucleo;

    // Verificar si la cédula o el ID Biométrico ya existen
    $check = $conexion->prepare("SELECT COUNT(*) FROM empleados WHERE cedula = :cedula OR anviz_id = :anviz");
    $check->execute([':cedula' => $cedula, ':anviz' => $anviz_id]);
    
    if ($check->fetchColumn() > 0) {
        $mensaje = "❌ Error: La Cédula o el ID Biométrico ya están registrados en el sistema.";
        $tipo_mensaje = "error";
    } else {
        // Guardar al nuevo empleado
        $sql_insert = "INSERT INTO empleados (cedula, nombres, apellidos, anviz_id, id_departamento, cargo, modalidad, contrato, id_nucleo, estado) 
                       VALUES (:ced, :nom, :ape, :anv, :dep, :car, :mod, :con, :nuc, 'Activo')";
        $stmt_insert = $conexion->prepare($sql_insert);
        
        $exito = $stmt_insert->execute([
            ':ced' => $cedula, ':nom' => $nombres, ':ape' => $apellidos, 
            ':anv' => $anviz_id, ':dep' => $id_departamento, ':car' => $cargo, 
            ':mod' => $modalidad, ':con' => $contrato, ':nuc' => $nucleo_asignado
        ]);

        if ($exito) {
            $mensaje = "✅ ¡Empleado registrado con éxito! Ahora puedes asignarle su horario en el listado.";
            $tipo_mensaje = "exito";
        } else {
            $mensaje = "❌ Error interno al guardar en la base de datos.";
            $tipo_mensaje = "error";
        }
    }
}

// ==========================================
// 3. OBTENER DATOS PARA LOS MENÚS DESPLEGABLES
// ==========================================
// Departamentos (Agrupados para evitar duplicados)
$q_deptos = $conexion->query("SELECT MIN(id_departamento) as id_departamento, nombre FROM departamentos GROUP BY nombre ORDER BY nombre ASC");
$departamentos = $q_deptos->fetchAll(PDO::FETCH_ASSOC);

// Núcleos (Solo para SuperAdmin)
$nucleos = [];
if ($mi_rol === 'SuperAdmin') {
    $q_nucs = $conexion->query("SELECT * FROM nucleos ORDER BY nombre_nucleo ASC");
    $nucleos = $q_nucs->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIBIA - Registrar Empleado</title>
    
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
        
        .record-section { background: white; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 30px; display: flex; flex-direction: column; }
        .record-title { text-align: center; font-size: 20px; font-weight: bold; color: #666; margin-bottom: 25px; font-style: italic; border-bottom: 2px solid #eee; padding-bottom: 10px;}

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }

        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group label { font-size: 13px; font-weight: bold; color: #333; }
        .form-group label span { color: #c0392b; } 
        
        .form-input, .form-select { width: 100%; padding: 10px 12px; font-size: 13px; border: 1px solid #ccc; border-radius: 4px; background-color: #fff; transition: border-color 0.3s; }
        .form-input:focus, .form-select:focus { outline: none; border-color: #4a90d9; box-shadow: 0 0 5px rgba(74, 144, 217, 0.3); }

        .form-full-width { grid-column: 1 / -1; }

        .action-buttons { display: flex; justify-content: center; gap: 15px; margin-top: 20px; border-top: 1px solid #eee; padding-top: 20px;}
        .btn { padding: 12px 30px; border: none; border-radius: 5px; font-weight: bold; font-size: 13px; cursor: pointer; text-transform: uppercase; text-decoration: none; transition: 0.3s; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .btn-guardar { background: linear-gradient(to bottom, #7cb342, #558b2f); color: white; }
        .btn-guardar:hover { background: linear-gradient(to bottom, #8bc34a, #689f38); transform: translateY(-2px); }
        .btn-volver { background: linear-gradient(to bottom, #6c757d, #495057); color: white; }
        .btn-volver:hover { background: linear-gradient(to bottom, #5a6268, #343a40); transform: translateY(-2px); }

        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; font-weight: bold; text-align: center; }
        .alert-exito { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

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
                            <a href="#" class="submenu-toggle active" style="font-weight: bold; color: #4a90d9; border-left: 4px solid #4a90d9; background-color: #f0f8ff;">
                                <span class="icon user-icon"></span><span class="text">Gestión de Personal</span>
                            </a>
                            <ul class="submenu open">
                                <li><a href="index.php"><span class="bullet"></span><span>Listado de Personal</span></a></li>
                                <li><a href="importar_horarios.php"><span class="bullet"></span><span>Importar Horarios (Excel)</span></a></li>
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
                    <h1 class="record-title">Ingreso de Nuevo Personal SIBIA</h1>
                    
                    <?php if($mensaje != ""): ?>
                        <div class="alert alert-<?= $tipo_mensaje ?>"><?= $mensaje ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="form-grid">
                            
                            <div class="form-group">
                                <label><span>*</span> Cédula de Identidad:</label>
                                <input type="text" name="cedula" class="form-input" placeholder="Ej: 29801856" required>
                            </div>

                            <div class="form-group">
                                <label><span>*</span> ID Biométrico (Anviz ID):</label>
                                <input type="number" name="anviz_id" class="form-input" placeholder="Ej: 105 (Número en el reloj)" required>
                            </div>

                            <div class="form-group">
                                <label><span>*</span> Nombres:</label>
                                <input type="text" name="nombres" class="form-input" placeholder="Ej: Wilfre Junior" required>
                            </div>

                            <div class="form-group">
                                <label><span>*</span> Apellidos:</label>
                                <input type="text" name="apellidos" class="form-input" placeholder="Ej: Willie Lopez" required>
                            </div>

                            <div class="form-group">
                                <label><span>*</span> Departamento:</label>
                                <select name="id_departamento" class="form-select" required>
                                    <option value="" disabled selected>Seleccione un departamento...</option>
                                    <?php foreach($departamentos as $d): ?>
                                        <option value="<?= $d['id_departamento'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label><span>*</span> Cargo Oficial:</label>
                                <input type="text" name="cargo" class="form-input" placeholder="Ej: Vigilante, Docente, Secretaria..." required>
                            </div>

                            <div class="form-group">
                                <label><span>*</span> Modalidad Académica/Laboral:</label>
                                <select name="modalidad" class="form-select" required>
                                    <option value="" disabled selected>Seleccione la modalidad...</option>
                                    <option value="Diurno">Diurno (Lunes a Viernes)</option>
                                    <option value="Nocturno">Nocturno (Fines de Semana)</option>
                                    <option value="Ambos">Ambas Modalidades</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label><span>*</span> Tipo de Contrato:</label>
                                <select name="contrato" class="form-select" required>
                                    <option value="Fijo">Fijo / Titular</option>
                                    <option value="Contratado">Contratado</option>
                                    <option value="Suplente">Suplente</option>
                                    <option value="Obrero">Obrero</option>
                                </select>
                            </div>

                            <?php if($mi_rol === 'SuperAdmin'): ?>
                            <div class="form-group form-full-width" style="background: #eef2f5; padding: 15px; border-left: 4px solid #4a90d9; border-radius: 4px;">
                                <label style="color: #1e5799;"><span>*</span> [Acceso Nacional] Asignar a Núcleo:</label>
                                <select name="id_nucleo" class="form-select" required style="border-color: #4a90d9;">
                                    <option value="" disabled selected>Seleccione la sede a la que pertenece...</option>
                                    <?php foreach($nucleos as $n): ?>
                                        <option value="<?= $n['id_nucleo'] ?>"><?= htmlspecialchars($n['nombre_nucleo']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                        </div>

                        <div class="action-buttons">
                            <a href="index.php" class="btn btn-volver">Volver al Listado</a>
                            <button type="submit" class="btn btn-guardar">Guardar Empleado</button>
                        </div>
                    </form>
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