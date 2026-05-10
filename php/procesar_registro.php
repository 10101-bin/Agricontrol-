<?php


// Iniciar sesión para manejar variables de sesión
session_start();

require_once '../config/database.php';

require_once 'config_email.php';

class ProcesadorRegistro
{

    private $conn;
    private $emailSender;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();

        if (!$this->conn) {
            $this->sendErrorResponse("Error de conexión a la base de datos");
        }
    }

    public function procesarRegistro()
    {
        try {
            // Verificar método de solicitud
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->sendErrorResponse("Método no permitido");
            }

            $datos = $this->sanitizarDatos($_POST);

            // Validar datos obligatorios
            $this->validarDatosObligatorios($datos);

            // Validar unicidad de datos
            $this->validarUnicidad($datos);

            // Generar PIN de 6 dígitos
            $pin = $this->generarPin();

            // Encriptar contraseña
            $contraseniaEncriptada = password_hash($datos['contrasenia'], PASSWORD_DEFAULT);

            // Obtener IP del usuario
            $ip = $this->getClientIP();

            // Calcular fecha de expiración (15 minutos)
            $fechaExpiracion = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            // Preparar consulta SQL para tabla de verificación
            $sql = "INSERT INTO verificacion_usuarios 
                    (nombre, apellido, cedula, telefono, correo, contrasenia, sexo, pin, ip, fecha_expiracion) 
                    VALUES 
                    (:nombre, :apellido, :cedula, :telefono, :correo, :contrasenia, :sexo, :pin, :ip, :fecha_expiracion)";

            $stmt = $this->conn->prepare($sql);

            // Ejecutar consulta con parámetros
            $stmt->execute([
                ':nombre' => $datos['nombre'],
                ':apellido' => $datos['apellido'],
                ':cedula' => $datos['cedula'],
                ':telefono' => $datos['telefono'],
                ':correo' => $datos['correo'],
                ':contrasenia' => $contraseniaEncriptada,
                ':sexo' => $datos['sexo'],
                ':pin' => $pin,
                ':ip' => $ip,
                ':fecha_expiracion' => $fechaExpiracion
            ]);

            // Enviar correo con PIN
            if ($this->enviarCorreoPin($datos['correo'], $datos['nombre'], $pin)) {
                $this->sendSuccessResponse(
                    "Registro exitoso. Se ha enviado un PIN de verificación a su correo electrónico.",
                    ['pin_enviado' => true]
                );
            } else {
                // Si falla el envío de correo, eliminar el registro
                $stmt = $this->conn->prepare("DELETE FROM verificacion_usuarios WHERE correo = :correo");
                $stmt->execute([':correo' => $datos['correo']]);

                $this->sendErrorResponse("Error al enviar el correo de verificación. Por favor intente nuevamente.");
            }

        } catch (Exception $e) {
            error_log("Error en registro: " . $e->getMessage());
            $this->sendErrorResponse("Error en el proceso de registro: " . $e->getMessage());
        }
    }

    private function sanitizarDatos($datos)
    {
        return [
            'nombre' => trim(htmlspecialchars(strip_tags($datos['nombre'] ?? ''))),
            'apellido' => trim(htmlspecialchars(strip_tags($datos['apellido'] ?? ''))),
            'cedula' => trim(htmlspecialchars(strip_tags($datos['cedula'] ?? ''))),
            'telefono' => trim(htmlspecialchars(strip_tags($datos['telefono'] ?? ''))),
            'correo' => trim(htmlspecialchars(strip_tags($datos['correo'] ?? ''))),
            'sexo' => trim(htmlspecialchars(strip_tags($datos['sexo'] ?? ''))),
            'contrasenia' => $datos['contrasenia'] ?? '',
            'confirmar_contrasenia' => $datos['confirmar_contrasenia'] ?? ''
        ];
    }

    private function validarDatosObligatorios($datos)
    {
        $camposRequeridos = ['nombre', 'apellido', 'cedula', 'telefono', 'correo', 'sexo', 'contrasenia', 'confirmar_contrasenia'];

        foreach ($camposRequeridos as $campo) {
            if (empty($datos[$campo])) {
                throw new Exception("El campo {$campo} es requerido");
            }
        }

        // Validar que las contraseñas coincidan
        if ($datos['contrasenia'] !== $datos['confirmar_contrasenia']) {
            throw new Exception("Las contraseñas no coinciden");
        }

        // Validar fortaleza de contraseña
        $this->validarFortalezaPassword($datos['contrasenia']);

        // Validar formato de correo
        if (!filter_var($datos['correo'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Formato de correo inválido");
        }
    }

    /**
     * Valida la fortaleza de la contraseña según requisitos de seguridad
     * 
     */
    private function validarFortalezaPassword($password)
    {
        $requisitos = [];

        if (strlen($password) < 8) {
            $requisitos[] = "mínimo 8 caracteres";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $requisitos[] = "al menos una mayúscula";
        }
        if (!preg_match('/[a-z]/', $password)) {
            $requisitos[] = "al menos una minúscula";
        }
        if (!preg_match('/[0-9]/', $password)) {
            $requisitos[] = "al menos un número";
        }
        if (!preg_match('/[@$!%*?&]/', $password)) {
            $requisitos[] = "al menos un carácter especial (@$!%*?&)";
        }

        if (!empty($requisitos)) {
            throw new Exception("La contraseña debe tener: " . implode(", ", $requisitos));
        }
    }

    private function validarUnicidad($datos)
    {
        // Verificar en tabla de usuarios activos
        $sql = "SELECT cedula, telefono, correo FROM usuario WHERE cedula = :cedula OR telefono = :telefono OR correo = :correo";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':cedula' => $datos['cedula'],
            ':telefono' => $datos['telefono'],
            ':correo' => $datos['correo']
        ]);

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row['cedula'] == $datos['cedula']) {
                throw new Exception("La cédula ya está registrada en el sistema");
            }
            if ($row['telefono'] == $datos['telefono']) {
                throw new Exception("El teléfono ya está registrado en el sistema");
            }
            if ($row['correo'] == $datos['correo']) {
                throw new Exception("El correo ya está registrado en el sistema");
            }
        }

        // Verificar en tabla de verificación pendiente
        $sql = "SELECT cedula, telefono, correo FROM verificacion_usuarios 
                WHERE (cedula = :cedula OR telefono = :telefono OR correo = :correo) 
                AND verificada = 0 
                AND fecha_expiracion > NOW()";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':cedula' => $datos['cedula'],
            ':telefono' => $datos['telefono'],
            ':correo' => $datos['correo']
        ]);

        if ($stmt->rowCount() > 0) {
            throw new Exception("Ya existe un registro pendiente de verificación con estos datos");
        }
    }

    private function generarPin()
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Obtiene la dirección IP real del cliente
     * 
     * @return string Dirección IP
     */
    private function getClientIP()
    {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if (isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if (isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if (isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if (isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';

        return $ipaddress;
    }

    private function enviarCorreoPin($correo, $nombre, $pin)
    {
        // Configuración del correo
        $asunto = "Agricontrol - Verificacion de cuenta";

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
                    <p>Gracias por registrarte en Agricontrol. Para completar tu registro, por favor ingresa el siguiente código de verificación:</p>
                    <div class='pin'>{$pin}</div>
                    <p><strong>Este código expirará en 15 minutos.</strong></p>
                    <p>Si no solicitaste este registro, puedes ignorar este mensaje.</p>
                </div>
                <div class='footer'>
                    <p>© 2026 Agricontrol - Sistema de Gestión Agrícola</p>
                </div>
            </div>
        </body>
        </html>
        ";

        // Enviar correo usando PHPMailer
        return enviarCorreo($correo, $nombre, $asunto, $mensaje);
    }

    /**
     * Envía respuesta de éxito al cliente
     * 
     * @param string $mensaje Mensaje de éxito
     * @param array $datos Datos adicionales
     */
    private function sendSuccessResponse($mensaje, $datos = [])
    {
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

// Ejecutar el proceso de registro
$procesador = new ProcesadorRegistro();
$procesador->procesarRegistro();
?>