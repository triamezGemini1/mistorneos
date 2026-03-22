<?php

declare(strict_types=1);

/**
 * Carga independiente (individual / equipos): hasta 4 registros partiresul editables por separado.
 *
 * @var list<array<string, mixed>> $filas
 * @var string $csrfToken
 * @var int $torneoId
 * @var int $mesaId
 * @var int $partida
 * @var int $puntosObjetivo
 */
$filas = $filas ?? [];
?>
<form id="mn-form-carga-estandar" class="mn-carga-res-form mn-form-carga--independiente" method="post" action="carga_resultados.php" novalidate>
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
  <input type="hidden" name="action" value="guardar_estandar" />
  <input type="hidden" name="torneo_id" value="<?= (int) $torneoId ?>" />
  <input type="hidden" name="mesa_id" value="<?= (int) $mesaId ?>" />
  <input type="hidden" name="partida" value="<?= (int) $partida ?>" />
  <input type="hidden" name="modelo" value="estandar" />

  <div class="mn-carga-res-gridhead" role="row">
    <span class="mn-carga-res-th">Jugador</span>
    <span class="mn-carga-res-th">Puntos</span>
    <span class="mn-carga-res-th">Sets</span>
    <span class="mn-carga-res-th">Chancleta / Zapato</span>
  </div>

  <?php foreach ($filas as $idx => $f) :
      if ($idx >= 4) {
          break;
      }
      $pid = (int) ($f['id'] ?? 0);
      $nom = trim((string) ($f['nombre'] ?? ''));
      $ced = trim((string) ($f['cedula'] ?? ''));
      $r1 = (int) ($f['resultado1'] ?? 0);
      $r2 = (int) ($f['resultado2'] ?? 0);
      $ch = (int) ($f['chancleta'] ?? 0);
      $za = (int) ($f['zapato'] ?? 0);
      ?>
    <div class="mn-carga-res-row" data-linea="<?= (int) $idx ?>">
      <input type="hidden" name="lineas[<?= (int) $idx ?>][partiresul_id]" value="<?= $pid ?>" />
      <div class="mn-carga-res-cell mn-carga-res-cell--jug">
        <strong class="mn-carga-res-nom"><?= htmlspecialchars($nom !== '' ? $nom : '—', ENT_QUOTES, 'UTF-8') ?></strong>
        <?php if ($ced !== '') : ?>
          <span class="mn-carga-res-doc"><?= htmlspecialchars($ced, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
      </div>
      <div class="mn-carga-res-cell">
        <label class="mn-visually-hidden" for="puntos_<?= (int) $idx ?>">Puntos fila <?= (int) ($idx + 1) ?></label>
        <input class="mn-input mn-carga-res-input" type="number" name="lineas[<?= (int) $idx ?>][puntos]" id="puntos_<?= (int) $idx ?>" value="<?= $r1 ?>" min="0" step="1" inputmode="numeric" data-campo="puntos" required />
      </div>
      <div class="mn-carga-res-cell">
        <label class="mn-visually-hidden" for="sets_<?= (int) $idx ?>">Sets fila <?= (int) ($idx + 1) ?></label>
        <input class="mn-input mn-carga-res-input" type="number" name="lineas[<?= (int) $idx ?>][sets]" id="sets_<?= (int) $idx ?>" value="<?= $r2 ?>" min="0" step="1" inputmode="numeric" data-campo="sets" required />
      </div>
      <div class="mn-carga-res-cell mn-carga-res-cell--extras">
        <div class="mn-carga-res-extras">
          <label class="mn-visually-hidden" for="ch_<?= (int) $idx ?>">Chancleta</label>
          <input class="mn-input mn-carga-res-input mn-carga-res-input--sm" type="number" name="lineas[<?= (int) $idx ?>][chancleta]" id="ch_<?= (int) $idx ?>" value="<?= $ch ?>" min="0" step="1" placeholder="Chanc." title="Chancletas" />
          <label class="mn-visually-hidden" for="za_<?= (int) $idx ?>">Zapato</label>
          <input class="mn-input mn-carga-res-input mn-carga-res-input--sm" type="number" name="lineas[<?= (int) $idx ?>][zapato]" id="za_<?= (int) $idx ?>" value="<?= $za ?>" min="0" step="1" placeholder="Zap." title="Zapatos" />
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <p class="mn-carga-res-valid-msg mn-hint mn-hint--error" id="mn-carga-valid-estandar" hidden></p>

  <button type="submit" class="mn-btn mn-btn--success mn-carga-res-submit">Guardar resultado</button>
</form>
