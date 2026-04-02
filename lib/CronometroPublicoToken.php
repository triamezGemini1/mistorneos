<?php
/**
 * Enlace firmado para el cronómetro público (sin sesión).
 * Caduca por tiempo; no expone el torneo sin la firma correcta.
 */
class CronometroPublicoToken {

    /** TTL por defecto: 7 días */
    public static function queryParams(int $torneo_id, int $ttl_seconds = 604800): array {
        $exp = time() + max(3600, $ttl_seconds);
        $sig = self::sign($torneo_id, $exp);
        return [
            't' => $torneo_id,
            'e' => $exp,
            's' => $sig,
        ];
    }

    public static function validate(int $torneo_id, int $exp, string $sig): bool {
        if ($torneo_id <= 0 || $exp < time() || $sig === '') {
            return false;
        }
        return hash_equals(self::sign($torneo_id, $exp), $sig);
    }

    private static function sign(int $torneo_id, int $exp): string {
        $secret = self::getSecret();
        return substr(hash_hmac('sha256', "cron_public|{$torneo_id}|{$exp}", $secret), 0, 32);
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
        }
        $key = $GLOBALS['APP_CONFIG']['APP_KEY'] ?? $GLOBALS['APP_CONFIG']['security']['csrf_key'] ?? null;
        if (!empty($key)) {
            return (string) $key;
        }
        return 'mistorneos_cron_public_default';
    }
}
