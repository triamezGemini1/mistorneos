<?php
/**
 * Limpia OPcache en el proceso PHP que atiende esta petición.
 * ELIMINAR este archivo después de usarlo (seguridad).
 *
 * Uso: https://tu-dominio.com/mistorneos_beta/public/clear_opcache.php
 */
header('Content-Type: text/plain; charset=utf-8');

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache se ha limpiado correctamente.\n";
    echo "\nNota: con PHP-FPM solo se limpia el worker que atendió esta petición.\n";
    echo "Si el problema continúa, reinicia PHP-FPM o visita esta URL varias veces.\n";
} else {
    echo "OPcache no está instalado o habilitado.\n";
}
