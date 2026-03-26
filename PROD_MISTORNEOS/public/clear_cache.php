<?php
/**
 * Script temporal para limpiar caché de OPcache
 * ELIMINAR ESTE ARCHIVO DESPUÉS DE USARLO POR SEGURIDAD
 */

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ OPcache limpiado exitosamente";
} else {
    echo "ℹ️ OPcache no está habilitado";
}

// Eliminar este archivo después de usarlo
echo "\n\n⚠️ RECUERDA ELIMINAR ESTE ARCHIVO (clear_cache.php) POR SEGURIDAD";












