<?php
/**
 * Resuelve un token de invitación y el estado del delegado en directorio_clubes.
 * Usado por /join para decidir: Formulario de Registro (id_usuario vacío) o Inscripción de jugadores (acceso expedito).
 */

if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../config/bootstrap.php';
}
if (!class_exists('DB')) {
    require_once __DIR__ . '/../config/db.php';
}

class InvitationJoinResolver
{
    /** @var string */
    private static $tableInvitations;

    private static function getTableInvitations(): string
    {
        if (self::$tableInvitations === null) {
            self::$tableInvitations = defined('TABLE_INVITATIONS') ? TABLE_INVITATIONS : 'invitaciones';
        }
        return self::$tableInvitations;
    }

    /**
     * Resuelve el token y devuelve datos para decidir el destino.
     * @param string $token Token de la invitación (64 chars hex)
     * @return array|null ['invitation' => array, 'id_directorio_club' => int, 'id_usuario_delegado' => int|null, 'requiere_registro' => bool]
     */
    public static function resolve(string $token): ?array
    {
        $token = trim($token);
        if (strlen($token) < 32) {
            return null;
        }
        try {
            $tb = self::getTableInvitations();
            $pdo = DB::pdo();
            $stmt = $pdo->prepare("SELECT i.*, c.nombre as club_nombre FROM {$tb} i LEFT JOIN clubes c ON c.id = i.club_id WHERE i.token = ? AND (i.estado = 'activa' OR i.estado = 'vinculado' OR i.estado = 0 OR i.estado = 1) LIMIT 1");
            $stmt->execute([$token]);
            $inv = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$inv) {
                return null;
            }
            $id_directorio_club = null;
            $id_usuario_delegado = null;
            if (!empty($inv['id_directorio_club'])) {
                $id_directorio_club = (int) $inv['id_directorio_club'];
            }
            if ($id_directorio_club === null || $id_directorio_club <= 0) {
                $club_nombre = $inv['club_nombre'] ?? null;
                if ($club_nombre !== null && $club_nombre !== '') {
                    $st = $pdo->prepare("SELECT id FROM directorio_clubes WHERE nombre = ? LIMIT 1");
                    $st->execute([$club_nombre]);
                    $row = $st->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        $id_directorio_club = (int) $row['id'];
                    }
                }
            }
            if ($id_directorio_club > 0) {
                $cols = @$pdo->query("SHOW COLUMNS FROM directorio_clubes LIKE 'id_usuario'")->fetchAll();
                if (!empty($cols)) {
                    $st = $pdo->prepare("SELECT id_usuario FROM directorio_clubes WHERE id = ? LIMIT 1");
                    $st->execute([$id_directorio_club]);
                    $row = $st->fetch(PDO::FETCH_ASSOC);
                    $id_usuario_delegado = isset($row['id_usuario']) && $row['id_usuario'] !== null && (string)$row['id_usuario'] !== '' ? (int) $row['id_usuario'] : null;
                }
            }
            $requiere_registro = ($id_usuario_delegado === null || $id_usuario_delegado <= 0);
            return [
                'invitation' => $inv,
                'id_directorio_club' => $id_directorio_club,
                'id_usuario_delegado' => $id_usuario_delegado,
                'requiere_registro' => $requiere_registro,
            ];
        } catch (Throwable $e) {
            error_log("InvitationJoinResolver::resolve " . $e->getMessage());
            return null;
        }
    }

    /**
     * Contexto para el registro fast-track: ID_CLUB, ENTIDAD_ID y datos del club desde el token.
     * @param string $token Token de la invitación
     * @return array|null ['club_id' => int, 'id_directorio_club' => int, 'entidad_id' => int, 'club_nombre' => string, 'invitation' => array, 'requiere_registro' => bool] o null
     */
    public static function getContextForRegistration(string $token): ?array
    {
        $resolved = self::resolve($token);
        if ($resolved === null) {
            return null;
        }
        $inv = $resolved['invitation'];
        $club_id = (int) ($inv['club_id'] ?? 0);
        $id_directorio_club = (int) $resolved['id_directorio_club'];
        $club_nombre = $inv['club_nombre'] ?? '';
        $entidad_id = 0;
        if ($club_id > 0) {
            try {
                $pdo = DB::pdo();
                $cols = @$pdo->query("SHOW COLUMNS FROM clubes LIKE 'entidad'")->fetchAll();
                if (!empty($cols)) {
                    $st = $pdo->prepare("SELECT entidad FROM clubes WHERE id = ? LIMIT 1");
                    $st->execute([$club_id]);
                    $row = $st->fetch(PDO::FETCH_ASSOC);
                    if ($row && isset($row['entidad']) && (string)$row['entidad'] !== '') {
                        $entidad_id = (int) $row['entidad'];
                    }
                }
            } catch (Throwable $e) {
                // ignorar
            }
        }
        return [
            'club_id' => $club_id,
            'id_directorio_club' => $id_directorio_club,
            'entidad_id' => $entidad_id,
            'club_nombre' => $club_nombre,
            'invitation' => $inv,
            'requiere_registro' => $resolved['requiere_registro'],
        ];
    }

    /**
     * Genera la URL de acceso único (join) para un token.
     * @param string $token
     * @param string|null $baseUrl Base URL de la app (ej. https://app.com o /mistorneos/public)
     * @return string
     */
    public static function buildJoinUrl(string $token, ?string $baseUrl = null): string
    {
        if ($baseUrl === null || $baseUrl === '') {
            $baseUrl = class_exists('AppHelpers') ? rtrim(AppHelpers::getPublicUrl(), '/') : '';
            if ($baseUrl === '') {
                $baseUrl = $GLOBALS['APP_CONFIG']['app']['base_url'] ?? '';
            }
            $baseUrl = rtrim($baseUrl, '/');
        }
        return $baseUrl . '/join?token=' . urlencode($token);
    }
}
