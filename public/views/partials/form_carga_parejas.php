<?php

declare(strict_types=1);

/**
 * Modelo B: Pareja 1 vs Pareja 2 (4 filas partiresul: secuencia 1-2 = pareja A, 3-4 = pareja B).
 *
 * @var list<array<string, mixed>> $filas
 * @var string $csrfToken
 * @var int $torneoId
 * @var int $mesaId
 * @var int $partida
 * @var int $puntosObjetivo
 */
$filas = $filas ?? [];
$a = $filas[0] ?? null;
$b = $filas[1] ?? null;
$c = $filas[2] ?? null;
$d = $filas[3] ?? null;

$nomA = function (?array $x): string {
    return $x ? trim((string) ($x['nombre'] ?? '')) : '';
};
$cedA = function (?array $x): string {
    return $x ? trim((string) ($x['cedula'] ?? '')) : '';
};

$r1a = (int) ($a['resultado1'] ?? 0);
$r2a = (int) ($a['resultado2'] ?? 0);
$cha = (int) ($a['chancleta'] ?? 0);
$zaa = (int) ($a['zapato'] ?? 0);

$r1b = (int) ($c['resultado1'] ?? 0);
$r2b = (int) ($c['resultado2'] ?? 0);
$chb = (int) ($c['chancleta'] ?? 0);
$zab = (int) ($c['zapato'] ?? 0);
?>
<form id="mn-form-carga-parejas" class="mn-carga-res-form" method="post" action="carga_resultados.php" novalidate>
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
  <input type="hidden" name="action" value="guardar_parejas" />
  <input type="hidden" name="torneo_id" value="<?= (int) $torneoId ?>" />
  <input type="hidden" name="mesa_id" value="<?= (int) $mesaId ?>" />
  <input type="hidden" name="partida" value="<?= (int) $partida ?>" />
  <input type="hidden" name="modelo" value="parejas" />
  <input type="hidden" name="pid_a1" value="<?= (int) ($a['id'] ?? 0) ?>" />
  <input type="hidden" name="pid_a2" value="<?= (int) ($b['id'] ?? 0) ?>" />
  <input type="hidden" name="pid_b1" value="<?= (int) ($c['id'] ?? 0) ?>" />
  <input type="hidden" name="pid_b2" value="<?= (int) ($d['id'] ?? 0) ?>" />

  <div class="mn-carga-res-parejas">
    <section class="mn-carga-res-bloque mn-carga-res-bloque--a" aria-labelledby="mn-pareja-a">
      <h3 id="mn-pareja-a" class="mn-carga-res-bloque-title">Pareja A</h3>
      <p class="mn-carga-res-bloque-jug">
        <span class="mn-carga-res-bloque-nom"><?= htmlspecialchars($nomA($a) !== '' ? $nomA($a) : '—', ENT_QUOTES, 'UTF-8') ?></span>
        <?php if ($cedA($a) !== '') : ?><span class="mn-carga-res-doc"><?= htmlspecialchars($cedA($a), ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
        <span class="mn-carga-res-bloque-sep">+</span>
        <span class="mn-carga-res-bloque-nom"><?= htmlspecialchars($nomA($b) !== '' ? $nomA($b) : '—', ENT_QUOTES, 'UTF-8') ?></span>
        <?php if ($cedA($b) !== '') : ?><span class="mn-carga-res-doc"><?= htmlspecialchars($cedA($b), ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
      </p>
      <div class="mn-carga-res-bloque-fields">
        <label class="mn-label" for="puntos_pareja_a">Puntos (pareja)</label>
        <input class="mn-input mn-carga-res-input" type="number" name="puntos_A" id="puntos_pareja_a" value="<?= $r1a ?>" min="0" step="1" data-campo="puntos" data-pareja="A" required />
        <label class="mn-label" for="sets_pareja_a">Sets (pareja)</label>
        <input class="mn-input mn-carga-res-input" type="number" name="sets_A" id="sets_pareja_a" value="<?= $r2a ?>" min="0" step="1" data-campo="sets" required />
        <div class="mn-carga-res-extras mn-carga-res-extras--block">
          <label class="mn-label" for="ch_a">Extras del vínculo</label>
          <div class="mn-carga-res-extras">
            <input class="mn-input mn-carga-res-input mn-carga-res-input--sm" type="number" name="chancleta_A" id="ch_a" value="<?= $cha ?>" min="0" placeholder="Chanc." title="Chancletas (pareja A)" />
            <input class="mn-input mn-carga-res-input mn-carga-res-input--sm" type="number" name="zapato_A" id="za_a" value="<?= $zaa ?>" min="0" placeholder="Zap." title="Zapatos (pareja A)" />
          </div>
        </div>
      </div>
    </section>

    <section class="mn-carga-res-bloque mn-carga-res-bloque--b" aria-labelledby="mn-pareja-b">
      <h3 id="mn-pareja-b" class="mn-carga-res-bloque-title">Pareja B</h3>
      <p class="mn-carga-res-bloque-jug">
        <span class="mn-carga-res-bloque-nom"><?= htmlspecialchars($nomA($c) !== '' ? $nomA($c) : '—', ENT_QUOTES, 'UTF-8') ?></span>
        <?php if ($cedA($c) !== '') : ?><span class="mn-carga-res-doc"><?= htmlspecialchars($cedA($c), ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
        <span class="mn-carga-res-bloque-sep">+</span>
        <span class="mn-carga-res-bloque-nom"><?= htmlspecialchars($nomA($d) !== '' ? $nomA($d) : '—', ENT_QUOTES, 'UTF-8') ?></span>
        <?php if ($cedA($d) !== '') : ?><span class="mn-carga-res-doc"><?= htmlspecialchars($cedA($d), ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
      </p>
      <div class="mn-carga-res-bloque-fields">
        <label class="mn-label" for="puntos_pareja_b">Puntos (pareja)</label>
        <input class="mn-input mn-carga-res-input" type="number" name="puntos_B" id="puntos_pareja_b" value="<?= $r1b ?>" min="0" step="1" data-campo="puntos" data-pareja="B" required />
        <label class="mn-label" for="sets_pareja_b">Sets (pareja)</label>
        <input class="mn-input mn-carga-res-input" type="number" name="sets_B" id="sets_pareja_b" value="<?= $r2b ?>" min="0" step="1" data-campo="sets" required />
        <div class="mn-carga-res-extras mn-carga-res-extras--block">
          <label class="mn-label" for="ch_b">Extras del vínculo</label>
          <div class="mn-carga-res-extras">
            <input class="mn-input mn-carga-res-input mn-carga-res-input--sm" type="number" name="chancleta_B" id="ch_b" value="<?= $chb ?>" min="0" placeholder="Chanc." />
            <input class="mn-input mn-carga-res-input mn-carga-res-input--sm" type="number" name="zapato_B" id="za_b" value="<?= $zab ?>" min="0" placeholder="Zap." />
          </div>
        </div>
      </div>
    </section>
  </div>

  <p class="mn-carga-res-valid-msg mn-hint mn-hint--error" id="mn-carga-valid-parejas" hidden></p>

  <button type="submit" class="mn-btn mn-btn--success mn-carga-res-submit">Guardar resultado</button>
</form>
