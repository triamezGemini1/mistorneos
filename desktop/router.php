<?php
/**
 * Router Desktop: sirve cualquier script de public/desktop/ cuando la URL es .../desktop/xxx.php
 */
$base = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'desktop';
$script = isset($_GET['d']) ? basename($_GET['d']) : 'index.php';
if (!preg_match('/^[a-z0-9_\-]+\.php$/i', $script) || !is_file($base . DIRECTORY_SEPARATOR . $script)) {
    $script = 'index.php';
}
require $base . DIRECTORY_SEPARATOR . $script;
