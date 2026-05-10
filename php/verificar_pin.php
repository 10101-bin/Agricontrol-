<?php
/**
 * Verificador de PIN - Versión con depuración
 */
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Función para registrar en log
function logDebug($mensaje) {
    error_log("[VERIFICAR_PIN] " . $mensaje);
}

try {
    logDebug("=== INICIO VERIFICACIÓN ===");
    
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception("Error de conexión a la base de datos");
    }
    
    $correo = trim($_POST['correo'] ?? '');
    $pin = trim($_POST['pin'] ?? '');
    
    logDebug("Correo: $correo");
    logDebug("PIN ingresado: $pin");
    
    if (empty($correo) || empty($pin)) {
        throw new Exception("Todos los campos son obligatorios");
    }
    
    if (!preg_match('/^\d{6}$/', $pin)) {
        throw new Exception("El PIN debe tener 6 dígitos");
    }
    
    // Buscar registro de verificación - USANDO CORREO EXACTO
    $sql = "SELECT * FROM verificacion_usuarios 
            WHERE correo = :correo AND verificada = 0 
            ORDER BY fecha_solicitud DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':correo' => $correo]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    logDebug("Registro encontrado: " . ($registro ? "SI" : "NO"));
    
    if (!$registro) {
        // Buscar si hay algún registro con este correo (incluso verificado)
        $sql2 = "SELECT * FROM verificacion_usuarios WHERE correo = :correo";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->execute([':correo' => $correo]);
        $cualquierRegistro = $stmt2->fetch(PDO::FETCH_ASSOC);
        
        if ($cualquierRegistro) {
            logDebug("Se encontró registro pero verificada = " . $cualquierRegistro['verificada']);
            throw new Exception("Esta cuenta ya fue verificada. Por favor inicia sesión.");
        } else {
            throw new Exception("No se encontró un registro de verificación para este correo");
        }
    }
    
    logDebug("ID: " . $registro['id']);
    logDebug("PIN en BD: " . $registro['pin']);
    logDebug("PIN ingresado: " . $pin);
    logDebug("¿Coinciden? " . ($registro['pin'] == $pin ? "SI" : "NO"));
    logDebug("Tipo de datos - PIN BD: " . gettype($registro['pin']) . ", PIN input: " . gettype($pin));
    
    // Verificar expiración
    $fechaExpiracion = new DateTime($registro['fecha_expiracion']);
    $ahora = new DateTime();
    
    if ($ahora > $fechaExpiracion) {
        logDebug("PIN EXPIRADO - Expiración: " . $registro['fecha_expiracion']);
        // Eliminar registro expirado
        $sql = "DELETE FROM verificacion_usuarios WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':id' => $registro['id']]);
        throw new Exception("El PIN ha expirado. Por favor regístrese nuevamente");
    }
    
    // Verificar intentos
    if ($registro['intentos'] >= 3) {
        logDebug("Demasiados intentos: " . $registro['intentos']);
        throw new Exception("Demasiados intentos fallidos. Por favor solicite un nuevo PIN");
    }
    
    // COMPARACIÓN ESTRICTA
    $pinValido = ($registro['pin'] === $pin);
    
    if (!$pinValido) {
        // Incrementar intentos
        $nuevosIntentos = $registro['intentos'] + 1;
        $sql = "UPDATE verificacion_usuarios SET intentos = :intentos WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':intentos' => $nuevosIntentos,
            ':id' => $registro['id']
        ]);
        
        $intentosRestantes = 3 - $nuevosIntentos;
        logDebug("PIN INCORRECTO - Intentos: $nuevosIntentos, Restantes: $intentosRestantes");
        throw new Exception("PIN incorrecto. Le quedan {$intentosRestantes} intentos");
    }
    
    logDebug("PIN CORRECTO - Activando usuario");
    
    // PIN correcto - Activar usuario
    $sql = "INSERT INTO usuario 
            (nombre, apellido, cedula, telefono, contrasenia, correo, sexo, id_produccion) 
            VALUES 
            (:nombre, :apellido, :cedula, :telefono, :contrasenia, :correo, :sexo, NULL)";
    $stmt = $conn->prepare($sql);
    $resultado = $stmt->execute([
        ':nombre' => $registro['nombre'],
        ':apellido' => $registro['apellido'],
        ':cedula' => $registro['cedula'],
        ':telefono' => $registro['telefono'],
        ':contrasenia' => $registro['contrasenia'],
        ':correo' => $registro['correo'],
        ':sexo' => $registro['sexo']
    ]);
    
    if (!$resultado) {
        throw new Exception("Error al activar la cuenta");
    }
    
    // Eliminar registro de verificación
    $sql = "DELETE FROM verificacion_usuarios WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $registro['id']]);
    
    logDebug("Usuario activado correctamente");

    // Crear sesión automática después de la verificación
    $_SESSION['usuario_id'] = $conn->lastInsertId();
    $_SESSION['usuario_nombre'] = $registro['nombre'];
    $_SESSION['usuario_apellido'] = $registro['apellido'];
    $_SESSION['usuario_correo'] = $registro['correo'];
    $_SESSION['usuario_cedula'] = $registro['cedula'];
    $_SESSION['usuario_sexo'] = $registro['sexo'];
    $_SESSION['login_time'] = time();
    $_SESSION['ultima_actividad'] = time();
    
    echo json_encode([
        'success' => true,
        'message' => "¡Cuenta verificada exitosamente! Ya estás conectado.",
        'redirect' => '../admin/usuario/index.php'
    ]);
    
} catch (Exception $e) {
    logDebug("ERROR: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>