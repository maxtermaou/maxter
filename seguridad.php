<?php
// Iniciamos el motor de sesiones de PHP
session_start();

// Preguntamos: "¿No existe la variable de sesión 'usuario'?"
if (!isset($_SESSION['usuario'])) {
    // Si no existe, lo mandamos al login y detenemos la carga de la página
    header("Location: login.php");
    exit();
}
?>