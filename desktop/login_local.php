<?php
/**
 * Punto de entrada Desktop (sin "public" en la URL).
 * Redirige al login real en public/desktop/
 */
$_desktop_base = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'desktop';
require $_desktop_base . DIRECTORY_SEPARATOR . 'login_local.php';
