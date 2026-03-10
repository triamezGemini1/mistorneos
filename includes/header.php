<?php
/**
 * Cabecera común: estructura HTML superior, metadatos y carga de assets (mistorneos).
 * Favicon y rutas base dinámicos según entorno (/pruebas/public, /mistorneos_beta/public, etc.).
 * Uso: definir $header_title opcional; luego include_once __DIR__ . '/../includes/header.php';
 * No cierra </head> para que la página pueda añadir estilos o meta adicionales.
 */
$header_title = $header_title ?? 'La Estación del Dominó';
// Ruta base para favicon y assets: misma que public/ para que logos e iconos carguen en cualquier despliegue
$header_asset_base = '';
if (defined('URL_BASE') && URL_BASE !== '' && URL_BASE !== '/') {
    $header_asset_base = rtrim(URL_BASE, '/');
} elseif (class_exists('AppHelpers')) {
    $pu = AppHelpers::getPublicUrl();
    $header_asset_base = (strpos($pu, 'http') === 0) ? parse_url($pu, PHP_URL_PATH) : $pu;
}
if ($header_asset_base === null || $header_asset_base === '') {
    $header_asset_base = '/mistorneos_beta/public';
}
$header_asset_base = rtrim($header_asset_base, '/');
?>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
  <meta name="theme-color" content="#1a365d">
  <!-- Favicon: ruta dinámica según subcarpeta (pruebas, mistorneos_beta, etc.) -->
  <link rel="icon" type="image/png" sizes="32x32" href="<?= htmlspecialchars($header_asset_base) ?>/favicon.png">
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($header_asset_base) ?>/favicon.ico">
  <title><?= htmlspecialchars($header_title) ?></title>
  <meta name="description" content="mistorneos - La Estación del Dominó. Gestión de torneos, inscripciones y resultados.">
