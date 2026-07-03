<?php
session_start();
require_once 'conexion.php';

$mensaje = "";
$tipo_mensaje = "";
$paso = 1; // 1: Pedir correo, 2: Pedir clave maestra (SuperAdmin)
$correo_superadmin = "";

// Lógica del cerebro SIBIA
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // CASO 1: El usuario envió su correo
    if (isset($_POST['correo']) && !isset($_POST['password'])) {
        $correo = trim($_POST['correo']);
        
        $sql = "SELECT * FROM usuarios WHERE correo = :correo";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([':correo' => $correo]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if ($user['rol'] == 'SuperAdmin') {
                // ¡Alerta! Es el Dios del sistema, pedimos clave maestra
                $paso = 2;
                $correo_superadmin = $correo;
            } else {
                // Es un Admin normal (RRHH Sede)
                $mensaje = "✅ Te hemos enviado un enlace seguro a tu correo institucional para restablecer tu contraseña.";
                $tipo_mensaje = "exito";
            }
        } else {
            $mensaje = "❌ El correo ingresado no está registrado en el SIBIA.";
            $tipo_mensaje = "error";
        }
    } 
    // CASO 2: El SuperAdmin envió su Contraseña Maestra
    elseif (isset($_POST['password']) && isset($_POST['correo_superadmin'])) {
        $correo = $_POST['correo_superadmin'];
        $password = $_POST['password'];
        
        // Buscamos al SuperAdmin por su correo
        $sql = "SELECT * FROM usuarios WHERE correo = :correo AND rol = 'SuperAdmin'";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([':correo' => $correo]);
        $superadmin = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verificamos si existe y si la contraseña coincide (encriptada o normal)
        if ($superadmin && (password_verify($password, $superadmin['password']) || $password === $superadmin['password'])) {
            // Acceso concedido al panel nacional
            $_SESSION['usuario'] = $superadmin['usuario'];
            $_SESSION['nombre_completo'] = $superadmin['nombre_completo'];
            $_SESSION['rol'] = $superadmin['rol'];
            
            // ¡Lo mandamos al imperio Dorado/Oscuro!
            header("Location: dashboard_superadmin.php");
            exit();
        } else {
            $mensaje = "❌ Contraseña Maestra incorrecta. Acceso Denegado.";
            $tipo_mensaje = "error";
            $paso = 2; // Lo dejamos en el paso 2 para que lo intente de nuevo
            $correo_superadmin = $correo;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIBIA - Acceso Especial</title>
    
    <style>
        /* ========================================================
           AQUÍ ESTÁ EXACTAMENTE TU CSS, SOLO UNIFICADO
           ======================================================== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; background-color: #ffffff; min-height: 100vh; display: flex; flex-direction: column; }
        
        /* Header Styles */
        .header { background: linear-gradient(135deg, #4a90d9 0%, #87ceeb 50%, #4a90d9 100%); padding: 20px; position: relative; overflow: hidden; }
        .header::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            background-image: 
                linear-gradient(45deg, rgba(255,255,255,0.1) 25%, transparent 25%),
                linear-gradient(-45deg, rgba(255,255,255,0.1) 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, rgba(255,255,255,0.1) 75%),
                linear-gradient(-45deg, transparent 75%, rgba(255,255,255,0.1) 75%);
            background-size: 40px 40px; background-position: 0 0, 0 20px, 20px -20px, -20px 0px; opacity: 0.3;
        }
        .header-content { display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto; position: relative; z-index: 1; }
        .logo-left img, .logo-right img { width: 100px; height: auto; }
        .header-center { flex: 1; display: flex; flex-direction: column; align-items: center; margin: 0 20px; }
        .banner-image { width: 100%; max-width: 500px; height: 120px; overflow: hidden; border-radius: 8px; }
        .banner-image img { width: 100%; height: 100%; object-fit: cover; }
        .banner-text { background-color: rgba(255, 255, 255, 0.9); padding: 5px 20px; margin-top: -20px; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .banner-text span { color: #1a5490; font-weight: bold; font-size: 14px; text-transform: uppercase; }
        
        /* Main Content Styles */
        .main-content { flex: 1; display: flex; justify-content: center; align-items: flex-start; padding: 40px 20px; }
        .form-container { text-align: center; max-width: 600px; width: 100%; }
        .form-title { color: #5a6b3d; font-size: 20px; font-weight: bold; margin-bottom: 20px; letter-spacing: 1px; }
        .form-description { color: #333; font-size: 13px; margin-bottom: 25px; }
        .password-form { display: inline-block; text-align: left; }
        .form-group { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
        .form-group label { font-size: 13px; color: #333; font-weight: normal; white-space: nowrap; }
        .required { color: #c41e3a; }
        .input-group { display: flex; gap: 5px; }
        
        /* Ajuste: Input más ancho para el correo */
        .document-input { padding: 6px 10px; border: 1px solid #ccc; border-radius: 3px; font-size: 13px; width: 280px; }
        .example { font-size: 11px; color: #666; white-space: nowrap; }
        
        /* Botones */
        .button-group { display: flex; justify-content: center; gap: 10px; margin-top: 20px; }
        .btn { padding: 8px 20px; border: none; border-radius: 4px; font-size: 13px; font-weight: bold; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.2); transition: all 0.3s ease; text-decoration: none; text-align: center; }
        .btn-enviar { background: linear-gradient(to bottom, #7cb342 0%, #558b2f 100%); color: white; border: 1px solid #4a7c23; }
        .btn-enviar:hover { background: linear-gradient(to bottom, #8bc34a 0%, #689f38 100%); }
        .btn-volver { background: linear-gradient(to bottom, #e53935 0%, #c62828 100%); color: white; border: 1px solid #b71c1c; }
        .btn-volver:hover { background: linear-gradient(to bottom, #ef5350 0%, #d32f2f 100%); }
        
        /* Footer Styles */
        .footer { padding: 30px 20px; text-align: center; }
        .footer-content { margin-bottom: 20px; }
        .footer-text { font-size: 11px; color: #333; margin-bottom: 5px; }
        .footer-logo img { width: 80px; height: auto; }
        
        /* MIS ESTILOS PARA ALERTAS DE PHP */
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; font-weight: bold; text-align: center; }
        .alert-exito { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        @media (max-width: 768px) {
            .header-content { flex-direction: column; gap: 15px; }
            .logo-left img, .logo-right img { width: 70px; }
            .banner-image { height: 100px; }
            .form-group { flex-direction: column; align-items: flex-start; }
            .example { margin-left: 0; margin-top: 5px; }
            .document-input { width: 100%; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo-left">
                <img src="logo_gobierno.png" alt="Gobierno">
            </div>
            <div class="header-center">
                <div class="banner-image">
                    <img src="fondo_unefa.jpg" alt="Estudiantes UNEFA">
                </div>
                <div class="banner-text">
                    <span>EXCELENCIA EDUCATIVA ABIERTA AL PUEBLO</span>
                </div>
            </div>
            <div class="logo-right">
                <img src="logo_unefa.png" alt="UNEFA Logo">
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="form-container">
            <h1 class="form-title">RECUPERACIÓN / ACCESO ESPECIAL</h1>
            
            <?php if($mensaje != ""): ?>
                <div class="alert alert-<?= $tipo_mensaje ?>">
                    <?= $mensaje ?>
                </div>
            <?php endif; ?>

            <?php if($paso == 1): ?>
            
            <p class="form-description">
                Ingrese su Correo Electrónico Institucional seguido de un clic en el botón Continuar.
            </p>

            <form class="password-form" method="POST" action="">
                <div class="form-group">
                    <label for="correo">
                        <span class="required">*</span> Correo:
                    </label>
                    <div class="input-group">
                        <input type="email" name="correo" id="correo" class="document-input" required>
                    </div>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn btn-enviar">Continuar</button>
                    <a href="login.php" class="btn btn-volver" style="display:inline-block;">Volver al Login</a>
                </div>
            </form>

            <?php elseif($paso == 2): ?>
            
            <p class="form-description" style="color: #c41e3a; font-weight: bold; font-size: 15px;">
                ⚠️ ACCESO NACIONAL DETECTADO.<br>Ingrese su Contraseña Maestra de SuperAdmin.
            </p>

            <form class="password-form" method="POST" action="">
                <input type="hidden" name="correo_superadmin" value="<?= htmlspecialchars($correo_superadmin) ?>">
                
                <div class="form-group">
                    <label for="password">
                        <span class="required">*</span> Clave Maestra:
                    </label>
                    <div class="input-group">
                        <input type="password" name="password" id="password" class="document-input" placeholder="••••••••" required>
                    </div>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn btn-enviar">Entrar al Sistema</button>
                    <a href="acceso_especial.php" class="btn btn-volver" style="display:inline-block;">Cancelar</a>
                </div>
            </form>

            <?php endif; ?>

        </div>
    </main>

    <footer class="footer">
        <div class="footer-content">
            <p class="footer-text">
                Todos los logos en este sitio son propiedad, y están registrados, por sus respectivos dueños.
            </p>
            <p class="footer-text">
                Todo lo demás tiene <strong>Derechos de Propiedad &copy; 2013-2026</strong> por la <strong>Universidad Nacional Experimental Politécnica de la Fuerza Armada</strong>
            </p>
            <p class="footer-text">
                Todos los derechos reservados. Módulo SIBIA.
            </p>
        </div>
        <div class="footer-logo">
            <img src="carabobo.png" alt="200 Años Batalla de Carabobo">
        </div>
    </footer>
</body>
</html>