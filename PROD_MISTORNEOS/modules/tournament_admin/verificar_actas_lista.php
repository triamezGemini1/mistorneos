<?php
/**
 * Lista de actas pendientes de verificación (origen QR)
 */
$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php']);
$base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';
$action_param = $use_standalone ? '?' : '&';
$url_volver = $base_url . $action_param . 'action=panel&torneo_id=' . (int)$torneo_id;
?>
<div class="mb-4">
    <a href="<?= htmlspecialchars($url_volver) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Volver al panel</a>
</div>

<h4 class="mb-3"><i class="fas fa-clipboard-check me-2"></i>Actas pendientes de verificación</h4>

<?php if (empty($actas_pendientes)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i>No hay actas pendientes de verificación.
    </div>
<?php else: ?>
    <p class="text-muted mb-3">Hay <?= count($actas_pendientes) ?> acta(s) enviada(s) por QR. Seleccione una para verificar.</p>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Ronda</th>
                    <th>Mesa</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($actas_pendientes as $a): ?>
                    <tr>
                        <td><?= (int)$a['partida'] ?></td>
                        <td><?= (int)$a['mesa'] ?></td>
                        <td>
                            <a href="<?= $base_url . $action_param ?>action=verificar_acta&torneo_id=<?= (int)$torneo_id ?>&ronda=<?= (int)$a['partida'] ?>&mesa=<?= (int)$a['mesa'] ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-eye me-1"></i>Verificar
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
