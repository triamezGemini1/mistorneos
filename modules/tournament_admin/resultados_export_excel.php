<?php
/**
 * Exportar reporte de resultados a Excel (.xlsx) vía PhpSpreadsheet.
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
$esEquipos = (int)($torneo['modalidad'] ?? 0) === 3;

require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator('Mistorneos')
    ->setTitle('Resultados ' . ($torneo['nombre'] ?? ''))
    ->setSubject('Reporte de resultados');

// ——— Hoja 1: Torneo ———
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Torneo');
$sheet->setCellValue('A1', 'Reporte de resultados');
$sheet->setCellValue('A2', 'Torneo');
$sheet->setCellValue('B2', $torneo['nombre'] ?? '');
$sheet->setCellValue('A3', 'Fecha');
$sheet->setCellValue('B3', $torneo['fechator'] ?? '');
$sheet->setCellValue('A4', 'Rondas');
$sheet->setCellValue('B4', $torneo['rondas'] ?? '');
$sheet->setCellValue('A5', 'Modalidad');
$sheet->setCellValue('B5', $esEquipos ? 'Por equipos (3)' : (string)($torneo['modalidad'] ?? ''));
$sheet->setCellValue('A6', 'Generado');
$sheet->setCellValue('B6', date('Y-m-d H:i:s'));
$sheet->setCellValue('A7', 'Participantes (no retirados)');
$sheet->setCellValue('B7', count($data['participantes']));

// ——— Hoja 2: Rondas ———
$sr = $spreadsheet->createSheet();
$sr->setTitle('Rondas');
$sr->fromArray([['Ronda', 'Mesas', 'Registros']], null, 'A1');
$h = 2;
foreach ($data['rondas'] as $r) {
    $sr->setCellValue('A' . $h, $r['num_ronda']);
    $sr->setCellValue('B' . $h, $r['mesas']);
    $sr->setCellValue('C' . $h, $r['registros']);
    $h++;
}

// ——— Hoja 3: Clubes ———
$sc = $spreadsheet->createSheet();
$sc->setTitle('Por club');
$sc->fromArray([['Club', 'Jugadores', 'Suma G', 'Suma P', 'Prom. efic.', 'Suma pts']], null, 'A1');
$h = 2;
foreach ($data['resumen_clubes'] as $c) {
    $sc->setCellValue('A' . $h, $c['club_nombre']);
    $sc->setCellValue('B' . $h, $c['jugadores']);
    $sc->setCellValue('C' . $h, $c['sum_ganados']);
    $sc->setCellValue('D' . $h, $c['sum_perdidos']);
    $sc->setCellValue('E' . $h, $c['avg_efectividad']);
    $sc->setCellValue('F' . $h, $c['sum_puntos']);
    $h++;
}

// ——— Hoja 4: Equipos (si aplica) ———
if ($esEquipos) {
    $se = $spreadsheet->createSheet();
    $se->setTitle('Equipos');
    $se->fromArray([['Pos', 'Código', 'Nombre', 'G', 'P', 'Efec.', 'Pts']], null, 'A1');
    $h = 2;
    foreach ($data['equipos'] as $eq) {
        $se->setCellValue('A' . $h, $eq['pos_equipo'] ?? '');
        $se->setCellValue('B' . $h, $eq['codigo_equipo']);
        $se->setCellValue('C' . $h, $eq['nombre_equipo'] ?? '');
        $se->setCellValue('D' . $h, $eq['g_eq'] ?? '');
        $se->setCellValue('E' . $h, $eq['p_eq'] ?? '');
        $se->setCellValue('F' . $h, $eq['ef_eq'] ?? '');
        $se->setCellValue('G' . $h, $eq['pts_eq'] ?? '');
        $h++;
    }
}

// ——— Hoja: Clasificación individual ———
$si = $spreadsheet->createSheet();
$si->setTitle('Clasificación');
$headers = ['Pos', 'Jugador', 'Cédula/ID', 'Club'];
if ($esEquipos) {
    $headers[] = 'Cód. equipo';
    $headers[] = 'Nombre equipo';
}
$headers = array_merge($headers, ['G', 'P', 'Efec.', 'Pts', 'Pts rnk', 'GFF', 'Sanc.', 'Tarjeta', 'Bye']);
$si->fromArray([$headers], null, 'A1');
$styleHeader = $si->getStyle('A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . '1');
$styleHeader->getFont()->setBold(true);
$styleHeader->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRgb('DDDDDD');

$row = 2;
$n = 0;
foreach ($data['participantes'] as $p) {
    $n++;
    $pos = (int)($p['posicion'] ?? 0);
    if ($pos <= 0) {
        $pos = $n;
    }
    $col = 1;
    $si->setCellValueByColumnAndRow($col++, $row, $pos);
    $si->setCellValueByColumnAndRow($col++, $row, $p['nombre_completo'] ?? '');
    $si->setCellValueByColumnAndRow($col++, $row, $p['cedula'] ?? $p['username'] ?? '');
    $si->setCellValueByColumnAndRow($col++, $row, $p['club_nombre'] ?? '');
    if ($esEquipos) {
        $si->setCellValueByColumnAndRow($col++, $row, $p['codigo_equipo'] ?? '');
        $si->setCellValueByColumnAndRow($col++, $row, $p['nombre_equipo'] ?? '');
    }
    $si->setCellValueByColumnAndRow($col++, $row, $p['ganados'] ?? '');
    $si->setCellValueByColumnAndRow($col++, $row, $p['perdidos'] ?? '');
    $si->setCellValueByColumnAndRow($col++, $row, $p['efectividad'] ?? '');
    $si->setCellValueByColumnAndRow($col++, $row, $p['puntos'] ?? '');
    $si->setCellValueByColumnAndRow($col++, $row, $p['ptosrnk'] ?? '');
    $si->setCellValueByColumnAndRow($col++, $row, $p['gff'] ?? '');
    $si->setCellValueByColumnAndRow($col++, $row, $p['sancion'] ?? '');
    $si->setCellValueByColumnAndRow($col++, $row, ResultadosReporteData::tarjetaTexto($p['tarjeta'] ?? 0));
    $si->setCellValueByColumnAndRow($col++, $row, $p['partidas_bye'] ?? 0);
    $row++;
}

$spreadsheet->setActiveSheetIndex(0);

$fname = 'resultados_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $torneo['nombre'] ?? 'torneo') . '_' . date('Y-m-d_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
