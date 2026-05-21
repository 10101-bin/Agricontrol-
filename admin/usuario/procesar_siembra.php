<?php
require_once '../../config/database.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die('Error de seguridad');
    }

    asegurarColumnasUnidadProduccion($pdo);
    
    $usuario_id = $_SESSION['usuario_id'] ?? 1;
    $id_producto = $_POST['id_producto'];
    $fecha_siembra = $_POST['fecha_siembra'];

    $stmt = $pdo->prepare("SELECT dias_cosecha_estimados FROM producto WHERE id_producto = ?");
    $stmt->execute([$id_producto]);
    $producto = $stmt->fetch();
    $dias_cosecha_estimados = $producto && is_numeric($producto['dias_cosecha_estimados']) ? (int) $producto['dias_cosecha_estimados'] : 0;
    $fecha_cosecha_estimada = date('Y-m-d', strtotime("+{$dias_cosecha_estimados} days", strtotime($fecha_siembra)));

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
        $fecha_siembra,
        $fecha_cosecha_estimada
    ]);
    
    $id_produccion = $pdo->lastInsertId();
    
    $stmt2 = $pdo->prepare("INSERT INTO usuario_produccion (id_usuario, id_produccion) VALUES (?, ?)");
    $stmt2->execute([$usuario_id, $id_produccion]);
    
    header('Location: index.php');
    exit();
}

function asegurarColumnasUnidadProduccion($pdo) {
    $columnas = [
        'tipo_medida_siembra' => "VARCHAR(100) NOT NULL",
        'tipo_medida_cosecha' => "VARCHAR(100) NULL",
    ];

    foreach ($columnas as $columna => $definicion) {
        $stmt = $pdo->prepare("
            SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'produccion'
              AND COLUMN_NAME = ?
            LIMIT 1
        ");
        $stmt->execute([$columna]);
        $info = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$info) {
            continue;
        }

        $longitud = (int) ($info['CHARACTER_MAXIMUM_LENGTH'] ?? 0);
        if ($info['DATA_TYPE'] !== 'varchar' || $longitud < 100) {
            $pdo->exec("ALTER TABLE produccion MODIFY $columna $definicion");
        }
    }
}
?>
