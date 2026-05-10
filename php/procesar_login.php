<?php

session_start();
require_once '../config/database.php';


class ProcesadorLogin {
    
    private $conn;         
    private $maxIntentos = 5; 
    private $tiempoBloqueo = 30; 
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        
        if (!$this->conn) {
            $this->sendErrorResponse("Error de conexión a la base de datos");
        }
    }
    

    public function procesarLogin() {
        try {
            // Verificar método de solicitud
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->sendErrorResponse("Método no permitido");
            }
            
            
            $correo = $this->sanitizarCorreo($_POST['correo'] ?? '');
            $contrasenia = $_POST['contrasenia'] ?? '';
            
            if (empty($correo) || empty($contrasenia)) {
                $this->sendErrorResponse("Todos los campos son obligatorios");
            }
            
            $this->verificarIntentosLogin($correo);
            
            $usuario = $this->buscarUsuario($correo);

            if (!$usuario) {
                $this->registrarIntentoFallido($correo);
                
                $this->sendErrorResponse("Correo o contraseña incorrectos");
            }

            if ($usuario['estado'] == 0) {
                $this->sendErrorResponse("Correo o contraseña incorrectos");
            }

            if (!password_verify($contrasenia, $usuario['contrasenia'])) {
                $this->registrarIntentoFallido($correo);
                $this->sendErrorResponse("Correo o contraseña incorrectos");
            }
  
            if ($usuario['estado'] == 0) {
                $this->sendErrorResponse("Cuenta desactivada. Contacte al administrador");
            }
            
            if (!password_verify($contrasenia, $usuario['contrasenia'])) {
                $this->registrarIntentoFallido($correo);
                $this->sendErrorResponse("Credenciales incorrectas");
            }
            
            $this->limpiarIntentosFallidos($correo);
            
            $this->crearSesion($usuario);
            
            $this->registrarLoginExitoso($usuario['id_usuario']);
            
            $this->sendSuccessResponse(
                "Bienvenido {$usuario['nombre']} {$usuario['apellido']}",
                ['redirect' => '../admin/usuario/index.php']
            );
            
        } catch (Exception $e) {
            error_log("Error en login: " . $e->getMessage());
            $this->sendErrorResponse($e->getMessage());
        }
    }
    
    private function sanitizarCorreo($correo) {
        return trim(htmlspecialchars(strip_tags($correo)));
    }
   
    private function verificarIntentosLogin($correo) {
        // Verificar en tabla temporal de intentos
        $sql = "SELECT intentos, bloqueado_hasta FROM recuperacion_contrasenia 
                WHERE correo = :correo ORDER BY fecha_solicitud DESC LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':correo' => $correo]);
        $intento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($intento && $intento['bloqueado_hasta']) {
            $bloqueadoHasta = new DateTime($intento['bloqueado_hasta']);
            $ahora = new DateTime();
            
            if ($ahora < $bloqueadoHasta) {
                $minutosRestantes = $ahora->diff($bloqueadoHasta)->i;
                throw new Exception("Demasiados intentos. Espere {$minutosRestantes} minutos");
            }
        }
    }
    
    private function buscarUsuario($correo) {
        $sql = "SELECT id_usuario, nombre, apellido, cedula, telefono, 
                       correo, contrasenia, estado, sexo, fecha_registro,
                       id_produccion
                FROM usuario 
                WHERE correo = :correo";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':correo' => $correo]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function registrarIntentoFallido($correo) {
        // Verificar si existe registro de intentos
        $sql = "SELECT id, intentos FROM recuperacion_contrasenia 
                WHERE correo = :correo AND usada = 0";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':correo' => $correo]);
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($registro) {
            $nuevosIntentos = $registro['intentos'] + 1;
            
            if ($nuevosIntentos >= $this->maxIntentos) {
                // Bloquear por tiempo
                $bloqueoHasta = date('Y-m-d H:i:s', strtotime("+{$this->tiempoBloqueo} minutes"));
                $sql = "UPDATE recuperacion_contrasenia 
                        SET intentos = :intentos, bloqueado_hasta = :bloqueo 
                        WHERE id = :id";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([
                    ':intentos' => $nuevosIntentos,
                    ':bloqueo' => $bloqueoHasta,
                    ':id' => $registro['id']
                ]);
            } else {
                $sql = "UPDATE recuperacion_contrasenia 
                        SET intentos = :intentos 
                        WHERE id = :id";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([
                    ':intentos' => $nuevosIntentos,
                    ':id' => $registro['id']
                ]);
            }
        } else {
            // Crear nuevo registro de intentos
            $sql = "INSERT INTO recuperacion_contrasenia 
                    (correo, contrasenia_temporal, fecha_expiracion, intentos) 
                    VALUES 
                    (:correo, :temp, :expiracion, 1)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':correo' => $correo,
                ':temp' => '',
                ':expiracion' => date('Y-m-d H:i:s')
            ]);
        }
    }
    
    private function limpiarIntentosFallidos($correo) {
        $sql = "UPDATE recuperacion_contrasenia 
                SET intentos = 0, bloqueado_hasta = NULL 
                WHERE correo = :correo";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':correo' => $correo]);
    }
    

    private function crearSesion($usuario) {
        $_SESSION['usuario_id'] = $usuario['id_usuario'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'];
        $_SESSION['usuario_apellido'] = $usuario['apellido'];
        $_SESSION['usuario_correo'] = $usuario['correo'];
        $_SESSION['usuario_cedula'] = $usuario['cedula'];
        $_SESSION['usuario_sexo'] = $usuario['sexo'];
        $_SESSION['login_time'] = time();
        $_SESSION['ultima_actividad'] = time();
    }
    
   
    private function registrarLoginExitoso($usuarioId) {
        // Aquí se podría actualizar un campo 'ultimo_login' en la tabla usuario
        // Si se desea agregar ese campo, descomentar la siguiente línea
        // $sql = "UPDATE usuario SET ultimo_login = NOW() WHERE id_usuario = :id";
        // $stmt = $this->conn->prepare($sql);
        // $stmt->execute([':id' => $usuarioId]);
    }

    private function sendSuccessResponse($mensaje, $datos = []) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $mensaje,
            'data' => $datos
        ]);
        exit;
    }
    
    /**
     * Envía respuesta de error al cliente
     * 
     * @param string $mensaje Mensaje de error
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

// Ejecutar el proceso de login
$procesador = new ProcesadorLogin();
$procesador->procesarLogin();
?>