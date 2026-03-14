<?php
/**
 * Vista HTML solo para imprimir (sin layout general).
 */
require_once __DIR__ . '/../../lib/app_helpers.php';
require_once __DIR__ . '/../../lib/ResultadosReporteData.php';

if (function_exists('recalcularPosiciones')) {
    recalcularPosiciones($torneo_id);
}

$pdo = DB::pdo();
$data = ResultadosReporteData::cargar($pdo, $torneo_id, $torneo);
$participantes = $data['participantes'];
$clubes = $data['resumen_clubes'];
$equipos = $data['equipos'];
$rondas = $data['rondas'];
$esEquipos = (int)($torneo['modalidad'] ?? 0) === 3;
$esc = static function ($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resultados — <?= $esc($torneo['nombre'] ?? '') ?></title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 10pt; }
        }
        body { font-family: system-ui, sans-serif; margin: 1rem; color: #111; }
        h1 { font-size: 1.25rem; margin: 0 0 0.5rem 0; }
        h2 { font-size: 1rem; margin: 1.25rem 0 0.5rem 0; border-bottom: 1px solid #333; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; font-size: 9pt; }
        th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; }
        th { background: #eee; }
        td.num { text-align: center; }
        .no-print { margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" onclick="window.print()" style="padding:10px 16px;cursor:pointer;font-weight:bold;">Imprimir / Guardar PDF</button>
    </div>
    <h1>Resultados — <?= $esc($torneo['nombre'] ?? '') ?></h1>
    <p style="margin:0;font-size:10pt;color:#444;">Fecha torneo: <?= $esc($torneo['fechator'] ?? '') ?> · Impreso <?= $esc(date('d/m/Y H:i')) ?></p>

    <?php if (!empty($rondas)): ?>
    <h2>Rondas</h2>
    <table>
        <tr><th>Ronda</th><th>Mesas</th><th>Registros</th></tr>
        <?php foreach ($rondas as $r): ?>
        <tr><td class="num"><?= $esc($r['num_ronda']) ?></td><td class="num"><?= $esc($r['mesas']) ?></td><td class="num"><?= $esc($r['registros']) ?></td></tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <?php if ($esEquipos && !empty($equipos)): ?>
    <h2>Equipos</h2>
    <table>
        <tr><th>Pos</th><th>Código</th><th>Nombre</th><th>G</th><th>P</th><th>Efec.</th><th>Pts</th></tr>
        <?php foreach ($equipos as $eq): ?>
        <tr>
            <td class="num"><?= $esc($eq['pos_equipo'] ?? '') ?></td>
            <td><?= $esc($eq['codigo_equipo']) ?></td>
            <td><?= $esc($eq['nombre_equipo'] ?? '') ?></td>
            <td class="num"><?= $esc($eq['g_eq'] ?? '') ?></td>
            <td class="num"><?= $esc($eq['p_eq'] ?? '') ?></td>
            <td class="num"><?= $esc($eq['ef_eq'] ?? '') ?></td>
            <td class="num"><?= $esc($eq['pts_eq'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <h2>Por club</h2>
    <table>
        <tr><th>Club</th><th>Jug.</th><th>∑G</th><th>∑P</th><th>Prom.efic</th><th>∑Pts</th></tr>
        <?php foreach ($clubes as $c): ?>
        <tr>
            <td><?= $esc($c['club_nombre']) ?></td>
            <td class="num"><?= $esc($c['jugadores']) ?></td>
            <td class="num"><?= $esc($c['sum_ganados']) ?></td>
            <td class="num"><?= $esc($c['sum_perdidos']) ?></td>
            <td class="num"><?= $c['avg_efectividad'] !== null ? number_format((float)$c['avg_efectividad'], 2) : '—' ?></td>
            <td class="num"><?= $esc($c['sum_puntos']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h2>Clasificación individual</h2>
    <table>
        <tr>
            <th>Pos</th><th>Jugador</th><th>Club</th><?php if ($esEquipos): ?><th>Equipo</th><?php endif; ?>
            <th>G</th><th>P</th><th>Efec.</th><th>Pts</th><th>Rnk</th><th>GFF</th><th>Sanc.</th><th>Tarj.</th>
        </tr>
        <?php
        $n = 0;
        foreach ($participantes as $p):
            $n++;
            $pos = (int)($p['posicion'] ?? 0);
            if ($pos <= 0) {
                $pos = $n;
            }
        ?>
        <tr>
            <td class="num"><?= $pos ?></td>
            <td><?= $esc($p['nombre_completo'] ?? '') ?></td>
            <td><?= $esc($p['club_nombre'] ?? '') ?></td>
            <?php if ($esEquipos): ?><td><?= $esc(trim(($p['codigo_equipo'] ?? '') . ' ' . ($p['nombre_equipo'] ?? ''))) ?></td><?php endif; ?>
            <td class="num"><?= $esc($p['ganados'] ?? '') ?></td>
            <td class="num"><?= $esc($p['perdidos'] ?? '') ?></td>
            <td class="num"><?= $esc($p['efectividad'] ?? '') ?></td>
            <td class="num"><?= $esc($p['puntos'] ?? '') ?></td>
            <td class="num"><?= $esc($p['ptosrnk'] ?? '') ?></td>
            <td class="num"><?= $esc($p['gff'] ?? '') ?></td>
            <td class="num"><?= $esc($p['sancion'] ?? '') ?></td>
            <td class="num"><?= $esc(ResultadosReporteData::tarjetaTexto($p['tarjeta'] ?? 0)) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>