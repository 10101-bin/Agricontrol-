<?php

require_once '../config/database.php';
require_once 'config_email.php';


class ReenviadorPin
{

    private $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();

        if (!$this->conn) {
            $this->sendErrorResponse("Error de conexión a la base de datos");
        }
    }

    public function reenviarPin()
    {
        try {
            // Verificar método de solicitud
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->sendErrorResponse("Método no permitido");
            }

            // Obtener correo
            $correo = $this->sanitizarCorreo($_POST['correo'] ?? '');

            if (empty($correo)) {
                $this->sendErrorResponse("Correo electrónico requerido");
            }

            // Buscar registro de verificación
            $registro = $this->buscarRegistroVerificacion($correo);

            if (!$registro) {
                $this->sendErrorResponse("No se encontró un registro de verificación para este correo");
            }

            // Verificar intentos de reenvío (máximo 3)
            if ($registro['intentos'] >= 3) {
                $this->sendErrorResponse("Ha excedido el número máximo de reenvíos. Por favor regístrese nuevamente");
            }

            $nuevoPin = $this->generarPin();

            // Actualizar PIN y fecha de expiración
            $nuevaExpiracion = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            $sql = "UPDATE verificacion_usuarios 
                    SET pin = :pin, 
                        fecha_expiracion = :expiracion,
                        intentos = intentos + 1,
                        fecha_solicitud = NOW()
                    WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':pin' => $nuevoPin,
                ':expiracion' => $nuevaExpiracion,
                ':id' => $registro['id']
            ]);

            // Enviar nuevo correo con PIN
            if ($this->enviarCorreoPin($correo, $registro['nombre'], $nuevoPin)) {
                $this->sendSuccessResponse("Se ha enviado un nuevo código PIN a su correo electrónico");
            } else {
                $this->sendErrorResponse("Error al enviar el correo. Por favor intente nuevamente");
            }

        } catch (Exception $e) {
            error_log("Error reenviando PIN: " . $e->getMessage());
            $this->sendErrorResponse($e->getMessage());
        }
    }

    private function sanitizarCorreo($correo)
    {
        return trim(htmlspecialchars(strip_tags($correo)));
    }

    private function buscarRegistroVerificacion($correo)
    {
        $sql = "SELECT * FROM verificacion_usuarios 
                WHERE correo = :correo AND verificada = 0";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':correo' => $correo]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function generarPin()
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function enviarCorreoPin($correo, $nombre, $pin)
    {
        $asunto = "Agricontrol - Nuevo código de verificacion";

        $mensaje = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2e7d32; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .pin { font-size: 32px; font-weight: bold; color: #2e7d32; text-align: center; padding: 20px; letter-spacing: 5px; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Agricontrol</h1>
                </div>
                <div class='content'>
                    <h2>Hola {$nombre},</h2>
                    <p>Has solicitado un nuevo código de verificación. Por favor ingresa el siguiente código:</p>
                    <div class='pin'>{$pin}</div>
                    <p><strong>Este código expirará en 15 minutos.</strong></p>
                    <p>Si no solicitaste este código, puedes ignorar este mensaje.</p>
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
    private function sendSuccessResponse($mensaje)
    {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $mensaje
        ]);
        exit;
    }


    private function sendErrorResponse($mensaje)
    {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $mensaje
        ]);
        exit;
    }
}

// Ejecutar el reenvío de PIN
$reenviador = new ReenviadorPin();
$reenviador->reenviarPin();
?>