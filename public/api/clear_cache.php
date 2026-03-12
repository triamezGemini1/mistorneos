<?php
/**
 * Script temporal para forzar que PHP sirva la versión actual de los archivos
 * (resetea OPcache). ELIMINAR después de usarlo en producción.
 *
 * Uso: https://tu-dominio.com/mistorneos_beta/public/api/clear_cache.php
 */
header('Content-Type: text/plain; charset=utf-8');

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache reseteado con éxito. Los scripts se recargarán desde disco en la próxima petición.\n";
} else {
    echo "OPcache no está activo o disponible en este servidor.\n";
}
