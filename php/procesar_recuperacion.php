<?php


require_once '../config/database.php';
require_once 'config_email.php';


class ProcesadorRecuperacion {
    
    private $conn;
    private $maxIntentos = 3;
    
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        
        if (!$this->conn) {
            $this->sendErrorResponse("Error de conexión a la base de datos");
        }
    }
    
    public function procesarRecuperacion() {
        try {
            // Verificar método de solicitud
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->sendErrorResponse("Método no permitido");
            }
            
            // Obtener acción
            $accion = $_POST['accion'] ?? '';
            
            switch ($accion) {
                case 'solicitar':
                    $this->solicitarRecuperacion();
                    break;
                case 'verificar':
                    $this->verificarCodigo();
                    break;
                case 'cambiar':
                    $this->cambiarContrasenia();
                    break;
                default:
                    $this->sendErrorResponse("Acción no válida");
            }
            
        } catch (Exception $e) {
            error_log("Error en recuperación: " . $e->getMessage());
            $this->sendErrorResponse($e->getMessage());
        }
    }
    
    
    private function solicitarRecuperacion() {
        $correo = $this->sanitizarCorreo($_POST['correo'] ?? '');
        
        if (empty($correo)) {
            $this->sendErrorResponse("Correo electrónico requerido");
        }
        
        // Verificar si el correo existe
        $usuario = $this->buscarUsuario($correo);
        
        if (!$usuario) {
            $this->sendErrorResponse("No existe una cuenta con este correo electrónico");
        }
        
        // Verificar intentos previos
        $this->verificarIntentosRecuperacion($correo);
        
        // Generar código temporal de 6 dígitos
        $codigo = $this->generarCodigo();
        
        // Encriptar código temporal
        $codigoEncriptado = password_hash($codigo, PASSWORD_DEFAULT);
        
        // Guardar en base de datos
        $fechaExpiracion = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        
        // Eliminar registros anteriores no usados
        $sql = "DELETE FROM recuperacion_contrasenia 
                WHERE correo = :correo AND usada = 0";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':correo' => $correo]);
        
        // Insertar nuevo registro
        $sql = "INSERT INTO recuperacion_contrasenia 
                (correo, contrasenia_temporal, fecha_expiracion) 
                VALUES 
                (:correo, :codigo, :expiracion)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':correo' => $correo,
            ':codigo' => $codigoEncriptado,
            ':expiracion' => $fechaExpiracion
        ]);
        
        // Enviar correo con código
        if ($this->enviarCorreoRecuperacion($correo, $usuario['nombre'], $codigo)) {
            $this->sendSuccessResponse(
                "Se ha enviado un código de recuperación a su correo electrónico",
                ['correo' => $correo]
            );
        } else {
            $this->sendErrorResponse("Error al enviar el correo de recuperación");
        }
    }
    
    private function verificarCodigo() {
        $correo = $this->sanitizarCorreo($_POST['correo'] ?? '');
        $codigo = $_POST['codigo'] ?? '';
        
        if (empty($correo) || empty($codigo)) {
            $this->sendErrorResponse("Todos los campos son obligatorios");
        }
        
        // Buscar registro de recuperación
        $registro = $this->buscarRegistroRecuperacion($correo);
        
        if (!$registro) {
            $this->sendErrorResponse("No hay una solicitud de recuperación activa");
        }
        
        // Verificar expiración
        $fechaExpiracion = new DateTime($registro['fecha_expiracion']);
        $ahora = new DateTime();
        
        if ($ahora > $fechaExpiracion) {
            $this->eliminarRegistroExpirado($correo);
            $this->sendErrorResponse("El código ha expirado. Por favor solicite uno nuevo");
        }
        
        // Verificar intentos
        if ($registro['intentos'] >= $this->maxIntentos) {
            $this->sendErrorResponse("Demasiados intentos fallidos. Por favor solicite un nuevo código");
        }
        
        // Verificar código
        if (!password_verify($codigo, $registro['contrasenia_temporal'])) {
            $this->registrarIntentoFallido($registro['id']);
            $intentosRestantes = $this->maxIntentos - ($registro['intentos'] + 1);
            $this->sendErrorResponse("Código incorrecto. Le quedan {$intentosRestantes} intentos");
        }
        
        // Código correcto - Marcar como usado temporalmente
        $sql = "UPDATE recuperacion_contrasenia 
                SET usada = 1 
                WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $registro['id']]);
        
        // Guardar en sesión que está autorizado para cambiar contraseña
        session_start();
        $_SESSION['recuperacion_autorizada'] = true;
        $_SESSION['recuperacion_correo'] = $correo;
        $_SESSION['recuperacion_id'] = $registro['id'];
        
        $this->sendSuccessResponse(
            "Código verificado correctamente. Proceda a cambiar su contraseña",
            ['autorizado' => true]
        );
    }
    
    
    private function cambiarContrasenia() {
        session_start();
        
        // Verificar autorización
        if (!isset($_SESSION['recuperacion_autorizada']) || !$_SESSION['recuperacion_autorizada']) {
            $this->sendErrorResponse("No autorizado. Debe verificar su identidad primero");
        }
        
        $correo = $_SESSION['recuperacion_correo'];
        $nuevaContrasenia = $_POST['nueva_contrasenia'] ?? '';
        $confirmarContrasenia = $_POST['confirmar_contrasenia'] ?? '';
        
        // Validar contraseña
        if (empty($nuevaContrasenia) || empty($confirmarContrasenia)) {
            $this->sendErrorResponse("Todos los campos son obligatorios");
        }
        
        if ($nuevaContrasenia !== $confirmarContrasenia) {
            $this->sendErrorResponse("Las contraseñas no coinciden");
        }
        
        // Validar fortaleza
        $this->validarFortalezaPassword($nuevaContrasenia);
        
        // Encriptar nueva contraseña
        $contraseniaEncriptada = password_hash($nuevaContrasenia, PASSWORD_DEFAULT);
        
        // Actualizar contraseña en base de datos
        $sql = "UPDATE usuario SET contrasenia = :contrasenia WHERE correo = :correo";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':contrasenia' => $contraseniaEncriptada,
            ':correo' => $correo
        ]);
        
        // Eliminar registro de recuperación
        $sql = "DELETE FROM recuperacion_contrasenia WHERE correo = :correo";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':correo' => $correo]);
        
        // Limpiar sesión
        unset($_SESSION['recuperacion_autorizada']);
        unset($_SESSION['recuperacion_correo']);
        unset($_SESSION['recuperacion_id']);
        
        $this->sendSuccessResponse("Contraseña actualizada exitosamente. Ya puede iniciar sesión");
    }
    
    private function buscarUsuario($correo) {
        $sql = "SELECT id_usuario, nombre, apellido FROM usuario WHERE correo = :correo AND estado = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':correo' => $correo]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function verificarIntentosRecuperacion($correo) {
        $sql = "SELECT COUNT(*) as total FROM recuperacion_contrasenia 
                WHERE correo = :correo AND usada = 0 
                AND fecha_solicitud > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':correo' => $correo]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($resultado['total'] >= 3) {
            $this->sendErrorResponse("Ha excedido el límite de solicitudes. Espere 1 hora para volver a intentar");
        }
    }
    
    /**
     * Busca registro de recuperación activo
     */
    private function buscarRegistroRecuperacion($correo) {
        $sql = "SELECT * FROM recuperacion_contrasenia 
                WHERE correo = :correo AND usada = 0 
                ORDER BY fecha_solicitud DESC LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':correo' => $correo]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function registrarIntentoFallido($id) {
        $sql = "UPDATE recuperacion_contrasenia 
                SET intentos = intentos + 1 
                WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
    }
    
    
    private function eliminarRegistroExpirado($correo) {
        $sql = "DELETE FROM recuperacion_contrasenia WHERE correo = :correo AND usada = 0";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':correo' => $correo]);
    }
    

    private function generarCodigo() {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    private function validarFortalezaPassword($password) {
        if (strlen($password) < 8) {
            throw new Exception("La contraseña debe tener mínimo 8 caracteres");
        }
        if (!preg_match('/[A-Z]/', $password)) {
            throw new Exception("La contraseña debe tener al menos una mayúscula");
        }
        if (!preg_match('/[a-z]/', $password)) {
            throw new Exception("La contraseña debe tener al menos una minúscula");
        }
        if (!preg_match('/[0-9]/', $password)) {
            throw new Exception("La contraseña debe tener al menos un número");
        }
        if (!preg_match('/[@$!%*?&]/', $password)) {
            throw new Exception("La contraseña debe tener al menos un carácter especial (@$!%*?&)");
        }
    }
    
    private function sanitizarCorreo($correo) {
        return trim(htmlspecialchars(strip_tags($correo)));
    }
    
    private function enviarCorreoRecuperacion($correo, $nombre, $codigo) {
        $asunto = "Agricontrol - Recuperación de contraseña";
        
        $mensaje = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #ffa000; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .codigo { font-size: 32px; font-weight: bold; color: #ffa000; text-align: center; padding: 20px; letter-spacing: 5px; }
                .warning { background: #fff3e0; padding: 15px; border-left: 4px solid #ffa000; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Agricontrol</h1>
                    <p>Recuperación de Contraseña</p>
                </div>
                <div class='content'>
                    <h2>Hola {$nombre},</h2>
                    <p>Hemos recibido una solicitud para restablecer tu contraseña. Utiliza el siguiente código para continuar:</p>
                    <div class='codigo'>{$codigo}</div>
                    <div class='warning'>
                        <strong>Importante:</strong>
                        <ul>
                            <li>Este código expirará en 30 minutos</li>
                            <li>No compartas este código con nadie</li>
                            <li>Si no solicitaste este cambio, ignora este mensaje</li>
                        </ul>
                    </div>
                </div>
                <div class='footer'>
                    <p>© 2024 Agricontrol - Sistema de Gestión Agrícola</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return enviarCorreo($correo, $nombre, $asunto, $mensaje);
    }
    
    /**
     * Envía respuesta de éxito
     */
    private function sendSuccessResponse($mensaje, $datos = []) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $mensaje,
            'data' => $datos
        ]);
        exit;
    }
    
    private function sendErrorResponse($mensaje) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $mensaje
        ]);
        exit;
    }
}

// Ejecutar el proceso de recuperación
$procesador = new ProcesadorRecuperacion();
$procesador->procesarRecuperacion();
?>