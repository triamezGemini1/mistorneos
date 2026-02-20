<?php
/**
 * Exportar Clubs a Excel (Formato HTML para Excel)
 * Incluye todas las columnas de la tabla clubs
 */



require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

// Verificar autenticación
Auth::requireRole(['admin_general', 'admin_torneo', 'admin_club']);

try {
    // Consultar todos los clubs
    $stmt = DB::pdo()->query("
        SELECT 
            id,
            nombre,
            delegado,
            telefono,
            direccion,
            logo,
            estatus,
            created_at,
            updated_at
        FROM clubes
        ORDER BY nombre ASC
    ");
    $clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($clubs)) {
        die('No hay datos para exportar');
    }
    
    // Generar nombre del archivo
    $filename = 'clubs_' . date('Y-m-d_His') . '.xls';
    
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
    <title>Reporte de Clubs</title>
</head>
<body>
    <table border="1" cellpadding="5" cellspacing="0">
        <thead>
            <!-- Título Principal -->
            <tr style="background-color: #667eea; color: white; font-weight: bold;">
                <td colspan="9" style="text-align: center; font-size: 14pt; padding: 10px;">
                    REPORTE DE CLUBS
                </td>
            </tr>
            <tr style="background-color: #667eea; color: white; font-weight: bold;">
                <td colspan="9" style="text-align: center; padding: 5px;">
                    Generado: <?= date('d/m/Y H:i') ?>
                </td>
            </tr>
            <tr style="height: 10px;"><td colspan="9"></td></tr>
            
            <!-- Headers de Columnas -->
            <tr style="background-color: #f8f9fa; font-weight: bold;">
                <td style="text-align: center;">ID</td>
                <td style="text-align: center;">NOMBRE DEL CLUB</td>
                <td style="text-align: center;">DELEGADO</td>
                <td style="text-align: center;">TELÉFONO</td>
                <td style="text-align: center;">DIRECCIÓN</td>
                <td style="text-align: center;">LOGO</td>
                <td style="text-align: center;">ESTADO</td>
                <td style="text-align: center;">FECHA CREACIÓN</td>
                <td style="text-align: center;">ÚLTIMA MODIFICACIÓN</td>
            </tr>
        </thead>
        <tbody>
            <?php 
            $num = 1;
            foreach ($clubs as $club): 
                $bg_color = ($num % 2 == 0) ? '#f8f9fa' : '#ffffff';
            ?>
                <tr style="background-color: <?= $bg_color ?>;">
                    <td style="text-align: center;"><?= $club['id'] ?></td>
                    <td style="font-weight: bold;"><?= htmlspecialchars($club['nombre']) ?></td>
                    <td><?= htmlspecialchars($club['delegado'] ?? '') ?></td>
                    <td><?= htmlspecialchars($club['telefono'] ?? '') ?></td>
                    <td><?= htmlspecialchars($club['direccion'] ?? '') ?></td>
                    <td><?= htmlspecialchars($club['logo'] ?? 'Sin logo') ?></td>
                    <td style="text-align: center; font-weight: bold; color: <?= $club['estatus'] ? 'green' : 'red' ?>;">
                        <?= $club['estatus'] ? 'ACTIVO' : 'INACTIVO' ?>
                    </td>
                    <td style="text-align: center;"><?= date('d/m/Y H:i', strtotime($club['created_at'])) ?></td>
                    <td style="text-align: center;"><?= date('d/m/Y H:i', strtotime($club['updated_at'])) ?></td>
                </tr>
            <?php 
            $num++;
            endforeach; 
            ?>
            
            <!-- Total -->
            <tr style="background-color: #e7f3ff; font-weight: bold;">
                <td colspan="9" style="text-align: center; padding: 10px;">
                    TOTAL DE CLUBS: <?= count($clubs) ?>
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
                Reporte completo de clubs con todas sus columnas
            </td>
        </tr>
    </table>
</body>
</html>














