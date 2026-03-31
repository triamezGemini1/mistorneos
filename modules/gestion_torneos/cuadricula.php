<?php
/**
 * Vista: Cuadrícula de Asignaciones
<<<<<<< HEAD
 * Estructura técnica: 22 filas x 18 columnas (9 pares IDEN | MESA)
 * Mantiene la lógica de carga de asignaciones existente.
=======
 * Rejilla: 8 segmentos (IDEN|MESA) × 12 filas datos = 96 jugadores/página; grid 13 filas (cabecera + datos).
 * Llenado vertical por segmento: índice en bloque = segmento * filas_datos + fila.
 * Celdas: resources/views/tournament/partials/grid_display.php (foreach $cuad_paginas + bucles internos).
 * Estilos 10": public/assets/css/custom-13inch.css (.grilla-pantalla: cabecera/datos en vh compactos).
>>>>>>> feature-final-unification
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

$map_max_partida_switch = isset($map_max_partida_switch) && is_array($map_max_partida_switch)
    ? $map_max_partida_switch
    : [];

/** 8 pares × 12 filas datos = 96 celdas jugador/página (16 columnas + cabecera = 13 filas en grid) */
$cuad_filas_datos = 12; // debe coincidir con grid_display.php y 12 filas de datos + 1 cabecera en CSS
$cuad_pares = 8;
$claseGrilla = 'grilla-pantalla';
$es_modalidad_equipos_v3 = (int)($torneo['modalidad'] ?? 0) === 3;
$usarNumfvd = !$es_modalidad_equipos_v3 && (int)($torneo['club_responsable'] ?? 0) === 7;

$listaPlana = [];
if (!empty($asignaciones) && is_array($asignaciones)) {
    foreach ($asignaciones as $asignacion) {
        $mesaRaw = $asignacion['mesa'] ?? 0;
        $mesa = (int) $mesaRaw;
        $secuencia = (int) ($asignacion['secuencia'] ?? 0);
        $letra = $letras[$secuencia] ?? '';
        $esBye = ($mesa === 0 || $mesaRaw === '0' || $mesaRaw === 0);
        $mesaDisplay = $esBye ? 'BYE' : ($mesa . $letra);
        $idMostrar = $usarNumfvd
            ? (int)($asignacion['numfvd'] ?? 0)
            : (int)($asignacion['id_usuario'] ?? 0);
        if ($idMostrar <= 0) {
            $idMostrar = (int)($asignacion['id_usuario'] ?? 0);
        }
        $listaPlana[] = [
            'id' => (string) $idMostrar,
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
$href_torneo_context_switch = AppHelpers::url('assets/css/torneo-context-switch.css');
$pageTitle = isset($titulo) ? (string) $titulo : ('Cuadrícula - Ronda ' . (int) ($numRonda ?? 0));
$href_panel = $base_url . ($use_standalone ? '?' : '&') . 'action=panel&torneo_id=' . (int) ($torneo['id'] ?? 0);
$tid_export = (int) ($torneo['id'] ?? 0);
$href_export_xls = $tid_export > 0 ? AppHelpers::torneoGestionUrl('inscripciones_export_xls', $tid_export) : '';
$href_export_pdf = $tid_export > 0 ? AppHelpers::torneoGestionUrl('inscripciones_export_pdf', $tid_export) : '';
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
    <link rel="stylesheet" href="<?php echo htmlspecialchars($href_torneo_context_switch, ENT_QUOTES, 'UTF-8'); ?>">
    <style>
<<<<<<< HEAD
        :root {
            --color-bg: #f4f6f8;
            --color-surface: #ffffff;
            --color-iden-bg: #4ade80;
            --color-iden-text: #000000;
            --color-mesa-bg: #60a5fa;
            --color-mesa-text: #000000;
            --color-border: #0f172a;
            --color-separator: #fb923c;
            --color-row-hover: #e2e8f0;
            --color-bye-bg: #fef08a;
            --cell-height: 38px;
            --cell-font-size: 0.98rem;
            --grid-min-col: 75px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { font-size: 16px; }
        body { font-family: "Segoe UI", Inter, sans-serif; background: var(--color-bg); color: #0f172a; }

        @media print {
            .no-print { display: none !important; }
            body { margin: 0; padding: 4mm; background: #fff; }
            .cuadricula-shell { box-shadow: none; border: 1px solid #000; }
        }

        .no-print {
            padding: 1rem;
            background: var(--color-surface);
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
            margin-bottom: 0.85rem;
        }

        .cuadricula-shell {
            background: var(--color-surface);
            padding: 0.8rem;
            margin: 0 auto;
            width: min(98vw, 1440px);
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
        }

        .cuadricula-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.4rem 0.2rem 0.65rem;
            margin-bottom: 0.2rem;
            font-size: 0.95rem;
            color: #0f172a;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .cuadricula-header-left { display: flex; align-items: center; justify-content: center; flex: 1; }
        .cuadricula-header-right { display: flex; align-items: center; gap: 0.5rem; }
        .cuadricula-header-torneo { font-weight: 700; letter-spacing: 0.01em; text-transform: uppercase; }

        .matrix-scroll {
            overflow-x: auto;
            width: 100%;
            -webkit-overflow-scrolling: touch;
        }

        .matrix-grid {
            display: grid;
            grid-template-columns: repeat(18, minmax(var(--grid-min-col), 1fr));
            width: 100%;
            min-width: 1350px;
            border: 1px solid var(--color-border);
            border-right: 0;
            border-bottom: 0;
            user-select: none;
        }

        .matrix-cell {
            min-height: var(--cell-height);
            height: var(--cell-height);
            line-height: var(--cell-height);
            border-right: 1px solid var(--color-border);
            border-bottom: 1px solid var(--color-border);
            padding: 0 0.35rem;
            text-align: center;
            font-size: var(--cell-font-size);
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .matrix-iden { background: var(--color-iden-bg); color: var(--color-iden-text); }
        .matrix-mesa { background: var(--color-mesa-bg); color: var(--color-mesa-text); }
        .matrix-head { text-transform: uppercase; letter-spacing: 0.02em; }
        .matrix-bye { font-style: italic; background: var(--color-bye-bg) !important; color: #000000; }
        .matrix-cell:nth-child(2n) { border-right: 2px solid var(--color-separator); }
        .matrix-cell.is-row-hover { filter: brightness(0.94); box-shadow: inset 0 0 0 9999px color-mix(in srgb, var(--color-row-hover) 22%, transparent); }

        @media screen and (max-width: 1440px) {
            .cuadricula-shell { width: 100%; border-radius: 0; }
        }
        @media screen and (max-width: 980px) {
            .cuadricula-header {
                font-size: 0.88rem;
                flex-direction: column;
                align-items: center;
            }
            .cuadricula-header-right { width: 100%; justify-content: flex-start; }
=======
        @media print {
            .no-print { display: none !important; }
            html.cuadricula-scroll-root, html.cuadricula-scroll-root body {
                height: auto !important;
                max-height: none !important;
                overflow: visible !important;
            }
            .cuadricula-shell { height: auto !important; max-height: none !important; overflow: visible !important; }
        }
        /* Equipos V3: cabecera compacta, sin desbordar 1366×768 */
        body.cuadricula-equipos-v3 .cuadricula-header-torneo { font-size: 0.8rem; line-height: 1.2; max-width: min(52vw, 520px); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        body.cuadricula-equipos-v3 .cuadricula-header { flex-wrap: nowrap; gap: 4px; }
        body.cuadricula-equipos-v3 .cuadricula-header-right { flex-wrap: nowrap; min-width: 0; }
        @media (max-width: 1366px) and (max-height: 800px) {
            body.cuadricula-equipos-v3 .cuadricula-header .btn-sm { font-size: 0.7rem; padding: 0.2rem 0.45rem; }
        }
        .cuadricula-header-switcher {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px 8px;
            min-width: 0;
        }
        .cuadricula-header-switcher .torneo-asociado-select-wrap {
            margin-bottom: 0 !important;
        }
        .cuadricula-header-switcher .tcs {
            align-self: center;
        }
        .cuadricula-header-actions {
            display: inline-flex;
            align-items: center;
            flex-wrap: nowrap;
            gap: 4px;
            flex-shrink: 0;
>>>>>>> feature-final-unification
        }
        .cuadricula-header-actions .btn-sm { white-space: nowrap; padding: 0.2rem 0.45rem; font-size: 0.78rem; }
        .cuadricula-header.no-print { padding: 2px 6px !important; min-height: 0; align-items: center !important; }
        .cuadricula-meta { padding: 2px 6px !important; font-size: 0.7rem !important; line-height: 1.2 !important; min-height: 0 !important; }
    </style>
</head>
<<<<<<< HEAD
<body>
    <div class="cuadricula-shell">
        <div class="cuadricula-header">
            <div class="cuadricula-header-left">
                <span class="cuadricula-header-torneo">
                    <?php echo htmlspecialchars(strtoupper($torneo['nombre'] ?? 'Torneo')); ?> - RONDA <?php echo $numRonda ?? 0; ?>
=======
<body class="page-cuadricula-10<?php echo $es_modalidad_equipos_v3 ? ' cuadricula-equipos-v3' : ''; ?>">
    <div class="cuadricula-shell">
        <div class="cuadricula-header no-print d-flex align-items-center justify-content-between flex-nowrap w-100">
            <span class="cuadricula-header-torneo mr-1" style="min-width:0;flex:1 1 auto;font-size:0.82rem;line-height:1.2;">
                <?php echo htmlspecialchars(strtoupper($torneo['nombre'] ?? 'Torneo'), ENT_QUOTES, 'UTF-8'); ?>
                — R<?php echo (int) ($numRonda ?? 0); ?>
                <?php if ($totalInscritos > 0): ?>
                    <span class="text-muted font-weight-normal"> · <?php echo (int) $totalInscritos; ?></span>
                <?php endif; ?>
            </span>
            <div class="cuadricula-header-right d-flex align-items-center flex-nowrap ml-1 justify-content-end" style="flex:0 1 auto;min-width:0;gap:4px;">
                <span class="tcs-info tcs-info--on-dark mb-0 align-self-center d-none d-xl-inline" style="font-size:0.72rem;">
                    <span class="tcs-info__dot" aria-hidden="true"></span>
                    <?php echo htmlspecialchars($activeContextName, ENT_QUOTES, 'UTF-8'); ?> #<?php echo $activeContextViewId; ?>
>>>>>>> feature-final-unification
                </span>
                <?php if (!empty($context_switcher['items'])): ?>
                <div class="cuadricula-header-switcher no-print" style="flex-wrap:nowrap;">
                    <?php
                    $tcs = [
                        'items' => $context_switcher['items'],
                        'active_id' => (int) ($context_switcher['active_tournament_id'] ?? 0),
                        'base_url' => $base_url,
                        'sep' => $use_standalone ? '?' : '&',
                        'ronda_base' => (int) ($numRonda ?? 0),
                        'map_max' => $map_max_partida_switch,
                        'mode' => 'cuadricula',
                        'theme' => 'on_dark',
                        'select_id' => 'torneo-asociado-select-cuad',
                        'show_info' => false,
                        'pill_row_class' => '',
                    ];
                    require __DIR__ . '/../../resources/views/partials/torneo_context_switch.php';
                    ?>
                </div>
                <?php endif; ?>
                <div class="cuadricula-header-actions" title="PDF, Excel, Volver al panel e imprimir cuadrícula">
                    <?php if ($href_export_pdf !== ''): ?>
                    <a href="<?php echo htmlspecialchars($href_export_pdf, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-danger btn-sm" target="_blank" rel="noopener">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                    <?php endif; ?>
                    <?php if ($href_export_xls !== ''): ?>
                    <a href="<?php echo htmlspecialchars($href_export_xls, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-success btn-sm" target="_blank" rel="noopener">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo htmlspecialchars($href_panel, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                    <button type="button" onclick="window.print()" class="btn btn-primary btn-sm" title="Imprimir esta cuadrícula">
                        <i class="fas fa-print"></i>
                    </button>
                </div>
            </div>
        </div>
<<<<<<< HEAD
        <div class="matrix-scroll">
            <?php
            $totalFilas = 22;
            $totalSegmentos = 9;
            $jugadoresPorSegmento = $totalFilas; // Máximo 22 jugadores por segmento
            
            // Organizar asignaciones por segmento (llenado vertical: segmento por segmento)
            $segmentos = [];
            
            // Inicializar segmentos vacíos
            for ($s = 0; $s < $totalSegmentos; $s++) {
                $segmentos[$s] = [];
            }
            
            // Distribuir asignaciones por segmentos (llenado vertical)
            // Llenar el primer segmento completamente antes de pasar al siguiente
            if (!empty($asignaciones)) {
                $indice = 0;
                foreach ($asignaciones as $asignacion) {
                    $segmento = floor($indice / $jugadoresPorSegmento);
                    if ($segmento >= $totalSegmentos) break;
                    
                    $segmentos[$segmento][] = $asignacion;
                    $indice++;
                }
            }
            ?>
            <div id="matrixGrid" class="matrix-grid" aria-label="Matriz de competencia">
                <?php for ($segmento = 0; $segmento < $totalSegmentos; $segmento++): ?>
                    <div class="matrix-cell matrix-iden matrix-head">IDEN</div>
                    <div class="matrix-cell matrix-mesa matrix-head">MESA</div>
                <?php endfor; ?>

                <?php for ($fila = 0; $fila < $totalFilas; $fila++): ?>
                    <?php for ($segmento = 0; $segmento < $totalSegmentos; $segmento++): ?>
                        <?php
                        $asignacion = isset($segmentos[$segmento][$fila]) ? $segmentos[$segmento][$fila] : null;
                        $idUsuario = '';
                        $mesaDisplay = '';
                        $esBye = false;
                        if ($asignacion) {
                            $idUsuario = (string)($asignacion['id_usuario'] ?? '');
                            $mesaRaw = $asignacion['mesa'] ?? 0;
                            $mesa = (int)$mesaRaw;
                            $secuencia = (int)($asignacion['secuencia'] ?? 0);
                            $letra = $letras[$secuencia] ?? '';
                            $esBye = ($mesa === 0 || $mesaRaw === '0' || $mesaRaw === 0);
                            $mesaDisplay = $esBye ? 'BYE' : ($mesa . $letra);
                        }
                        ?>
                        <div class="matrix-cell matrix-iden<?php echo $esBye ? ' matrix-bye' : ''; ?>" data-row="<?php echo $fila; ?>"><?php echo htmlspecialchars($idUsuario); ?></div>
                        <div class="matrix-cell matrix-mesa<?php echo $esBye ? ' matrix-bye' : ''; ?>" data-row="<?php echo $fila; ?>"><?php echo htmlspecialchars($mesaDisplay); ?></div>
                    <?php endfor; ?>
                <?php endfor; ?>
            </div>
        </div>
    </div>
    <script>
        (function () {
            var grid = document.getElementById('matrixGrid');
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
=======
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
>>>>>>> feature-final-unification
    </script>
</body>
</html>
