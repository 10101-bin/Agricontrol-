<?php
/**
 * Script de diagnóstico para PDFs
 * Sube un PDF y ve exactamente qué datos se extraen
 */

require_once '../config/database.php';
require_once '../vendor/autoload.php';
require_once 'procesar.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$diagnostico = [];
$pdf_texto_extraido = '';
$datos_parseados = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['pdf_diagnostico'])) {
    $archivo = $_FILES['pdf_diagnostico'];
    
    if ($archivo['error'] === UPLOAD_ERR_OK && $archivo['type'] === 'application/pdf') {
        $temp_dir = __DIR__ . '/temp_uploads/';
        if (!file_exists($temp_dir)) mkdir($temp_dir, 0777, true);
        
        $archivo_temp = $temp_dir . 'diagnostico_' . time() . '.pdf';
        move_uploaded_file($archivo['tmp_name'], $archivo_temp);
        
        try {
            // 1. Verificar archivo
            $diagnostico['archivo_existe'] = file_exists($archivo_temp);
            $diagnostico['archivo_size'] = filesize($archivo_temp);
            
            // 2. Intentar parsear
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($archivo_temp);
            $diagnostico['pdf_parseado'] = true;
            
            // 3. Extraer texto
            $pdf_texto_extraido = $pdf->getText();
            $diagnostico['texto_length'] = strlen($pdf_texto_extraido);
            $diagnostico['lineas_totales'] = count(explode("\n", $pdf_texto_extraido));
            
            // 4. Limpiar texto
            $texto_limpio = limpiarTextoExtraido($pdf_texto_extraido);
            $diagnostico['texto_limpio_length'] = strlen($texto_limpio);
            
            // 5. Parsear precios con función mejorada
            $datos_precios = parsearTablaPreciosAvanzado($texto_limpio);
            $diagnostico['precios_encontrados'] = count($datos_precios);
            $datos_parseados['precios'] = $datos_precios;
            
            // 6. Parsear consumo
            $datos_consumo = parsearTablaConsumo($texto_limpio);
            $diagnostico['consumos_encontrados'] = count($datos_consumo);
            $datos_parseados['consumos'] = $datos_consumo;
            
        } catch (Exception $e) {
            $diagnostico['error'] = $e->getMessage();
        }
        
        if (file_exists($archivo_temp)) unlink($archivo_temp);
    } else {
        $diagnostico['error'] = 'Por favor carga un archivo PDF válido';
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico de PDFs - SIPSA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; padding: 20px; }
        .card { border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .code-box { background: #f4f4f4; border-left: 4px solid #007bff; padding: 15px; border-radius: 4px; overflow-x: auto; max-height: 400px; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
    </style>
</head>
<body>
<div class="container">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1 class="mb-4">🔍 Diagnóstico de Lectura de PDFs</h1>
            <a href="index.php" class="btn btn-secondary mb-3">← Volver</a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card p-4">
                <h5>📤 Cargar PDF para Diagnóstico</h5>
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Selecciona un PDF</label>
                        <input type="file" name="pdf_diagnostico" class="form-control" accept=".pdf" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Analizar PDF</button>
                </form>
            </div>
        </div>

        <?php if (!empty($diagnostico)): ?>
        <div class="col-md-6">
            <div class="card p-4 mb-4">
                <h5>✓ Resultados del Análisis</h5>
                <table class="table table-sm">
                    <?php foreach ($diagnostico as $clave => $valor): ?>
                        <tr>
                            <td><strong><?php echo ucfirst(str_replace('_', ' ', $clave)); ?>:</strong></td>
                            <td>
                                <?php 
                                if ($clave === 'error') {
                                    echo '<span class="error">' . htmlspecialchars($valor) . '</span>';
                                } elseif (is_bool($valor)) {
                                    echo $valor ? '<span class="success">✓ Sí</span>' : '<span class="error">✗ No</span>';
                                } else {
                                    echo htmlspecialchars($valor);
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($pdf_texto_extraido)): ?>
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card p-4">
                <h5>📄 Texto Extraído del PDF (primeras 1000 caracteres)</h5>
                <div class="code-box">
                    <pre><?php echo htmlspecialchars(substr($pdf_texto_extraido, 0, 1000)); ?></pre>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card p-4">
                <h5>📄 Texto Limpio (primeras 1000 caracteres)</h5>
                <div class="code-box">
                    <pre><?php echo htmlspecialchars(substr(limpiarTextoExtraido($pdf_texto_extraido), 0, 1000)); ?></pre>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($datos_parseados)): ?>
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card p-4">
                <h5>💰 Datos Parseados - PRECIOS (Función Avanzada)</h5>
                <?php if (!empty($datos_parseados['precios']) && count($datos_parseados['precios']) > 0): ?>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Precio Mínimo</th>
                                <th>Precio Máximo</th>
                                <th>Promedio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($datos_parseados['precios'] as $precio): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($precio['producto']); ?></td>
                                    <td><?php echo number_format($precio['precio_minimo'], 2); ?></td>
                                    <td><?php echo number_format($precio['precio_maximo'], 2); ?></td>
                                    <td><strong><?php echo number_format($precio['precio_promedio'], 2); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="warning">⚠️ No se encontraron datos de precios</p>
                <?php endif; ?>
            </div>

            <div class="card p-4 mt-3">
                <h5>📊 Datos Parseados - CONSUMO</h5>
                <?php if (!empty($datos_parseados['consumos']) && count($datos_parseados['consumos']) > 0): ?>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Cantidad Consumo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($datos_parseados['consumos'] as $consumo): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($consumo['producto']); ?></td>
                                    <td><?php echo number_format($consumo['cantidad_consumo'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="warning">⚠️ No se encontraron datos de consumo</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
