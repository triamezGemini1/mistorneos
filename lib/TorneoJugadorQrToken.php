<?php
declare(strict_types=1);

/**
 * Token corto firmado (torneo + jugador) para enlaces QR públicos.
 * Formato: base64url(torneo_id:id_usuario) . '.' . base64url(10 bytes HMAC).
 */
final class TorneoJugadorQrToken
{
    private static function secret(): string
    {
        $k = '';
        if (class_exists('Env')) {
            $k = (string) Env::get('APP_KEY', '');
        }
        if ($k === '') {
            $k = (string) (getenv('APP_KEY') ?: '');
        }
        if ($k === '' && !empty($GLOBALS['APP_CONFIG']['APP_KEY'])) {
            $k = (string) $GLOBALS['APP_CONFIG']['APP_KEY'];
        }
        if ($k === '') {
            $k = 'mistorneos-torneo-qr-dev-key';
        }

        return $k;
    }

    private static function b64urlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $s): string
    {
        $s = strtr($s, '-_', '+/');
        $pad = strlen($s) % 4;
        if ($pad > 0) {
            $s .= str_repeat('=', 4 - $pad);
        }
        $out = base64_decode($s, true);

        return $out === false ? '' : $out;
    }

    public static function encode(int $torneoId, int $idUsuario): string
    {
        if ($torneoId < 1 || $idUsuario < 1) {
            throw new InvalidArgumentException('IDs inválidos');
        }
        $inner = $torneoId . ':' . $idUsuario;
        $payload = self::b64urlEncode($inner);
        $sigRaw = substr(hash_hmac('sha256', $payload, self::secret(), true), 0, 10);
        $sig = self::b64urlEncode($sigRaw);

        return $payload . '.' . $sig;
    }

    /**
     * @return array{torneo_id: int, id_usuario: int}|null
     */
    public static function decode(string $token): ?array
    {
        $token = trim($token);
        if ($token === '' || !str_contains($token, '.')) {
            return null;
        }
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }
        [$payload, $sig] = $parts;
        if ($payload === '' || $sig === '') {
            return null;
        }
        $expected = self::b64urlEncode(substr(hash_hmac('sha256', $payload, self::secret(), true), 0, 10));
        if (!hash_equals($expected, $sig)) {
            return null;
        }
        $inner = self::b64urlDecode($payload);
        if ($inner === '' || !str_contains($inner, ':')) {
            return null;
        }
        [$t, $u] = explode(':', $inner, 2);
        if (!ctype_digit($t) || !ctype_digit($u)) {
            return null;
        }
        $torneoId = (int) $t;
        $idUsuario = (int) $u;
        if ($torneoId < 1 || $idUsuario < 1) {
            return null;
        }

        return ['torneo_id' => $torneoId, 'id_usuario' => $idUsuario];
    }
}
