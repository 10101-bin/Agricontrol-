<?php
require_once '../config/database.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['exito' => false, 'mensaje' => 'Sesion no valida.']);
    exit;
}

try {
    $tipo = $_POST['tipo'] ?? '';

    if ($tipo === 'precio') {
        $id = (int) ($_POST['id'] ?? 0);
        $precio = (int) round((float) ($_POST['valor'] ?? 0));
        $fuente = trim($_POST['fuente'] ?? 'SIPSA');

        if ($id <= 0 || $precio <= 0) {
            throw new Exception('Datos de precio invalidos.');
        }

        $precioUnidadSql = columnaExisteGuardar($pdo, 'precio_producto', 'precio_por_unidad')
            ? ', precio_por_unidad = CASE WHEN cantidad_unidad IS NOT NULL AND cantidad_unidad > 0 THEN ? / cantidad_unidad ELSE NULL END'
            : '';

        if (columnaExisteGuardar($pdo, 'precio_producto', 'fuente_datos')) {
            $stmt = $pdo->prepare('UPDATE precio_producto SET precio_promedio = ?, fuente_datos = ? WHERE id_precio = ?');
            if ($precioUnidadSql) {
                $stmt = $pdo->prepare('UPDATE precio_producto SET precio_promedio = ?, fuente_datos = ?' . $precioUnidadSql . ' WHERE id_precio = ?');
                $stmt->execute([$precio, $fuente, $precio, $id]);
            } else {
                $stmt->execute([$precio, $fuente, $id]);
            }
        } elseif (columnaExisteGuardar($pdo, 'precio_producto', 'fuente')) {
            $stmt = $pdo->prepare('UPDATE precio_producto SET precio_promedio = ?, fuente = ? WHERE id_precio = ?');
            if ($precioUnidadSql) {
                $stmt = $pdo->prepare('UPDATE precio_producto SET precio_promedio = ?, fuente = ?' . $precioUnidadSql . ' WHERE id_precio = ?');
                $stmt->execute([$precio, $fuente, $precio, $id]);
            } else {
                $stmt->execute([$precio, $fuente, $id]);
            }
        } else {
            $stmt = $pdo->prepare('UPDATE precio_producto SET precio_promedio = ? WHERE id_precio = ?');
            if ($precioUnidadSql) {
                $stmt = $pdo->prepare('UPDATE precio_producto SET precio_promedio = ?' . $precioUnidadSql . ' WHERE id_precio = ?');
                $stmt->execute([$precio, $precio, $id]);
            } else {
                $stmt->execute([$precio, $id]);
            }
        }

        echo json_encode(['exito' => true, 'mensaje' => 'Precio actualizado.']);
        exit;
    }

    if ($tipo === 'consumo') {
        $id = (int) ($_POST['id'] ?? 0);
        $cantidad = round((float) ($_POST['valor'] ?? 0), 2);
        $fuente = trim($_POST['fuente'] ?? 'DANE');

        if ($id <= 0 || $cantidad <= 0) {
            throw new Exception('Datos de consumo invalidos.');
        }

        $stmt = $pdo->prepare('UPDATE consumo_nacional SET cantidad_consumo = ?, fuente = ? WHERE id_consumo = ?');
        $stmt->execute([$cantidad, $fuente, $id]);

        echo json_encode(['exito' => true, 'mensaje' => 'Consumo actualizado.']);
        exit;
    }

    throw new Exception('Tipo de registro no valido.');
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['exito' => false, 'mensaje' => $e->getMessage()]);
}

function columnaExisteGuardar($pdo, $tabla, $columna) {
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
