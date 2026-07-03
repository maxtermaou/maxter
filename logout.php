<?php
session_start();
session_destroy(); // Destruye toda la memoria de la sesión
header("Location: login.php");
exit();
?>