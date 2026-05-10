<?php
require_once 'auth.php';
require_once '../../config/database.php';

if (isset($_GET['id_departamento'])) {
    $stmt = $pdo->prepare("SELECT id_municipio, nombre FROM municipio WHERE id_departamento = ? ORDER BY nombre");
    $stmt->execute([$_GET['id_departamento']]);
    $municipios = $stmt->fetchAll();
    echo json_encode($municipios);
}
?>