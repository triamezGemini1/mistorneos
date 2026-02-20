<?php
/**
 * Clase unificada para envío de notificaciones
 * Soporta: WhatsApp (enlace wa.me), Email (PHPMailer), Telegram (Bot API)
 */
require_once __DIR__ . '/TelegramBot.php';

class NotificationSender {
    
    /**
     * Genera URL de WhatsApp wa.me con mensaje prellenado
     * @param string $telefono Número con código país (ej: 584241234567)
     * @param string $mensaje Texto del mensaje
     * @return string URL completa
     */
    public static function whatsappLink(string $telefono, string $mensaje): string {
        $telefono = preg_replace('/[^0-9]/', '', $telefono);
        if ($telefono && $telefono[0] == '0') $telefono = substr($telefono, 1);
        if ($telefono && strlen($telefono) == 10 && !str_starts_with($telefono, '58')) {
            $telefono = '58' . $telefono;
        }
        $encoded = urlencode($mensaje);
        return "https://wa.me/{$telefono}?text={$encoded}";
    }
    
    /**
     * Envía email mediante PHPMailer
     * @param string $email Destinatario
     * @param string $asunto Asunto del correo
     * @param string $mensaje Cuerpo (HTML o texto)
     * @param string $nombre_destinatario Nombre para personalizar
     * @return array ['ok' => bool, 'error' => string|null]
     */
    public static function sendEmail(string $email, string $asunto, string $mensaje, string $nombre_destinatario = ''): array {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Email inválido'];
        }
        
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return ['ok' => false, 'error' => 'PHPMailer no disponible'];
        }
        
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['MAIL_USERNAME'] ?? '';
            $mail->Password = $_ENV['MAIL_PASSWORD'] ?? '';
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int)($_ENV['MAIL_PORT'] ?? 587);
            
            $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'] ?? $_ENV['MAIL_FROM'] ?? 'noreply@mistorneos.com', $_ENV['MAIL_FROM_NAME'] ?? 'La Estación del Dominó');
            $mail->addAddress($email, $nombre_destinatario ?: '');
            $mail->Subject = $asunto;
            $mail->isHTML(true);
            $mail->Body = nl2br(htmlspecialchars($mensaje));
            
            $mail->send();
            return ['ok' => true, 'error' => null];
        } catch (Exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Envía mensaje por Telegram Bot API
     * @param string $chat_id Chat ID del destinatario
     * @param string $mensaje Texto a enviar
     * @return array ['ok' => bool, 'error' => string|null]
     */
    public static function sendTelegram(string $chat_id, string $mensaje): array {
        $token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
        return TelegramBot::sendMessage($token, $chat_id, $mensaje);
    }
    
    /**
     * Reemplaza variables en el mensaje: {nombre}, {torneo}, {club}, etc.
     */
    public static function replaceVariables(string $mensaje, array $vars): string {
        foreach ($vars as $key => $value) {
            $mensaje = str_replace('{' . $key . '}', (string)$value, $mensaje);
        }
        return $mensaje;
    }
}
