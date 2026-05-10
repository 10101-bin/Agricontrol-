<?php
/**
 * Archivo de depuración para verificar PIN
 */
require_once '../config/database.php';

header('Content-Type: text/plain');

$database = new Database();
$conn = $database->getConnection();

$correo = $_POST['correo'] ?? $_GET['correo'] ?? 'jg336949@gmail.com';
$pin = $_POST['pin'] ?? $_GET['pin'] ?? '';

echo "=== DEPURACIÓN DE VERIFICACIÓN DE PIN ===\n\n";
echo "Correo: $correo\n";
echo "PIN ingresado: $pin\n\n";

// Buscar todos los registros de este correo
$sql = "SELECT * FROM verificacion_usuarios WHERE correo = :correo ORDER BY fecha_solicitud DESC";
$stmt = $conn->prepare($sql);
$stmt->execute([':correo' => $correo]);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($registros) > 0) {
    echo "Se encontraron " . count($registros) . " registro(s):\n\n";
    
    foreach ($registros as $i => $reg) {
        echo "--- REGISTRO " . ($i + 1) . " ---\n";
        echo "ID: {$reg['id']}\n";
        echo "Nombre: {$reg['nombre']}\n";
        echo "Correo: {$reg['correo']}\n";
        echo "PIN en BD: '{$reg['pin']}'\n";
        echo "Longitud PIN BD: " . strlen($reg['pin']) . "\n";
        echo "Verificada: " . ($reg['verificada'] ? 'SI' : 'NO') . "\n";
        echo "Intentos: {$reg['intentos']}\n";
        echo "Fecha expiración: {$reg['fecha_expiracion']}\n";
        echo "Fecha solicitud: {$reg['fecha_solicitud']}\n";
        
        if ($pin) {
            echo "¿Coincide con PIN ingresado? ";
            if ($reg['pin'] === $pin) {
                echo "SÍ \n";
            } else {
                echo "NO \n";
                echo "  Comparación: '" . $reg['pin'] . "' vs '" . $pin . "'\n";
            }
        }
        echo "\n";
    }
} else {
    echo "No se encontraron registros para el correo: $correo\n\n";
}

// Mostrar todos los registros pendientes
echo "=== TODOS LOS REGISTROS PENDIENTES ===\n";
$sql = "SELECT id, nombre, correo, pin, fecha_expiracion, verificada, intentos 
        FROM verificacion_usuarios 
        WHERE verificada = 0 
        ORDER BY fecha_solicitud DESC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$todos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($todos) > 0) {
    foreach ($todos as $reg) {
        $expirado = (new DateTime($reg['fecha_expiracion'])) < (new DateTime()) ? 'EXPIRADO' : 'VIGENTE';
        echo "ID: {$reg['id']} | Correo: {$reg['correo']} | PIN: {$reg['pin']} | $expirado | Intentos: {$reg['intentos']}\n";
    }
} else {
    echo "No hay registros pendientes\n";
}
?>