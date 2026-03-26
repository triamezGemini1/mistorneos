<?php
/**
 * Exportar Clubes a Excel - Formato Simple
 * Columnas: ID, NOMBRE
 */



require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

// Verificar autenticación
Auth::requireRole(['admin_general', 'admin_torneo', 'admin_club']);

try {
    // Consultar todos los clubes activos
    $stmt = DB::pdo()->query("
        SELECT 
            id,
            nombre
        FROM clubes
        WHERE estatus = 1
        ORDER BY nombre ASC
    ");
    $clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($clubs)) {
        die('No hay clubes para exportar');
    }
    
    // Generar nombre del archivo
    $filename = 'clubes_' . date('Y-m-d_His') . '.xls';
    
    // Headers para descarga como Excel
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
} catch (Exception $e) {
    die('Error: ' . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Listado de Clubes</title>
</head>
<body>
    <table border="1" cellpadding="5" cellspacing="0">
        <thead>
            <tr style="background-color: #667eea; color: white; font-weight: bold;">
                <td colspan="2" style="text-align: center; font-size: 16pt; padding: 15px;">
                    LISTADO DE CLUBES
                </td>
            </tr>
            <tr style="background-color: #667eea; color: white; font-weight: bold;">
                <td colspan="2" style="text-align: center; padding: 8px;">
                    Generado: <?= date('d/m/Y H:i:s') ?>
                </td>
            </tr>
            <tr style="height: 10px;"><td colspan="2"></td></tr>
            <tr style="background-color: #4a5568; color: white; font-weight: bold; font-size: 12pt;">
                <td style="text-align: center; padding: 10px; width: 100px;">ID</td>
                <td style="text-align: center; padding: 10px;">NOMBRE</td>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total_clubes = 0;
            foreach ($clubs as $idx => $club): 
                $total_clubes++;
                $bg_color = ($idx % 2 == 0) ? '#ffffff' : '#f8f9fa';
            ?>
                <tr style="background-color: <?= $bg_color ?>;">
                    <td style="text-align: center; padding: 8px;"><?= $club['id'] ?></td>
                    <td style="padding: 8px;"><?= htmlspecialchars($club['nombre']) ?></td>
                </tr>
            <?php endforeach; ?>
            
            <!-- Total -->
            <tr style="background-color: #28a745; color: white; font-weight: bold; font-size: 12pt;">
                <td colspan="2" style="text-align: center; padding: 12px;">
                    TOTAL DE CLUBES: <?= $total_clubes ?>
                </td>
            </tr>
        </tbody>
    </table>
    
    <table border="0" style="margin-top: 20px; width: 100%;">
        <tr>
            <td style="text-align: center; color: #666; font-size: 9pt;">
                Serviclubes LED - Sistema de Gestión de Torneos
            </td>
        </tr>
        <tr>
            <td style="text-align: center; color: #666; font-size: 8pt;">
                Exportación generada el <?= date('d/m/Y H:i:s') ?>
            </td>
        </tr>
    </table>
</body>
</html>


