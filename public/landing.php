<?php
/**
 * Landing Page Pública - La Estación del Dominó
 * Redirige a la landing oficial (SPA): landing-spa.php
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../lib/app_helpers.php';

$landing_spa_url = rtrim(app_base_url(), '/') . '/public/landing-spa.php';
header('Location: ' . $landing_spa_url, true, 302);
exit;
