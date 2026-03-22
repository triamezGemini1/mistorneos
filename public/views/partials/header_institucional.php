<?php

declare(strict_types=1);

/**
 * Cintillo institucional (14"): jerarquía Entidad → Organización → Club → Torneo.
 * Defina antes $mn_institucional con InstitucionalContextService o un array compatible.
 *
 * Para PDFs / impresión, incluya este mismo partial en la plantilla HTML.
 *
 * @var array<string, mixed> $mn_institucional
 */

if (!isset($mn_institucional) || !is_array($mn_institucional)) {
    $mn_institucional = [
        'entidad' => ['id' => 0, 'nombre' => '', 'logo' => null],
        'organizacion' => ['id' => 0, 'nombre' => '', 'logo' => null],
        'club' => null,
        'torneo' => null,
    ];
}

$e = $mn_institucional['entidad'] ?? [];
$o = $mn_institucional['organizacion'] ?? [];
$c = $mn_institucional['club'] ?? null;
$t = $mn_institucional['torneo'] ?? null;

$en = trim((string) ($e['nombre'] ?? ''));
$on = trim((string) ($o['nombre'] ?? ''));
$cn = is_array($c) ? trim((string) ($c['nombre'] ?? '')) : '';
$tn = is_array($t) ? trim((string) ($t['nombre'] ?? '')) : '';

$elogo = is_array($e) && !empty($e['logo']) ? (string) $e['logo'] : '';
$ologo = is_array($o) && !empty($o['logo']) ? (string) $o['logo'] : '';

$hayAlguno = $en !== '' || $on !== '' || $cn !== '' || $tn !== '';
?>
<?php if ($hayAlguno) : ?>
  <div class="mn-institutional" role="navigation" aria-label="Jerarquía institucional">
    <div class="mn-institutional__inner">
      <div class="mn-institutional__logos">
        <?php if ($elogo !== '') : ?>
          <img class="mn-institutional__logo" src="<?= htmlspecialchars($elogo, ENT_QUOTES, 'UTF-8') ?>" alt="" width="28" height="28" loading="lazy" />
        <?php endif; ?>
        <?php if ($ologo !== '') : ?>
          <img class="mn-institutional__logo" src="<?= htmlspecialchars($ologo, ENT_QUOTES, 'UTF-8') ?>" alt="" width="28" height="28" loading="lazy" />
        <?php endif; ?>
      </div>
      <p class="mn-institutional__crumbs">
        <?php if ($en !== '') : ?>
          <span class="mn-institutional__seg"><span class="mn-institutional__lbl">Entidad</span> <?= htmlspecialchars($en, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
        <?php if ($on !== '') : ?>
          <span class="mn-institutional__sep" aria-hidden="true">›</span>
          <span class="mn-institutional__seg"><span class="mn-institutional__lbl">Organización</span> <?= htmlspecialchars($on, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
        <?php if ($cn !== '') : ?>
          <span class="mn-institutional__sep" aria-hidden="true">›</span>
          <span class="mn-institutional__seg"><span class="mn-institutional__lbl">Club</span> <?= htmlspecialchars($cn, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
        <?php if ($tn !== '') : ?>
          <span class="mn-institutional__sep" aria-hidden="true">›</span>
          <span class="mn-institutional__seg"><span class="mn-institutional__lbl">Torneo</span> <?= htmlspecialchars($tn, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
      </p>
    </div>
  </div>
<?php endif; ?>
