<?php
/**
 * Helper para generar y validar tokens de seguridad en URLs QR de hojas de anotación.
 * Token dinámico: incluye torneo + mesa + ronda. Si la ronda cambia, el token es diferente.
 */
class QrMesaTokenHelper {

    /**
     * Genera un token HMAC para la URL QR de una mesa.
     * @param int $torneo_id ID del torneo
     * @param int $mesa Número de mesa
     * @param int $ronda Número de ronda
     * @return string Token de 32 caracteres hex
     */
    public static function generar(int $torneo_id, int $mesa, int $ronda): string {
        $secret = self::getSecret();
        $data = "t{$torneo_id}m{$mesa}r{$ronda}";
        return substr(hash_hmac('sha256', $data, $secret), 0, 32);
    }

    /**
     * Valida el token para los parámetros dados.
     * @param int $torneo_id
     * @param int $mesa
     * @param int $ronda
     * @param string $token
     * @return bool
     */
    public static function validar(int $torneo_id, int $mesa, int $ronda, string $token): bool {
        if (empty($token)) {
            return false;
        }
        $esperado = self::generar($torneo_id, $mesa, $ronda);
        return hash_equals($esperado, $token);
    }

    private static function getSecret(): string {
        if (!class_exists('Env') && file_exists(__DIR__ . '/../config/bootstrap.php')) {
            require_once __DIR__ . '/../config/bootstrap.php';
        }
        if (class_exists('Env')) {
            $key = Env::get('APP_KEY');
            if (!empty($key)) {
                return (string) $key;
            }
            $key = Env::get('QR_TOKEN_SECRET');
            if (!empty($key)) {
                return (string) $key;
            }
        }
        $key = $GLOBALS['APP_CONFIG']['security']['csrf_key'] ?? null;
        if (!empty($key)) {
            return (string) $key;
        }
        return 'mistorneos_qr_default_key';
    }
}
