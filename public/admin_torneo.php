<?php

declare(strict_types=1);

/**
 * Gestión de torneos (núcleo nuevo): listado acotado por organización y cintillo institucional.
 */

$root = dirname(__DIR__);
require $root . '/config/bootstrap.php';
require_once $root . '/app/Database/ConnectionException.php';
require_once $root . '/app/Database/Connection.php';
require_once $root . '/app/Core/TorneoService.php';
require_once $root . '/app/Core/InstitucionalContextService.php';
require_once $root . '/app/Helpers/AdminApi.php';

if (mn_admin_session() === null) {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $to = str_contains($script, '/public/') ? 'index.php' : 'public/index.php';
    header('Location: ' . $to . '?acceso=admin', true, 303);
    exit;
}

$admin = mn_admin_session();
$scope = mn_admin_torneo_query_scope();

$script = $_SERVER['SCRIPT_NAME'] ?? '';
$publicPrefix = str_contains($script, '/public/') ? '' : 'public/';

$error = null;
$torneos = [];

try {
    $pdo = Connection::get();
} catch (ConnectionException $e) {
    $pdo = null;
    $error = 'Sin conexión a la base de datos.';
}

if ($pdo !== null) {
    if ($scope === false) {
        $error = 'Su rol requiere una organización asignada para ver torneos.';
    } elseif ($scope === null) {
        $torneos = TorneoService::listarRecientesGlobal($pdo, 80);
    } else {
        $torneos = TorneoService::listarPorOrganizacion($pdo, $scope, 200);
    }
}

$mn_institucional = [];
try {
    $mn_institucional = InstitucionalContextService::forAdmin($pdo, $admin, null, null);
} catch (Throwable $e) {
    $mn_institucional = InstitucionalContextService::forAdmin(null, $admin, null, null);
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Torneos — mistorneos</title>
  <link rel="stylesheet" href="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>assets/css/mistorneos-core.css" />
</head>
<body class="mn-dash-body">
  <?php require $root . '/public/views/partials/header_institucional.php'; ?>
  <div class="mn-container" style="padding:1.25rem 0 2rem;">
    <header style="margin-bottom:1.25rem;">
      <h1 style="margin:0;font-size:1.35rem;color:var(--mn-blue-deep);">Torneos de su ámbito</h1>
      <p class="mn-hint" style="margin:0.35rem 0 0;">
        <?php if ($scope === null) : ?>
          Vista de administrador general (sin filtro por organización en esta pantalla).
        <?php else : ?>
          Solo se listan torneos con <code>organizacion_id</code> = su organización.
        <?php endif; ?>
      </p>
    </header>

    <?php if ($error !== null) : ?>
      <p class="mn-hint mn-hint--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php else : ?>
      <div class="mn-checkin-table-scroll" style="max-width:56rem;">
        <table class="mn-checkin-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Nombre</th>
              <th>Fecha</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($torneos as $t) : ?>
              <tr>
                <td><?= (int) ($t['id'] ?? 0) ?></td>
                <td><?= htmlspecialchars((string) ($t['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($t['fechator'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                  <a class="mn-btn mn-btn--success" style="font-size:0.875rem;padding:0.35rem 0.75rem;" href="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>checkin.php?torneo_id=<?= (int) ($t['id'] ?? 0) ?>">Check-in</a>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if ($torneos === []) : ?>
              <tr><td colspan="4" class="mn-hint">No hay torneos en este ámbito.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <p style="margin-top:1.5rem;">
      <a href="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>dashboard_test.php">← Volver al panel admin</a>
    </p>
  </div>
</body>
</html>
