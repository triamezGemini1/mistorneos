<?php
/**
 * Constantes globales del sistema Desktop.
 * Incluido por db_bridge.php para que estén disponibles en todo el core.
 * Ruta única de BD y filtro por entidad para evitar mezcla de datos.
 */
if (!defined('DESKTOP_CORE_CONFIG_LOADED')) {
    define('DESKTOP_CORE_CONFIG_LOADED', true);
}

if (!defined('DESKTOP_VERSION')) {
    define('DESKTOP_VERSION', '1.0.4');
}
if (!defined('APP_NAME')) {
    define('APP_NAME', 'La Estación del Dominó - Desktop');
}
if (!defined('RELOAD_INTERVAL')) {
    define('RELOAD_INTERVAL', 30000); // Refresco automático de clasificación/sync (milisegundos)
}

/**
 * Ruta única y absoluta de la base de datos SQLite.
 * CLI y web comparten este mismo archivo (mistorneos/desktop/data/mistorneos_local.db).
 */
if (!defined('DESKTOP_DB_PATH')) {
    $base = dirname(__DIR__);
    $resolved = realpath($base);
    if ($resolved === false) {
        $resolved = $base;
    }
    define('DESKTOP_DB_PATH', $resolved . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mistorneos_local.db');
}

/**
 * Filtro global por entidad: entidad_id del administrador local.
 * Todas las inserciones en usuarios, tournaments e inscritos deben incluir este valor
 * para evitar mezcla de datos entre entidades. 0 = sin restricción.
 */
if (!defined('DESKTOP_ENTIDAD_ID')) {
    define('DESKTOP_ENTIDAD_ID', 0);
}
