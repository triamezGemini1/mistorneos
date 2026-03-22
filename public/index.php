<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/config/bootstrap.php';
require_once $root . '/app/Database/ConnectionException.php';
require_once $root . '/app/Database/Connection.php';
require_once $root . '/app/Core/UsuariosMaestroSnapshot.php';

$top5Staff = [];
try {
    $pdoLanding = Connection::get();
    $top5Staff = UsuariosMaestroSnapshot::top5StaffRecientes($pdoLanding);
} catch (Throwable $e) {
    error_log('landing top5 staff (usuarios maestro): ' . $e->getMessage());
}

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
        case 'db':
            $authMessage = 'No hay conexión con la base de datos en este momento.';
            break;
        case 'unsafe_storage':
            $authMessage = 'Las contraseñas en base de datos usan un formato antiguo (p. ej. MD5 o SHA-256). Deben migrarse a password_hash() de PHP antes de permitir el acceso.';
            break;
        case 'inactive':
            $authMessage = 'La cuenta está inactiva o deshabilitada.';
            break;
        default:
            break;
    }
}
if ($authMessage === null && isset($_GET['acceso'])) {
    if ((string) $_GET['acceso'] === 'restringido') {
        $authMessage = 'Inicie sesión o regístrese para acceder a esa sección.';
    } elseif ((string) $_GET['acceso'] === 'admin') {
        $authMessage = 'Inicie sesión como administrador para acceder al check-in u otras herramientas del panel.';
    }
}

$invitacionHint = null;
if (isset($_GET['invitacion']) && (string) $_GET['invitacion'] === '1') {
    $invitacionHint = 'Ha entrado por un enlace de torneo. Complete el registro con su cédula en la sección de abajo; al finalizar quedará inscrito en el torneo si corresponde.';
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
  <?php if ($invitacionHint !== null) : ?>
    <div class="mn-container" style="padding-top:0.5rem;" role="status">
      <p class="mn-hint" style="background:#e8f4fd;border:1px solid var(--mn-border);border-radius:var(--mn-radius);padding:0.75rem 1rem;"><?= htmlspecialchars($invitacionHint, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
  <?php endif; ?>
  <?php require $partials . '/navbar.php'; ?>
  <?php require $partials . '/hero.php'; ?>
  <?php require $partials . '/registro_padron.php'; ?>
  <?php require $partials . '/info_grid.php'; ?>
  <?php require $partials . '/footer.php'; ?>
  <?php require $authView; ?>
  <script src="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>assets/js/auth-modal.js" defer></script>
  <script src="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>assets/js/landing-search.js" defer></script>
  <script src="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>assets/js/registro-padron.js" defer></script>
</body>
</html>
