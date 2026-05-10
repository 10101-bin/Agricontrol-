<?php
// config.php
$host = 'localhost';
$user = 'root';
$password = 'guayabo21';
$database = 'agricontrol';

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Configurar charset para tildes y ñ
$conn->set_charset("utf8");
?>