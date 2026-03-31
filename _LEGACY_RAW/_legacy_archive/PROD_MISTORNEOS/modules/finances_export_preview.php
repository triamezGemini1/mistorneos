<?php
// Minimal export preview used by the background worker to render the HTML for PDFs
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

$torneo_id = isset($_GET['torneo_id']) ? (int)$_GET['torneo_id'] : 0;
$pdo = DB::pdo();
$sql = "SELECT d.*, c.nombre AS club_name FROM deuda_clubes d LEFT JOIN clubes c ON d.club_id = c.id";
$params = [];
if ($torneo_id) {
    $sql .= " WHERE d.torneo_id = ?";
    $params[] = $torneo_id;
}
$sql .= " ORDER BY d.monto_total - d.abono DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$debts = $stmt->fetchAll();

$body = '<h2>Export - Deudas por Club</h2>';
$body .= '<table><thead><tr><th>Club</th><th>Total</th><th>Monto Inscritos</th><th>Abono</th><th>Pendiente</th></tr></thead><tbody>';
foreach ($debts as $d) {
    $body .= '<tr>';
    $body .= '<td>' . htmlspecialchars($d['club_name'] ?? 'Sin club') . '</td>';
    $body .= '<td>' . (int)$d['total_inscritos'] . '</td>';
    $body .= '<td>' . number_format((float)$d['monto_inscritos'],2,',','.') . '</td>';
    $body .= '<td>' . number_format((float)$d['abono'],2,',','.') . '</td>';
    $body .= '<td>' . number_format(((float)$d['monto_total'] - (float)$d['abono']),2,',','.') . '</td>';
    $body .= '</tr>';
}
$body .= '</tbody></table>';

// use pdf_template if available
if (function_exists('pdf_template')) {
    echo pdf_template('Export Deudas', $body);
} else {
    echo '<html><head><meta charset="utf-8"></head><body>' . $body . '</body></html>';
}
