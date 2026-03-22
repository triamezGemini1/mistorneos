<?php
/**
 * Reporte PDF del Directorio de Clubes (solo admin_general).
 * Campos: Nombre del Club, Dirección, Delegado, Teléfono, Email.
 */
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/admin_general_auth.php';
require_once __DIR__ . '/../../lib/report_generator.php';

requireAdminGeneral();

try {
    $pdo = DB::pdo();
    $stmt = $pdo->query("
        SELECT nombre, direccion, delegado, telefono, email, estatus
        FROM directorio_clubes
        ORDER BY nombre ASC
    ");
    $clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $report = new ReportGenerator('Directorio de Clubes', 'portrait');
    $content = $report->addReportHeader('Listado de clubes del directorio');

    $total = count($clubs);
    $activos = count(array_filter($clubs, fn($c) => !empty($c['estatus'])));
    $content .= $report->generateStatsBoxes([
        ['number' => $total, 'label' => 'Total'],
        ['number' => $activos, 'label' => 'Activos'],
        ['number' => $total - $activos, 'label' => 'Inactivos'],
    ]);

    $content .= '<h2>Listado</h2>';
    if (empty($clubs)) {
        $content .= '<p style="text-align: center; color: #999; padding: 20px;">No hay registros en el directorio.</p>';
    } else {
        $headers = ['#', 'Nombre del Club', 'Dirección', 'Delegado', 'Teléfono', 'Email'];
        $rows = [];
        foreach ($clubs as $i => $c) {
            $rows[] = [
                $i + 1,
                htmlspecialchars($c['nombre'] ?? ''),
                htmlspecialchars($c['direccion'] ?? ''),
                htmlspecialchars($c['delegado'] ?? ''),
                htmlspecialchars($c['telefono'] ?? ''),
                htmlspecialchars($c['email'] ?? ''),
            ];
        }
        $content .= $report->generateTable($headers, $rows);
    }

    $report->setContent($content);
    $filename = 'directorio_clubes_' . date('Y-m-d_His') . '.pdf';
    $report->generate($filename, true);
} catch (Exception $e) {
    die('Error: ' . htmlspecialchars($e->getMessage()));
}
