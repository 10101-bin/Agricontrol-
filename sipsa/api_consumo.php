<?php
require_once '../config/database.php';
header('Content-Type: application/json');

try {
    $producto = $_GET['producto'] ?? '';
    $buscar = trim($_GET['buscar'] ?? '');
    $anio = $_GET['anio'] ?? '';
    $fecha_desde = $_GET['fecha_desde'] ?? '';
    $fecha_hasta = $_GET['fecha_hasta'] ?? '';

    $sql = "
        SELECT
            c.id_consumo,
            t.fecha,
            t.anio,
            t.mes,
            p.nombre AS producto,
            p.unidad_medida,
            c.cantidad_consumo,
            c.fuente
        FROM consumo_nacional c
        JOIN producto p ON c.id_producto = p.id_producto
        JOIN tiempo t ON c.id_tiempo = t.id_tiempo
        WHERE 1=1
    ";

    $params = [];

    if ($producto) {
        $sql .= " AND c.id_producto = ?";
        $params[] = $producto;
    }
    if ($anio) {
        $sql .= " AND t.anio = ?";
        $params[] = $anio;
    }
    if ($fecha_desde) {
        $sql .= " AND t.fecha >= ?";
        $params[] = $fecha_desde;
    }
    if ($fecha_hasta) {
        $sql .= " AND t.fecha <= ?";
        $params[] = $fecha_hasta;
    }
    if ($buscar !== '') {
        $sql .= " AND p.nombre LIKE ?";
        $params[] = '%' . $buscar . '%';
    }

    $sql .= " ORDER BY t.fecha DESC, p.nombre ASC LIMIT 1000";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
