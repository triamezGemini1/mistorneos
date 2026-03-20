<?php
/**
 * Vista: Cuadrícula de Asignaciones
 * Estructura técnica: 22 filas x 18 columnas (9 pares IDEN | MESA)
 * Mantiene la lógica de carga de asignaciones existente.
 */
if (!isset($base_url) || !isset($use_standalone)) {
    $script_actual = basename($_SERVER['PHP_SELF'] ?? '');
    $use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php']);
    $base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';
}

// Mapear secuencia a letra: Pareja AC (sec 1,2) → A,C | Pareja BD (sec 3,4) → B,D
$letras = [1 => 'A', 2 => 'C', 3 => 'B', 4 => 'D'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuadrícula - Ronda <?php echo $numRonda ?? 0; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
            --cell-font-size: 0.85rem;
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
        }
    </style>
</head>
<body>
    <div class="cuadricula-shell">
        <div class="cuadricula-header">
            <div class="cuadricula-header-left">
                <span class="cuadricula-header-torneo">
                    <?php echo htmlspecialchars(strtoupper($torneo['nombre'] ?? 'Torneo')); ?> - RONDA <?php echo $numRonda ?? 0; ?>
                </span>
            </div>
            <div class="cuadricula-header-right">
                <button onclick="window.print()" class="btn btn-primary btn-sm">
                    <i class="fas fa-print mr-2"></i> Imprimir
                </button>
                <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=panel&torneo_id=<?php echo $torneo['id']; ?>" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left mr-2"></i> Volver al Panel
                </a>
            </div>
        </div>
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
    </script>
</body>
</html>
