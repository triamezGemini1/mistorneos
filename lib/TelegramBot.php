<?php
/**
 * Wrapper para Telegram Bot API
 * Envio de mensajes a usuarios mediante chat_id
 */
class TelegramBot {
    private static $api_base = 'https://api.telegram.org/bot';

    /**
     * Envia un mensaje a un chat_id
     * @param string $token Token del bot (TELEGRAM_BOT_TOKEN)
     * @param string|int $chat_id ID del chat del destinatario
     * @param string $text Mensaje a enviar
     * @return array ['ok' => bool, 'error' => string|null]
     */
    public static function sendMessage(string $token, $chat_id, string $text): array {
        if (empty($token) || empty($chat_id) || trim($text) === '') {
            return ['ok' => false, 'error' => 'Token, chat_id o texto vacio'];
        }

        $url = self::$api_base . $token . '/sendMessage';
        $data = [
            'chat_id' => $chat_id,
            'text' => $text,
            'disable_web_page_preview' => true
        ];

        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($data),
                'timeout' => 15
            ]
        ];

        try {
            $context = stream_context_create($opts);
            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                return ['ok' => false, 'error' => 'Error de conexion con Telegram'];
            }

            $json = json_decode($response, true);
            if (!$json) {
                return ['ok' => false, 'error' => 'Respuesta invalida de Telegram'];
            }

            if (isset($json['ok']) && $json['ok'] === true) {
                return ['ok' => true, 'error' => null];
            }

            $error = $json['description'] ?? 'Error desconocido';
            return ['ok' => false, 'error' => $error];
        } catch (Exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verifica si el token del bot es valido
     */
    public static function getMe(string $token): ?array {
        if (empty($token)) return null;
        $url = self::$api_base . $token . '/getMe';
        $response = @file_get_contents($url);
        if (!$response) return null;
        $json = json_decode($response, true);
        return ($json['ok'] ?? false) ? ($json['result'] ?? []) : null;
    }
}
