<?php
/**
 * Exportar Directorio de Clubes a Excel (solo admin_general).
 * Campos: Nombre del Club, Dirección, Delegado, Teléfono, Email (sin Logo).
 */
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/admin_general_auth.php';

requireAdminGeneral();

try {
    $stmt = DB::pdo()->query("
        SELECT nombre, direccion, delegado, telefono, email
        FROM directorio_clubes
        ORDER BY nombre ASC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'directorio_clubes_' . date('Y-m-d_His') . '.xls';
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
    <title>Directorio de Clubes</title>
</head>
<body>
    <table border="1" cellpadding="5" cellspacing="0">
        <thead>
            <tr style="background-color: #198754; color: white; font-weight: bold;">
                <td colspan="5" style="text-align: center; font-size: 14pt; padding: 10px;">DIRECTORIO DE CLUBES</td>
            </tr>
            <tr style="background-color: #198754; color: white; font-weight: bold;">
                <td colspan="5" style="text-align: center; padding: 5px;">Generado: <?= date('d/m/Y H:i') ?></td>
            </tr>
            <tr style="height: 10px;"><td colspan="5"></td></tr>
            <tr style="background-color: #f8f9fa; font-weight: bold;">
                <td>Nombre del Club</td>
                <td>Dirección</td>
                <td>Delegado</td>
                <td>Teléfono</td>
                <td>Email</td>
            </tr>
        </thead>
        <tbody>
            <?php
            $num = 1;
            foreach ($rows as $r):
                $bg = ($num % 2 == 0) ? '#f8f9fa' : '#ffffff';
            ?>
                <tr style="background-color: <?= $bg ?>;">
                    <td><?= htmlspecialchars($r['nombre'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['direccion'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['delegado'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['telefono'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['email'] ?? '') ?></td>
                </tr>
            <?php $num++; endforeach; ?>
            <tr style="background-color: #e7f3ff; font-weight: bold;">
                <td colspan="5" style="text-align: center; padding: 10px;">Total: <?= count($rows) ?> registros</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
