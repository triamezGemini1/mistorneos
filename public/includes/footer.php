<?php
/**
 * Footer común: scripts JS y cierre de body/html.
 * Bootstrap (ruta absoluta CDN) para páginas que no usan layout.php; luego core footer (jQuery, DataTables, cierre).
 */
if (!isset($layout_asset_base) || $layout_asset_base === '') {
    $layout_asset_base = class_exists('AppHelpers') ? AppHelpers::getPublicUrl() : '';
    if ($layout_asset_base === '' && !empty($_SERVER['SCRIPT_NAME'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $layout_asset_base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    }
}
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
<?php include_once __DIR__ . '/../../core/includes/footer.php';
