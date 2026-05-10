<?php

require_once '../vendor/PHPMailer/src/Exception.php';
require_once '../vendor/PHPMailer/src/PHPMailer.php';
require_once '../vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'jg336949@gmail.com');      
define('SMTP_PASS', 'sdaa ulem wplr lwda');        
define('SMTP_FROM', 'jg336949@gmail.com');
define('SMTP_FROM_NAME', 'Agricontrol');


function enviarCorreo($correoDestino, $nombreDestino, $asunto, $mensajeHTML)
{
    $mail = new PHPMailer(true);

    try {
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        // Configuración del correo
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($correoDestino, $nombreDestino);

        // Contenido del correo
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body = $mensajeHTML;
        $mail->AltBody = strip_tags($mensajeHTML);

        // Enviar correo
        $mail->send();
        return true;

    } catch (Exception $e) {
        // Registrar error
        error_log("Error al enviar correo a {$correoDestino}: " . $mail->ErrorInfo);
        return false;
    }
}


function correoExiste($correo, $conn)
{
    try {
        $sql = "SELECT correo FROM usuario WHERE correo = :correo AND estado = 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':correo' => $correo]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}
?>