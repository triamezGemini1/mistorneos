<?php
/**
 * Selector unificado de torneos asociados (píldoras + desplegable opcional).
 * Requiere: torneoContextSwitchHref() en torneo_gestion.php
 *
 * $tcs: items[], active_id, base_url, sep, ronda_base (solo compat.; el enlace usa última ronda en destino), map_max[], mode, theme (on_dark|on_light),
 *       select_id, show_select (bool), show_info (bool), context_name, context_id,
 *       extra[] (p.ej. mesa para registrar_resultados), select_label_class, select_class,
 *       pill_row_class, aria_label (opcionales)
 */
if (empty($tcs) || !is_array($tcs)) {
    return;
}
$items = $tcs['items'] ?? [];
if (!is_array($items) || $items === []) {
    return;
}
if (!function_exists('torneoContextSwitchHref')) {
    return;
}

$activeId = (int) ($tcs['active_id'] ?? 0);
$mode = (string) ($tcs['mode'] ?? 'cuadricula');
$theme = (string) ($tcs['theme'] ?? 'on_light');
$baseUrl = (string) ($tcs['base_url'] ?? '');
$sep = (string) ($tcs['sep'] ?? '&');
$rondaBase = (int) ($tcs['ronda_base'] ?? 0);
$mapMax = isset($tcs['map_max']) && is_array($tcs['map_max']) ? $tcs['map_max'] : [];
$selectId = (string) ($tcs['select_id'] ?? ('tcs-sel-' . preg_replace('/\W/', '', $mode)));
$showSelect = array_key_exists('show_select', $tcs) ? (bool) $tcs['show_select'] : true;
$extra = isset($tcs['extra']) && is_array($tcs['extra']) ? $tcs['extra'] : [];
$contextName = (string) ($tcs['context_name'] ?? '');
$contextId = (int) ($tcs['context_id'] ?? 0);
$showInfo = array_key_exists('show_info', $tcs) ? (bool) $tcs['show_info'] : true;
$labelClass = (string) ($tcs['select_label_class'] ?? 'mb-0 mr-1 small text-muted');
$selectClass = array_key_exists('select_class', $tcs)
    ? (string) $tcs['select_class']
    : 'form-control form-control-sm';
$pillRowClass = trim((string) ($tcs['pill_row_class'] ?? ''));

$switchCount = count($items);
$compact = $switchCount >= 3;
$themeClass = $theme === 'on_dark' ? 'tcs--on-dark' : 'tcs--on-light';
$infoTheme = $theme === 'on_dark' ? 'tcs-info--on-dark' : 'tcs-info--on-light';
$ariaLabel = (string) ($tcs['aria_label'] ?? 'Torneos asociados (mismo evento)');
?>
<?php if ($showInfo): ?>
<span class="tcs-info <?php echo $infoTheme; ?> mr-2">
    <span class="tcs-info__dot" aria-hidden="true"></span>
    Visualizando: Torneo <?php echo htmlspecialchars($contextName, ENT_QUOTES, 'UTF-8'); ?> [#<?php echo $contextId; ?>]
</span>
<?php endif; ?>

<?php if ($showSelect && $switchCount > 1): ?>
<div class="torneo-asociado-select-wrap d-flex align-items-center flex-shrink-0 mr-2 mb-1 mb-md-0">
    <label for="<?php echo htmlspecialchars($selectId, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($labelClass, ENT_QUOTES, 'UTF-8'); ?>">Torneo asociado</label>
    <select id="<?php echo htmlspecialchars($selectId, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($selectClass, ENT_QUOTES, 'UTF-8'); ?>" style="min-width:10rem;max-width:14rem;" title="Cambiar al torneo hermano del mismo evento">
        <?php foreach ($items as $switchItem): ?>
            <?php
            $switchId = (int) ($switchItem['id'] ?? 0);
            $switchLabel = (string) ($switchItem['nombre'] ?? ('Torneo #' . $switchId));
            $hrefSel = torneoContextSwitchHref($baseUrl, $sep, $mode, $switchId, $rondaBase, $mapMax, $extra);
            $isSel = ($switchId === $activeId);
            ?>
            <option value="<?php echo htmlspecialchars($hrefSel, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $isSel ? ' selected' : ''; ?>>
                <?php echo htmlspecialchars($switchLabel, ENT_QUOTES, 'UTF-8'); ?> (#<?php echo $switchId; ?>)
            </option>
        <?php endforeach; ?>
    </select>
</div>
<?php endif; ?>

<div class="tcs <?php echo $themeClass; ?><?php echo $compact ? ' tcs--compact' : ''; ?><?php echo $pillRowClass !== '' ? ' ' . htmlspecialchars($pillRowClass, ENT_QUOTES, 'UTF-8') : ''; ?>" role="group" aria-label="<?php echo htmlspecialchars($ariaLabel, ENT_QUOTES, 'UTF-8'); ?>">
    <?php foreach ($items as $switchItem): ?>
        <?php
        $switchId = (int) ($switchItem['id'] ?? 0);
        $switchLabel = (string) ($switchItem['nombre'] ?? ('Torneo #' . $switchId));
        $switchParentEventId = (int) ($switchItem['parent_event_id'] ?? 0);
        $isActive = ($switchId === $activeId);
        $href = torneoContextSwitchHref($baseUrl, $sep, $mode, $switchId, $rondaBase, $mapMax, $extra);
        ?>
        <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"
           class="tcs__pill js-context-switch<?php echo $isActive ? ' is-active' : ''; ?>"
           aria-pressed="<?php echo $isActive ? 'true' : 'false'; ?>"
           title="<?php echo htmlspecialchars('ID Sistema: ' . $switchId . ($switchParentEventId ? ' | Evento Padre: ' . $switchParentEventId : ''), ENT_QUOTES, 'UTF-8'); ?>">
            <span class="tcs__pill-name"><?php echo htmlspecialchars($switchLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="tcs__pill-meta" aria-hidden="true">
                <span class="tcs__meta-item"><span class="tcs__meta-k">ID</span><span class="tcs__meta-v"><?php echo $switchId; ?></span></span>
                <?php if ($switchParentEventId > 0): ?>
                <span class="tcs__meta-sep" aria-hidden="true"></span>
                <span class="tcs__meta-item"><span class="tcs__meta-k">Padre</span><span class="tcs__meta-v"><?php echo $switchParentEventId; ?></span></span>
                <?php endif; ?>
            </span>
        </a>
    <?php endforeach; ?>
</div>
<?php if ($showSelect && $switchCount > 1): ?>
<script>
(function () {
    var sel = document.getElementById(<?php echo json_encode($selectId, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);
    if (!sel) return;
    sel.addEventListener('change', function () {
        if (this.value) window.location.href = this.value;
    });
})();
</script>
<?php endif; ?>
