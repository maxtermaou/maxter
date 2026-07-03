<?php
require_once 'seguridad.php';
require_once 'conexion.php';

$q_user = $conexion->prepare("SELECT rol, id_nucleo FROM usuarios WHERE usuario = :user");
$q_user->execute([':user' => $_SESSION['usuario']]);
$datos_usuario = $q_user->fetch(PDO::FETCH_ASSOC);

$mi_rol = $datos_usuario['rol'] ?? 'Admin_Sede';
$mi_nucleo = $datos_usuario['id_nucleo'];

$filtro_sede = "";
$parametros = [];

if ($mi_rol !== 'SuperAdmin') {
    $filtro_sede = " WHERE e.id_nucleo = :id_nucleo";
    $parametros[':id_nucleo'] = $mi_nucleo;
}

$nombre_sede = "VISIÓN NACIONAL (Todas las Sedes)";
if ($mi_rol !== 'SuperAdmin' && $mi_nucleo) {
    $q_sede = $conexion->prepare("SELECT nombre_nucleo FROM nucleos WHERE id_nucleo = :id");
    $q_sede->execute([':id' => $mi_nucleo]);
    $res_sede = $q_sede->fetch(PDO::FETCH_ASSOC);
    if($res_sede) { $nombre_sede = strtoupper($res_sede['nombre_nucleo']); }
}

$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$registros_por_pagina = 10;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

$sql_count = "SELECT COUNT(*) as total FROM analisis_ia a JOIN empleados e ON a.id_empleado = e.id_empleado $filtro_sede";
$stmt_count = $conexion->prepare($sql_count);
$stmt_count->execute($parametros);
$total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

$sql = "SELECT a.id_analisis, a.fecha_analisis, a.tipo_observacion, a.estado_revision, 
               e.nombres, e.apellidos, d.nombre AS departamento
        FROM analisis_ia a
        JOIN empleados e ON a.id_empleado = e.id_empleado
        LEFT JOIN departamentos d ON e.id_departamento = d.id_departamento
        $filtro_sede
        ORDER BY a.estado_revision ASC, a.fecha_analisis DESC
        LIMIT $offset, $registros_por_pagina";
        
$stmt = $conexion->prepare($sql);
$stmt->execute($parametros);
$alertas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIBIA - Panel de IA</title>
    
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

        .header-alertas { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 15px; }
        .header-alertas h2 { font-size: 18px; color: #333; margin: 0; }
        
        .btn-ia { background: linear-gradient(to bottom, #8e44ad, #732d91); color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 13px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-ia:hover { background: linear-gradient(to bottom, #9b59b6, #8e44ad); transform: translateY(-2px); }

        .table-container { overflow-x: auto; border: 1px solid #ccc; border-radius: 5px; background: white; }
        .sibia-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .sibia-table th { background: linear-gradient(to bottom, #4a90d9, #2a6fb9); color: white; padding: 12px 15px; text-align: left; font-size: 12px; font-weight: bold; text-transform: uppercase; border-radius: 3px 3px 0 0; }
        .sibia-table td { padding: 12px 15px; border-bottom: 1px solid #eee; font-size: 13px; color: #555; }
        .sibia-table tr:hover { background-color: #f0f8ff; }
        
        .badge-pendiente { color: #c0392b; font-weight: bold; font-size: 12px; }
        .badge-listo { background-color: #d4edda; color: #155724; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; border: 1px solid #c3e6cb; display: inline-block;}
        
        .btn-revisar { background: linear-gradient(to bottom, #e74c3c, #c0392b); color: white; padding: 6px 12px; text-decoration: none; border-radius: 3px; font-size: 11px; font-weight: bold; display: inline-block; transition: 0.3s; }
        .btn-revisar:hover { background: linear-gradient(to bottom, #ef5350, #d32f2f); }
        
        .alert-success { background-color: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; font-weight: bold; text-align: center; border: 1px solid #c3e6cb; }

        .pagination { display: flex; justify-content: flex-end; align-items: center; gap: 5px; margin-top: 15px; padding: 10px; background-color: #e0e0e0; border-radius: 0 0 5px 5px; }
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
                                <li><a href="alertas.php" style="font-weight: bold; color: #4a90d9;"><span class="bullet"></span><span>Panel de Alertas (IA)</span></a></li>
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

                <div class="main-display">
                    <?php if (isset($_GET['analisis']) && $_GET['analisis'] == 'exito'): ?>
                        <div class='alert-success'>✅ ¡Análisis Completado! La Inteligencia Artificial ha procesado la asistencia con éxito.</div>
                    <?php endif; ?>

                    <div class="header-alertas">
                        <h2>📊 PANEL DE ALERTAS INTELIGENTES</h2>
                        <a href="ejecutar_ia.php" class="btn-ia">⚡ Ejecutar Análisis IA</a>
                    </div>
                    
                    <div class="table-container">
                        <table class="sibia-table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Empleado</th>
                                    <th>Departamento</th>
                                    <th>Observación del Sistema</th>
                                    <th>Estado</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($alertas) > 0): ?>
                                    <?php foreach ($alertas as $alerta): ?>
                                        <tr>
                                            <td><?= date("d/m/Y", strtotime($alerta['fecha_analisis'])) ?></td>
                                            <td><strong><?= htmlspecialchars($alerta['nombres'] . " " . $alerta['apellidos']) ?></strong></td>
                                            <td><?= htmlspecialchars($alerta['departamento']) ?></td>
                                            <td><?= htmlspecialchars($alerta['tipo_observacion']) ?></td>
                                            <?php if ($alerta['estado_revision'] == 0): ?>
                                                <td class='badge-pendiente'>⚠️ Pendiente</td>
                                                <td><a href='marcar_revisado.php?id=<?= $alerta['id_analisis'] ?>' class='btn-revisar'>Revisar</a></td>
                                            <?php else: ?>
                                                <td><span class='badge-listo'>✔️ Revisado</span></td>
                                                <td>-</td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan='6' style='text-align:center; padding: 20px;'>No hay alertas generadas por la IA en este núcleo.</td></tr>
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