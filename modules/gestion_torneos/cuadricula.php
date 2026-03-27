<?php
/**
 * Vista: Cuadrícula de Asignaciones
 * Rejilla: 8 segmentos (IDEN|MESA) × 12 filas datos = 96 jugadores/página; grid 13 filas (cabecera + datos).
 * Llenado vertical por segmento: índice en bloque = segmento * filas_datos + fila.
 * Celdas: resources/views/tournament/partials/grid_display.php (foreach $cuad_paginas + bucles internos).
 * Estilos 10": public/assets/css/custom-13inch.css (.matrix-header 5vh, .matrix-row 6.8vh).
 */
if (!isset($base_url) || !isset($use_standalone)) {
    $script_actual = basename($_SERVER['PHP_SELF'] ?? '');
    $use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php'], true);
    $base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';
}

$letras = [1 => 'A', 2 => 'C', 3 => 'B', 4 => 'D'];

if (!isset($asignaciones) || !is_array($asignaciones)) {
    $asignaciones = [];
}
if (!isset($torneo) || !is_array($torneo)) {
    $torneo = ['id' => 0, 'nombre' => 'Torneo'];
}
$context_switcher = isset($context_switcher) && is_array($context_switcher)
    ? $context_switcher
    : ['active_tournament_id' => (int)($torneo['id'] ?? 0), 'items' => []];
$activeContextName = (string)($torneo['nombre'] ?? 'Torneo');
$activeContextViewId = (int)($torneo['id'] ?? 0);
if (!empty($context_switcher['items']) && is_array($context_switcher['items'])) {
    $activeContextId = (int)($context_switcher['active_tournament_id'] ?? ($torneo['id'] ?? 0));
    foreach ($context_switcher['items'] as $ctxItem) {
        if ((int)($ctxItem['id'] ?? 0) === $activeContextId) {
            $activeContextName = (string)($ctxItem['nombre'] ?? $activeContextName);
            $activeContextViewId = (int)($ctxItem['id'] ?? $activeContextViewId);
            break;
        }
    }
}

$totalInscritos = isset($totalInscritos)
    ? (int) $totalInscritos
    : (isset($totalAsignaciones) ? (int) $totalAsignaciones : 0);

/** 8 pares × 12 filas datos = 96 celdas jugador/página (16 columnas + cabecera = 13 filas en grid) */
$cuad_filas_datos = 12; // debe coincidir con grid_display.php y 12 filas de datos + 1 cabecera en CSS
$cuad_pares = 8;
$claseGrilla = 'grilla-pantalla';

$listaPlana = [];
if (!empty($asignaciones) && is_array($asignaciones)) {
    foreach ($asignaciones as $asignacion) {
        $mesaRaw = $asignacion['mesa'] ?? 0;
        $mesa = (int) $mesaRaw;
        $secuencia = (int) ($asignacion['secuencia'] ?? 0);
        $letra = $letras[$secuencia] ?? '';
        $esBye = ($mesa === 0 || $mesaRaw === '0' || $mesaRaw === 0);
        $mesaDisplay = $esBye ? 'BYE' : ($mesa . $letra);
        $listaPlana[] = [
            'id' => (string) ($asignacion['id_usuario'] ?? ''),
            'mesa' => $mesaDisplay,
            'bye' => $esBye,
        ];
    }
}

$cuad_cap = $cuad_filas_datos * $cuad_pares;
if (empty($listaPlana)) {
    $cuad_paginas = [[]];
} else {
    $cuad_paginas = array_chunk($listaPlana, $cuad_cap);
}

require_once __DIR__ . '/../../lib/app_helpers.php';
$href_custom_13 = AppHelpers::url('assets/css/custom-13inch.css');
$pageTitle = isset($titulo) ? (string) $titulo : ('Cuadrícula - Ronda ' . (int) ($numRonda ?? 0));
?>
<!DOCTYPE html>
<html lang="es" class="cuadricula-scroll-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($href_custom_13, ENT_QUOTES, 'UTF-8'); ?>">
    <style>
        @media print {
            .no-print { display: none !important; }
            html.cuadricula-scroll-root, html.cuadricula-scroll-root body {
                height: auto !important;
                max-height: none !important;
                overflow: visible !important;
            }
            .cuadricula-shell { height: auto !important; max-height: none !important; overflow: visible !important; }
        }
        .header-context-switch {
            display: inline-flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 999px;
            padding: 2px;
            gap: 2px;
            margin-right: 8px;
        }
        .header-context-switch .switch-item {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            min-width: 86px;
            padding: 4px 9.5px;
            border-radius: 999px;
            color: rgba(255, 255, 255, 0.92);
            font-size: 12px;
            font-weight: 600;
            line-height: 1;
            white-space: nowrap;
        }
        .header-context-switch .switch-item:hover { color: #fff; background: rgba(255,255,255,0.16); }
        .header-context-switch .switch-item.is-active { background: #005c44; color: #fff; }
        .header-context-switch.is-compact { gap: 5px; }
        .header-context-switch.is-compact .switch-item { font-size: 13px; min-width: 74px; padding: 4px 7.6px; }
        .header-context-switch .text-id-ghost { font-size: 10px; color: rgba(255,255,255,0.72); margin-left: 4px; font-weight: 500; }
        .header-context-info {
            display: inline-flex;
            align-items: center;
            font-size: 12px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.88);
            margin-right: 8px;
            white-space: nowrap;
        }
        .header-context-info .context-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
            background: #3498db;
        }
        @media (max-width: 1366px) {
            .header-context-switch .switch-item { min-width: 78px; padding: 4px 8px; font-size: 11px; }
            .header-context-info { font-size: 11px; }
        }
    </style>
</head>
<body class="page-cuadricula-10">
    <div class="cuadricula-shell">
        <div class="cuadricula-header no-print d-flex align-items-center justify-content-between flex-wrap w-100">
            <span class="cuadricula-header-torneo mr-2" style="min-width:0;">
                <?php echo htmlspecialchars(strtoupper($torneo['nombre'] ?? 'Torneo'), ENT_QUOTES, 'UTF-8'); ?>
                — RONDA <?php echo (int) ($numRonda ?? 0); ?>
                <?php if ($totalInscritos > 0): ?>
                    <span class="text-muted font-weight-normal"> · <?php echo (int) $totalInscritos; ?> inscritos</span>
                <?php endif; ?>
            </span>
            <div class="cuadricula-header-right d-flex align-items-center ml-auto" style="flex-shrink:0;">
                <span class="header-context-info">
                    <span class="context-dot" aria-hidden="true"></span>
                    Visualizando: Torneo <?php echo htmlspecialchars($activeContextName, ENT_QUOTES, 'UTF-8'); ?> [#<?php echo $activeContextViewId; ?>]
                </span>
                <?php if (!empty($context_switcher['items'])): ?>
                    <?php
                    $activeTournamentId = (int)($context_switcher['active_tournament_id'] ?? 0);
                    $sepSwitch = $use_standalone ? '?' : '&';
                    $switchCount = count($context_switcher['items']);
                    ?>
                    <div class="header-context-switch<?php echo $switchCount >= 3 ? ' is-compact' : ''; ?>" role="group" aria-label="Selector de contexto del torneo">
                        <?php foreach ($context_switcher['items'] as $switchItem): ?>
                            <?php
                            $switchId = (int)($switchItem['id'] ?? 0);
                            $switchLabel = (string)($switchItem['nombre'] ?? ('Torneo #' . $switchId));
                            $switchParentEventId = (int)($switchItem['parent_event_id'] ?? 0);
                            $isActiveSwitch = ($switchId === $activeTournamentId);
                            $switchHref = $base_url . $sepSwitch . 'action=cuadricula&torneo_id=' . $switchId . '&ronda=' . (int)($numRonda ?? 0) . '&switch_torneo_id=' . $switchId . '&return_action=cuadricula';
                            ?>
                            <a href="<?php echo htmlspecialchars($switchHref, ENT_QUOTES, 'UTF-8'); ?>"
                               class="switch-item js-context-switch<?php echo $isActiveSwitch ? ' is-active' : ''; ?>"
                               aria-pressed="<?php echo $isActiveSwitch ? 'true' : 'false'; ?>"
                               title="<?php echo htmlspecialchars('ID Sistema: ' . $switchId . ' | Evento Padre: ' . $switchParentEventId, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($switchLabel, ENT_QUOTES, 'UTF-8'); ?>
                                <span class="text-id-ghost">#<?php echo $switchId; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <button type="button" onclick="window.print()" class="btn btn-primary btn-sm">
                    <i class="fas fa-print mr-2"></i> Imprimir
                </button>
                <a href="<?php echo htmlspecialchars($base_url . ($use_standalone ? '?' : '&') . 'action=panel&torneo_id=' . (int) ($torneo['id'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary btn-sm ml-1">
                    <i class="fas fa-arrow-left mr-2"></i> Volver al panel
                </a>
            </div>
        </div>
        <div class="cuadricula-meta no-print" id="cuadriculaMeta" aria-live="polite"></div>
        <?php
        // Rejilla IDEN|MESA: parcial (foreach $cuad_paginas, segmentos, celdas matrix-cell)
        include __DIR__ . '/../../resources/views/tournament/partials/grid_display.php';
        ?>
    </div>
    <script>
(function () {
    var ROTACION_MS = 30 * 60 * 1000;
    var series = document.querySelectorAll('.cuadricula-serie');
    var meta = document.getElementById('cuadriculaMeta');
    var idx = 0;

    function formatearRestanteCuad(ms) {
        var s = Math.max(0, Math.ceil(ms / 1000));
        var m = Math.floor(s / 60);
        var r = s % 60;
        return m + ':' + (r < 10 ? '0' : '') + r;
    }

    function mostrarSerie(i) {
        for (var j = 0; j < series.length; j++) {
            series[j].classList.toggle('is-hidden-screen', j !== i);
        }
    }

    if (series.length > 1) {
        var deadline = Date.now() + ROTACION_MS;
        function tick() {
            var left = deadline - Date.now();
            if (left <= 0) {
                idx = (idx + 1) % series.length;
                mostrarSerie(idx);
                deadline = Date.now() + ROTACION_MS;
                left = ROTACION_MS;
            }
            if (meta) {
                meta.textContent = 'Página ' + (idx + 1) + ' de ' + series.length
                    + ' · siguiente en ' + formatearRestanteCuad(left) + ' (mm:ss)';
            }
        }
        tick();
        setInterval(tick, 1000);
        mostrarSerie(0);
    } else if (meta) {
        meta.textContent = '';
    }

    var grid = document.querySelector('.cuadricula-matrix-grid');
    if (!grid) return;

    function clearHover() {
        var active = grid.querySelectorAll('.matrix-cell.is-row-hover');
        for (var i = 0; i < active.length; i++) active[i].classList.remove('is-row-hover');
    }

    grid.addEventListener('mouseover', function (ev) {
        var cell = ev.target.closest('.matrix-cell[data-row]');
        if (!cell || !grid.contains(cell)) return;
        clearHover();
        var row = cell.getAttribute('data-row');
        var rowCells = grid.querySelectorAll('.matrix-cell[data-row="' + row + '"]');
        for (var i = 0; i < rowCells.length; i++) rowCells[i].classList.add('is-row-hover');
    });
    grid.addEventListener('mouseleave', clearHover);

})();
    </script>
</body>
</html>
