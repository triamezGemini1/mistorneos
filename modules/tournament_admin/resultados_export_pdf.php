<?php
/**
 * PDF por tipo de reporte (Letter). tipo= por_club | general | equipos_resumido | equipos_detallado | consolidado
 */
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../lib/app_helpers.php';
require_once __DIR__ . '/../../lib/ResultadosReporteData.php';
require_once __DIR__ . '/../../lib/ResultadosPorClubHelper.php';

Auth::requireRole(['admin_general', 'admin_torneo', 'admin_club']);

$torneoId = (int)($_GET['torneo_id'] ?? 0);
$tipo = preg_replace('/[^a-z_]/', '', (string)($_GET['tipo'] ?? 'consolidado'));
$allowed = ['por_club', 'general', 'posiciones', 'equipos_resumido', 'equipos_detallado', 'consolidado'];
if (!in_array($tipo, $allowed, true)) {
    $tipo = 'consolidado';
}

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

$esEquipos = (int)($torneo['modalidad'] ?? 0) === 3;
if ($tipo === 'general' && !$esEquipos) {
    $tipo = 'consolidado';
}
if (in_array($tipo, ['equipos_resumido', 'equipos_detallado'], true) && !$esEquipos) {
    $tipo = 'consolidado';
}

if (function_exists('recalcularPosiciones')) {
    recalcularPosiciones($torneoId);
}

$esc = static function ($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
};
$nombreTorneo = $esc($torneo['nombre'] ?? 'Torneo');
$fechaGen = date('d/m/Y H:i');
$fechaTor = $esc($torneo['fechator'] ?? '');

$css = '
    @page { size: letter portrait; margin: 12mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #111; }
    h1 { font-size: 12pt; margin: 0 0 4px 0; }
    h2 { font-size: 9pt; margin: 10px 0 4px 0; border-bottom: 1px solid #333; }
    .meta { font-size: 7pt; color: #444; margin-bottom: 8px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    th, td { border: 1px solid #555; padding: 2px 4px; text-align: left; }
    th { background: #e0e0e0; font-weight: bold; font-size: 7pt; }
    td.num { text-align: center; }
    .club-block { page-break-inside: avoid; margin-bottom: 10px; }
';

ob_start();

echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . $css . '</style></head><body>';

if ($tipo === 'por_club') {
    $topN = max(1, (int)($torneo['pareclub'] ?? 8));
    $dataClub = obtenerTopJugadoresPorClub($pdo, $torneoId, $topN);
    echo '<h1>Resultados por club — ' . $nombreTorneo . '</h1>';
    echo '<div class="meta">Fecha torneo: ' . $fechaTor . ' · Generado: ' . $esc($fechaGen) . ' · Top ' . $topN . ' por club</div>';
    echo '<h2>Resumen por club (orden competición)</h2><table><tr><th>Club</th><th>Jug.</th><th>∑G</th><th>∑P</th><th>Prom.ef.</th><th>∑Pts</th><th>∑GFF</th><th>Mej.pos</th></tr>';
    foreach ($dataClub['estadisticas'] as $st) {
        echo '<tr><td>' . $esc($st['club_nombre']) . '</td><td class="num">' . (int)$st['cantidad_jugadores'] . '</td><td class="num">' . (int)$st['total_ganados'] . '</td><td class="num">' . (int)$st['total_perdidos'] . '</td><td class="num">' . (int)$st['promedio_efectividad'] . '</td><td class="num">' . (int)$st['total_puntos_grupo'] . '</td><td class="num">' . (int)$st['total_gff'] . '</td><td class="num">' . (int)$st['mejor_posicion'] . '</td></tr>';
    }
    echo '</table>';
    $byClub = [];
    foreach ($dataClub['detalle'] as $row) {
        $byClub[$row['club_nombre']][] = $row;
    }
    foreach ($byClub as $clubNombre => $rows) {
        echo '<div class="club-block"><h2>' . $esc($clubNombre) . '</h2><table><tr><th>#</th><th>Jugador</th><th>Pos</th><th>G</th><th>P</th><th>Ef.</th><th>Pts</th><th>Rnk</th><th>GFF</th></tr>';
        foreach ($rows as $r) {
            echo '<tr><td class="num">' . (int)$r['ranking'] . '</td><td>' . $esc($r['nombre']) . '</td><td class="num">' . (int)$r['posicion'] . '</td><td class="num">' . (int)$r['ganados'] . '</td><td class="num">' . (int)$r['perdidos'] . '</td><td class="num">' . (int)$r['efectividad'] . '</td><td class="num">' . (int)$r['puntos'] . '</td><td class="num">' . (int)$r['ptosrnk'] . '</td><td class="num">' . (int)$r['gff'] . '</td></tr>';
        }
        echo '</table></div>';
    }
} elseif ($tipo === 'general' || $tipo === 'posiciones') {
    $data = ResultadosReporteData::cargar($pdo, $torneoId, $torneo);
    $participantes = $data['participantes'];
    $h1 = $tipo === 'posiciones' ? 'Tabla de posiciones — ' : 'Resultados general — Clasificación individual — ';
    echo '<h1>' . $h1 . $nombreTorneo . '</h1>';
    echo '<div class="meta">Fecha torneo: ' . $fechaTor . ' · Generado: ' . $esc($fechaGen) . '</div>';
    echo '<table><tr><th>Pos</th><th>Jugador</th><th>Club</th><th>Equipo</th><th>G</th><th>P</th><th>Ef.</th><th>Pts</th><th>Rnk</th><th>GFF</th><th>Sanc.</th><th>Tarj.</th></tr>';
    $n = 0;
    foreach ($participantes as $p) {
        $n++;
        $pos = (int)($p['posicion'] ?? 0) ?: $n;
        $eq = trim(($p['codigo_equipo'] ?? '') . ' ' . ($p['nombre_equipo'] ?? ''));
        echo '<tr><td class="num">' . $pos . '</td><td>' . $esc($p['nombre_completo'] ?? '') . '</td><td>' . $esc($p['club_nombre'] ?? '') . '</td><td>' . $esc($eq) . '</td><td class="num">' . $esc($p['ganados'] ?? '') . '</td><td class="num">' . $esc($p['perdidos'] ?? '') . '</td><td class="num">' . $esc($p['efectividad'] ?? '') . '</td><td class="num">' . $esc($p['puntos'] ?? '') . '</td><td class="num">' . $esc($p['ptosrnk'] ?? '') . '</td><td class="num">' . $esc($p['gff'] ?? '') . '</td><td class="num">' . $esc($p['sancion'] ?? '') . '</td><td class="num">' . $esc(ResultadosReporteData::tarjetaTexto($p['tarjeta'] ?? 0)) . '</td></tr>';
    }
    echo '</table>';
} elseif ($tipo === 'equipos_resumido') {
    $sql = "SELECT e.codigo_equipo, e.nombre_equipo, c.nombre AS club_nombre, e.posicion, e.ganados, e.perdidos, e.efectividad, e.puntos, e.sancion, e.gff
        FROM equipos e LEFT JOIN clubes c ON e.id_club = c.id
        WHERE e.id_torneo = ? AND e.estatus = 0 AND e.codigo_equipo IS NOT NULL AND e.codigo_equipo != ''
        ORDER BY e.ganados DESC, e.efectividad DESC, e.puntos DESC, e.codigo_equipo";
    $st = $pdo->prepare($sql);
    $st->execute([$torneoId]);
    $eqs = $st->fetchAll(PDO::FETCH_ASSOC);
    echo '<h1>Resultados equipos (resumido) — ' . $nombreTorneo . '</h1>';
    echo '<div class="meta">Fecha torneo: ' . $fechaTor . ' · Generado: ' . $esc($fechaGen) . '</div>';
    echo '<table><tr><th>Pos</th><th>Cód.</th><th>Equipo</th><th>Club</th><th>G</th><th>P</th><th>Ef.</th><th>Pts</th><th>GFF</th><th>Sanc.</th></tr>';
    $pos = 1;
    foreach ($eqs as $e) {
        echo '<tr><td class="num">' . $pos++ . '</td><td>' . $esc($e['codigo_equipo']) . '</td><td>' . $esc($e['nombre_equipo'] ?? '') . '</td><td>' . $esc($e['club_nombre'] ?? '') . '</td><td class="num">' . (int)$e['ganados'] . '</td><td class="num">' . (int)$e['perdidos'] . '</td><td class="num">' . (int)$e['efectividad'] . '</td><td class="num">' . (int)$e['puntos'] . '</td><td class="num">' . (int)($e['gff'] ?? 0) . '</td><td class="num">' . (int)($e['sancion'] ?? 0) . '</td></tr>';
    }
    echo '</table>';
} elseif ($tipo === 'equipos_detallado') {
    $sqlEq = "SELECT e.codigo_equipo, e.nombre_equipo, c.nombre AS club_nombre, e.ganados, e.perdidos, e.efectividad, e.puntos, e.gff, e.sancion
        FROM equipos e LEFT JOIN clubes c ON e.id_club = c.id
        WHERE e.id_torneo = ? AND e.estatus = 0 AND e.codigo_equipo IS NOT NULL AND e.codigo_equipo != ''
        ORDER BY e.ganados DESC, e.efectividad DESC, e.puntos DESC";
    $eqs = $pdo->prepare($sqlEq);
    $eqs->execute([$torneoId]);
    $lista = $eqs->fetchAll(PDO::FETCH_ASSOC);
    echo '<h1>Resultados equipos (detallado) — ' . $nombreTorneo . '</h1>';
    echo '<div class="meta">Fecha torneo: ' . $fechaTor . ' · Generado: ' . $esc($fechaGen) . '</div>';
    $gffSql = ResultadosReporteData::SQL_GFF_SUBQUERY;
    foreach ($lista as $e) {
        echo '<div class="club-block"><h2>[' . $esc($e['codigo_equipo']) . '] ' . $esc($e['nombre_equipo'] ?? '') . ' — ' . $esc($e['club_nombre'] ?? '') . '</h2>';
        echo '<div class="meta">Equipo: G ' . (int)$e['ganados'] . ' P ' . (int)$e['perdidos'] . ' Ef ' . (int)$e['efectividad'] . ' Pts ' . (int)$e['puntos'] . ' GFF ' . (int)($e['gff'] ?? 0) . '</div>';
        $sj = $pdo->prepare("SELECT u.nombre AS nombre_completo, i.posicion, i.ganados, i.perdidos, i.efectividad, i.puntos, i.ptosrnk, {$gffSql} AS gff, i.sancion, i.tarjeta
            FROM inscritos i INNER JOIN usuarios u ON i.id_usuario = u.id
            WHERE i.torneo_id = ? AND i.codigo_equipo = ? AND i.estatus != 'retirado'
            ORDER BY i.ganados DESC, i.efectividad DESC, i.puntos DESC");
        $sj->execute([$torneoId, $e['codigo_equipo']]);
        $jug = $sj->fetchAll(PDO::FETCH_ASSOC);
        echo '<table><tr><th>Jugador</th><th>Pos</th><th>G</th><th>P</th><th>Ef.</th><th>Pts</th><th>Rnk</th><th>GFF</th></tr>';
        foreach ($jug as $j) {
            echo '<tr><td>' . $esc($j['nombre_completo']) . '</td><td class="num">' . (int)$j['posicion'] . '</td><td class="num">' . (int)$j['ganados'] . '</td><td class="num">' . (int)$j['perdidos'] . '</td><td class="num">' . (int)$j['efectividad'] . '</td><td class="num">' . (int)$j['puntos'] . '</td><td class="num">' . (int)$j['ptosrnk'] . '</td><td class="num">' . (int)$j['gff'] . '</td></tr>';
        }
        echo '</table></div>';
    }
} else {
    $data = ResultadosReporteData::cargar($pdo, $torneoId, $torneo);
    $participantes = $data['participantes'];
    $clubes = $data['resumen_clubes'];
    $equipos = $data['equipos'];
    $rondas = $data['rondas'];
    echo '<h1>Reporte consolidado — ' . $nombreTorneo . '</h1><div class="meta">Fecha torneo: ' . $fechaTor . ' · Generado: ' . $esc($fechaGen) . '</div>';
    if (!empty($rondas)) {
        echo '<h2>Rondas</h2><table><tr><th>Ronda</th><th>Mesas</th><th>Reg.</th></tr>';
        foreach ($rondas as $r) {
            echo '<tr><td class="num">' . $esc($r['num_ronda']) . '</td><td class="num">' . $esc($r['mesas']) . '</td><td class="num">' . $esc($r['registros']) . '</td></tr>';
        }
        echo '</table>';
    }
    if ($esEquipos && !empty($equipos)) {
        echo '<h2>Equipos</h2><table><tr><th>Pos</th><th>Cód</th><th>Nombre</th><th>G</th><th>P</th><th>Ef</th><th>Pts</th></tr>';
        foreach ($equipos as $eq) {
            echo '<tr><td class="num">' . $esc($eq['pos_equipo'] ?? '') . '</td><td>' . $esc($eq['codigo_equipo']) . '</td><td>' . $esc($eq['nombre_equipo'] ?? '') . '</td><td class="num">' . $esc($eq['g_eq'] ?? '') . '</td><td class="num">' . $esc($eq['p_eq'] ?? '') . '</td><td class="num">' . $esc($eq['ef_eq'] ?? '') . '</td><td class="num">' . $esc($eq['pts_eq'] ?? '') . '</td></tr>';
        }
        echo '</table>';
    }
    echo '<h2>Por club</h2><table><tr><th>Club</th><th>Jug</th><th>∑G</th><th>∑P</th></tr>';
    foreach ($clubes as $c) {
        echo '<tr><td>' . $esc($c['club_nombre']) . '</td><td class="num">' . $esc($c['jugadores']) . '</td><td class="num">' . $esc($c['sum_ganados']) . '</td><td class="num">' . $esc($c['sum_perdidos']) . '</td></tr>';
    }
    echo '</table><h2>Clasificación individual</h2><table><tr><th>Pos</th><th>Jugador</th><th>Club</th><th>G</th><th>P</th><th>Pts</th></tr>';
    $n = 0;
    foreach ($participantes as $p) {
        $n++;
        $pos = (int)($p['posicion'] ?? 0) ?: $n;
        echo '<tr><td class="num">' . $pos . '</td><td>' . $esc($p['nombre_completo'] ?? '') . '</td><td>' . $esc($p['club_nombre'] ?? '') . '</td><td class="num">' . $esc($p['ganados'] ?? '') . '</td><td class="num">' . $esc($p['perdidos'] ?? '') . '</td><td class="num">' . $esc($p['puntos'] ?? '') . '</td></tr>';
    }
    echo '</table>';
}

echo '</body></html>';
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
$dompdf->setPaper('letter', 'portrait');
$dompdf->render();
$fname = 'resultados_' . $tipo . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $torneo['nombre'] ?? 't') . '_' . date('Y-m-d') . '.pdf';
$dompdf->stream($fname, ['Attachment' => true]);
exit;
