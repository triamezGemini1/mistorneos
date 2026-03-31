<?php

declare(strict_types=1);

/** @var list<array<string, mixed>> $torneosPublicos */
/** @var string $publicPrefix */

$torneosPublicos = isset($torneosPublicos) && is_array($torneosPublicos) ? $torneosPublicos : [];
$resUrl = htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') . 'resultados.php';
$calUrl = htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') . 'calendario.php';
$afilUrl = htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') . 'afiliacion.php';

?>
<section class="mn-section" id="destacados" aria-label="Torneos y accesos">
  <div class="mn-container mn-grid-3">
    <article class="mn-card" id="torneos">
      <h2 class="mn-card__title">Próximos y recientes</h2>
      <?php if ($torneosPublicos === []) : ?>
        <ul class="mn-list">
          <li><span class="mn-placeholder">No hay torneos cargados o revisá la conexión a la base de datos.</span></li>
        </ul>
      <?php else : ?>
        <ul class="mn-list">
          <?php foreach (array_slice($torneosPublicos, 0, 6) as $t) : ?>
            <li>
              <a href="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>resultado_torneo.php?torneo_id=<?= (int) ($t['id'] ?? 0) ?>">
                <?= htmlspecialchars((string) ($t['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
              </a>
              <br />
              <span class="mn-placeholder" style="font-size:0.875rem;">
                <?= htmlspecialchars((string) ($t['fechator'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <p style="margin-top:1rem;">
        <a class="mn-btn mn-btn--ghost" style="font-size:0.875rem;" href="<?= $calUrl ?>">Ver calendario completo</a>
      </p>
    </article>
    <article class="mn-card" id="resultados">
      <h2 class="mn-card__title">Resultados</h2>
      <p class="mn-hint" style="margin-top:0;margin-bottom:0.75rem;">
        Listado de torneos publicados con enlace a la ficha de cada evento (inscripciones y actividad registrada).
      </p>
      <a class="mn-btn mn-btn--success mn-btn--block" href="<?= $resUrl ?>">Abrir resultados</a>
    </article>
    <article class="mn-card" id="ranking">
      <h2 class="mn-card__title">Afiliación</h2>
      <p class="mn-hint" style="margin-top:0;margin-bottom:0.75rem;">
        Clubes y organizaciones pueden iniciar una solicitud de alta. El contacto se configura en el entorno del servidor.
      </p>
      <a class="mn-btn mn-btn--ghost mn-btn--block" href="<?= $afilUrl ?>">Solicitar afiliación</a>
    </article>
  </div>
</section>
