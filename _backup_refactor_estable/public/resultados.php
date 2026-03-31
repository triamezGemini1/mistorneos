<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/config/bootstrap.php';
require_once $root . '/app/Database/ConnectionException.php';
require_once $root . '/app/Database/Connection.php';
require_once $root . '/app/Core/PublicSiteService.php';

$script = $_SERVER['SCRIPT_NAME'] ?? '';
$publicPrefix = str_contains($script, '/public/') ? '' : 'public/';
$csrfToken = csrf_token();

$torneos = [];
try {
    $pdo = Connection::get();
    $torneos = PublicSiteService::listarTorneosPublicos($pdo, 120);
} catch (Throwable $e) {
    error_log('public/resultados.php: ' . $e->getMessage());
}

$pageTitle = 'Resultados y torneos';

ob_start();
?>
  <div class="mn-container" style="padding:2rem 0;">
    <header class="mn-public-head">
      <h1 class="mn-hero__title" style="margin-bottom:0.5rem;">Resultados y torneos publicados</h1>
      <p class="mn-hint" style="margin-top:0;">
        Consulta sin iniciar sesión. Para gestión operativa usá <a href="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>index.php">el inicio</a>.
      </p>
    </header>

    <div class="mn-card" style="margin-top:1.5rem;overflow:auto;">
      <table class="mn-public-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Fecha</th>
            <th>Organización</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($torneos as $t) : ?>
            <tr>
              <td><?= (int) ($t['id'] ?? 0) ?></td>
              <td><?= htmlspecialchars((string) ($t['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string) ($t['fechator'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string) ($t['organizacion_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
              <td>
                <a class="mn-btn mn-btn--ghost" style="font-size:0.875rem;padding:0.35rem 0.75rem;" href="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>resultado_torneo.php?torneo_id=<?= (int) ($t['id'] ?? 0) ?>">Ver ficha</a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if ($torneos === []) : ?>
            <tr><td colspan="5" class="mn-hint">No hay torneos publicados o sin conexión a datos.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php
$content = ob_get_clean();
require $root . '/public/views/layout_public.php';
