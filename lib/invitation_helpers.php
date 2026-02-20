<?php


require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/environment.php';

/**
 * Helpers para generación y validación de URLs de invitación
 */
class InvitationHelpers {
    /**
     * Devuelve la ruta absoluta del directorio public en el filesystem
     */
    public static function publicDirPath(): string {
        return realpath(__DIR__ . '/../public') ?: (__DIR__ . '/../public');
    }

    /**
     * Verifica que el archivo simple_invitation_login.php existe en public
     */
    public static function simpleLoginExists(): bool {
        $path = self::publicDirPath() . '/simple_invitation_login.php';
        return file_exists($path) && is_file($path);
    }

    /**
     * Construye la URL absoluta al login simple de invitación
     */
    public static function buildSimpleInvitationUrl(int $torneoId, int $clubId): string {
        return AppHelpers::simpleInvitation($torneoId, $clubId);
    }
}
