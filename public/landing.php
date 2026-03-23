<?php
/**
 * Landing Page Pública - La Estación del Dominó
 * Redirige a la landing oficial (SPA): landing-spa.php
 * Usa getPublicUrl() para que el retorno desde login funcione en cualquier subcarpeta.
 */
try {
    require_once __DIR__ . '/../config/bootstrap.php';
    require_once __DIR__ . '/../lib/app_helpers.php';
    $landing_spa_url = rtrim(class_exists('AppHelpers') ? AppHelpers::getPublicUrl() : (rtrim(app_base_url(), '/') . '/public'), '/') . '/landing-spa.php';
    if (!headers_sent()) {
        header('Location: ' . $landing_spa_url, true, 302);
    }
    exit;
} catch (Throwable $e) {
    error_log('landing.php: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Error</title></head><body><p>Error al cargar. <a href="login.php">Ir al login</a></p></body></html>';
    exit;
}
