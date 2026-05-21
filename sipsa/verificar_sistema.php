<?php
require_once '../config/database.php';
require_once '../vendor/autoload.php';
require_once 'procesar.php';

echo "=== VERIFICACIÓN DE BASE DE DATOS ===\n\n";

// Verificar productos
$stmt = $pdo->query("SELECT COUNT(*) as total FROM producto");
$total_productos = $stmt->fetch()['total'];
echo "Total productos en BD: $total_productos\n";

// Verificar municipios
$stmt = $pdo->query("SELECT COUNT(*) as total FROM municipio");
$total_municipios = $stmt->fetch()['total'];
echo "Total municipios en BD: $total_municipios\n";

// Verificar precios existentes
$stmt = $pdo->query("SELECT COUNT(*) as total FROM precio_producto");
$total_precios = $stmt->fetch()['total'];
echo "Total precios registrados: $total_precios\n";

// Mostrar algunos productos de ejemplo
echo "\n=== EJEMPLOS DE PRODUCTOS ===\n";
$stmt = $pdo->query("SELECT nombre FROM producto LIMIT 10");
while ($row = $stmt->fetch()) {
    echo "- " . $row['nombre'] . "\n";
}

// Mostrar algunos municipios de ejemplo
echo "\n=== EJEMPLOS DE MUNICIPIOS ===\n";
$stmt = $pdo->query("SELECT nombre FROM municipio LIMIT 10");
while ($row = $stmt->fetch()) {
    echo "- " . $row['nombre'] . "\n";
}

echo "\n=== FUNCIONES DISPONIBLES ===\n";
echo "parsearTablaPreciosAvanzado: " . (function_exists('parsearTablaPreciosAvanzado') ? 'SÍ' : 'NO') . "\n";
echo "extraerMunicipioDelTexto: " . (function_exists('extraerMunicipioDelTexto') ? 'SÍ' : 'NO') . "\n";
echo "extraerFechaDelTexto: " . (function_exists('extraerFechaDelTexto') ? 'SÍ' : 'NO') . "\n";
echo "Clase Parser: " . (class_exists('Smalot\PdfParser\Parser') ? 'SÍ' : 'NO') . "\n";
?>