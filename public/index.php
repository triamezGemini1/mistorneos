<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/config/bootstrap.php';

$script = $_SERVER['SCRIPT_NAME'] ?? '';
$publicPrefix = str_contains($script, '/public/') ? '' : 'public/';

$csrfToken = csrf_token();

$authMessage = null;
if (isset($_GET['auth'])) {
    switch ((string) $_GET['auth']) {
        case 'csrf':
            $authMessage = 'Sesión de seguridad caducada. Intente de nuevo.';
            break;
        case 'invalid':
            $authMessage = 'Revise usuario y contraseña.';
            break;
        case 'pending':
            $authMessage = 'Validación recibida. La capa de datos aún no está conectada.';
            break;
        default:
            break;
    }
}

$partials = $root . '/public/views/partials';
$authView = $root . '/modules/auth/views/login_modal.php';

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>mistorneos</title>
  <link rel="stylesheet" href="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>assets/css/mistorneos-core.css" />
</head>
<body>
  <a class="mn-skip" href="#buscar">Ir al buscador</a>
  <?php if ($authMessage !== null) : ?>
    <div class="mn-container" style="padding-top:1rem;" role="status">
      <p class="mn-hint mn-hint--error"><?= htmlspecialchars($authMessage, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
  <?php endif; ?>
  <?php require $partials . '/navbar.php'; ?>
  <?php require $partials . '/hero.php'; ?>
  <?php require $partials . '/info_grid.php'; ?>
  <?php require $partials . '/footer.php'; ?>
  <?php require $authView; ?>
  <script src="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>assets/js/auth-modal.js" defer></script>
</body>
</html>
