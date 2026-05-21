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
    $id = (int) ($_POST['id'] ?? 0);

    if ($id <= 0) {
        throw new Exception('Id invalido.');
    }

    if ($tipo === 'precio') {
        $stmt = $pdo->prepare('DELETE FROM precio_producto WHERE id_precio = ?');
        $stmt->execute([$id]);
        echo json_encode(['exito' => true, 'mensaje' => 'Precio eliminado.']);
        exit;
    }

    if ($tipo === 'consumo') {
        $stmt = $pdo->prepare('DELETE FROM consumo_nacional WHERE id_consumo = ?');
        $stmt->execute([$id]);
        echo json_encode(['exito' => true, 'mensaje' => 'Consumo eliminado.']);
        exit;
    }

    throw new Exception('Tipo de registro no valido.');
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['exito' => false, 'mensaje' => $e->getMessage()]);
}
?>
