<?php
require_once 'auth.php';
require_once '../../config/database.php';

if (isset($_GET['id']) && isset($_GET['csrf_token'])) {
    if ($_GET['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die('Error de seguridad');
    }
    
    $usuario_id = $_SESSION['usuario_id'] ?? 1;
    
    $stmt = $pdo->prepare("DELETE FROM usuario_produccion WHERE id_produccion = ? AND id_usuario = ?");
    $stmt->execute([$_GET['id'], $usuario_id]);
    
    $stmt2 = $pdo->prepare("DELETE FROM produccion WHERE id_produccion = ? AND id_usuario = ?");
    $stmt2->execute([$_GET['id'], $usuario_id]);
    
    header('Location: index.php');
    exit();
}
?>