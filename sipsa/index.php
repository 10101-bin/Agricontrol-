<?php
require_once '../config/database.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['archivo_precios'])) {
    require_once 'procesar.php';
    
    $archivo = $_FILES['archivo_precios'];
    $tipo_archivo = $_POST['tipo_archivo']; // 'zip' o 'pdf'
    $tipo_datos = $_POST['tipo_datos']; // 'precios' o 'consumo'
    
    if ($archivo['error'] === UPLOAD_ERR_OK) {
        $temp_dir = __DIR__ . '/temp_uploads/';
        if (!file_exists($temp_dir)) mkdir($temp_dir, 0777, true);
        
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $tipo_archivo_real = in_array($extension, ['zip', 'pdf'], true) ? $extension : '';

        if ($tipo_archivo_real === '') {
            $error = "Solo se permiten archivos PDF o ZIP.";
        } elseif ($tipo_archivo !== $tipo_archivo_real) {
            $error = "El tipo seleccionado no coincide con el archivo cargado.";
        } else {
            $archivo_temp = $temp_dir . uniqid('sipsa_', true) . '.' . $extension;
            move_uploaded_file($archivo['tmp_name'], $archivo_temp);

            if ($tipo_archivo_real == 'zip') {
                $resultado = procesarZipPrecios($archivo_temp, $pdo, $tipo_datos);
            } else {
                $resultado = procesarPDFPrecios($archivo_temp, $pdo, $archivo['name'], $tipo_datos);
            }

            if ($resultado['exito']) {
                $mensaje = $resultado['mensaje'];
            } else {
                $error = $resultado['mensaje'];
            }

            if (file_exists($archivo_temp)) unlink($archivo_temp);
        }
    } else {
        $error = "Error al subir el archivo.";
    }
}

// Estadísticas
$total_precios = $pdo->query("SELECT COUNT(*) as total FROM precio_producto")->fetch()['total'];
$total_consumo = $pdo->query("SELECT COUNT(*) as total FROM consumo_nacional")->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPSA - Cargador de Datos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/estilo.css">
</head>
<body>

<nav class="navbar navbar-expand-lg mb-4">
    <div class="container">
        <span class="navbar-brand">📊 SIPSA - Datos Agrícolas</span>
        <div class="ms-auto">
            <span class="text-white me-3"><?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?></span>
            <a href="../logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
        </div>
    </div>
</nav>

<div class="container mb-5">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <!-- Tarjetas de estadísticas -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo number_format($total_precios); ?></div>
                        <div class="stats-label">Precios Registrados</div>
                        <a href="consultar.php" class="btn btn-sm btn-outline-light mt-2">Ver Precios</a>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo number_format($total_consumo); ?></div>
                        <div class="stats-label">Registros de Consumo</div>
                        <a href="consultar_consumo.php" class="btn btn-sm btn-outline-light mt-2">Ver Consumo</a>
                    </div>
                </div>
            </div>
            
            <div class="card-sembrio p-4">
                <h2 class="titulo-dashboard mb-4">📤 Cargar Datos SIPSA</h2>
                
                <?php if ($mensaje): ?>
                    <div class="alert alert-success"><?php echo $mensaje; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo de datos</label>
                            <select name="tipo_datos" class="form-select" required>
                                <option value="precios">💰 Precios de productos</option>
                                <option value="consumo">📊 Consumo nacional</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo de archivo</label>
                            <select name="tipo_archivo" class="form-select" required>
                                <option value="zip">📦 Archivo ZIP (múltiples PDFs)</option>
                                <option value="pdf">📄 PDF individual</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Seleccionar archivo</label>
                        <input type="file" name="archivo_precios" accept=".zip,.pdf" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="btn-guardar w-100">Procesar Archivo</button>
                </form>
                
                <hr class="my-4">
                
                <div class="mt-3">
                    <h5>📋 Instrucciones:</h5>
                    <ol class="text-muted">
                        <li>Selecciona si vas a cargar <strong>Precios</strong> o <strong>Consumo Nacional</strong></li>
                        <li>Descarga el archivo ZIP desde el portal SIPSA del DANE</li>
                        <li>Selecciona el archivo y haz clic en "Procesar Archivo"</li>
                        <li>El sistema extrae automáticamente los datos</li>
                        <li>Los PDFs se eliminan después de procesar</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
