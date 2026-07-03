<?php
session_start();
require_once 'seguridad.php';
require_once 'conexion.php';

// ==========================================
// 1. CEREBRO Y SEGURIDAD ESTRICTA (Solo SuperAdmin)
// ==========================================
$q_user = $conexion->prepare("SELECT rol, id_nucleo FROM usuarios WHERE usuario = :user");
$q_user->execute([':user' => $_SESSION['usuario']]);
$datos_usuario = $q_user->fetch(PDO::FETCH_ASSOC);

$mi_rol = $datos_usuario['rol'] ?? 'Admin_Sede';

// ¡EXPULSAR INTRUSOS! Si un usuario normal intenta entrar aquí, lo devolvemos al inicio.
if ($mi_rol !== 'SuperAdmin') {
    header("Location: dashboard.php");
    exit();
}

$mensaje = "";
$tipo_mensaje = "";

// ==========================================
// 2. PROCESAR CREACIÓN DE NUEVO USUARIO (PRG)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_usuario'])) {
    $usuario = trim($_POST['nuevo_usuario']);
    $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT); // Encriptación profesional
    $nombre = trim(strtoupper($_POST['nombre_completo']));
    $rol = $_POST['rol_usuario'];
    
    // Si es SuperAdmin, el núcleo es NULL (Nacional). Si es Admin_Sede, agarra el núcleo del selector.
    $id_nucleo = ($rol === 'SuperAdmin') ? NULL : $_POST['id_nucleo'];

    // Verificar si el nombre de usuario ya existe
    $check = $conexion->prepare("SELECT COUNT(*) FROM usuarios WHERE usuario = :usr");
    $check->execute([':usr' => $usuario]);

    if ($check->fetchColumn() > 0) {
        $_SESSION['mensaje_usr'] = "❌ Error: El nombre de usuario '$usuario' ya está en uso.";
        $_SESSION['tipo_mensaje_usr'] = "error";
    } else {
        $sql_insert = "INSERT INTO usuarios (usuario, password, nombre_completo, rol, id_nucleo, estado) 
                       VALUES (:usr, :pass, :nom, :rol, :nuc, 'Activo')";
        $stmt = $conexion->prepare($sql_insert);
        $exito = $stmt->execute([
            ':usr' => $usuario, ':pass' => $password, ':nom' => $nombre, 
            ':rol' => $rol, ':nuc' => $id_nucleo
        ]);

        if ($exito) {
            $_SESSION['mensaje_usr'] = "✅ ¡Usuario de sistema creado con éxito!";
            $_SESSION['tipo_mensaje_usr'] = "exito";
        } else {
            $_SESSION['mensaje_usr'] = "❌ Error interno al crear el usuario en la base de datos.";
            $_SESSION['tipo_mensaje_usr'] = "error";
        }
    }
    header("Location: gestion_usuarios.php");
    exit();
}

// ==========================================
// 3. CAMBIAR ESTADO DE USUARIO (Directo)
// ==========================================
// Inactivar (Con protección para que el SuperAdmin no se inactive a sí mismo por error)
if (isset($_GET['inactivar_id'])) {
    $id_inactivar = (int)$_GET['inactivar_id'];
    $conexion->query("UPDATE usuarios SET estado = 'Inactivo' WHERE id_usuario = $id_inactivar AND usuario != '{$_SESSION['usuario']}'");
    $_SESSION['mensaje_usr'] = "🚫 Acceso del usuario bloqueado correctamente.";
    $_SESSION['tipo_mensaje_usr'] = "exito";
    header("Location: gestion_usuarios.php");
    exit();
}
// Activar
if (isset($_GET['activar_id'])) {
    $id_activar = (int)$_GET['activar_id'];
    $conexion->query("UPDATE usuarios SET estado = 'Activo' WHERE id_usuario = $id_activar");
    $_SESSION['mensaje_usr'] = "✅ Acceso del usuario restaurado correctamente.";
    $_SESSION['tipo_mensaje_usr'] = "exito";
    header("Location: gestion_usuarios.php");
    exit();
}

// Leer y borrar mensajes
if (isset($_SESSION['mensaje_usr'])) {
    $mensaje = $_SESSION['mensaje_usr'];
    $tipo_mensaje = $_SESSION['tipo_mensaje_usr'];
    unset($_SESSION['mensaje_usr']);
    unset($_SESSION['tipo_mensaje_usr']);
}

// ==========================================
// 4. OBTENER DATOS PARA LAS VISTAS
// ==========================================
$q_nucs = $conexion->query("SELECT * FROM nucleos ORDER BY nombre_nucleo ASC");
$nucleos = $q_nucs->fetchAll(PDO::FETCH_ASSOC);

$q_usuarios = $conexion->query("
    SELECT u.*, n.nombre_nucleo 
    FROM usuarios u 
    LEFT JOIN nucleos n ON u.id_nucleo = n.id_nucleo 
    ORDER BY u.rol ASC, u.nombre_completo ASC
");
$lista_usuarios = $q_usuarios->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIBIA - Gestión de Usuarios</title>
    
    <style>
        /* ESTILOS SIBIA BASE */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; background-color: #f5f5f5; color: #333; }
        .page-container { min-height: 100vh; display: flex; flex-direction: column; }
        
        .header-banner { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #1e3c72 100%); position: relative; overflow: hidden; }
        .header-banner::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-image: linear-gradient(45deg, rgba(255,255,255,0.05) 25%, transparent 25%), linear-gradient(-45deg, rgba(255,255,255,0.05) 25%, transparent 25%), linear-gradient(45deg, transparent 75%, rgba(255,255,255,0.05) 75%), linear-gradient(-45deg, transparent 75%, rgba(255,255,255,0.05) 75%); background-size: 40px 40px; opacity: 0.3; }
        .banner-content { display: flex; align-items: center; justify-content: space-between; max-width: 1200px; margin: 0 auto; padding: 10px 20px; position: relative; z-index: 1; }
        .logo-left img, .logo-right img { height: 120px; width: auto; }
        .banner-center { flex: 1; display: flex; flex-direction: column; align-items: center; position: relative; }
        .photo-group { width: 350px; height: 180px; border-radius: 50%; overflow: hidden; border: 4px solid rgba(255,255,255,0.5); box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .photo-group img { width: 100%; height: 100%; object-fit: cover; }
        .banner-text { background: linear-gradient(to bottom, #d35400, #e67e22); padding: 8px 25px; border-radius: 20px; margin-top: -25px; box-shadow: 0 2px 8px rgba(0,0,0,0.3); position: relative; z-index: 2; }
        .banner-text span { color: white; font-size: 14px; font-weight: bold; text-shadow: 1px 1px 2px rgba(0,0,0,0.3); letter-spacing: 1px; }

        .main-content { display: flex; flex: 1; max-width: 1200px; margin: 0 auto; width: 100%; padding: 20px; gap: 20px; }
        .sidebar { flex: 0 0 220px; background: white; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); height: fit-content; }
        .menu ul { list-style: none; }
        .menu-item { border-bottom: 1px solid #eee; position: relative; }
        .menu-item:last-child { border-bottom: none; }
        .menu-item > a { display: flex; align-items: center; padding: 12px 15px; text-decoration: none; color: #666; font-size: 13px; transition: background-color 0.2s; }
        .menu-item > a:hover { background-color: #f9f9f9; }
        .menu-item > a.active { background-color: #f0f8ff; color: #1e3c72; font-weight: bold; border-left: 4px solid #1e3c72;}

        .icon { width: 18px; height: 18px; margin-right: 10px; display: inline-block; position: relative; }
        .home-icon { background: linear-gradient(135deg, #e74c3c, #c0392b); border-radius: 3px; }
        .home-icon::before { content: ''; position: absolute; top: 4px; left: 50%; transform: translateX(-50%); width: 0; height: 0; border-left: 5px solid transparent; border-right: 5px solid transparent; border-bottom: 6px solid white; }
        .home-icon::after { content: ''; position: absolute; bottom: 3px; left: 50%; transform: translateX(-50%); width: 8px; height: 6px; background: white; border-radius: 1px; }
        
        .user-icon { background: linear-gradient(135deg, #ff9800, #f57c00); border-radius: 50%; }
        .user-icon::before { content: ''; position: absolute; top: 4px; left: 50%; transform: translateX(-50%); width: 6px; height: 6px; background: white; border-radius: 50%; }
        .user-icon::after { content: ''; position: absolute; bottom: 3px; left: 50%; transform: translateX(-50%); width: 10px; height: 5px; background: white; border-radius: 5px 5px 0 0; }

        .star-icon { background: #8bc34a; border-radius: 50%; }
        .star-icon::before { content: '★'; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-size: 10px; }

        .submenu { display: none; background-color: #fafafa; }
        .menu-item:hover .submenu { display: block; }
        .submenu li a { padding: 10px 15px 10px 43px; font-size: 12px; color: #666; display: flex; align-items: center; text-decoration: none; }
        .submenu li a:hover { background-color: #f0f0f0; color: #1e3c72; }
        .bullet { width: 12px; height: 12px; background: linear-gradient(135deg, #8bc34a, #689f38); border-radius: 50%; margin-right: 8px; position: relative; }
        .bullet::after { content: ''; position: absolute; top: 3px; left: 4px; width: 4px; height: 4px; background: rgba(255,255,255,0.8); border-radius: 50%; }

        .content-area { flex: 1; display: flex; flex-direction: column; }
        
        /* Cabecera Negra y Oro para el SuperAdmin */
        .user-header { background: #111; padding: 15px 20px; margin-bottom: 20px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.3); display: flex; justify-content: space-between; align-items: center; color: white; border-bottom: 3px solid #f1c40f;}
        
        .record-section { background: white; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 30px; display: flex; flex-direction: column; margin-bottom: 20px;}
        .record-title { text-align: center; font-size: 20px; font-weight: bold; color: #333; margin-bottom: 25px; border-bottom: 2px solid #eee; padding-bottom: 10px;}

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }

        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group label { font-size: 13px; font-weight: bold; color: #333; }
        .form-group label span { color: #c0392b; } 
        
        .form-input, .form-select { width: 100%; padding: 10px 12px; font-size: 13px; border: 1px solid #ccc; border-radius: 4px; background-color: #fff; transition: border-color 0.3s; }
        .form-input:focus, .form-select:focus { outline: none; border-color: #1e3c72; box-shadow: 0 0 5px rgba(30, 60, 114, 0.3); }

        .btn-guardar { background: linear-gradient(to bottom, #1e3c72, #2a5298); color: white; padding: 12px 30px; border: none; border-radius: 5px; font-weight: bold; font-size: 13px; cursor: pointer; text-transform: uppercase; transition: 0.3s; box-shadow: 0 2px 4px rgba(0,0,0,0.2); width: 100%;}
        .btn-guardar:hover { background: linear-gradient(to bottom, #2a5298, #1e3c72); transform: translateY(-2px); }

        .table-container { overflow-x: auto; border: 1px solid #ccc; border-radius: 5px; background: white; }
        .grades-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .grades-table thead { background-color: #1e3c72; color: white;}
        .grades-table th { padding: 12px 10px; text-align: left; font-weight: bold; border-bottom: 2px solid #112244; text-transform: uppercase; }
        .grades-table td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; vertical-align: middle; }
        .grades-table tbody tr:nth-child(even) { background-color: #f2f6fa; }
        .grades-table tbody tr:hover { background-color: #e2eaf4; }

        .fila-inactiva { opacity: 0.6; background-color: #f9f9f9 !important; }
        .badge-super { background-color: #f1c40f; color: #333; padding: 4px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; }
        .badge-admin { background-color: #3498db; color: white; padding: 4px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; }

        .btn-accion { padding: 5px 8px; border-radius: 3px; text-decoration: none; color: white; font-weight: bold; font-size: 11px; display: inline-block; transition: 0.2s; border: none; cursor: pointer;}
        .btn-inactivar { background-color: #e74c3c; }
        .btn-inactivar:hover { background-color: #c0392b; }
        .btn-activar { background-color: #2ecc71; }
        .btn-activar:hover { background-color: #27ae60; }

        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; font-weight: bold; text-align: center; }
        .alert-exito { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .footer { text-align: center; padding: 20px; background: white; border-top: 1px solid #eee; margin-top: auto; }
        .footer p { font-size: 11px; color: #666; margin: 3px 0; }
        .footer strong { color: #333; font-weight: bold; }
    </style>
    
    <script>
        // Script para ocultar/mostrar el selector de núcleo según el rol
        function toggleNucleo() {
            var rol = document.getElementById('rol_usuario').value;
            var boxNucleo = document.getElementById('box_nucleo');
            var selectNucleo = document.getElementById('id_nucleo');
            
            if (rol === 'SuperAdmin') {
                boxNucleo.style.display = 'none';
                selectNucleo.required = false;
            } else {
                boxNucleo.style.display = 'block';
                selectNucleo.required = true;
            }
        }
        window.onload = toggleNucleo;
    </script>
</head>
<body>
    <div class="page-container">
        <header class="header-banner">
            <div class="banner-content">
                <div class="logo-left"><img src="gobierno.png" alt="Gobierno"></div>
                <div class="banner-center">
                    <div class="photo-group"><img src="fondo_unefa.jpg" alt="Estudiantes"></div>
                    <div class="banner-text"><span>SISTEMA NACIONAL SIBIA</span></div>
                </div>
                <div class="logo-right"><img src="logo.png" alt="Logo SIBIA"></div>
            </div>
        </header>

        <main class="main-content">
            <aside class="sidebar">
                <nav class="menu">
                    <ul>
                        <li class="menu-item"><a href="dashboard.php"><span class="icon home-icon"></span><span class="text">Inicio (Tablero)</span></a></li>
                        <li class="menu-item">
                            <a href="#" class="submenu-toggle active" style="font-weight: bold; color: #4a90d9; border-left: 4px solid #4a90d9; background-color: #f0f8ff;">
                                <span class="icon star-icon"></span><span class="text">De Interés</span>
                            </a>
                            <ul class="submenu" style="display: block;">
                                <li><a href="alertas.php"><span class="bullet"></span><span>Panel de Alertas (IA)</span></a></li>
                                <li><a href="index.php" style="font-weight: bold; color: #4a90d9;"><span class="bullet"></span><span>Listado de Personal</span></a></li>
                                
                                <li><a href="importar_horarios.php"><span class="bullet"></span><span>Importar Horarios (Excel)</span></a></li>
                                
                                <li><a href="registrar_permiso.php"><span class="bullet"></span><span>Permisos / Reposos</span></a></li>
                                <li><a href="historial_asistencia.php"><span class="bullet"></span><span>Historial de Asistencias</span></a></li>
                            </ul>
                        </li>
                        <li class="menu-item"><a href="#"><span class="icon user-icon"></span><span class="text">Cuenta</span></a>
                            <ul class="submenu">
                                <li><a href="logout.php" style="color: #c0392b;"><b>Cerrar Sesión</b></a></li>
                            </ul>
                        </li>
                    </ul>
                </nav>
            </aside>

            <section class="content-area">
                <div class="user-header">
                    <div style="text-align: left;">
                        <span style="font-size: 14px; font-weight: bold; color: #f1c40f;">ACCESO CLASIFICADO:</span><br>
                        <span style="font-size: 12px; font-weight: bold;">MÓDULO DE ADMINISTRACIÓN DEL SISTEMA</span>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 14px; font-weight: bold; display: block;"><?= strtoupper(htmlspecialchars($_SESSION['nombre_completo'])) ?></span>
                        <span style="font-size: 11px; color: #aaa;">Rol: SUPER-USUARIO NACIONAL</span>
                    </div>
                </div>

                <div class="record-section">
                    <h1 class="record-title">🔑 Crear Nueva Cuenta de Acceso</h1>
                    
                    <?php if($mensaje != ""): ?>
                        <div class="alert alert-<?= $tipo_mensaje ?>"><?= $mensaje ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="guardar_usuario" value="1">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label><span>*</span> Nombre Completo del Operador:</label>
                                <input type="text" name="nombre_completo" class="form-input" placeholder="Ej: Lic. Maria Perez" required>
                            </div>

                            <div class="form-group">
                                <label><span>*</span> Nombre de Usuario (Login):</label>
                                <input type="text" name="nuevo_usuario" class="form-input" placeholder="Ej: mperez_st" required>
                            </div>

                            <div class="form-group">
                                <label><span>*</span> Contraseña Temporal:</label>
                                <input type="password" name="password" class="form-input" placeholder="Mínimo 6 caracteres" required minlength="6">
                            </div>

                            <div class="form-group">
                                <label><span>*</span> Nivel de Privilegios (Rol):</label>
                                <select name="rol_usuario" id="rol_usuario" class="form-select" onchange="toggleNucleo()" required>
                                    <option value="Admin_Sede" selected>Administrador de Sede (Normal)</option>
                                    <option value="SuperAdmin">SuperUsuario (Acceso Total)</option>
                                </select>
                            </div>

                            <div class="form-group form-full-width" id="box_nucleo" style="background: #f8f9fa; padding: 15px; border-left: 4px solid #1e3c72; border-radius: 4px;">
                                <label style="color: #1e3c72;"><span>*</span> Asignar a Núcleo (Sede):</label>
                                <select name="id_nucleo" id="id_nucleo" class="form-select">
                                    <option value="" disabled selected>Seleccione la sede que este usuario va a controlar...</option>
                                    <?php foreach($nucleos as $n): ?>
                                        <option value="<?= $n['id_nucleo'] ?>"><?= htmlspecialchars($n['nombre_nucleo']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p style="font-size: 11px; color: #666; margin-top: 5px;">Nota: El usuario solo podrá ver y gestionar el personal y asistencia de la sede asignada.</p>
                            </div>
                        </div>

                        <div style="text-align: center; margin-top: 10px;">
                            <button type="submit" class="btn-guardar" style="width: auto;">+ Registrar Usuario en el Sistema</button>
                        </div>
                    </form>
                </div>

                <div class="record-section">
                    <h2 style="font-size: 16px; color: #333; margin-bottom: 15px; border-bottom: 2px solid #eee; padding-bottom: 10px;">Lista de Operadores del Sistema (Cuentas Creadas)</h2>
                    
                    <div class="table-container">
                        <table class="grades-table">
                            <thead>
                                <tr>
                                    <th>Nombre del Operador</th>
                                    <th>Usuario (Login)</th>
                                    <th>Rol</th>
                                    <th>Núcleo Asignado</th>
                                    <th style="text-align: center;">Acceso</th>
                                    <th style="text-align: center;">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lista_usuarios as $usr): ?>
                                    <tr class="<?= ($usr['estado'] == 'Inactivo') ? 'fila-inactiva' : '' ?>">
                                        <td><strong><?= htmlspecialchars($usr['nombre_completo']) ?></strong></td>
                                        <td><code><?= htmlspecialchars($usr['usuario']) ?></code></td>
                                        <td>
                                            <?php if ($usr['rol'] == 'SuperAdmin'): ?>
                                                <span class="badge-super">SuperUsuario</span>
                                            <?php else: ?>
                                                <span class="badge-admin">Admin Sede</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $usr['id_nucleo'] ? htmlspecialchars($usr['nombre_nucleo']) : '<strong>[ACCESO NACIONAL]</strong>' ?></td>
                                        
                                        <td style="text-align: center;">
                                            <span style="color: <?= ($usr['estado'] == 'Activo') ? '#2ecc71' : '#e74c3c' ?>; font-weight: bold;">
                                                <?= htmlspecialchars($usr['estado']) ?>
                                            </span>
                                        </td>
                                        
                                        <td style="text-align: center;">
                                            <?php if ($usr['usuario'] === $_SESSION['usuario']): ?>
                                                <span style="font-size: 10px; color: #aaa;">(Tú)</span>
                                            <?php else: ?>
                                                <?php if ($usr['estado'] == 'Activo'): ?>
                                                    <a href="?inactivar_id=<?= $usr['id_usuario'] ?>" class="btn-accion btn-inactivar" title="Bloquear Acceso" onclick="return confirm('¿Bloquear a este usuario? Ya no podrá entrar al sistema SIBIA.');">🚫 Bloquear</a>
                                                <?php else: ?>
                                                    <a href="?activar_id=<?= $usr['id_usuario'] ?>" class="btn-accion btn-activar" title="Restaurar Acceso">✅ Activar</a>
                                                <?php endif; ?>
                                            <?php endif; ?>
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