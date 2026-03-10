<?php
/**
 * Footer común: cierre de etiquetas </body></html> y scripts JS globales (rutas absolutas CDN).
 * Único punto para pie de página y scripts compartidos.
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
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
</body>
</html>
