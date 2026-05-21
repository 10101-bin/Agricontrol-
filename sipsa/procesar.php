<?php
require_once '../config/database.php';

$autoload_path = __DIR__ . '/../vendor/autoload.php';

if (file_exists($autoload_path)) {
    require_once $autoload_path;
} else {
    die("Error: No se encontro vendor/autoload.php");
}

if (!class_exists('Smalot\PdfParser\Parser')) {
    die("Error: Clase Parser no encontrada");
}

function procesarZipPrecios($zip_path, $pdo, $tipo_datos) {
    if (!class_exists('ZipArchive')) {
        return ['exito' => false, 'mensaje' => 'Error: La extension ZIP de PHP no esta habilitada.'];
    }

    $zip = new ZipArchive();
    $temp_dir = __DIR__ . '/temp_uploads/extracted_' . uniqid('', true) . '/';

    if (!is_dir($temp_dir)) {
        mkdir($temp_dir, 0777, true);
    }

    if ($zip->open($zip_path) !== true) {
        limpiarDirectorio($temp_dir);
        return ['exito' => false, 'mensaje' => 'Error: No se pudo abrir el archivo ZIP.'];
    }

    $pdfs = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entrada = $zip->getNameIndex($i);
        if (!preg_match('/\.pdf$/i', $entrada)) {
            continue;
        }

        $nombre_seguro = basename(str_replace('\\', '/', $entrada));
        $destino = $temp_dir . uniqid('pdf_', true) . '_' . $nombre_seguro;

        $contenido = $zip->getFromIndex($i);
        if ($contenido === false) {
            continue;
        }

        file_put_contents($destino, $contenido);
        $pdfs[] = ['ruta' => $destino, 'nombre' => $nombre_seguro];
    }

    $zip->close();

    if (empty($pdfs)) {
        limpiarDirectorio($temp_dir);
        return ['exito' => false, 'mensaje' => 'No se encontraron PDFs dentro del ZIP.'];
    }

    $total_insertados = 0;
    $total_pdfs = 0;
    $errores = [];

    foreach ($pdfs as $pdf) {
        $resultado = procesarPDFPrecios($pdf['ruta'], $pdo, $pdf['nombre'], $tipo_datos);
        if ($resultado['exito']) {
            $total_pdfs++;
            $total_insertados += isset($resultado['insertados']) ? (int) $resultado['insertados'] : 0;
        } else {
            $errores[] = $pdf['nombre'] . ': ' . $resultado['mensaje'];
        }
    }

    limpiarDirectorio($temp_dir);

    $tipo_texto = ($tipo_datos === 'consumo') ? 'consumo nacional' : 'precios';
    $mensaje = "Procesados $total_pdfs PDFs de $tipo_texto. Registros nuevos: $total_insertados.";

    if (!empty($errores)) {
        $mensaje .= ' Errores: ' . implode(' | ', array_slice($errores, 0, 8));
        if (count($errores) > 8) {
            $mensaje .= ' y ' . (count($errores) - 8) . ' mas.';
        }
    }

    return ['exito' => true, 'mensaje' => $mensaje, 'insertados' => $total_insertados];
}

function procesarPDFPrecios($pdf_path, $pdo, $nombre_archivo, $tipo_datos) {
    try {
        if (!file_exists($pdf_path)) {
            return ['exito' => false, 'mensaje' => "Archivo PDF no encontrado: $pdf_path"];
        }

        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($pdf_path);
        $texto = limpiarTextoExtraido($pdf->getText());

        if (trim($texto) === '') {
            return ['exito' => false, 'mensaje' => 'No se pudo extraer texto del PDF.'];
        }

        $fecha = extraerFecha($nombre_archivo, $texto);
        $id_tiempo = obtenerIdTiempo($fecha, $pdo);

        if ($tipo_datos === 'consumo') {
            return procesarConsumo($texto, $nombre_archivo, $pdo, $id_tiempo);
        }

        return procesarPrecios($texto, $nombre_archivo, $pdo, $id_tiempo);
    } catch (Exception $e) {
        error_log("Error procesando PDF $nombre_archivo: " . $e->getMessage());
        return ['exito' => false, 'mensaje' => $e->getMessage()];
    }
}

function procesarPrecios($texto, $nombre_archivo, $pdo, $id_tiempo) {
    asegurarColumnasPrecioPresentacion($pdo);

    $municipio_nombre = extraerMunicipioDelNombre($nombre_archivo);

    if ($municipio_nombre === '') {
        $municipio_nombre = extraerMunicipioDelTexto($texto);
    }

    if ($municipio_nombre === '') {
        return ['exito' => false, 'mensaje' => 'No se pudo identificar el municipio.'];
    }

    $id_municipio = buscarIdPorNombre($pdo, 'municipio', 'id_municipio', 'nombre', $municipio_nombre);

    if (!$id_municipio) {
        return ['exito' => false, 'mensaje' => "Municipio '$municipio_nombre' no encontrado en la base de datos."];
    }

    $datos = parsearTablaPreciosAvanzado($texto);

    if (empty($datos)) {
        return ['exito' => false, 'mensaje' => 'No se encontraron precios en el PDF.'];
    }

    $contador = 0;
    $no_encontrados = [];

    foreach ($datos as $dato) {
        $id_producto = buscarIdProducto($pdo, $dato['producto']);

        if (!$id_producto) {
            $no_encontrados[] = $dato['producto'];
            continue;
        }

        if ((float) $dato['precio_promedio'] <= 0) {
            continue;
        }

        $stmt = $pdo->prepare("
            SELECT id_precio
            FROM precio_producto
            WHERE id_producto = ?
              AND id_municipio = ?
              AND id_tiempo = ?
              AND COALESCE(presentacion, '') = COALESCE(?, '')
              AND COALESCE(cantidad_unidad, 0) = COALESCE(?, 0)
              AND COALESCE(unidad_base, '') = COALESCE(?, '')
            LIMIT 1
        ");
        $stmt->execute([
            $id_producto,
            $id_municipio,
            $id_tiempo,
            $dato['presentacion'] ?? null,
            $dato['cantidad_unidad'] ?? null,
            $dato['unidad_base'] ?? null,
        ]);

        if ($stmt->fetch()) {
            continue;
        }

        insertarPrecioProducto($pdo, $id_producto, $id_municipio, $id_tiempo, $dato);

        $contador++;
    }

    $mensaje = "$contador precios registrados para $municipio_nombre.";
    if (!empty($no_encontrados)) {
        $mensaje .= ' Productos no encontrados: ' . implode(', ', array_slice(array_unique($no_encontrados), 0, 10));
    }

    return ['exito' => true, 'mensaje' => $mensaje, 'insertados' => $contador];
}

function insertarPrecioProducto($pdo, $id_producto, $id_municipio, $id_tiempo, $dato) {
    $columnas = ['id_producto', 'id_municipio', 'id_tiempo', 'precio_promedio'];
    $valores = [
        $id_producto,
        $id_municipio,
        $id_tiempo,
        (int) round((float) $dato['precio_promedio']),
    ];

    $extras = [
        'presentacion' => $dato['presentacion'] ?? null,
        'cantidad_unidad' => $dato['cantidad_unidad'] ?? null,
        'unidad_base' => $dato['unidad_base'] ?? null,
        'precio_por_unidad' => isset($dato['precio_por_unidad']) ? (int) round((float) $dato['precio_por_unidad']) : null,
    ];

    foreach ($extras as $columna => $valor) {
        if (columnaExiste($pdo, 'precio_producto', $columna)) {
            $columnas[] = $columna;
            $valores[] = $valor;
        }
    }

    if (columnaExiste($pdo, 'precio_producto', 'fuente_datos')) {
        $columnas[] = 'fuente_datos';
        $valores[] = 'SIPSA';
    } elseif (columnaExiste($pdo, 'precio_producto', 'fuente')) {
        $columnas[] = 'fuente';
        $valores[] = 'SIPSA';
    }

    $placeholders = implode(', ', array_fill(0, count($columnas), '?'));
    $sql = 'INSERT INTO precio_producto (' . implode(', ', $columnas) . ') VALUES (' . $placeholders . ')';

    $insert = $pdo->prepare($sql);
    $insert->execute($valores);
}

function procesarConsumo($texto, $nombre_archivo, $pdo, $id_tiempo) {
    $datos = parsearTablaConsumo($texto);

    if (empty($datos)) {
        return ['exito' => false, 'mensaje' => 'No se encontraron datos de consumo en el PDF.'];
    }

    $contador = 0;
    $no_encontrados = [];

    foreach ($datos as $dato) {
        $id_producto = buscarIdProducto($pdo, $dato['producto']);

        if (!$id_producto) {
            $no_encontrados[] = $dato['producto'];
            continue;
        }

        if ((float) $dato['cantidad_consumo'] <= 0) {
            continue;
        }

        $stmt = $pdo->prepare("
            SELECT id_consumo
            FROM consumo_nacional
            WHERE id_producto = ? AND id_tiempo = ?
            LIMIT 1
        ");
        $stmt->execute([$id_producto, $id_tiempo]);

        if ($stmt->fetch()) {
            continue;
        }

        $insert = $pdo->prepare("
            INSERT INTO consumo_nacional
                (id_producto, id_tiempo, cantidad_consumo, fuente)
            VALUES (?, ?, ?, 'DANE')
        ");
        $insert->execute([
            $id_producto,
            $id_tiempo,
            round((float) $dato['cantidad_consumo'], 2),
        ]);
        $contador++;
    }

    $mensaje = "$contador registros de consumo guardados.";
    if (!empty($no_encontrados)) {
        $mensaje .= ' Productos no encontrados: ' . implode(', ', array_slice(array_unique($no_encontrados), 0, 10));
    }

    return ['exito' => true, 'mensaje' => $mensaje, 'insertados' => $contador];
}

function limpiarDirectorio($directorio) {
    if (!is_dir($directorio)) {
        return;
    }

    $archivos = glob($directorio . '*');
    foreach ($archivos as $archivo) {
        if (is_dir($archivo)) {
            limpiarDirectorio($archivo . DIRECTORY_SEPARATOR);
        } elseif (is_file($archivo)) {
            unlink($archivo);
        }
    }

    rmdir($directorio);
}

function limpiarTextoExtraido($texto) {
    $texto = str_replace(["\r\n", "\r"], "\n", $texto);
    $texto = preg_replace("/[ \t\x{00A0}]+/u", ' ', $texto);
    $texto = preg_replace("/\n{3,}/", "\n\n", $texto);

    $lineas = explode("\n", $texto);
    $lineas = array_map('trim', $lineas);
    $lineas = array_filter($lineas, function ($linea) {
        return $linea !== '';
    });

    return implode("\n", $lineas);
}

function extraerFecha($nombre_archivo, $texto) {
    $fecha = extraerFechaDelNombre($nombre_archivo);
    if ($fecha !== '') {
        return $fecha;
    }

    $fecha = extraerFechaDelTexto($texto);
    if ($fecha !== '') {
        return $fecha;
    }

    return date('Y-m-d');
}

function extraerFechaDelNombre($nombre_archivo) {
    if (preg_match('/(\d{2})-(\d{2})-(\d{4})/u', $nombre_archivo, $m)) {
        return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
    }

    if (preg_match('/(\d{4})-(\d{2})-(\d{2})/u', $nombre_archivo, $m)) {
        return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
    }

    return '';
}

function extraerFechaDelTexto($texto) {
    if (preg_match('/(\d{1,2})\s+de\s+([a-zA-Z\x{00C0}-\x{017F}]+)\s+de\s+(\d{4})/u', $texto, $m)) {
        $meses = [
            'enero' => 1,
            'febrero' => 2,
            'marzo' => 3,
            'abril' => 4,
            'mayo' => 5,
            'junio' => 6,
            'julio' => 7,
            'agosto' => 8,
            'septiembre' => 9,
            'setiembre' => 9,
            'octubre' => 10,
            'noviembre' => 11,
            'diciembre' => 12,
        ];

        $mes_nombre = normalizarTexto($m[2]);
        if (isset($meses[$mes_nombre])) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], $meses[$mes_nombre], (int) $m[1]);
        }
    }

    if (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/u', $texto, $m)) {
        return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
    }

    return '';
}

function obtenerIdTiempo($fecha, $pdo) {
    $timestamp = strtotime($fecha);
    if ($timestamp === false) {
        $timestamp = time();
    }

    $anio = (int) date('Y', $timestamp);
    $mes = (int) date('n', $timestamp);
    $trimestre = (int) ceil($mes / 3);
    $fecha_mes = sprintf('%04d-%02d-01', $anio, $mes);

    $stmt = $pdo->prepare('SELECT id_tiempo FROM tiempo WHERE anio = ? AND mes = ? LIMIT 1');
    $stmt->execute([$anio, $mes]);
    $tiempo = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tiempo) {
        return $tiempo['id_tiempo'];
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO tiempo (anio, mes, trimestre, fecha) VALUES (?, ?, ?, ?)');
        $stmt->execute([$anio, $mes, $trimestre, $fecha_mes]);
    } catch (PDOException $e) {
        $stmt = $pdo->prepare('SELECT id_tiempo FROM tiempo WHERE anio = ? AND mes = ? LIMIT 1');
        $stmt->execute([$anio, $mes]);
        $tiempo = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($tiempo) {
            return $tiempo['id_tiempo'];
        }
        throw $e;
    }

    return $pdo->lastInsertId();
}

function extraerMunicipioDelNombre($nombre_archivo) {
    $nombre = preg_replace('/\.pdf$/i', '', basename($nombre_archivo));
    $nombre = preg_replace('/[-_ ]+\d{2}-\d{2}-\d{4}$/u', '', $nombre);
    $nombre = preg_replace('/[-_ ]+\d{4}-\d{2}-\d{2}$/u', '', $nombre);
    $nombre = preg_replace('/\([^)]*\)/u', '', $nombre);

    $partes = explode(',', $nombre);
    $municipio = trim($partes[0]);
    $municipio = preg_replace('/\s+/', ' ', $municipio);

    return $municipio ? $municipio : '';
}

function extraerMunicipioDelTexto($texto) {
    $lineas = explode("\n", $texto);

    foreach ($lineas as $linea) {
        if (preg_match('/^(.*?)(?:\s+-\s+)?precios\s+de\s+venta\s+mayorista/i', $linea, $m)) {
            $posible = trim($m[1]);
            if ($posible !== '') {
                return limpiarNombreMunicipio($posible);
            }
        }
    }

    if (preg_match('/Mercado\s+de\s+([a-zA-Z\x{00C0}-\x{017F}\s\.]+?)(?:\n|,|\.)/u', $texto, $m)) {
        return limpiarNombreMunicipio($m[1]);
    }

    return '';
}

function limpiarNombreMunicipio($nombre) {
    $nombre = preg_replace('/\([^)]*\)/u', '', $nombre);
    $nombre = preg_replace('/\b(central|mayorista|mercado|plaza|abastos|corabastos|cav)\b/iu', '', $nombre);
    $nombre = preg_replace('/\s+/', ' ', $nombre);
    return trim($nombre, " \t\n\r\0\x0B,.-");
}

function parsearTablaPreciosAvanzado($texto) {
    $lineas = explode("\n", $texto);
    $datos = [];
    $procesados = [];

    foreach ($lineas as $linea) {
        $linea = trim(preg_replace('/\s+/', ' ', $linea));

        if ($linea === '' || strlen($linea) < 8 || esLineaEncabezado($linea)) {
            continue;
        }

        $numeros = [];
        if (!preg_match_all('/\d+(?:[.,]\d{1,3})*/u', $linea, $coincidencias, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        foreach ($coincidencias[0] as $coincidencia) {
            $valor = convertirNumero($coincidencia[0]);
            if ($valor > 0) {
                $numeros[] = ['texto' => $coincidencia[0], 'valor' => $valor, 'pos' => $coincidencia[1]];
            }
        }

        if (count($numeros) < 2) {
            continue;
        }

        $valores_precio = array_slice(array_column($numeros, 'valor'), -4);
        $precio_min = min($valores_precio);
        $precio_max = max($valores_precio);
        $precio_promedio = array_sum($valores_precio) / count($valores_precio);

        if ($precio_min > $precio_max) {
            $tmp = $precio_min;
            $precio_min = $precio_max;
            $precio_max = $tmp;
        }

        if ($precio_min <= 0 || $precio_max <= 0 || $precio_max > 100000000) {
            continue;
        }

        $inicio_numeros = $numeros[count($numeros) - 2]['pos'];
        $texto_campos = substr($linea, 0, $inicio_numeros);
        $campos = extraerProductoPresentacionUnidad($texto_campos);
        $producto = $campos['producto'];

        if ($producto === '' || esLineaEncabezado($producto)) {
            continue;
        }

        $key = normalizarTexto($producto);
        if ($key === '' || isset($procesados[$key])) {
            continue;
        }

        $datos[] = [
            'producto' => $producto,
            'presentacion' => $campos['presentacion'],
            'cantidad_unidad' => $campos['cantidad_unidad'],
            'unidad_base' => $campos['unidad_base'],
            'precio_minimo' => $precio_min,
            'precio_maximo' => $precio_max,
            'precio_promedio' => $precio_promedio,
            'precio_por_unidad' => calcularPrecioPorUnidad($precio_promedio, $campos['cantidad_unidad']),
        ];
        $procesados[$key] = true;
    }

    return $datos;
}

function extraerProductoPresentacionUnidad($texto_campos) {
    $texto_campos = trim(preg_replace('/\s+/', ' ', $texto_campos));
    $presentaciones = '(Kilogramo|Bulto|Bolsa|Caja|Canastilla|Docena|Atado\/manojo|Atado|Manojo|Unidad|Libra|Tonelada|Carga|Racimo)';
    $unidades = '(Kilogramo|Kilogramos|Kilo|Kilos|Gramo|Gramos|Libra|Libras|Unidad|Unidades|Tonelada|Toneladas)';

    $resultado = [
        'producto' => limpiarNombreProducto($texto_campos),
        'presentacion' => null,
        'cantidad_unidad' => null,
        'unidad_base' => null,
    ];

    if (preg_match('/^(.*?)\s+' . $presentaciones . '\s+(\d+(?:[.,]\d+)?)\s+' . $unidades . '$/iu', $texto_campos, $m)) {
        $resultado['producto'] = limpiarNombreProducto($m[1]);
        $resultado['presentacion'] = normalizarEtiquetaUnidad($m[2]);
        $resultado['cantidad_unidad'] = convertirNumero($m[3]);
        $resultado['unidad_base'] = normalizarEtiquetaUnidad($m[4]);
        return $resultado;
    }

    if (preg_match('/^(.*?)\s+(\d+(?:[.,]\d+)?)\s+' . $unidades . '$/iu', $texto_campos, $m)) {
        $resultado['producto'] = limpiarNombreProducto($m[1]);
        $resultado['presentacion'] = normalizarEtiquetaUnidad($m[3]);
        $resultado['cantidad_unidad'] = convertirNumero($m[2]);
        $resultado['unidad_base'] = normalizarEtiquetaUnidad($m[3]);
        return $resultado;
    }

    if (preg_match('/^(.*?)\s+' . $presentaciones . '$/iu', $texto_campos, $m)) {
        $resultado['producto'] = limpiarNombreProducto($m[1]);
        $resultado['presentacion'] = normalizarEtiquetaUnidad($m[2]);
        return $resultado;
    }

    return $resultado;
}

function calcularPrecioPorUnidad($precio_promedio, $cantidad_unidad) {
    $cantidad = (float) $cantidad_unidad;
    if ($cantidad <= 0) {
        return null;
    }

    return ((float) $precio_promedio) / $cantidad;
}

function normalizarEtiquetaUnidad($valor) {
    $valor = trim(preg_replace('/\s+/', ' ', (string) $valor));
    if ($valor === '') {
        return null;
    }

    if (function_exists('mb_convert_case')) {
        return mb_convert_case($valor, MB_CASE_TITLE, 'UTF-8');
    }

    return ucwords(strtolower($valor));
}

function parsearTablaPrecios($texto) {
    return parsearTablaPreciosAvanzado($texto);
}

function parsearTablaConsumo($texto) {
    $lineas = explode("\n", $texto);
    $datos = [];
    $procesados = [];

    foreach ($lineas as $linea) {
        $linea = trim(preg_replace('/\s+/', ' ', $linea));

        if ($linea === '' || strlen($linea) < 5 || esLineaEncabezado($linea)) {
            continue;
        }

        if (!preg_match('/^(.+?)\s+(\d+(?:[.,]\d{1,3})*)\s*(?:ton|t|toneladas|kg|kilogramos|unidades)?\b/iu', $linea, $m)) {
            continue;
        }

        $producto = limpiarNombreProducto($m[1]);
        $cantidad = convertirNumero($m[2]);

        if ($producto === '' || $cantidad <= 0 || esLineaEncabezado($producto)) {
            continue;
        }

        $key = normalizarTexto($producto);
        if (isset($procesados[$key])) {
            continue;
        }

        $datos[] = [
            'producto' => $producto,
            'cantidad_consumo' => $cantidad,
        ];
        $procesados[$key] = true;
    }

    return $datos;
}

function buscarIdProducto($pdo, $nombre_producto) {
    return buscarIdPorNombre($pdo, 'producto', 'id_producto', 'nombre', $nombre_producto);
}

function columnaExiste($pdo, $tabla, $columna) {
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

function asegurarColumnasPrecioPresentacion($pdo) {
    $columnas = [
        'presentacion' => "VARCHAR(80) NULL",
        'cantidad_unidad' => "DECIMAL(10,2) NULL",
        'unidad_base' => "VARCHAR(80) NULL",
        'precio_por_unidad' => "DECIMAL(14,2) NULL",
    ];

    foreach ($columnas as $columna => $definicion) {
        if (!columnaExiste($pdo, 'precio_producto', $columna)) {
            $pdo->exec("ALTER TABLE precio_producto ADD COLUMN $columna $definicion");
        }
    }
}

function buscarIdPorNombre($pdo, $tabla, $id_columna, $nombre_columna, $nombre_buscado) {
    static $cache = [];

    $cache_key = $tabla . '.' . $nombre_columna;
    if (!isset($cache[$cache_key])) {
        $stmt = $pdo->query("SELECT $id_columna AS id, $nombre_columna AS nombre FROM $tabla");
        $cache[$cache_key] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $buscado = normalizarTexto($nombre_buscado);
    if ($buscado === '') {
        return null;
    }

    $mejor_id = null;
    $mejor_puntaje = 0;

    foreach ($cache[$cache_key] as $fila) {
        $nombre_bd = normalizarTexto($fila['nombre']);
        if ($nombre_bd === '') {
            continue;
        }

        $puntaje = 0;
        if ($nombre_bd === $buscado) {
            $puntaje = 100;
        } elseif (strpos($nombre_bd, $buscado) !== false || strpos($buscado, $nombre_bd) !== false) {
            $puntaje = 80;
        } else {
            similar_text($nombre_bd, $buscado, $porcentaje);
            $puntaje = $porcentaje;
        }

        if ($puntaje > $mejor_puntaje) {
            $mejor_puntaje = $puntaje;
            $mejor_id = $fila['id'];
        }
    }

    return ($mejor_puntaje >= 72) ? $mejor_id : null;
}

function limpiarNombreProducto($producto) {
    $producto = preg_replace('/\b(presentacion|unidades|precio|minimo|maximo|promedio|kg|kilogramo|kilogramos|gramo|gramos|libra|libras|bulto|bolsa|caja|canastilla|unidad|unidades|docena|atado|manojo|tonelada|toneladas)\b/iu', ' ', $producto);
    $producto = preg_replace('/\b\d+\s*(kg|kilogramo|kilogramos|g|gramos|lb|libras|unidades?)\b/iu', ' ', $producto);
    $producto = preg_replace('/[^a-zA-Z\x{00C0}-\x{017F}\s\-\(\)\/]/u', ' ', $producto);
    $producto = preg_replace('/\s+/', ' ', $producto);
    return trim($producto, " \t\n\r\0\x0B-/,.");
}

function esLineaEncabezado($linea) {
    return preg_match('/\b(dane|sipsa|boletin|diario|producto|presentacion|precio|minimo|maximo|promedio|fuente|mercado|mayorista|ronda|total|periodo|fecha|consumo|cantidad|pagina)\b/iu', $linea);
}

function convertirNumero($numero) {
    $numero = trim($numero);

    if ($numero === '') {
        return 0;
    }

    $tiene_punto = strpos($numero, '.') !== false;
    $tiene_coma = strpos($numero, ',') !== false;

    if ($tiene_punto && $tiene_coma) {
        $numero = str_replace('.', '', $numero);
        $numero = str_replace(',', '.', $numero);
    } elseif ($tiene_coma) {
        $partes = explode(',', $numero);
        if (strlen(end($partes)) === 3 && count($partes) === 2) {
            $numero = str_replace(',', '', $numero);
        } else {
            $numero = str_replace(',', '.', $numero);
        }
    } elseif ($tiene_punto) {
        $partes = explode('.', $numero);
        if (strlen(end($partes)) === 3) {
            $numero = str_replace('.', '', $numero);
        }
    }

    return (float) $numero;
}

function normalizarTexto($texto) {
    $texto = trim((string) $texto);

    if (function_exists('mb_strtolower')) {
        $texto = mb_strtolower($texto, 'UTF-8');
    } else {
        $texto = strtolower($texto);
    }

    $transliterado = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
    if ($transliterado !== false) {
        $texto = $transliterado;
    }

    $texto = preg_replace('/[^a-z0-9\s]/', ' ', $texto);
    $texto = preg_replace('/\s+/', ' ', $texto);

    return trim($texto);
}
?>
