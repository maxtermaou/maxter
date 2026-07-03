<?php
require_once 'seguridad.php';
require_once 'conexion.php';

// ==========================================
// 1. SEGURIDAD ESTRICTA (Solo SuperAdmin)
// ==========================================
$q_user = $conexion->prepare("SELECT rol FROM usuarios WHERE usuario = :user");
$q_user->execute([':user' => $_SESSION['usuario']]);
$datos_usuario = $q_user->fetch(PDO::FETCH_ASSOC);

$mi_rol = $datos_usuario['rol'] ?? 'Admin_Sede';

if ($mi_rol !== 'SuperAdmin') {
    // Si un infiltrado llega aquí, lo mandamos al tablero normal
    header("Location: dashboard.php");
    exit();
}

// ==========================================
// 2. MÉTRICAS NACIONALES (KPIs para la Sala de Mando)
// ==========================================

// 1. Total de Usuarios del Sistema (Cuentas creadas)
$q_usuarios = $conexion->query("SELECT COUNT(*) as total FROM usuarios");
$tot_usuarios = $q_usuarios->fetch(PDO::FETCH_ASSOC)['total'];

// 2. Total de Núcleos/Sedes registrados
$q_nucleos = $conexion->query("SELECT COUNT(*) as total FROM nucleos");
$tot_nucleos = $q_nucleos->fetch(PDO::FETCH_ASSOC)['total'];

// 3. Empleados Activos a Nivel Nacional (Toda la BD)
$q_personal = $conexion->query("SELECT COUNT(*) as total FROM empleados WHERE estado = 'Activo'");
$tot_personal = $q_personal->fetch(PDO::FETCH_ASSOC)['total'];

// 4. Últimas conexiones / Cuentas creadas (Para el resumen)
$q_ultimos_usr = $conexion->query("SELECT nombre_completo, rol, estado FROM usuarios ORDER BY id_usuario DESC LIMIT 5");
$ultimos_usuarios = $q_ultimos_usr->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIBIA - Panel de Administración Nacional</title>
    
    <style>
        /* ESTILOS SIBIA BASE */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; background-color: #f5f5f5; color: #333; }
        .page-container { min-height: 100vh; display: flex; flex-direction: column; }
        
        /* BANNER SUPERADMIN: Negro Azabache con borde Dorado */
        .header-banner { background: linear-gradient(135deg, #111 0%, #2c2c2c 50%, #000 100%); position: relative; overflow: hidden; border-bottom: 4px solid #f1c40f; }
        .header-banner::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            background-image: linear-gradient(45deg, rgba(255,255,255,0.03) 25%, transparent 25%), linear-gradient(-45deg, rgba(255,255,255,0.03) 25%, transparent 25%), linear-gradient(45deg, transparent 75%, rgba(255,255,255,0.03) 75%), linear-gradient(-45deg, transparent 75%, rgba(255,255,255,0.03) 75%);
            background-size: 40px 40px; opacity: 0.5;
        }
        .banner-content { display: flex; align-items: center; justify-content: space-between; max-width: 1200px; margin: 0 auto; padding: 10px 20px; position: relative; z-index: 1; }
        .logo-left img, .logo-right img { height: 120px; width: auto; filter: drop-shadow(0px 0px 5px rgba(255,255,255,0.2)); }
        .banner-center { flex: 1; display: flex; flex-direction: column; align-items: center; position: relative; }
        
        /* Aro dorado para la foto */
        .photo-group { width: 350px; height: 180px; border-radius: 50%; overflow: hidden; border: 4px solid #f1c40f; box-shadow: 0 4px 15px rgba(0,0,0,0.8); }
        .photo-group img { width: 100%; height: 100%; object-fit: cover; }
        
        /* Texto Dorado */
        .banner-text { background: linear-gradient(to bottom, #f1c40f, #d4ac0d); padding: 8px 25px; border-radius: 20px; margin-top: -25px; box-shadow: 0 2px 8px rgba(0,0,0,0.5); position: relative; z-index: 2; }
        .banner-text span { color: #111; font-size: 14px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }

        /* MAIN CONTENT & SIDEBAR */
        .main-content { display: flex; flex: 1; max-width: 1200px; margin: 0 auto; width: 100%; padding: 20px; gap: 20px; }
        .sidebar { flex: 0 0 220px; background: white; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); height: fit-content; }
        .menu ul { list-style: none; }
        .menu-item { border-bottom: 1px solid #eee; position: relative; }
        .menu-item:last-child { border-bottom: none; }
        .menu-item > a { display: flex; align-items: center; padding: 12px 15px; text-decoration: none; color: #666; font-size: 13px; transition: 0.2s; }
        .menu-item > a:hover { background-color: #f9f9f9; }
        
        /* Opción activa en Dorado y Negro */
        .menu-item > a.active { background-color: #fffdf5; color: #111; font-weight: bold; border-left: 4px solid #f1c40f;}

        /* Iconos del menú */
        .icon { width: 18px; height: 18px; margin-right: 10px; display: inline-block; position: relative; }
        
        .home-icon { background: linear-gradient(135deg, #111, #333); border-radius: 3px; }
        .home-icon::before { content: ''; position: absolute; top: 4px; left: 50%; transform: translateX(-50%); width: 0; height: 0; border-left: 5px solid transparent; border-right: 5px solid transparent; border-bottom: 6px solid #f1c40f; }
        .home-icon::after { content: ''; position: absolute; bottom: 3px; left: 50%; transform: translateX(-50%); width: 8px; height: 6px; background: #f1c40f; border-radius: 1px; }
        
        .user-icon { background: linear-gradient(135deg, #f1c40f, #d4ac0d); border-radius: 50%; }
        .user-icon::before { content: ''; position: absolute; top: 4px; left: 50%; transform: translateX(-50%); width: 6px; height: 6px; background: #111; border-radius: 50%; }
        .user-icon::after { content: ''; position: absolute; bottom: 3px; left: 50%; transform: translateX(-50%); width: 10px; height: 5px; background: #111; border-radius: 5px 5px 0 0; }

        .shield-icon { background: #333; border-radius: 3px 3px 10px 10px; border: 2px solid #f1c40f; }
        .shield-icon::before { content: '✓'; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #f1c40f; font-size: 10px; font-weight: bold;}

        /* Content Area */
        .content-area { flex: 1; display: flex; flex-direction: column; }
        .user-header { background: white; padding: 15px 20px; margin-bottom: 20px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; border-left: 5px solid #111; }
        
        .main-display { flex: 1; background: white; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 25px; display: flex; flex-direction: column; }

        /* Tarjetas KPI */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; width: 100%; margin-top: 15px; }
        .kpi-card { border-radius: 8px; padding: 25px 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; transition: transform 0.3s ease; }
        .kpi-card:hover { transform: translateY(-5px); box-shadow: 0 6px 15px rgba(0,0,0,0.15); }
        .kpi-card h3 { font-size: 13px; text-transform: uppercase; margin-bottom: 10px; letter-spacing: 1px; font-weight: bold; }
        .kpi-card .numero { font-size: 48px; font-weight: bold; line-height: 1; text-shadow: 1px 1px 2px rgba(0,0,0,0.1); }

        /* Colores de las tarjetas */
        .card-dark { background: linear-gradient(to bottom, #2c2c2c 0%, #111 100%); color: white; border: 1px solid #000; }
        .card-dark .numero { color: #f1c40f; } /* Número dorado sobre fondo negro */
        
        .card-gold { background: linear-gradient(to bottom, #f1c40f 0%, #d4ac0d 100%); color: #111; border: 1px solid #b8960b; }
        
        .card-light { background: #fdfdfd; color: #333; border: 1px solid #eee; border-top: 4px solid #111; }

        .welcome-text { text-align: center; margin: 40px 0 20px 0; color: #555; line-height: 1.6; }
        .welcome-text h2 { color: #111; margin-bottom: 10px; }

        /* Tabla minimalista para el resumen */
        .simple-table { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 20px; }
        .simple-table th { background: #f9f9f9; padding: 10px; text-align: left; border-bottom: 2px solid #ddd; color: #555; }
        .simple-table td { padding: 10px; border-bottom: 1px solid #eee; }
        .badge-activo { color: #2ecc71; font-weight: bold; }
        .badge-inactivo { color: #e74c3c; font-weight: bold; }

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
                    <div class="banner-text"><span>ADMINISTRACIÓN NACIONAL SIBIA</span></div>
                </div>
                <div class="logo-right"><img src="logo_unefa.png" alt="Logo SIBIA"></div>
            </div>
        </header>

        <main class="main-content">
            
            <aside class="sidebar">
                <nav class="menu">
                    <ul>
                        <li class="menu-item">
                            <a href="dashboard_superadmin.php" class="active">
                                <span class="icon home-icon"></span>
                                <span class="text">Tablero Principal</span>
                            </a>
                        </li>
                        
                        <li class="menu-item">
                            <a href="gestion_usuarios.php">
                                <span class="icon user-icon"></span>
                                <span class="text">Gestión de Usuarios</span>
                            </a>
                        </li>

                        <li class="menu-item">
                            <a href="#">
                                <span class="icon shield-icon"></span>
                                <span class="text">Visor de Personal (Lectura)</span>
                            </a>
                        </li>
                        
                        <li class="menu-item">
                            <a href="logout.php" style="color: #c0392b;">
                                <span class="icon" style="background: transparent; text-align: center;">🚪</span>
                                <b>Cerrar Sesión</b>
                            </a>
                        </li>
                    </ul>
                </nav>
            </aside>

            <section class="content-area">
                
                <div class="user-header">
                    <div style="text-align: left;">
                        <span style="font-size: 14px; font-weight: bold; color: #111;">NIVEL DE ACCESO:</span><br>
                        <span style="font-size: 12px; color: #d4ac0d; font-weight: bold;">SUPER-USUARIO (SOPORTE TÉCNICO)</span>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 14px; font-weight: bold; color: #333; display: block;">
                            <?= strtoupper(htmlspecialchars($_SESSION['nombre_completo'])) ?>
                        </span>
                        <span style="font-size: 11px; color: #888;">Operador del Sistema</span>
                    </div>
                </div>

                <div class="main-display">
                    <h2 style="font-size: 18px; color: #111; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 10px;">ESTADO GLOBAL DE LA PLATAFORMA SIBIA</h2>
                    
                    <div class="kpi-grid">
                        <div class="kpi-card card-dark">
                            <h3>🏢 Sedes (Núcleos)</h3>
                            <div class="numero"><?= $tot_nucleos ?></div>
                        </div>
                        <div class="kpi-card card-gold">
                            <h3>👨‍💻 Cuentas de Acceso</h3>
                            <div class="numero"><?= $tot_usuarios ?></div>
                        </div>
                        <div class="kpi-card card-light">
                            <h3>👥 Total Personal Biométrico</h3>
                            <div class="numero"><?= $tot_personal ?></div>
                        </div>
                    </div>

                    <div class="welcome-text">
                        <h2>Panel de Control Informático</h2>
                        <p>Bienvenido, <strong><?= htmlspecialchars($_SESSION['nombre_completo']) ?></strong>. Desde esta interfaz segura usted tiene el control total sobre los operadores de Recursos Humanos de cada sede.<br>Recuerde que cualquier modificación en la Gestión de Usuarios tiene impacto inmediato en el acceso a la plataforma.</p>
                    </div>

                    <div style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 20px;">
                        <h3 style="font-size: 14px; color: #666; margin-bottom: 10px;">Últimas Cuentas en el Sistema</h3>
                        <table class="simple-table">
                            <thead>
                                <tr>
                                    <th>Operador</th>
                                    <th>Nivel de Permiso</th>
                                    <th>Estado de la Cuenta</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($ultimos_usuarios as $usr): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($usr['nombre_completo']) ?></strong></td>
                                    <td><?= $usr['rol'] === 'SuperAdmin' ? 'Administrador Nacional' : 'RRHH - Sede' ?></td>
                                    <td>
                                        <span class="<?= $usr['estado'] == 'Activo' ? 'badge-activo' : 'badge-inactivo' ?>">
                                            <?= $usr['estado'] ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
</body>
</html>