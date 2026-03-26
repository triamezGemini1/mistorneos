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

$torneoId = isset($_GET['torneo_id']) ? (int) $_GET['torneo_id'] : 0;
$error = null;
$torneo = null;
$nInscritos = 0;
$nPartidas = 0;

if ($torneoId <= 0) {
    $error = 'Indique un torneo válido (resultado_torneo.php?torneo_id=ID).';
} else {
    try {
        $pdo = Connection::get();
        $torneo = PublicSiteService::obtenerTorneoPublico($pdo, $torneoId);
        if ($torneo === null) {
            $error = 'Torneo no encontrado.';
        } else {
            $nInscritos = PublicSiteService::contarInscritosTorneo($pdo, $torneoId);
            $nPartidas = PublicSiteService::contarPartidasRegistradas($pdo, $torneoId);
        }
    } catch (Throwable $e) {
        $error = 'No se pudo cargar la información.';
        error_log('public/resultado_torneo.php: ' . $e->getMessage());
    }
}

$pageTitle = $torneo !== null ? (string) ($torneo['nombre'] ?? 'Torneo') : 'Torneo';

ob_start();
?>
  <div class="mn-container" style="padding:2rem 0;">
    <?php if ($error !== null) : ?>
      <p class="mn-hint mn-hint--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
      <p><a href="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>resultados.php">← Volver a resultados</a></p>
    <?php else : ?>
      <header class="mn-public-head">
        <h1 class="mn-hero__title" style="margin-bottom:0.5rem;"><?= htmlspecialchars((string) ($torneo['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="mn-hint" style="margin-top:0;">
          <?= htmlspecialchars((string) ($torneo['fechator'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
          <?php if (($torneo['lugar'] ?? '') !== '') : ?>
            · Lugar: <?= htmlspecialchars((string) $torneo['lugar'], ENT_QUOTES, 'UTF-8') ?>
          <?php endif; ?>
        </p>
        <?php if (($torneo['organizacion_nombre'] ?? '') !== '') : ?>
          <p class="mn-hint" style="margin-top:0.75rem;">Organización: <?= htmlspecialchars((string) $torneo['organizacion_nombre'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
      </header>

      <div class="mn-grid-3 mn-card" style="margin-top:1.5rem;padding:1.25rem;">
        <article>
          <h2 class="mn-card__title" style="font-size:1rem;">Inscripciones</h2>
          <p class="mn-placeholder" style="font-size:1.5rem;font-weight:700;"><?= (int) $nInscritos ?></p>
        </article>
        <article>
          <h2 class="mn-card__title" style="font-size:1rem;">Partidas registradas</h2>
          <p class="mn-placeholder" style="font-size:1.5rem;font-weight:700;"><?= (int) $nPartidas ?></p>
        </article>
        <article>
          <h2 class="mn-card__title" style="font-size:1rem;">Modalidad</h2>
          <p class="mn-placeholder"><?= htmlspecialchars((string) ($torneo['tipo_torneo'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
        </article>
      </div>

      <p class="mn-hint" style="margin-top:1.5rem;">
        Clasificación detallada por mesa puede consultarse desde el equipo del torneo o herramientas internas cuando estén publicadas.
      </p>

      <p style="margin-top:1.5rem;">
        <a href="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>resultados.php">← Todos los torneos</a>
        · <a href="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>calendario.php">Calendario</a>
      </p>
    <?php endif; ?>
  </div>
<?php
$content = ob_get_clean();
require $root . '/public/views/layout_public.php';
