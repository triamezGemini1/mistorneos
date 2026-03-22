<?php

declare(strict_types=1);

/**
 * Alta en tabla maestra usuarios a partir de datos del padrón (BD auxiliar).
 */
final class RegistroDesdePadronService
{
    private static ?bool $tieneColumnaTelefono = null;

    /**
     * @return array{ok: true, user_id: int, username: string, nombre: string, cedula: string, email: string, nacionalidad: string, role: string}|array{ok: false, error: string}
     */
    public static function registrar(PDO $operativa, ?PDO $padron, AtletaService $atletaSvc, array $input): array
    {
        $cedulaRaw = trim((string) ($input['cedula'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $telefono = trim((string) ($input['telefono'] ?? ''));

        if ($padron === null) {
            return ['ok' => false, 'error' => 'padron_no_disponible'];
        }

        $digits = AtletaService::normalizarDocumentoNumerico($cedulaRaw);
        if (strlen($digits) < 4 || strlen($digits) > 20) {
            return ['ok' => false, 'error' => 'cedula_invalida'];
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'email_invalido'];
        }

        if (strlen($email) > 100) {
            return ['ok' => false, 'error' => 'email_invalido'];
        }

        if (strlen($password) < 8) {
            return ['ok' => false, 'error' => 'password_debil'];
        }

        $padronData = $atletaSvc->consultarPadron($cedulaRaw);
        if ($padronData === null) {
            return ['ok' => false, 'error' => 'no_en_padron'];
        }

        $tabla = $atletaSvc->tablaMaestra();

        if (self::cedulaExisteEnMaestro($operativa, $tabla, $cedulaRaw, $digits)) {
            return ['ok' => false, 'error' => 'cedula_duplicada'];
        }

        if (self::emailExiste($operativa, $tabla, $email)) {
            return ['ok' => false, 'error' => 'email_duplicado'];
        }

        $nombre = mb_substr(trim($padronData['nombre_completo'] ?? ''), 0, 62);
        if ($nombre === '') {
            $nombre = 'Usuario ' . $digits;
        }

        $nac = strtoupper((string) ($padronData['nacionalidad'] ?? 'V'));
        if (!in_array($nac, ['V', 'E', 'J', 'P'], true)) {
            $nac = 'V';
        }

        $sexo = $padronData['sexo'] ?? 'M';
        if (!in_array($sexo, ['M', 'F', 'O'], true)) {
            $sexo = 'M';
        }

        $fechnac = $padronData['fecha_nacimiento'];
        if ($fechnac !== null && $fechnac !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechnac) !== 1) {
            $fechnac = null;
        }

        $username = self::generarUsernameUnico($operativa, $tabla, $digits, $email);
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $cedulaGuardar = strlen($cedulaRaw) <= 20 ? $cedulaRaw : $digits;

        $cols = [
            'nombre', 'cedula', 'nacionalidad', 'sexo', 'fechnac', 'email',
            'username', 'password_hash', 'role', 'club_id', 'entidad', 'status', 'approved_at',
        ];
        $vals = [
            $nombre, $cedulaGuardar, $nac, $sexo, $fechnac ?: null, $email,
            $username, $hash, 'usuario', 0, 0, 0, date('Y-m-d H:i:s'),
        ];

        if (self::tablaTieneTelefono($operativa, $tabla) && $telefono !== '') {
            $cols[] = 'telefono';
            $vals[] = mb_substr($telefono, 0, 50);
        }

        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $colSql = implode(', ', array_map(static fn (string $c): string => '`' . str_replace('`', '', $c) . '`', $cols));
        $sql = "INSERT INTO `{$tabla}` ({$colSql}) VALUES ({$placeholders})";

        try {
            $st = $operativa->prepare($sql);
            $st->execute($vals);
            $id = (int) $operativa->lastInsertId();

            return [
                'ok' => true,
                'user_id' => $id,
                'username' => $username,
                'nombre' => $nombre,
                'cedula' => $cedulaGuardar,
                'email' => $email,
                'nacionalidad' => $nac,
                'role' => 'usuario',
            ];
        } catch (PDOException $e) {
            error_log('RegistroDesdePadronService: ' . $e->getMessage());
            if ((int) $e->getCode() === 23000) {
                return ['ok' => false, 'error' => 'duplicado_bd'];
            }

            return ['ok' => false, 'error' => 'error_insercion'];
        }
    }

    public static function cedulaExisteEnMaestro(
        PDO $op,
        string $tabla,
        string $cedulaRaw,
        string $digits
    ): bool {
        $variants = array_values(array_unique(array_filter([
            $cedulaRaw,
            $digits,
            'V' . $digits,
            'E' . $digits,
        ], static fn ($v) => $v !== '' && strlen((string) $v) <= 20)));

        if ($variants === []) {
            return false;
        }

        $in = implode(',', array_fill(0, count($variants), '?'));
        $sql = "SELECT id FROM `{$tabla}` WHERE cedula IN ({$in}) LIMIT 1";
        $st = $op->prepare($sql);
        $st->execute($variants);

        return $st->fetch(PDO::FETCH_ASSOC) !== false;
    }

    private static function emailExiste(PDO $op, string $tabla, string $email): bool
    {
        $st = $op->prepare("SELECT id FROM `{$tabla}` WHERE email = ? LIMIT 1");
        $st->execute([$email]);

        return $st->fetch(PDO::FETCH_ASSOC) !== false;
    }

    private static function generarUsernameUnico(PDO $op, string $tabla, string $digits, string $email): string
    {
        $local = preg_replace('/[^a-zA-Z0-9_]/', '', strstr($email, '@', true) ?: 'user');
        if ($local === '' || strlen($local) < 3) {
            $local = 'u' . $digits;
        }
        $base = mb_substr($local, 0, 50);
        $candidate = $base;
        $n = 0;
        do {
            $st = $op->prepare("SELECT id FROM `{$tabla}` WHERE username = ? LIMIT 1");
            $st->execute([$candidate]);
            if ($st->fetch(PDO::FETCH_ASSOC) === false) {
                return $candidate;
            }
            $n++;
            $candidate = mb_substr($base . $n, 0, 60);
        } while ($n < 5000);

        return 'u' . $digits . '_' . bin2hex(random_bytes(3));
    }

    private static function tablaTieneTelefono(PDO $op, string $tabla): bool
    {
        if (self::$tieneColumnaTelefono !== null) {
            return self::$tieneColumnaTelefono;
        }
        try {
            $q = $op->query("SHOW COLUMNS FROM `{$tabla}` LIKE 'telefono'");
            self::$tieneColumnaTelefono = $q !== false && $q->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (Throwable $e) {
            self::$tieneColumnaTelefono = false;
        }

        return self::$tieneColumnaTelefono;
    }
}
