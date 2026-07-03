<?php
require_once 'seguridad.php';
require_once 'conexion.php';

// ==========================================
// 1. CEREBRO MULTI-SEDE (SICEU NACIONAL)
// ==========================================
$q_user = $conexion->prepare("SELECT rol, id_nucleo FROM usuarios WHERE usuario = :user");
$q_user->execute([':user' => $_SESSION['usuario']]);
$datos_usuario = $q_user->fetch(PDO::FETCH_ASSOC);

$mi_rol = $datos_usuario['rol'] ?? 'Admin_Sede'; // Evita errores si está vacío
$mi_nucleo = $datos_usuario['id_nucleo'];

$filtro_sede = "";
$parametros = [':hoy' => date('Y-m-d')];

if ($mi_rol !== 'SuperAdmin') {
    $filtro_sede = " AND e.id_nucleo = :id_nucleo";
    $parametros[':id_nucleo'] = $mi_nucleo;
}

// ==========================================
// 2. CÁLCULO DE MÉTRICAS (KPIs)
// ==========================================
// 1. Nómina Total
$q_nomina = $conexion->prepare("SELECT COUNT(*) as total FROM empleados e WHERE e.estado = 'Activo' $filtro_sede");
$q_nomina->execute($mi_rol !== 'SuperAdmin' ? [':id_nucleo' => $mi_nucleo] : []);
$tot_nomina = $q_nomina->fetch(PDO::FETCH_ASSOC)['total'];

// 2. Vinieron Hoy
$q_asistencias = $conexion->prepare("
    SELECT COUNT(DISTINCT e.id_empleado) as total 
    FROM registros_asistencia r 
    JOIN empleados e ON r.anviz_id = e.anviz_id 
    WHERE DATE(r.fecha_hora) = :hoy AND r.tipo_marca = 'Entrada' $filtro_sede
");
$q_asistencias->execute($parametros);
$tot_asistencias = $q_asistencias->fetch(PDO::FETCH_ASSOC)['total'];

// 3. Presentes Ahora
$q_presentes = $conexion->prepare("
    SELECT COUNT(*) AS total 
    FROM empleados e
    WHERE e.estado = 'Activo' $filtro_sede AND (
        SELECT tipo_marca 
        FROM registros_asistencia r 
        WHERE r.anviz_id = e.anviz_id AND DATE(r.fecha_hora) = :hoy 
        ORDER BY r.fecha_hora DESC LIMIT 1
    ) = 'Entrada'
");
$q_presentes->execute($parametros);
$tot_presentes = $q_presentes->fetch(PDO::FETCH_ASSOC)['total'];

// 4. Permisos Hoy
$q_permisos = $conexion->prepare("
    SELECT COUNT(*) as total 
    FROM justificaciones j 
    JOIN empleados e ON j.id_empleado = e.id_empleado 
    WHERE :hoy BETWEEN j.fecha_inicio AND j.fecha_fin $filtro_sede
");
$q_permisos->execute($parametros);
$tot_permisos = $q_permisos->fetch(PDO::FETCH_ASSOC)['total'];

// Determinamos el título de la sede
$nombre_sede = "VISIÓN NACIONAL (Todas las Sedes)";
if ($mi_rol !== 'SuperAdmin' && $mi_nucleo) {
    $q_sede = $conexion->prepare("SELECT nombre_nucleo FROM nucleos WHERE id_nucleo = :id");
    $q_sede->execute([':id' => $mi_nucleo]);
    $res_sede = $q_sede->fetch(PDO::FETCH_ASSOC);
    if($res_sede) {
        $nombre_sede = strtoupper($res_sede['nombre_nucleo']);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIBIA - Inicio</title>
    
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
        
        .main-display { flex: 1; background: white; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 25px; display: flex; flex-direction: column; }

        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; width: 100%; margin-top: 15px; }
        .kpi-card { border-radius: 8px; padding: 25px 20px; color: white; box-shadow: 0 4px 10px rgba(0,0,0,0.15); display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; transition: transform 0.3s ease; }
        .kpi-card:hover { transform: translateY(-5px); }
        .kpi-card h3 { font-size: 14px; text-transform: uppercase; margin-bottom: 10px; letter-spacing: 1px; font-weight: bold; opacity: 0.9; }
        .kpi-card .numero { font-size: 48px; font-weight: bold; line-height: 1; text-shadow: 2px 2px 4px rgba(0,0,0,0.2); }

        .card-blue { background: linear-gradient(to bottom, #4a90d9 0%, #2a6fb9 100%); border: 1px solid #1a5490; }
        .card-green { background: linear-gradient(to bottom, #7cb342 0%, #558b2f 100%); border: 1px solid #4a7c23; }
        .card-orange { background: linear-gradient(to bottom, #ffb74d 0%, #f57c00 100%); border: 1px solid #e65100; }
        .card-red { background: linear-gradient(to bottom, #e53935 0%, #c62828 100%); border: 1px solid #b71c1c; }

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
                        <li class="menu-item"><a href="dashboard.php" class="active"><span class="icon home-icon"></span><span class="text">Inicio (Tablero)</span></a></li>
                        
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
                        <span style="font-size: 14px; font-weight: bold; color: #333; display: block;">
                            <?= strtoupper(htmlspecialchars($_SESSION['nombre_completo'])) ?>
                        </span>
                        <span style="font-size: 11px; color: #888;">Rol: <?= htmlspecialchars($mi_rol) ?></span>
                    </div>
                </div>

                <div class="main-display">
                    <h2 style="font-size: 18px; color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 10px;">RESUMEN DE ASISTENCIA DIARIA</h2>
                    
                    <div class="kpi-grid">
                        <div class="kpi-card card-blue">
                            <h3>👥 Nómina Total</h3>
                            <div class="numero"><?= $tot_nomina ?></div>
                        </div>
                        <div class="kpi-card card-green">
                            <h3>✅ Vinieron Hoy</h3>
                            <div class="numero"><?= $tot_asistencias ?></div>
                        </div>
                        <div class="kpi-card card-orange">
                            <h3>🚶 Presentes Ahora</h3>
                            <div class="numero"><?= $tot_presentes ?></div>
                        </div>
                        <div class="kpi-card card-red">
                            <h3>📝 Permisos Hoy</h3>
                            <div class="numero"><?= $tot_permisos ?></div>
                        </div>
                    </div>
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