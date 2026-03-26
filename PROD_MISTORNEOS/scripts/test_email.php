<?php
/**
 * Script de prueba SMTP para Mistorneos.
 * Uso: php scripts/test_email.php destino@correo.com
 */

// Reducir tiempos de espera para que no se quede colgado
ini_set('default_socket_timeout', '12');

require __DIR__ . '/../config/bootstrap.php';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    fwrite(STDERR, "Falta vendor/autoload.php. Ejecuta: composer install\n");
    exit(1);
}
require $autoload;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$to = $argv[1] ?? null;
if (!$to) {
    fwrite(STDERR, "Indica el email destino: php scripts/test_email.php destino@correo.com\n");
    exit(1);
}

$configPath = __DIR__ . '/../config/email.php';
if (!file_exists($configPath)) {
    fwrite(STDERR, "No se encontró config/email.php. Configura SMTP primero.\n");
    exit(1);
}
$emailCfg = require $configPath;
$smtp = $emailCfg['smtp'] ?? [];
$debugCfg = $emailCfg['debug'] ?? ['enabled' => false];

try {
    $mail = new PHPMailer(true);
$mail->CharSet = 'UTF-8';
$mail->Timeout = 12;       // segundos de timeout SMTP
$mail->SMTPAutoTLS = true; // forzar intento TLS

    if (!empty($smtp)) {
        $mail->isSMTP();
        $mail->Host = $smtp['host'] ?? 'localhost';
        $mail->Port = $smtp['port'] ?? 587;
        $mail->SMTPAuth = !empty($smtp['username']);
        if ($mail->SMTPAuth) {
            $mail->Username = $smtp['username'] ?? '';
            $mail->Password = $smtp['password'] ?? '';
            $enc = $smtp['encryption'] ?? PHPMailer::ENCRYPTION_STARTTLS;
            $mail->SMTPSecure = $enc === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        }
    } else {
        $mail->isMail();
    }

    // Debug opcional
    if (!empty($debugCfg['enabled'])) {
        $mail->SMTPDebug = 2;
        $logFile = $debugCfg['log_file'] ?? (__DIR__ . '/../logs/email_debug.log');
        $mail->Debugoutput = function ($str, $level) use ($logFile) {
            $line = '[' . date('Y-m-d H:i:s') . "][lvl:$level] $str\n";
            file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
            fwrite(STDERR, $line);
        };
    }

    $fromEmail = $smtp['from_email'] ?? 'noreply@mistorneos.com';
    $fromName = $smtp['from_name'] ?? 'Sistema de Inscripciones - Mistorneos';

    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($to);
$mail->Subject = 'Prueba SMTP - Mistorneos';
$mail->Body = 'Correo de prueba enviado desde scripts/test_email.php';

    $mail->send();
    echo "Correo enviado correctamente a {$to} usando " . ($mail->isSMTP() ? 'SMTP' : 'mail()') . "\n";
} catch (Exception $e) {
    fwrite(STDERR, "Error enviando correo: " . $e->getMessage() . "\n");
    exit(1);
}

