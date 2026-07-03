<?php
session_start();
require_once 'seguridad.php';
require_once 'conexion.php';

// Validar que vengan los datos por la URL
if (isset($_GET['id']) && isset($_GET['accion'])) {
    $id_empleado = (int)$_GET['id'];
    $accion = $_GET['accion'];
    
    // Verificamos quién es el usuario actual para la seguridad Multi-Sede
    $q_user = $conexion->prepare("SELECT rol, id_nucleo FROM usuarios WHERE usuario = :user");
    $q_user->execute([':user' => $_SESSION['usuario']]);
    $datos_usuario = $q_user->fetch(PDO::FETCH_ASSOC);
    $mi_rol = $datos_usuario['rol'] ?? 'Admin_Sede';
    $mi_nucleo = $datos_usuario['id_nucleo'];

    // Para mayor seguridad: verificamos que el empleado que quieren inactivar pertenece a SU núcleo
    $filtro_seguridad = "";
    $parametros = [':id_emp' => $id_empleado];
    if ($mi_rol !== 'SuperAdmin') {
        $filtro_seguridad = " AND id_nucleo = :id_nucleo";
        $parametros[':id_nucleo'] = $mi_nucleo;
    }

    $nuevo_estado = ($accion == 'inactivar') ? 'Inactivo' : 'Activo';

    // Ejecutamos el cambio ("Soft Delete")
    $sql_update = "UPDATE empleados SET estado = :nuevo_estado WHERE id_empleado = :id_emp $filtro_seguridad";
    $stmt = $conexion->prepare($sql_update);
    $parametros[':nuevo_estado'] = $nuevo_estado;
    
    if ($stmt->execute($parametros) && $stmt->rowCount() > 0) {
        $_SESSION['mensaje_personal'] = "✅ Estado del empleado actualizado a " . strtoupper($nuevo_estado) . " correctamente.";
        $_SESSION['tipo_mensaje_personal'] = "exito";
    } else {
        $_SESSION['mensaje_personal'] = "❌ No se pudo cambiar el estado. Permiso denegado o empleado no encontrado.";
        $_SESSION['tipo_mensaje_personal'] = "error";
    }
}

// Redirigir de vuelta al listado
header("Location: index.php");
exit();
?>