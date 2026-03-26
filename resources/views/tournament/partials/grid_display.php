<?php
/**
 * Parcial: rejilla de cuadrícula (IDEN|MESA por segmento).
 * Contexto esperado (definido en modules/gestion_torneos/cuadricula.php):
 * $cuad_paginas, $cuad_pares, $cuad_filas_datos, $claseGrilla
 */
if (!isset($cuad_paginas) || !is_array($cuad_paginas)) {
    $cuad_paginas = [[]];
}
$cuad_pares = isset($cuad_pares) ? (int) $cuad_pares : 8;
$cuad_filas_datos = isset($cuad_filas_datos) ? (int) $cuad_filas_datos : 12;
$claseGrilla = isset($claseGrilla) ? (string) $claseGrilla : 'grilla-pantalla';
?>
        <div class="matrix-scroll">
            <div id="matrixMount">
                <?php foreach ($cuad_paginas as $cuad_serie_idx => $cuad_chunk) :
                    /*
                     * Misma lógica que matriz 22×9 (PROD): repartir la lista en segmentos en vertical
                     * (cada segmento recibe hasta $cuad_filas_datos jugadores en orden), luego pintar
                     * por filas: fila r, segmento s => $segmentos[$s][$r].
                     * Equivalente a índice plano i = s * $cuad_filas_datos + r dentro del chunk.
                     */
                    $segmentos = [];
                    for ($seg_init = 0; $seg_init < $cuad_pares; $seg_init++) {
                        $segmentos[$seg_init] = [];
                    }
                    if (!empty($cuad_chunk) && is_array($cuad_chunk)) {
                        $indice = 0;
                        foreach ($cuad_chunk as $fila_jugador) {
                            $segmento = (int) floor($indice / $cuad_filas_datos);
                            if ($segmento >= $cuad_pares) {
                                break;
                            }
                            $segmentos[$segmento][] = $fila_jugador;
                            $indice++;
                        }
                    }
                    ?>
                <div class="cuadricula-serie<?php echo $cuad_serie_idx > 0 ? ' is-hidden-screen' : ''; ?>" data-serie="<?php echo (int) $cuad_serie_idx; ?>">
                    <div class="cuadricula-grid-wrap">
                        <div class="cuadricula-matrix-grid matrix-grid <?php echo htmlspecialchars($claseGrilla, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php for ($h = 0; $h < $cuad_pares; $h++) : ?>
                                <div class="matrix-cell matrix-iden matrix-head">IDEN</div>
                                <div class="matrix-cell matrix-mesa matrix-head">MESA</div>
                            <?php endfor; ?>
                            <?php for ($r = 0; $r < $cuad_filas_datos; $r++) : ?>
                                <?php for ($s = 0; $s < $cuad_pares; $s++) :
                                    $jug = isset($segmentos[$s][$r]) ? $segmentos[$s][$r] : null;
                                    $cuad_bye = $jug && !empty($jug['bye']);
                                    ?>
                                    <div class="matrix-cell matrix-iden<?php echo $cuad_bye ? ' matrix-bye' : ''; ?>" data-row="<?php echo (int) $r; ?>"><?php echo $jug ? htmlspecialchars((string) $jug['id'], ENT_QUOTES, 'UTF-8') : ''; ?></div>
                                    <div class="matrix-cell matrix-mesa<?php echo $cuad_bye ? ' matrix-bye' : ''; ?>" data-row="<?php echo (int) $r; ?>"><?php echo $jug ? htmlspecialchars((string) $jug['mesa'], ENT_QUOTES, 'UTF-8') : ''; ?></div>
                                <?php endfor; ?>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
