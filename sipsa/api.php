<?php
require_once '../config/database.php';
header('Content-Type: application/json');

try {
    $producto = $_GET['producto'] ?? '';
    $departamento = $_GET['departamento'] ?? '';
    $municipio = $_GET['municipio'] ?? '';
    $ronda = $_GET['ronda'] ?? '';
    $buscar = trim($_GET['buscar'] ?? '');
    $fecha_desde = $_GET['fecha_desde'] ?? '';
    $fecha_hasta = $_GET['fecha_hasta'] ?? '';

    $fuentePrecio = columnaExisteApi($pdo, 'precio_producto', 'fuente_datos')
        ? 'pp.fuente_datos'
        : (columnaExisteApi($pdo, 'precio_producto', 'fuente') ? 'pp.fuente' : "'SIPSA'");
    $tieneRonda = columnaExisteApi($pdo, 'precio_producto', 'ronda');
    $campoRonda = $tieneRonda ? 'pp.ronda' : "''";
    $campoPresentacion = columnaExisteApi($pdo, 'precio_producto', 'presentacion') ? 'pp.presentacion' : 'NULL';
    $campoCantidadUnidad = columnaExisteApi($pdo, 'precio_producto', 'cantidad_unidad') ? 'pp.cantidad_unidad' : 'NULL';
    $campoUnidadBase = columnaExisteApi($pdo, 'precio_producto', 'unidad_base') ? 'pp.unidad_base' : 'NULL';
    $campoPrecioUnidad = columnaExisteApi($pdo, 'precio_producto', 'precio_por_unidad') ? 'pp.precio_por_unidad' : 'NULL';

    $sql = "
        SELECT
            pp.id_precio,
            t.fecha,
            p.nombre as producto,
            p.unidad_medida,
            d.nombre as departamento,
            m.nombre as municipio,
            $campoPresentacion AS presentacion,
            $campoCantidadUnidad AS cantidad_unidad,
            $campoUnidadBase AS unidad_base,
            pp.precio_promedio,
            $campoPrecioUnidad AS precio_por_unidad,
            $campoRonda AS ronda,
            $fuentePrecio AS fuente_datos
        FROM precio_producto pp
        JOIN producto p ON pp.id_producto = p.id_producto
        JOIN municipio m ON pp.id_municipio = m.id_municipio
        JOIN departamento d ON m.id_departamento = d.id_departamento
        JOIN tiempo t ON pp.id_tiempo = t.id_tiempo
        WHERE 1=1
    ";

    $params = [];

    if ($producto) {
        $sql .= " AND pp.id_producto = ?";
        $params[] = $producto;
    }
    if ($departamento) {
        $sql .= " AND d.id_departamento = ?";
        $params[] = $departamento;
    }
    if ($municipio) {
        $sql .= " AND pp.id_municipio = ?";
        $params[] = $municipio;
    }
    if ($ronda && $tieneRonda) {
        $sql .= " AND pp.ronda = ?";
        $params[] = $ronda;
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
        $sql .= " AND (p.nombre LIKE ? OR m.nombre LIKE ? OR d.nombre LIKE ?)";
        $textoBuscar = '%' . $buscar . '%';
        $params[] = $textoBuscar;
        $params[] = $textoBuscar;
        $params[] = $textoBuscar;
    }

    $sql .= " ORDER BY t.fecha DESC, d.nombre ASC, m.nombre ASC, p.nombre ASC LIMIT 1000";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($resultados);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function columnaExisteApi($pdo, $tabla, $columna) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$tabla, $columna]);
    return ((int) $stmt->fetch(PDO::FETCH_ASSOC)['total']) > 0;
}
?>
