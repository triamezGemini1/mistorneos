<?php
/**
 * Reporte de Jugadores Retirados en PDF
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/report_generator.php';

Auth::requireRole(['admin_general', 'admin_torneo', 'admin_club']);

try {
    $pdo = DB::pdo();

    $tournament_filter = !empty($_GET['torneo_id']) ? (int)$_GET['torneo_id'] : null;
    $club_ids = $_GET['club_id'] ?? $_GET['club_id[]'] ?? [];
    if (!is_array($club_ids)) {
        $club_ids = $club_ids ? [(int)$club_ids] : [];
    }
    $club_filter = null;
    if (count($club_ids) === 1) {
        $club_filter = (int)$club_ids[0];
    }
    $sexo_filter = $_GET['sexo'] ?? '';
    $search = $_GET['q'] ?? '';

    $where = ["(r.estatus = 4 OR r.estatus = 'retirado')"];
    $params = [];

    if ($tournament_filter) {
        $where[] = "r.torneo_id = ?";
        $params[] = $tournament_filter;
    }

    if ($club_filter) {
        $where[] = "r.id_club = ?";
        $params[] = $club_filter;
    } elseif (!empty($club_ids)) {
        $ph = implode(',', array_fill(0, count($club_ids), '?'));
        $where[] = "r.id_club IN ($ph)";
        $params = array_merge($params, array_map('intval', $club_ids));
    }

    if ($sexo_filter && in_array($sexo_filter, ['M', 'F'])) {
        $where[] = "(u.sexo = ? OR u.sexo = ?)";
        $params[] = $sexo_filter;
        $params[] = ($sexo_filter === 'M' ? 1 : 2);
    }

    if ($search) {
        $where[] = "(u.nombre LIKE ? OR u.cedula LIKE ? OR u.username LIKE ?)";
        $term = "%{$search}%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $where_clause = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT r.id as inscrito_id, r.torneo_id, r.id_usuario, r.id_club, r.celular, r.estatus,
               u.nombre, u.cedula, u.sexo, u.username,
               t.nombre as torneo_nombre, t.fechator as torneo_fecha,
               c.nombre as club_nombre
        FROM inscritos r
        LEFT JOIN usuarios u ON r.id_usuario = u.id
        INNER JOIN tournaments t ON r.torneo_id = t.id
        LEFT JOIN clubes c ON r.id_club = c.id
        WHERE {$where_clause}
        ORDER BY t.nombre ASC, COALESCE(c.nombre, 'Sin Club') ASC, u.nombre ASC
    ");
    $stmt->execute($params);
    $registrants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stats_where = "(r.estatus = 4 OR r.estatus = 'retirado')";
    $stats_params = [];
    if ($tournament_filter) {
        $stats_where .= " AND r.torneo_id = ?";
        $stats_params[] = $tournament_filter;
    }
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total,
               SUM(CASE WHEN u.sexo = 'M' OR u.sexo = 1 THEN 1 ELSE 0 END) as masculino,
               SUM(CASE WHEN u.sexo = 'F' OR u.sexo = 2 THEN 1 ELSE 0 END) as femenino,
               COUNT(DISTINCT r.id_club) as clubes
        FROM inscritos r
        LEFT JOIN usuarios u ON r.id_usuario = u.id
        WHERE {$stats_where}
    ");
    $stmt->execute($stats_params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $report_title = 'Reporte de Jugadores Retirados';
    if ($tournament_filter) {
        $stmt = $pdo->prepare("SELECT nombre FROM tournaments WHERE id = ?");
        $stmt->execute([$tournament_filter]);
        $t_info = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($t_info) {
            $report_title = htmlspecialchars($t_info['nombre']) . ' - Jugadores Retirados';
        }
    }

    $report = new ReportGenerator($report_title, 'landscape');
    $content = '';
    $subtitle = 'Lista de jugadores retirados del torneo';

    if ($tournament_filter) {
        $stmt = $pdo->prepare("SELECT nombre, fechator FROM tournaments WHERE id = ?");
        $stmt->execute([$tournament_filter]);
        $t_info = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($t_info) {
            $subtitle = htmlspecialchars($t_info['nombre']);
            if ($t_info['fechator']) {
                $subtitle .= ' - ' . ReportGenerator::formatDate($t_info['fechator']);
            }
            if ($club_filter) {
                $stmt2 = $pdo->prepare("SELECT nombre FROM clubes WHERE id = ?");
                $stmt2->execute([$club_filter]);
                $c_info = $stmt2->fetch(PDO::FETCH_ASSOC);
                if ($c_info) {
                    $subtitle .= ' - ' . htmlspecialchars($c_info['nombre']);
                }
            }
        }
    }
    if ($search) {
        $subtitle .= ' - Búsqueda: "' . htmlspecialchars($search) . '"';
    }

    $content .= $report->addReportHeader($subtitle);
    $content .= $report->generateStatsBoxes([
        ['number' => $stats['total'] ?? 0, 'label' => 'Total Retirados'],
        ['number' => $stats['masculino'] ?? 0, 'label' => 'Masculino'],
        ['number' => $stats['femenino'] ?? 0, 'label' => 'Femenino'],
        ['number' => $stats['clubes'] ?? 0, 'label' => 'Clubes']
    ]);
    $content .= '<h2>Listado de Jugadores Retirados</h2>';

    if (empty($registrants)) {
        $content .= '<p style="text-align: center; color: #999; padding: 20px;">No se encontraron jugadores retirados con los filtros aplicados</p>';
    } else {
        $inscritos_agrupados_temp = [];
        foreach ($registrants as $reg) {
            $tid = (int)($reg['torneo_id'] ?? 0);
            $tnom = $reg['torneo_nombre'] ?? 'Sin Torneo';
            $cid = $reg['id_club'] !== null ? (int)$reg['id_club'] : null;
            $cnom = $reg['club_nombre'] ?? 'Sin Club';
            $ckey = $cid !== null ? "t{$tid}_c{$cid}" : "t{$tid}_null_" . md5($cnom);

            if (!isset($inscritos_agrupados_temp[$tid])) {
                $inscritos_agrupados_temp[$tid] = ['id' => $tid, 'nombre' => $tnom, 'clubes' => []];
            }
            if (!isset($inscritos_agrupados_temp[$tid]['clubes'][$ckey])) {
                $inscritos_agrupados_temp[$tid]['clubes'][$ckey] = ['id' => $cid, 'nombre' => $cnom, 'inscritos' => []];
            }
            $inscritos_agrupados_temp[$tid]['clubes'][$ckey]['inscritos'][] = $reg;
        }

        uasort($inscritos_agrupados_temp, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));
        $inscritos_agrupados = [];
        foreach ($inscritos_agrupados_temp as $tid => $torneo) {
            uasort($torneo['clubes'], fn($a, $b) => strcmp($a['nombre'], $b['nombre']));
            $clubes_ord = [];
            foreach ($torneo['clubes'] as $club) {
                if (!empty($club['inscritos'])) {
                    usort($club['inscritos'], fn($a, $b) => strcmp($a['nombre'] ?? '', $b['nombre'] ?? ''));
                    $clubes_ord[] = $club;
                }
            }
            $torneo['clubes'] = $clubes_ord;
            $inscritos_agrupados[] = $torneo;
        }

        $headers = ['ID', 'Nombre', 'Username', 'Cédula', 'Sexo', 'Club'];
        foreach ($inscritos_agrupados as $datos_torneo) {
            if (count($inscritos_agrupados) > 1) {
                $content .= '<div style="margin-top: 30px;"><h2 style="background-color: #ffc107; color: #212529; padding: 15px;">' . htmlspecialchars($datos_torneo['nombre']) . '</h2></div>';
            }
            foreach ($datos_torneo['clubes'] as $datos_club) {
                $content .= '<div style="margin-top: 20px;"><h3 style="background-color: #f0f0f0; padding: 10px; border-left: 4px solid #ffc107;">' . htmlspecialchars($datos_club['nombre']) . ' <span style="background-color: #ffc107; padding: 3px 8px; border-radius: 3px;">' . count($datos_club['inscritos']) . ' retirado(s)</span></h3></div>';
                $rows = [];
                foreach ($datos_club['inscritos'] as $r) {
                    $sexo = $r['sexo'] ?? '';
                    $s = ($sexo === 'M' || $sexo == 1) ? 'M' : (($sexo === 'F' || $sexo == 2) ? 'F' : '-');
                    $rows[] = [
                        $r['inscrito_id'] ?? '',
                        htmlspecialchars($r['nombre'] ?? 'N/A'),
                        htmlspecialchars($r['username'] ?? ''),
                        htmlspecialchars($r['cedula'] ?? ''),
                        $s,
                        htmlspecialchars($r['club_nombre'] ?? 'Sin club')
                    ];
                }
                $content .= $report->generateTable($headers, $rows);
            }
        }
    }

    $report->setContent($content);
    $filename = 'reporte_retirados_' . date('Y-m-d_His') . '.pdf';
    $report->generate($filename, true);

} catch (Exception $e) {
    die("Error: " . htmlspecialchars($e->getMessage()));
}
