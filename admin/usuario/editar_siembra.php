<?php
require_once 'auth.php';
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die('Error de seguridad');
    }
    
    $usuario_id = $_SESSION['usuario_id'];
    
    $stmt = $pdo->prepare("
        UPDATE produccion 
        SET cantidad_sembrada = ?, fecha_cosecha_estimada = ?
        WHERE id_produccion = ? AND id_usuario = ?
    ");
    
    $stmt->execute([
        $_POST['cantidad_sembrada'],
        $_POST['fecha_cosecha_estimada'],
        $_POST['id_produccion'],
        $usuario_id
    ]);
    
    header('Location: index.php');
    exit();
}
?>