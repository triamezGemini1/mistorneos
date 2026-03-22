<?php
/**
 * Redireccin al formulario de nuevo club en el dashboard.
 * Mantiene compatibilidad con enlaces antiguos (invitations, etc.).
 */
if (!defined('APP_BOOTSTRAPPED')) {
    require_once __DIR__ . '/../../config/bootstrap.php';
}
$url = (class_exists('AppHelpers') && method_exists('AppHelpers', 'dashboard'))
    ? AppHelpers::dashboard('clubs', ['action' => 'new'])
    : 'index.php?page=clubs&action=new';
header('Location: ' . $url);
exit;
