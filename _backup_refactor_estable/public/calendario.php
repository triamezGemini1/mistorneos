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
    $torneos = PublicSiteService::listarTorneosPublicos($pdo, 200);
} catch (Throwable $e) {
    error_log('public/calendario.php: ' . $e->getMessage());
}

/** @var array<string, list<array<string, mixed>>> $porMes */
$porMes = [];
foreach ($torneos as $t) {
    $raw = (string) ($t['fechator'] ?? '');
    $mes = 'Sin fecha';
    if ($raw !== '') {
        $ts = strtotime($raw);
        if ($ts !== false) {
            $mes = date('Y-m', $ts);
        }
    }
    if (!isset($porMes[$mes])) {
        $porMes[$mes] = [];
    }
    $porMes[$mes][] = $t;
}
ksort($porMes);

$pageTitle = 'Calendario de torneos';

ob_start();
?>
  <div class="mn-container" style="padding:2rem 0;">
    <header class="mn-public-head">
      <h1 class="mn-hero__title" style="margin-bottom:0.5rem;">Calendario de torneos</h1>
      <p class="mn-hint" style="margin-top:0;">
        Eventos agrupados por mes (fecha de referencia). <a href="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>resultados.php">Ver tabla de resultados</a>.
      </p>
    </header>

    <?php foreach ($porMes as $mes => $lista) : ?>
      <section class="mn-card" style="margin-top:1.25rem;" aria-labelledby="mn-cal-<?= htmlspecialchars(preg_replace('/\W+/', '-', $mes), ENT_QUOTES, 'UTF-8') ?>">
        <h2 class="mn-card__title" id="mn-cal-<?= htmlspecialchars(preg_replace('/\W+/', '-', $mes), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($mes === 'Sin fecha' ? 'Sin fecha definida' : $mes, ENT_QUOTES, 'UTF-8') ?></h2>
        <ul class="mn-list">
          <?php foreach ($lista as $t) : ?>
            <li>
              <strong><?= htmlspecialchars((string) ($t['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
              · <?= htmlspecialchars((string) ($t['fechator'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
              <?php if (trim((string) ($t['organizacion_nombre'] ?? '')) !== '') : ?>
                · <?= htmlspecialchars((string) $t['organizacion_nombre'], ENT_QUOTES, 'UTF-8') ?>
              <?php endif; ?>
              <br />
              <a href="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>resultado_torneo.php?torneo_id=<?= (int) ($t['id'] ?? 0) ?>">Ver ficha pública</a>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endforeach; ?>

    <?php if ($torneos === []) : ?>
      <p class="mn-hint mn-card" style="margin-top:1.25rem;padding:1rem;">No hay torneos para mostrar.</p>
    <?php endif; ?>
  </div>
<?php
$content = ob_get_clean();
require $root . '/public/views/layout_public.php';
