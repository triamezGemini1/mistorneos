<?php

declare(strict_types=1);

/**
 * Plantilla páginas públicas (misma cabecera/pie que el landing).
 *
 * @var string $pageTitle
 * @var string $publicPrefix
 * @var string $root
 * @var string $content HTML del <main>
 */

$pageTitle = isset($pageTitle) ? (string) $pageTitle : 'mistorneos';
$publicPrefix = isset($publicPrefix) ? (string) $publicPrefix : '';
$root = isset($root) ? (string) $root : dirname(__DIR__, 2);
$content = isset($content) ? (string) $content : '';

/**
 * Requiere que la página que incluye este layout haya cargado ya
 * config/bootstrap.php (sesión, AuthHelper) y defina $csrfToken si usa el modal de login.
 */
if (!isset($csrfToken)) {
    $csrfToken = function_exists('csrf_token') ? csrf_token() : '';
}
require_once $root . '/app/Helpers/AdminApi.php';

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — mistorneos</title>
  <link rel="stylesheet" href="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>assets/css/mistorneos-core.css" />
</head>
<body>
  <a class="mn-skip" href="#mn-public-main">Ir al contenido</a>
  <?php require $root . '/public/views/partials/navbar.php'; ?>
  <main id="mn-public-main" class="mn-public-page">
    <?= $content ?>
  </main>
  <?php require $root . '/public/views/partials/footer.php'; ?>
  <?php
  $authView = $root . '/modules/auth/views/login_modal.php';
  if (is_readable($authView)) {
      require $authView;
  }
  ?>
  <script src="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>assets/js/auth-modal.js" defer></script>
</body>
</html>
