<?php
require_once 'auth.php';
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die('Error de seguridad');
    }
    
    $usuario_id = $_SESSION['usuario_id'];
    
    $stmt = $pdo->prepare("SELECT cantidad_sembrada FROM produccion WHERE id_produccion = ? AND id_usuario = ?");
    $stmt->execute([$_POST['id_produccion'], $usuario_id]);
    $siembra = $stmt->fetch();
    
    $rendimiento = null;
    if ($siembra && $siembra['cantidad_sembrada'] > 0) {
        $rendimiento = $_POST['cantidad_producida'] / $siembra['cantidad_sembrada'];
    }
    
    $stmt2 = $pdo->prepare("
        UPDATE produccion 
        SET cantidad_producida = ?, 
            tipo_medida_cosecha = ?,
            rendimiento = ?,
            fecha_cosecha_real = ?
        WHERE id_produccion = ? AND id_usuario = ?
    ");
    
    $stmt2->execute([
        $_POST['cantidad_producida'],
        $_POST['tipo_medida_cosecha'],
        $rendimiento,
        $_POST['fecha_cosecha_real'],
        $_POST['id_produccion'],
        $usuario_id
    ]);
    
    header('Location: index.php');
    exit();
}
?>