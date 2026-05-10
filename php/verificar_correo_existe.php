<?php

require_once '../config/database.php';

class VerificadorExistencia {
    
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        
        if (!$this->conn) {
            $this->sendErrorResponse("Error de conexión a la base de datos");
        }
    }
    
    public function verificar() {
        try {
            // Verificar método de solicitud
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->sendErrorResponse("Método no permitido");
            }
            
            // Obtener tipo y valor
            $tipo = $_POST['tipo'] ?? '';
            $valor = $_POST[$tipo] ?? '';
            
            if (empty($tipo) || empty($valor)) {
                $this->sendErrorResponse("Datos incompletos");
            }
            
            $valor = $this->sanitizarValor($tipo, $valor);
            
            // Verificar existencia
            $existe = $this->verificarExistencia($tipo, $valor);
            
            // Enviar respuesta
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'exists' => $existe,
                'tipo' => $tipo
            ]);
            exit;
            
        } catch (Exception $e) {
            error_log("Error verificando existencia: " . $e->getMessage());
            $this->sendErrorResponse($e->getMessage());
        }
    }
    
    private function sanitizarValor($tipo, $valor) {
        $valor = trim(htmlspecialchars(strip_tags($valor)));
        
        switch ($tipo) {
            case 'correo':
                return filter_var($valor, FILTER_SANITIZE_EMAIL);
            case 'cedula':
            case 'telefono':
                return preg_replace('/[^0-9]/', '', $valor);
            default:
                return $valor;
        }
    }
    
    private function verificarExistencia($tipo, $valor) {
        // Verificar en tabla usuario (usuarios activos)
        $sql = "SELECT {$tipo} FROM usuario WHERE {$tipo} = :valor AND estado = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':valor' => $valor]);
        
        if ($stmt->rowCount() > 0) {
            return true;
        }
        
        // Verificar en tabla de verificación (registros pendientes)
        $sql = "SELECT {$tipo} FROM verificacion_usuarios 
                WHERE {$tipo} = :valor AND verificada = 0 
                AND fecha_expiracion > NOW()";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':valor' => $valor]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Envía respuesta de error
     */
    private function sendErrorResponse($mensaje) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $mensaje
        ]);
        exit;
    }
}

// Ejecutar la verificación
$verificador = new VerificadorExistencia();
$verificador->verificar();
?>