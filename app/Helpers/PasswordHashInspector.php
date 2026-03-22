<?php

declare(strict_types=1);

/**
 * Valida que el almacenamiento de contraseña sea compatible con password_verify().
 * Rechaza MD5, SHA-256 en hex y candidatos a texto plano (no usar mecanismos inseguros).
 */
final class PasswordHashInspector
{
    public const REASON_MD5 = 'md5';
    public const REASON_SHA1 = 'sha1';
    public const REASON_SHA256 = 'sha256';
    public const REASON_SHA512 = 'sha512';
    public const REASON_PLAINTEXT = 'plaintext';
    public const REASON_UNKNOWN = 'unknown';

    /**
     * @return array{ok: bool, legacy_insecure: bool, reason: ?string}
     */
    public static function verify(string $plain, string $storedHash): array
    {
        $storedHash = trim($storedHash);
        if ($storedHash === '') {
            return ['ok' => false, 'legacy_insecure' => false, 'reason' => null];
        }

        $legacy = self::detectLegacyInsecure($storedHash);
        if ($legacy !== null) {
            error_log('mistorneos auth: hash de contraseña no soportado (' . $legacy . ') — migrar a password_hash()');
            return ['ok' => false, 'legacy_insecure' => true, 'reason' => $legacy];
        }

        $info = password_get_info($storedHash);
        if (($info['algo'] ?? 0) === 0) {
            error_log('mistorneos auth: formato de hash desconocido (no es password_hash de PHP)');
            return ['ok' => false, 'legacy_insecure' => true, 'reason' => self::REASON_UNKNOWN];
        }

        return [
            'ok' => password_verify($plain, $storedHash),
            'legacy_insecure' => false,
            'reason' => null,
        ];
    }

    private static function detectLegacyInsecure(string $h): ?string
    {
        if (preg_match('/^[a-f0-9]{32}$/i', $h) === 1) {
            return self::REASON_MD5;
        }
        if (preg_match('/^[a-f0-9]{40}$/i', $h) === 1) {
            return self::REASON_SHA1;
        }
        if (preg_match('/^[a-f0-9]{64}$/i', $h) === 1) {
            return self::REASON_SHA256;
        }
        if (preg_match('/^[a-f0-9]{128}$/i', $h) === 1) {
            return self::REASON_SHA512;
        }
        if (strlen($h) < 50 && strpos($h, '$') === false) {
            return self::REASON_PLAINTEXT;
        }

        return null;
    }
}
