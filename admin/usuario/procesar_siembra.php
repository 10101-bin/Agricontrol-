<?php
require_once '../../config/database.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die('Error de seguridad');
    }
    
    $usuario_id = $_SESSION['usuario_id'] ?? 1;
    
    // Insertar en produccion SIN id_tiempo
    $stmt = $pdo->prepare("
        INSERT INTO produccion (
            id_usuario, id_producto, id_municipio, cantidad_sembrada, 
            tipo_medida_siembra, fecha_siembra, fecha_cosecha_estimada,
            cantidad_producida, rendimiento
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, NULL)
    ");
    
    $stmt->execute([
        $usuario_id,
        $_POST['id_producto'],
        $_POST['id_municipio'],
        $_POST['cantidad_sembrada'],
        $_POST['tipo_medida_siembra'],
        $_POST['fecha_siembra'],
        $_POST['fecha_cosecha_estimada']
    ]);
    
    $id_produccion = $pdo->lastInsertId();
    
    $stmt2 = $pdo->prepare("INSERT INTO usuario_produccion (id_usuario, id_produccion) VALUES (?, ?)");
    $stmt2->execute([$usuario_id, $id_produccion]);
    
    header('Location: index.php');
    exit();
}
?>