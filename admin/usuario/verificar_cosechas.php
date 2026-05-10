<?php
require_once 'auth.php';
require_once '../../config/database.php';

$stmt = $pdo->prepare("
    SELECT 
        p.*, 
        u.nombre, u.apellido, u.correo,
        pr.nombre as producto_nombre,
        m.nombre as municipio_nombre
    FROM produccion p
    JOIN usuario u ON p.id_usuario = u.id_usuario
    JOIN producto pr ON p.id_producto = pr.id_producto
    JOIN municipio m ON p.id_municipio = m.id_municipio
    WHERE p.fecha_cosecha_estimada <= CURDATE() 
    AND p.cantidad_producida IS NULL
");

$stmt->execute();
$pendientes = $stmt->fetchAll();

foreach ($pendientes as $pendiente) {
    // Aquí llamas a tu función de correo con PHPMailer
    // enviarCorreo($pendiente['correo'], $asunto, $mensaje);
    
    echo "Recordatorio enviado a: " . $pendiente['correo'] . " - Cultivo: " . $pendiente['producto_nombre'] . "\n";
}

echo "Procesados " . count($pendientes) . " recordatorios.\n";
?>