<?php
/**
 * Badges compactos: inscritos totales, jugadores confirmados, equipos activos.
 * Variables: $contadores_inscripcion (array con inscritos_total, jugadores_confirmados, equipos_activos)
 */
if (!isset($contadores_inscripcion) || !is_array($contadores_inscripcion)) {
    $contadores_inscripcion = ['inscritos_total' => 0, 'jugadores_confirmados' => 0, 'equipos_activos' => 0];
}
$bi = (int) ($contadores_inscripcion['inscritos_total'] ?? 0);
$bj = (int) ($contadores_inscripcion['jugadores_confirmados'] ?? 0);
$be = (int) ($contadores_inscripcion['equipos_activos'] ?? 0);
?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-2" role="group" aria-label="Resumen de inscripciones del torneo">
    <span class="badge rounded-pill bg-primary" title="Registros en inscritos (todos los estatus)">Inscritos <?php echo $bi; ?></span>
    <span class="badge rounded-pill bg-success" title="Inscritos confirmados (pueden jugar)">Jugadores <?php echo $bj; ?></span>
    <span class="badge rounded-pill bg-secondary" title="Equipos activos en el torneo (modalidad equipos/parejas)">Equipos <?php echo $be; ?></span>
</div>
