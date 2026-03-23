<?php
/**
 * Cierre de body/html y scripts JS comunes (rutas absolutas CDN).
 * Incluir al final de la página antes de cerrar body.
 * Variables opcionales: $layout_asset_base (para scripts locales con base absoluta).
 */
$footer_asset_base = $layout_asset_base ?? '';
if ($footer_asset_base === '' && function_exists('AppHelpers::getPublicUrl')) {
    $footer_asset_base = AppHelpers::getPublicUrl();
}
if ($footer_asset_base === '' && defined('URL_BASE') && URL_BASE !== '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $footer_asset_base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(URL_BASE, '/');
}
?>
  <!-- jQuery (ruta absoluta CDN) -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <!-- DataTables (rutas absolutas CDN) -->
  <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
</body>
</html>
