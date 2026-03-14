<?php
/**
 * Exportar reporte de resultados a PDF (Dompdf).
 */
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../lib/app_helpers.php';
require_once __DIR__ . '/../../lib/ResultadosReporteData.php';

Auth::requireRole(['admin_general', 'admin_torneo', 'admin_club']);

$torneoId = (int)($_GET['torneo_id'] ?? 0);
if ($torneoId <= 0 || !Auth::canAccessTournament($torneoId)) {
    http_response_code(403);
    exit('Acceso denegado');
}

$pdo = DB::pdo();
$stmt = $pdo->prepare('SELECT * FROM tournaments WHERE id = ?');
$stmt->execute([$torneoId]);
$torneo = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$torneo) {
    http_response_code(404);
    exit('Torneo no encontrado');
}

$data = ResultadosReporteData::cargar($pdo, $torneoId, $torneo);
$participantes = $data['participantes'];
$clubes = $data['resumen_clubes'];
$equipos = $data['equipos'];
$rondas = $data['rondas'];
$esEquipos = (int)($torneo['modalidad'] ?? 0) === 3;

$esc = static function ($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
};

$fechaGen = date('d/m/Y H:i');
$nombreTorneo = $esc($torneo['nombre'] ?? 'Torneo');
$fechaTor = $esc($torneo['fechator'] ?? '');

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
        h1 { font-size: 14px; margin: 0 0 6px 0; }
        h2 { font-size: 11px; margin: 14px 0 6px 0; border-bottom: 1px solid #333; }
        .meta { font-size: 8px; color: #444; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #666; padding: 3px 4px; text-align: left; }
        th { background: #e8e8e8; font-weight: bold; }
        td.num { text-align: center; }
        .small { font-size: 7px; }
    </style>
</head>
<body>
    <h1>Reporte de resultados — <?= $nombreTorneo ?></h1>
    <div class="meta">
        Fecha torneo: <?= $fechaTor ?> · Generado: <?= $esc($fechaGen) ?> · Participantes activos: <?= count($participantes) ?>
    </div>

    <h2>Resumen del torneo</h2>
    <table class="small">
        <tr><th>Campo</th><th>Valor</th></tr>
        <tr><td>Nombre</td><td><?= $nombreTorneo ?></td></tr>
        <tr><td>Rondas previstas</td><td class="num"><?= $esc($torneo['rondas'] ?? '—') ?></td></tr>
        <tr><td>Rondas con registros</td><td class="num"><?= count($rondas) ?></td></tr>
        <tr><td>Modalidad</td><td class="num"><?= $esEquipos ? 'Por equipos' : 'Individual / otros' ?></td></tr>
    </table>

    <?php if (!empty($rondas)): ?>
    <h2>Rondas (mesas / registros en partiresul)</h2>
    <table class="small">
        <tr><th>Ronda</th><th>Mesas distintas</th><th>Filas registradas</th></tr>
        <?php foreach ($rondas as $r): ?>
        <tr>
            <td class="num"><?= $esc($r['num_ronda']) ?></td>
            <td class="num"><?= $esc($r['mesas']) ?></td>
            <td class="num"><?= $esc($r['registros']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <?php if ($esEquipos && !empty($equipos)): ?>
    <h2>Clasificación por equipos</h2>
    <table class="small">
        <tr>
            <th>Pos.</th><th>Código</th><th>Equipo</th><th>G</th><th>P</th><th>Efec.</th><th>Pts</th>
        </tr>
        <?php foreach ($equipos as $eq): ?>
        <tr>
            <td class="num"><?= $esc($eq['pos_equipo'] ?? '—') ?></td>
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

    <h2>Resumen por club</h2>
    <table class="small">
        <tr><th>Club</th><th>Jug.</th><th>∑ G</th><th>∑ P</th><th>Prom. efic.</th><th>∑ Pts</th></tr>
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

    <h2>Clasificación individual (todos los participantes)</h2>
    <table class="small">
        <tr>
            <th>Pos.</th><th>Jugador</th><th>Club</th><?php if ($esEquipos): ?><th>Equipo</th><?php endif; ?>
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
            $eqCell = $esEquipos ? '<td>' . $esc(($p['codigo_equipo'] ?? '') . ' ' . ($p['nombre_equipo'] ?? '')) . '</td>' : '';
        ?>
        <tr>
            <td class="num"><?= $pos ?></td>
            <td><?= $esc($p['nombre_completo'] ?? '') ?></td>
            <td><?= $esc($p['club_nombre'] ?? '—') ?></td>
            <?= $eqCell ?>
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
<?php
$html = ob_get_clean();

if (!class_exists(\Dompdf\Dompdf::class)) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

$options = new \Dompdf\Options();
$options->set('isRemoteEnabled', false);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new \Dompdf\Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$fname = 'resultados_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $torneo['nombre'] ?? 'torneo') . '_' . date('Y-m-d') . '.pdf';
$dompdf->stream($fname, ['Attachment' => true]);
exit;
