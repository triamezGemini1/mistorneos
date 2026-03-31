<?php

declare(strict_types=1);

/**
 * Entrada del sitio: landing público completo (recuperado desde main, adaptado al refactor).
 * Unificar URL: .../mistorneos/public/ → .../mistorneos/
 */
$__mnScript = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
if ($__mnScript !== '' && str_contains($__mnScript, '/public/')) {
    require_once dirname(__DIR__) . '/app/Helpers/LandingUrl.php';
    header('Location: ' . mn_landing_absolute_url(), true, 303);
    exit;
}

require __DIR__ . '/landing.php';
