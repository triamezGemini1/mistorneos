<?php

declare(strict_types=1);

/** @var list<array{nombre:string,username:string,rol:string,cedula:string,ref:string}> $top5Staff */

?>
<section class="mn-section" aria-label="Resumen">
  <div class="mn-container mn-grid-3">
    <article class="mn-card" id="torneos">
      <h2 class="mn-card__title">Torneos activos</h2>
      <ul class="mn-list">
        <li><span class="mn-placeholder">Sin datos — conectar capa de datos</span></li>
      </ul>
    </article>
    <article class="mn-card" id="resultados">
      <h2 class="mn-card__title">Top 5 — equipo de gestión</h2>
      <p class="mn-hint" style="margin-top:0;margin-bottom:0.75rem;">
        Registros recientes en la tabla maestra <code>usuarios</code> (administradores y delegados).
        La base auxiliar de <strong>personas</strong> no se usa aquí: solo para validar datos al dar de alta usuarios.
      </p>
      <?php if ($top5Staff === []) : ?>
        <ul class="mn-list">
          <li><span class="mn-placeholder">Sin filas que mostrar o error de consulta.</span></li>
        </ul>
      <?php else : ?>
        <ul class="mn-list">
          <?php foreach ($top5Staff as $row) : ?>
            <li>
              <strong><?= htmlspecialchars($row['nombre'] !== '' ? $row['nombre'] : $row['username'], ENT_QUOTES, 'UTF-8') ?></strong><br />
              <span class="mn-placeholder" style="font-size:0.875rem;">
                <?= htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') ?>
                · rol <span class="mn-tag mn-tag--ok"><?= htmlspecialchars($row['rol'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php if ($row['cedula'] !== '') : ?>
                  <br />Doc. <?= htmlspecialchars($row['cedula'], ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
                <br /><span class="mn-results__meta">Ref. tiempo: <?= htmlspecialchars($row['ref'], ENT_QUOTES, 'UTF-8') ?></span>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </article>
    <article class="mn-card" id="ranking">
      <h2 class="mn-card__title">Ranking local</h2>
      <ul class="mn-list">
        <li><span class="mn-placeholder">Sin datos — conectar capa de datos</span></li>
      </ul>
    </article>
  </div>
</section>
