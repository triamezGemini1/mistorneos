<?php
/**
 * Lista de torneos con actas pendientes de verificación (origen QR)
 * Permite elegir un torneo para ir a verificar_resultados
 */
$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php']);
$base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';
$action_param = $use_standalone ? '?' : '&';

$torneos = $torneos ?? [];
$total_actas_pendientes = (int)($total_actas_pendientes ?? 0);
?>
<div class="mb-4">
    <a href="<?= htmlspecialchars($base_url . $action_param . 'action=index') ?>" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i>Volver a Torneos
    </a>
</div>

<h4 class="mb-3"><i class="fas fa-check-double me-2"></i>Verificación de Actas QR</h4>

<?php if (empty($torneos)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i>No hay actas pendientes de verificación en ningún torneo.
    </div>
<?php else: ?>
    <p class="text-muted mb-3">
        Hay <strong><?= $total_actas_pendientes ?></strong> acta(s) pendientes en <strong><?= count($torneos) ?></strong> torneo(s). 
        Seleccione un torneo para verificar sus actas.
    </p>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Torneo</th>
                    <th>Fecha</th>
                    <th>Organización</th>
                    <th class="text-center">Actas pendientes</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($torneos as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars($t['nombre'] ?? '') ?></td>
                        <td><?= htmlspecialchars($t['fechator'] ?? '') ?></td>
                        <td><?= htmlspecialchars($t['organizacion_nombre'] ?? 'N/A') ?></td>
                        <td class="text-center">
                            <span class="badge bg-danger rounded-pill"><?= (int)($t['actas_pendientes'] ?? 0) ?></span>
                        </td>
                        <td>
                            <a href="<?= htmlspecialchars($base_url . $action_param . 'action=verificar_resultados&torneo_id=' . (int)$t['id']) ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-clipboard-check me-1"></i>Verificar actas
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
