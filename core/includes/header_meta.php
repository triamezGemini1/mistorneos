<?php
/**
 * Fragmento de head: favicon con ruta absoluta para evitar 404.
 * Usar en <head> con include_once.
 * Favicon: /mistorneos_beta/favicon.ico cuando la app está en subcarpeta; si no, /favicon.ico.
 */
if (!function_exists('core_favicon_path')) {
    function core_favicon_path(): string {
        if (defined('APP_FAVICON_PATH') && APP_FAVICON_PATH !== '') {
            return APP_FAVICON_PATH;
        }
        if (defined('URL_BASE') && URL_BASE !== '' && URL_BASE !== '/') {
            $base = rtrim(str_replace('\\', '/', dirname(URL_BASE)), '/');
            return ($base === '' || $base === '.') ? '/favicon.ico' : $base . '/favicon.ico';
        }
        if (!empty($_SERVER['SCRIPT_NAME'])) {
            $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
            if ($scriptDir !== '.' && $scriptDir !== '/' && (str_ends_with($scriptDir, '/public') || strpos($scriptDir, '/public/') !== false)) {
                $path = $scriptDir === '/public' ? '' : rtrim(preg_replace('#/public/?$#', '', $scriptDir), '/');
                if ($path !== '' && $path[0] === '/') {
                    return $path . '/favicon.ico';
                }
            }
        }
        return '/mistorneos_beta/favicon.ico';
    }
}
$favicon_href = core_favicon_path();
?>
<link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($favicon_href) ?>">
