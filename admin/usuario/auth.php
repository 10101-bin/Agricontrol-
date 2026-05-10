<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../index.html');
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$nombre_usuario = $_SESSION['usuario_nombre'] ?? 'Usuario';
?>