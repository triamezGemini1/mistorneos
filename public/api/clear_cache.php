<?php
/**
 * Script temporal para forzar que PHP sirva la versión actual de los archivos
 * (resetea OPcache). ELIMINAR después de usarlo en producción.
 *
 * Uso: https://tu-dominio.com/mistorneos_beta/public/api/clear_cache.php
 */
header('Content-Type: text/plain; charset=utf-8');

$guardar_equipo = __DIR__ . '/guardar_equipo.php';

if (function_exists('opcache_reset')) {
    if (function_exists('opcache_invalidate') && file_exists($guardar_equipo)) {
        opcache_invalidate($guardar_equipo, true);
        echo "guardar_equipo.php invalidado en OPcache.\n";
    }
    opcache_reset();
    echo "OPcache reseteado con éxito.\n";
} else {
    echo "OPcache no está activo o disponible en este servidor.\n";
}

echo "\n---\n";
echo "Si el log SIGUE mostrando 'POST recibido' (en lugar de 'POST/input recibido'),\n";
echo "PHP-FPM está usando varios workers y solo se limpió uno. Reinicia PHP-FPM:\n";
echo "  sudo systemctl restart php-fpm\n";
echo "  (o php8.1-fpm / php8.2-fpm según tu versión)\n";
echo "\nEn hosting compartido, desde el panel busca 'Reiniciar PHP' o contacta al proveedor.\n";
