<?php
/**
 * Eliminar Última Ronda
 */

// Verificar que la tabla partiresul existe
if (!$tabla_partiresul_existe) {
    echo '<div class="alert alert-danger">';
    echo '<h6 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Tabla partiresul no encontrada</h6>';
    echo '<p class="mb-2">La tabla <code>partiresul</code> no existe. Para eliminar rondas, debe crear esta tabla primero.</p>';
    echo '<p class="mb-0">Ejecute: <code>php scripts/migrate_partiresul_table.php</code></p>';
    echo '</div>';
    return;
}

// Procesar eliminación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_ronda'])) {
    require_once __DIR__ . '/../../config/csrf.php';
    CSRF::validate();
    
    $numero_ronda = (int)($_POST['numero_ronda'] ?? 0);
    
    if ($numero_ronda <= 0) {
        $error_message = 'Debe especificar el número de ronda a eliminar';
    } else {
        try {
            // Verificar que la ronda existe
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total
                FROM partiresul
                WHERE id_torneo = ? AND partida = ?
            ");
            $stmt->execute([$torneo_id, $numero_ronda]);
            $total_partidas = (int)$stmt->fetchColumn();
            
            if ($total_partidas == 0) {
                $error_message = "La ronda #{$numero_ronda} no existe";
            } else {
                // Verificar si hay partidas registradas
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as registradas
                    FROM partiresul
                    WHERE id_torneo = ? AND partida = ? AND registrado = 1
                ");
                $stmt->execute([$torneo_id, $numero_ronda]);
                $partidas_registradas = (int)$stmt->fetchColumn();
                
                if ($partidas_registradas > 0) {
                    $error_message = "No se puede eliminar la ronda #{$numero_ronda} porque tiene {$partidas_registradas} partidas ya registradas";
                } else {
                    // Eliminar la ronda
                    $stmt = $pdo->prepare("
                        DELETE FROM partiresul
                        WHERE id_torneo = ? AND partida = ?
                    ");
                    $stmt->execute([$torneo_id, $numero_ronda]);
                    
                    header('Location: index.php?page=tournament_admin&torneo_id=' . $torneo_id . '&action=eliminar_ronda&success=' . urlencode("Ronda #{$numero_ronda} eliminada exitosamente"));
                    exit;
                }
            }
        } catch (Exception $e) {
            $error_message = 'Error al eliminar ronda: ' . $e->getMessage();
        }
    }
}

// Obtener lista de rondas
$stmt = $pdo->prepare("
    SELECT 
        partida,
        COUNT(*) as total_partidas,
        COUNT(CASE WHEN registrado = 1 THEN 1 END) as partidas_registradas,
        COUNT(CASE WHEN registrado = 0 THEN 1 END) as partidas_pendientes,
        MAX(mesa) as total_mesas
    FROM partiresul
    WHERE id_torneo = ?
    GROUP BY partida
    ORDER BY partida DESC
");
$stmt->execute([$torneo_id]);
$rondas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$ultima_ronda = !empty($rondas) ? (int)$rondas[0]['partida'] : 0;
?>

<div class="card">
    <div class="card-header bg-danger text-white">
        <h5 class="mb-0">
            <i class="fas fa-trash-alt me-2"></i>Eliminar Última Ronda
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($rondas)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                No hay rondas generadas para este torneo.
            </div>
        <?php else: ?>
            <div class="table-responsive mb-4">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Ronda</th>
                            <th>Total Partidas</th>
                            <th>Registradas</th>
                            <th>Pendientes</th>
                            <th>Mesas</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rondas as $ronda): ?>
                            <tr class="<?= $ronda['partida'] == $ultima_ronda ? 'table-warning' : '' ?>">
                                <td><strong>Ronda #<?= $ronda['partida'] ?></strong></td>
                                <td><?= $ronda['total_partidas'] ?></td>
                                <td>
                                    <span class="badge bg-success"><?= $ronda['partidas_registradas'] ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-warning"><?= $ronda['partidas_pendientes'] ?></span>
                                </td>
                                <td><?= $ronda['total_mesas'] ?></td>
                                <td>
                                    <?php if ($ronda['partida'] == $ultima_ronda): ?>
                                        <span class="badge bg-danger">Última Ronda</span>
                                    <?php elseif ($ronda['partidas_registradas'] == $ronda['total_partidas']): ?>
                                        <span class="badge bg-success">Completa</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">En Proceso</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <form method="POST" action="">
                <?= CSRF::input(); ?>
                <input type="hidden" name="eliminar_ronda" value="1">
                
                <div class="alert alert-danger">
                    <h6 class="alert-heading">
                        <i class="fas fa-exclamation-triangle me-2"></i>Advertencia
                    </h6>
                    <p class="mb-2">
                        <strong>Se eliminará la última ronda generada (Ronda #<?= $ultima_ronda ?>).</strong>
                    </p>
                    <p class="mb-0">
                        Solo se pueden eliminar rondas que NO tengan partidas registradas.
                        Esta acción no se puede deshacer.
                    </p>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Confirmar Número de Ronda a Eliminar</label>
                    <input type="number" name="numero_ronda" class="form-control" 
                           value="<?= $ultima_ronda ?>" min="1" required>
                    <small class="text-muted">Ingrese el número de ronda que desea eliminar</small>
                </div>
                
                <div class="d-flex justify-content-end gap-2">
                    <a href="admin_torneo.php?action=panel&torneo_id=<?= $torneo_id ?>" 
                       class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </a>
                    <button type="submit" class="btn btn-danger" 
                            onclick="return confirm('¿Está seguro de eliminar la Ronda #<?= $ultima_ronda ?>? Esta acción no se puede deshacer.');">
                        <i class="fas fa-trash-alt me-2"></i>Eliminar Ronda
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

