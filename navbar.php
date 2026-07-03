<style>
    /* Estilos de la barra de navegación */
    .navbar {
        background-color: #003366; /* Azul institucional */
        overflow: hidden;
        display: flex;
        align-items: center;
        padding: 0 20px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        margin-bottom: 20px;
    }
    .navbar a {
        color: white;
        text-align: center;
        padding: 16px 20px;
        text-decoration: none;
        font-size: 16px;
        transition: background-color 0.3s;
    }
    .navbar a:hover {
        background-color: #00509e; /* Azul más claro al pasar el mouse */
    }
    .navbar .logo {
        font-weight: bold;
        font-size: 20px;
        margin-right: auto; /* Empuja los enlaces hacia la derecha */
        color: #ff9800; /* Tono naranja/dorado para resaltar */
        text-decoration: none;
    }
    /* Estilo para saber en qué página estamos (opcional pero recomendado) */
    .navbar a.active {
        background-color: #00509e;
        border-bottom: 3px solid #ff9800;
    }
</style>

<div class="navbar">
    <a href="dashboard.php" class="logo">HR-UNEFA IA</a>
    
    <a href="dashboard.php">Panel de Alertas (IA)</a>
    <a href="index.php">Listado de Personal</a>
    <a href="historial_asistencia.php">Historial de Asistencia</a> 
    <a href="registro_empleado.php">✚ Nuevo Empleado</a>
    <a href="registrar_permiso.php">📝 Permisos/Reposos</a>
    <a href="logout.php" style="margin-left: auto; background-color: #dc3545; border-radius: 4px; padding: 8px 15px; margin-top: 8px; margin-bottom: 8px; height: fit-content;">🚪 Salir</a>
</div>