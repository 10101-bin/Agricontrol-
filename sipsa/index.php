<?php
require_once '../config/database.php';
session_start();

// Verificar autenticación (ajusta a tu sistema)
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['archivo_precios'])) {
    require_once 'procesar.php';
    
    $archivo = $_FILES['archivo_precios'];
    $tipo = $_POST['tipo_archivo'];
    
    if ($archivo['error'] === UPLOAD_ERR_OK) {
        $temp_dir = __DIR__ . '/temp_uploads/';
        if (!file_exists($temp_dir)) mkdir($temp_dir, 0777, true);
        
        $archivo_temp = $temp_dir . basename($archivo['name']);
        move_uploaded_file($archivo['tmp_name'], $archivo_temp);
        
        if ($tipo == 'zip') {
            $resultado = procesarZipPrecios($archivo_temp, $pdo);
        } else {
            $resultado = procesarPDFPrecios($archivo_temp, $pdo, $archivo['name']);
        }
        
        if ($resultado['exito']) {
            $mensaje = $resultado['mensaje'];
        } else {
            $error = $resultado['mensaje'];
        }
        
        // Limpiar archivo temporal
        if (file_exists($archivo_temp)) unlink($archivo_temp);
    } else {
        $error = "Error al subir el archivo.";
    }
}

// Estadísticas para mostrar
$total_precios = $pdo->query("SELECT COUNT(*) as total FROM precio_producto")->fetch()['total'];
$ultima_carga = $pdo->query("SELECT MAX(fecha) as fecha FROM tiempo")->fetch()['fecha'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPSA - Cargador de Precios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/estilo.css">
</head>
<body>

<nav class="navbar navbar-expand-lg mb-4">
    <div class="container">
        <span class="navbar-brand">📊 SIPSA - Precios Mayoristas</span>
        <div class="ms-auto">
            <span class="text-white me-3">Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?></span>
            <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
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
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo $ultima_carga ? date('d/m/Y', strtotime($ultima_carga)) : 'Ninguna'; ?></div>
                        <div class="stats-label">Última Carga</div>
                    </div>
                </div>
            </div>
            
            <div class="card-sembrio p-4">
                <h2 class="titulo-dashboard mb-4">📤 Cargar Precios SIPSA</h2>
                
                <?php if ($mensaje): ?>
                    <div class="alert alert-success"><?php echo $mensaje; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="card p-3 h-100" style="background: #f8f9fa; border: none; border-radius: 15px;">
                            <h5>📦 Archivo ZIP (Recomendado)</h5>
                            <p class="text-muted small">Descarga el ZIP completo desde el portal del DANE</p>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="tipo_archivo" value="zip">
                                <input type="file" name="archivo_precios" accept=".zip" class="form-control mb-2" required>
                                <button type="submit" class="btn-guardar w-100">Procesar ZIP</button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="card p-3 h-100" style="background: #f8f9fa; border: none; border-radius: 15px;">
                            <h5>📄 PDF Individual</h5>
                            <p class="text-muted small">Para actualizaciones puntuales o municipios sueltos</p>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="tipo_archivo" value="pdf">
                                <input type="file" name="archivo_precios" accept=".pdf" class="form-control mb-2" required>
                                <button type="submit" class="btn-guardar w-100">Procesar PDF</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <hr class="my-4">
                
                
                <div class="mt-4 d-flex gap-3">
                    <a href="consultar.php" class="btn-produccion">Consultar Precios</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>