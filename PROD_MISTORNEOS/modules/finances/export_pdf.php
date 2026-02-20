<?php
/**
 * Exportar Deudas a PDF
 */



require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole(['admin_general', 'admin_torneo', 'admin_club']);

try {
    $torneo_id = (int)($_GET['torneo_id'] ?? 0);
    
    // Validar acceso al torneo si está especificado
    if ($torneo_id > 0 && !Auth::canAccessTournament($torneo_id)) {
        throw new Exception('No tiene permisos para acceder a este torneo');
    }
    
    $titulo = 'TODAS LAS DEUDAS';
    $where = "";
    $params = [];
    
    if ($torneo_id > 0) {
        $where = "WHERE d.torneo_id = ?";
        $params = [$torneo_id];
        
        $stmt = DB::pdo()->prepare("SELECT nombre FROM tournaments WHERE id = ?");
        $stmt->execute([$torneo_id]);
        $torneo_nombre = $stmt->fetchColumn();
        if ($torneo_nombre) {
            $titulo = strtoupper($torneo_nombre);
        }
    }
    
    $pdo = DB::pdo();
    $stmt = $pdo->prepare("
        SELECT 
            d.*,
            c.nombre as club_nombre,
            c.delegado as club_delegado,
            c.telefono as club_telefono,
            t.nombre as torneo_nombre,
            t.fechator as torneo_fecha
        FROM deuda_clubes d
        INNER JOIN clubes c ON d.club_id = c.id
        INNER JOIN tournaments t ON d.torneo_id = t.id
        $where
        ORDER BY (d.monto_total - d.abono) DESC, c.nombre ASC
    ");
    $stmt->execute($params);
    $deudas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($deudas)) {
        die('No hay datos para exportar');
    }
    
    // Calcular totales
    $totales = [
        'inscritos' => array_sum(array_column($deudas, 'total_inscritos')),
        'deuda' => array_sum(array_column($deudas, 'monto_total')),
        'pagado' => array_sum(array_column($deudas, 'abono')),
        'pendiente' => 0
    ];
    $totales['pendiente'] = $totales['deuda'] - $totales['pagado'];
    
} catch (Exception $e) {
    die('Error: ' . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Deudas</title>
    <style>
        @page { margin: 1.5cm; size: letter landscape; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 9pt; }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            text-align: center;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .header h1 { font-size: 18pt; margin-bottom: 5px; }
        .header p { font-size: 10pt; margin: 3px 0; }
        table { width: 100%; border-collapse: collapse; font-size: 8pt; }
        th { background: #f8f9fa; border: 1px solid #dee2e6; padding: 6px 4px; text-align: center; font-weight: bold; }
        td { border: 1px solid #dee2e6; padding: 4px; }
        tbody tr:nth-child(even) { background: #f8f9fa; }
        .total-row { background: #28a745 !important; color: white; font-weight: bold; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 8pt; color: #666; padding-top: 10px; border-top: 1px solid #ccc; }
    </style>
</head>
<body>
    <div class="header">
        <h1>?? REPORTE DE DEUDAS</h1>
        <p><?= htmlspecialchars($titulo) ?></p>
        <p>Generado: <?= date('d/m/Y H:i') ?></p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th style="width: 18%;">CLUB</th>
                <th style="width: 12%;">DELEGADO</th>
                <th style="width: 10%;">TEL�FONO</th>
                <th style="width: 15%;">TORNEO</th>
                <th style="width: 8%;">FECHA</th>
                <th style="width: 7%;">INSCRITOS</th>
                <th style="width: 10%;">DEUDA</th>
                <th style="width: 10%;">PAGADO</th>
                <th style="width: 10%;">PENDIENTE</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($deudas as $d): 
                $pendiente = (float)$d['monto_total'] - (float)$d['abono'];
            ?>
                <tr>
                    <td style="font-weight: bold;"><?= htmlspecialchars($d['club_nombre']) ?></td>
                    <td><?= htmlspecialchars($d['club_delegado'] ?? '') ?></td>
                    <td><?= htmlspecialchars($d['club_telefono'] ?? '') ?></td>
                    <td><?= htmlspecialchars($d['torneo_nombre']) ?></td>
                    <td style="text-align: center;"><?= date('d/m/Y', strtotime($d['torneo_fecha'])) ?></td>
                    <td style="text-align: center;"><?= $d['total_inscritos'] ?></td>
                    <td style="text-align: right;">$<?= number_format((float)$d['monto_total'], 2) ?></td>
                    <td style="text-align: right; color: green;">$<?= number_format((float)$d['abono'], 2) ?></td>
                    <td style="text-align: right; font-weight: bold; color: <?= $pendiente > 0 ? 'red' : 'green' ?>;">
                        $<?= number_format((float)$pendiente, 2) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            
            <tr class="total-row">
                <td colspan="5" style="text-align: right; padding: 8px;">TOTALES:</td>
                <td style="text-align: center;"><?= $totales['inscritos'] ?></td>
                <td style="text-align: right;">$<?= number_format((float)$totales['deuda'], 2) ?></td>
                <td style="text-align: right;">$<?= number_format((float)$totales['pagado'], 2) ?></td>
                <td style="text-align: right;">$<?= number_format((float)$totales['pendiente'], 2) ?></td>
            </tr>
        </tbody>
    </table>
    
    <div class="footer">
        Serviclubes LED | Documento generado el <?= date('d/m/Y H:i:s') ?>
    </div>
    
    <script>
        window.onload = function() {
            setTimeout(function() { window.print(); }, 500);
        };
    </script>
</body>
</html>

