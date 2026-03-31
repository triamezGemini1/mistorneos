<?php

declare(strict_types=1);

/**
 * Modo provisional: pantallas internas redirigen al landing hasta definir el nuevo dashboard.
 * Ponga define(..., false) o elimine la definición y use solo el return para restaurar rutas internas.
 */
if (!defined('MN_INTERNAL_ROUTES_REDIRECT_LANDING')) {
    define('MN_INTERNAL_ROUTES_REDIRECT_LANDING', false);
}

if (!MN_INTERNAL_ROUTES_REDIRECT_LANDING) {
    return;
}

require_once dirname(__DIR__) . '/app/Helpers/LandingUrl.php';
header('Location: ' . mn_landing_absolute_url(), true, 303);
exit;
