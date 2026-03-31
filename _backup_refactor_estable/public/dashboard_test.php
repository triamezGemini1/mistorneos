<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/config/bootstrap.php';
require $root . '/config/internal_to_landing.php';
require_once $root . '/app/Database/ConnectionException.php';
require_once $root . '/app/Database/Connection.php';
require_once $root . '/app/Core/InstitucionalContextService.php';

$u = $_SESSION['admin_user'] ?? null;
if (!is_array($u) || empty($u['id'])) {
    header('Location: index.php', true, 303);
    exit;
}

$mn_institucional = [
    'entidad' => ['id' => 0, 'nombre' => '', 'logo' => null],
    'organizacion' => ['id' => 0, 'nombre' => '', 'logo' => null],
    'club' => null,
    'torneo' => null,
];
try {
    $mn_institucional = InstitucionalContextService::forAdmin(Connection::get(), is_array($u) ? $u : null, null, null);
} catch (Throwable $e) {
    $mn_institucional = InstitucionalContextService::forAdmin(null, is_array($u) ? $u : null, null, null);
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Sesión — mistorneos</title>
  <link rel="stylesheet" href="assets/css/mistorneos-core.css" />
</head>
<body>
  <?php require $root . '/public/views/partials/header_institucional.php'; ?>
  <div class="mn-container" style="padding:2rem 0;">
    <div class="mn-card" style="max-width:36rem;">
      <h1 class="mn-card__title">Acceso correcto (prueba)</h1>
      <p class="mn-placeholder">Usuario: <?= htmlspecialchars((string) $u['username'], ENT_QUOTES, 'UTF-8') ?></p>
      <p class="mn-placeholder">Rol: <?= htmlspecialchars((string) $u['role'], ENT_QUOTES, 'UTF-8') ?></p>
      <p class="mn-hint">Esta página confirma la sesión administrador. Sustituir por el panel real en fases siguientes.</p>
      <p class="mn-hint" style="margin-top:1rem;">Motor de torneos: <a href="admin_torneo.php">Listado y check-in por organización</a> · <a href="checkin.php?torneo_id=1">Check-in directo</a> (ajuste <code>torneo_id</code>).</p>
      <p style="margin-top:1.5rem;"><a class="mn-btn mn-btn--success" href="index.php">Volver al inicio</a></p>
    </div>
  </div>
</body>
</html>
