<?php
// 1. Conectamos a la base de datos
require_once 'conexion.php';

// 2. Verificamos si recibimos un ID válido por la URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    
    $id_analisis = $_GET['id'];

    try {
        // 3. Preparamos la orden SQL para actualizar el estado a 1 (Revisado)
        $sql = "UPDATE analisis_ia SET estado_revision = 1 WHERE id_analisis = :id";
        
        $stmt = $conexion->prepare($sql);
        // Vinculamos el ID de forma segura para evitar inyecciones SQL
        $stmt->bindParam(':id', $id_analisis, PDO::PARAM_INT);
        $stmt->execute();

        // 4. Redirigimos al usuario de vuelta al Dashboard
        header("Location: dashboard.php");
        exit(); // Detenemos la ejecución por seguridad

    } catch(PDOException $e) {
        echo "Error al actualizar el registro: " . $e->getMessage();
    }
} else {
    // Si alguien intenta entrar a esta página sin un ID, lo devolvemos
    header("Location: dashboard.php");
    exit();
}
?>