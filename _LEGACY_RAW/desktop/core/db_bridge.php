<?php
/**
 * Bridge de base de datos para desktop/core.
 * Este archivo no debe producir salida (no echo/print, sin espacio antes de <?php) para evitar "headers already sent".
 * Ruta única: DESKTOP_DB_PATH (mistorneos/desktop/data/mistorneos_local.db).
 * CLI e interfaz web usan el mismo archivo. Expone DB::pdo() y DB::getEntidadId().
 */
require_once __DIR__ . '/config.php';

if (!defined('DESKTOP_CORE_DB_LOADED')) {
    define('DESKTOP_CORE_DB_LOADED', true);
}

class DB
{
    private static ?PDO $pdo = null;

    /** Ruta única y absoluta de la BD (definida en config.php). */
    private static function getDbPath(): string
    {
        $path = defined('DESKTOP_DB_PATH') ? DESKTOP_DB_PATH : (dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mistorneos_local.db');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $path;
    }

    /**
     * Devuelve el entidad_id del administrador local (filtro global).
     * Prioridad: sesión (usuario logueado) > constante DESKTOP_ENTIDAD_ID.
     */
    public static function getEntidadId(): int
    {
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['desktop_entidad_id'])) {
            return (int) $_SESSION['desktop_entidad_id'];
        }
        return (int) (defined('DESKTOP_ENTIDAD_ID') ? DESKTOP_ENTIDAD_ID : 0);
    }

    /**
     * Obtiene la conexión PDO a SQLite (db_local.sqlite).
     * Inyecta también $conn y $db en el ámbito global para código que espere esas variables.
     */
    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $path = self::getDbPath();
            $dsn = 'sqlite:' . $path;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            self::$pdo = new PDO($dsn, null, null, $options);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
            self::$pdo->exec('PRAGMA journal_mode = WAL');
            $GLOBALS['conn'] = self::$pdo;
            $GLOBALS['db']  = self::$pdo;
        }
        return self::$pdo;
    }

    /**
     * Resetea la conexión (útil para tests o recarga).
     */
    public static function reset(): void
    {
        self::$pdo = null;
    }
}
