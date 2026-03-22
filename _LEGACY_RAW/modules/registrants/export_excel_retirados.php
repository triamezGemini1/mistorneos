<?php
/**
 * Exportar Jugadores Retirados a Excel
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole(['admin_general', 'admin_torneo', 'admin_club']);

try {
    $torneo_id = !empty($_GET['torneo_id']) ? (int)$_GET['torneo_id'] : null;
    $club_ids = $_GET['club_id'] ?? [];
    if (!is_array($club_ids)) {
        $club_ids = $club_ids ? [(int)$club_ids] : [];
    }

    $titulo_torneo = 'Todos los Torneos';
    if ($torneo_id) {
        $stmt = DB::pdo()->prepare("SELECT nombre FROM tournaments WHERE id = ?");
        $stmt->execute([$torneo_id]);
        $t = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($t) {
            $titulo_torneo = $t['nombre'] . ' - Retirados';
        }
    }

    $where = ["(r.estatus = 4 OR r.estatus = 'retirado')"];
    $params = [];

    if ($torneo_id) {
        $where[] = "r.torneo_id = ?";
        $params[] = $torneo_id;
    }
    if (!empty($club_ids)) {
        $ph = implode(',', array_fill(0, count($club_ids), '?'));
        $where[] = "r.id_club IN ($ph)";
        $params = array_merge($params, array_map('intval', $club_ids));
    }

    $sexo = $_GET['sexo'] ?? '';
    if ($sexo && in_array($sexo, ['M', 'F'])) {
        $where[] = "(u.sexo = ? OR u.sexo = ?)";
        $params[] = $sexo;
        $params[] = ($sexo === 'M' ? 1 : 2);
    }
    $q = $_GET['q'] ?? '';
    if ($q) {
        $where[] = "(u.nombre LIKE ? OR u.cedula LIKE ? OR u.username LIKE ?)";
        $term = '%' . $q . '%';
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $where_clause = implode(' AND ', $where);

    $stmt = DB::pdo()->prepare("
        SELECT r.id, u.nombre, u.username, u.cedula, u.sexo, c.nombre as club_nombre
        FROM inscritos r
        LEFT JOIN usuarios u ON r.id_usuario = u.id
        LEFT JOIN clubes c ON r.id_club = c.id
        WHERE {$where_clause}
        ORDER BY COALESCE(c.nombre, 'zzz') ASC, u.nombre ASC
    ");
    $stmt->execute($params);
    $retirados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($retirados)) {
        header('Content-Type: text/html; charset=utf-8');
        die('No hay jugadores retirados para exportar con los filtros aplicados.');
    }

    $filename = 'retirados_' . date('Y-m-d_His') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Reporte Retirados</title></head>
<body>
<table border="1" cellpadding="5" cellspacing="0">
    <thead>
        <tr style="background-color: #ffc107; color: #212529; font-weight: bold;">
            <td colspan="6" style="text-align: center; font-size: 14pt; padding: 10px;">REPORTE DE JUGADORES RETIRADOS - <?= htmlspecialchars($titulo_torneo) ?></td>
        </tr>
        <tr style="background-color: #ffc107; color: #212529; font-weight: bold;">
            <td colspan="6" style="text-align: center; padding: 5px;">Generado: <?= date('d/m/Y H:i') ?></td>
        </tr>
        <tr style="height: 10px;"><td colspan="6"></td></tr>
        <tr style="background-color: #f8f9fa; font-weight: bold;">
            <td>ID</td>
            <td>NOMBRE</td>
            <td>USERNAME</td>
            <td>CÉDULA</td>
            <td>SEXO</td>
            <td>CLUB</td>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($retirados as $r):
            $s = ($r['sexo'] === 'M' || $r['sexo'] == 1) ? 'M' : (($r['sexo'] === 'F' || $r['sexo'] == 2) ? 'F' : '-');
        ?>
        <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= htmlspecialchars($r['nombre'] ?? 'N/A') ?></td>
            <td><?= htmlspecialchars($r['username'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['cedula'] ?? '') ?></td>
            <td><?= $s ?></td>
            <td><?= htmlspecialchars($r['club_nombre'] ?? 'Sin club') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</body>
</html>
<?php
} catch (Exception $e) {
    die('Error: ' . htmlspecialchars($e->getMessage()));
}
