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
    $filtro_sede = " AND e.id_nucleo = :id_nucleo";
    $parametros[':id_nucleo'] = $mi_nucleo;
}

$nombre_sede = "VISIÓN NACIONAL (Todas las Sedes)";
if ($mi_rol !== 'SuperAdmin' && $mi_nucleo) {
    $q_sede = $conexion->prepare("SELECT nombre_nucleo FROM nucleos WHERE id_nucleo = :id");
    $q_sede->execute([':id' => $mi_nucleo]);
    $res_sede = $q_sede->fetch(PDO::FETCH_ASSOC);
    if($res_sede) { $nombre_sede = strtoupper($res_sede['nombre_nucleo']); }
}

$mensaje = "";
$tipo_mensaje = "";
if (isset($_SESSION['mensaje_personal'])) {
    $mensaje = $_SESSION['mensaje_personal'];
    $tipo_mensaje = $_SESSION['tipo_mensaje_personal'];
    unset($_SESSION['mensaje_personal']);
    unset($_SESSION['tipo_mensaje_personal']);
}

$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$registros_por_pagina = 10; 
$offset = ($pagina_actual - 1) * $registros_por_pagina;

$sql_base = "FROM empleados e LEFT JOIN departamentos d ON e.id_departamento = d.id_departamento WHERE 1=1 $filtro_sede";

if ($busqueda != '') {
    $sql_base .= " AND (e.cedula LIKE :busqueda OR e.nombres LIKE :busqueda OR e.apellidos LIKE :busqueda OR d.nombre LIKE :busqueda)";
    $parametros[':busqueda'] = "%$busqueda%";
}

$sql_count = "SELECT COUNT(*) as total " . $sql_base;
$stmt_count = $conexion->prepare($sql_count);
$stmt_count->execute($parametros);
$total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

$sql_datos = "SELECT e.*, d.nombre AS departamento " . $sql_base . " ORDER BY e.estado ASC, e.nombres ASC LIMIT $offset, $registros_por_pagina";
$stmt_datos = $conexion->prepare($sql_datos);
$stmt_datos->execute($parametros);
$empleados = $stmt_datos->fetchAll(PDO::FETCH_ASSOC);

$filtros_url = "&busqueda=" . urlencode($busqueda);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIBIA - Listado de Personal</title>
    
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

        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .filter-form { background: #fafafa; padding: 10px 15px; border-radius: 5px; border: 1px solid #ccc; display: flex; gap: 10px; align-items: center; }
        .filter-input { border: 1px solid #ccc; padding: 8px 10px; font-size: 13px; border-radius: 3px; width: 250px; }
        .btn-buscar { background: #003366; color: white; border: none; padding: 9px 15px; font-size: 12px; font-weight: bold; border-radius: 3px; cursor: pointer; transition: 0.3s; }
        .btn-buscar:hover { background: #00509e; }
        .btn-limpiar { background: #6c757d; color: white; padding: 9px 15px; font-size: 12px; font-weight: bold; border-radius: 3px; text-decoration: none; transition: 0.3s; }
        .btn-limpiar:hover { background: #5a6268; }
        .btn-nuevo { background: linear-gradient(to bottom, #7cb342, #558b2f); color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: bold; font-size: 13px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); transition: 0.3s; display: flex; align-items: center; gap: 8px;}
        .btn-nuevo:hover { background: linear-gradient(to bottom, #8bc34a, #689f38); transform: translateY(-2px); }

        .btn-accion { padding: 5px 8px; border-radius: 3px; text-decoration: none; color: white; font-weight: bold; font-size: 11px; display: inline-block; transition: 0.2s; border: none; cursor: pointer; margin: 0 2px;}
        .btn-editar { background-color: #3498db; }
        .btn-editar:hover { background-color: #2980b9; }
        .btn-horario { background-color: #8e44ad; }
        .btn-horario:hover { background-color: #732d91; }
        .btn-inactivar { background-color: #e74c3c; }
        .btn-inactivar:hover { background-color: #c0392b; }
        .btn-activar { background-color: #2ecc71; }
        .btn-activar:hover { background-color: #27ae60; }

        .table-container { overflow-x: auto; border: 1px solid #ccc; border-radius: 5px; background: white; }
        .grades-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .grades-table thead { background-color: #d3d3d3; }
        .grades-table th { padding: 10px 8px; text-align: left; font-weight: bold; color: #333; border-bottom: 2px solid #999; text-transform: uppercase; }
        .grades-table td { padding: 10px 8px; text-align: left; border-bottom: 1px solid #ddd; vertical-align: middle; }
        .grades-table tbody tr:nth-child(odd) { background-color: #e8f5e9; }
        .grades-table tbody tr:nth-child(even) { background-color: #c8e6c9; }
        .grades-table tbody tr:hover { background-color: #a5d6a7; }

        .fila-inactiva { opacity: 0.6; background-color: #f9f9f9 !important; }
        .badge-activo { background-color: #155724; color: white; padding: 4px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; }
        .badge-inactivo { background-color: #721c24; color: white; padding: 4px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; }
        .badge-mod { background-color: #e67e22; color: white; padding: 3px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; }

        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; font-weight: bold; text-align: center; }
        .alert-exito { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

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
                            <a href="#" class="submenu-toggle active" style="font-weight: bold; color: #4a90d9; border-left: 4px solid #4a90d9; background-color: #f0f8ff;">
                                <span class="icon user-icon"></span><span class="text">Gestión de Personal</span>
                            </a>
                            <ul class="submenu open">
                                <li><a href="index.php" style="font-weight: bold; color: #4a90d9;"><span class="bullet"></span><span>Listado de Personal</span></a></li>
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
                    <h1 class="record-title">Listado General de Personal</h1>
                    
                    <?php if($mensaje != ""): ?>
                        <div class="alert alert-<?= $tipo_mensaje ?>"><?= $mensaje ?></div>
                    <?php endif; ?>

                    <div class="toolbar">
                        <form method="GET" action="" class="filter-form">
                            <span style="font-size: 13px; font-weight: bold; color: #333;">Buscar:</span>
                            <input type="text" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>" class="filter-input" placeholder="Cédula, Nombre, Apellido o Depto...">
                            <button type="submit" class="btn-buscar">FILTRAR</button>
                            <a href="index.php" class="btn-limpiar">LIMPIAR</a>
                        </form>
                        
                        <a href="registro_empleado.php" class="btn-nuevo">
                            <span>➕</span> Registrar Nuevo Empleado
                        </a>
                    </div>

                    <div class="table-container">
                        <table class="grades-table">
                            <thead>
                                <tr>
                                    <th>Cédula</th>
                                    <th>Nombres</th>
                                    <th>Apellidos</th>
                                    <th>Departamento</th>
                                    <th>Cargo</th>
                                    <th>Modalidad</th>
                                    <th style="text-align: center;">Estado</th>
                                    <th style="text-align: center;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($empleados) > 0): ?>
                                    <?php foreach ($empleados as $emp): ?>
                                        <tr class="<?= ($emp['estado'] == 'Inactivo') ? 'fila-inactiva' : '' ?>">
                                            <td><strong><?= htmlspecialchars($emp['cedula']) ?></strong></td>
                                            <td><?= htmlspecialchars($emp['nombres']) ?></td>
                                            <td><?= htmlspecialchars($emp['apellidos']) ?></td>
                                            <td><?= htmlspecialchars($emp['departamento'] ?? 'Sin asignar') ?></td>
                                            <td><?= htmlspecialchars($emp['cargo'] ?? 'N/A') ?></td>
                                            
                                            <td><span class="badge-mod"><?= htmlspecialchars($emp['modalidad'] ?? 'Diurno') ?></span></td>
                                            
                                            <td style="text-align: center;">
                                                <?php if ($emp['estado'] == 'Activo'): ?>
                                                    <span class="badge-activo">ACTIVO</span>
                                                <?php else: ?>
                                                    <span class="badge-inactivo">INACTIVO</span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td style="text-align: center; white-space: nowrap;">
                                                <a href="editar_empleado.php?id=<?= $emp['id_empleado'] ?>" class="btn-accion btn-editar" title="Editar datos">✏️</a>
                                                <a href="gestionar_horario.php?id=<?= $emp['id_empleado'] ?>" class="btn-accion btn-horario" title="Asignar Horario Flexible">⏰</a>
                                                <?php if ($emp['estado'] == 'Activo'): ?>
                                                    <a href="cambiar_estado.php?id=<?= $emp['id_empleado'] ?>&accion=inactivar" class="btn-accion btn-inactivar" title="Inactivar empleado" onclick="return confirm('¿Estás seguro de inactivar a este empleado?');">🚫</a>
                                                <?php else: ?>
                                                    <a href="cambiar_estado.php?id=<?= $emp['id_empleado'] ?>&accion=activar" class="btn-accion btn-activar" title="Re-activar empleado">✅</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" style="background-color: white; padding: 20px; text-align: center;">No se encontró personal con los criterios de búsqueda.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_paginas > 1): ?>
                    <div class="pagination">
                        <?php if ($pagina_actual > 1): ?>
                            <a href="?pagina=1<?= $filtros_url ?>" class="page-link">Primero</a>
                            <a href="?pagina=<?= $pagina_actual - 1 ?><?= $filtros_url ?>" class="page-link">Anterior</a>
                        <?php else: ?>
                            <span class="page-disabled">Primero</span>
                            <span class="page-disabled">Anterior</span>
                        <?php endif; ?>

                        <?php for ($i = max(1, $pagina_actual - 2); $i <= min($total_paginas, $pagina_actual + 2); $i++): ?>
                            <a href="?pagina=<?= $i ?><?= $filtros_url ?>" class="page-number <?= ($pagina_actual == $i) ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($pagina_actual < $total_paginas): ?>
                            <a href="?pagina=<?= $pagina_actual + 1 ?><?= $filtros_url ?>" class="page-link">Siguiente</a>
                            <a href="?pagina=<?= $total_paginas ?><?= $filtros_url ?>" class="page-link">Último</a>
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