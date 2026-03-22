<?php
/**
 * Layout mínimo para la vista Podios / Podios Equipos.
 * Se usa cuando se accede a Podios desde el dashboard para mostrar solo la página
 * de podios (sin sidebar ni header del dashboard).
 */
$layout_asset_base = AppHelpers::getPublicUrl();
$page_title = 'Podios';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <base href="<?= htmlspecialchars($layout_asset_base) ?>/">
  <title><?= htmlspecialchars($page_title) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php require_once __DIR__ . '/../../modules/torneo_gestion.php'; ?>
</body>
</html>
