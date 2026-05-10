<?php
require_once 'auth.php';
require_once '../../config/database.php';

if (isset($_GET['id'])) {
    $usuario_id = $_SESSION['usuario_id'];
    
    $stmt = $pdo->prepare("
        SELECT id_produccion, cantidad_producida, tipo_medida_cosecha, 
               factor_conversion, fecha_cosecha_real
        FROM produccion 
        WHERE id_produccion = ? AND id_usuario = ?
    ");
    $stmt->execute([$_GET['id'], $usuario_id]);
    echo json_encode($stmt->fetch());
}
?>