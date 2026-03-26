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
<style>
.verif-index-page { max-width: 960px; margin: 0 auto; }
.verif-index-page .page-head {
    display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem;
}
.verif-index-page .page-title {
    font-size: 1.35rem; font-weight: 600; color: #1e293b; margin: 0;
    display: flex; align-items: center; gap: 0.5rem;
}
.verif-index-page .btn-back {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.5rem 1rem; font-size: 0.875rem; border-radius: 8px;
    background: #f1f5f9; color: #475569; text-decoration: none; border: 1px solid #e2e8f0;
    transition: background 0.15s, color 0.15s;
}
.verif-index-page .btn-back:hover { background: #e2e8f0; color: #1e293b; }
.verif-index-page .intro {
    font-size: 0.9rem; color: #64748b; margin-bottom: 1.25rem; line-height: 1.5;
}
.verif-index-page .card-wrap {
    background: #fff; border-radius: 12px; border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06); overflow: hidden;
}
.verif-index-page .card-head {
    padding: 1rem 1.25rem; background: linear-gradient(135deg, #475569 0%, #334155 100%);
    color: #fff; font-weight: 600; font-size: 0.95rem;
}
.verif-index-page .empty-state {
    padding: 2rem 1.5rem; text-align: center; color: #64748b;
    background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px;
    font-size: 0.95rem; display: flex; align-items: center; justify-content: center; gap: 0.75rem; flex-wrap: wrap;
}
.verif-index-page .empty-state i { color: #22c55e; font-size: 1.5rem; }
.verif-index-page .table-verif {
    width: 100%; border-collapse: collapse; font-size: 0.9rem;
}
.verif-index-page .table-verif th {
    text-align: left; padding: 0.85rem 1.25rem; background: #f8fafc;
    color: #475569; font-weight: 600; border-bottom: 1px solid #e2e8f0;
}
.verif-index-page .table-verif td {
    padding: 0.85rem 1.25rem; border-bottom: 1px solid #f1f5f9; color: #334155;
}
.verif-index-page .table-verif tbody tr { transition: background 0.12s; }
.verif-index-page .table-verif tbody tr:hover { background: #f8fafc; }
.verif-index-page .table-verif .badge-actas {
    display: inline-block; padding: 0.25rem 0.6rem; border-radius: 999px;
    font-size: 0.8rem; font-weight: 600; background: #fef2f2; color: #dc2626;
}
.verif-index-page .btn-verificar {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.45rem 0.9rem; font-size: 0.85rem; border-radius: 8px;
    background: #3b82f6; color: #fff; text-decoration: none; font-weight: 500;
    border: none; transition: background 0.15s;
}
.verif-index-page .btn-verificar:hover { background: #2563eb; color: #fff; }
</style>

<div class="verif-index-page">
    <div class="page-head">
        <a href="<?= htmlspecialchars($base_url . $action_param . 'action=index') ?>" class="btn-back">
            <i class="fas fa-arrow-left"></i> Volver a Torneos
        </a>
    </div>
    <h2 class="page-title"><i class="fas fa-check-double"></i> Verificación de Actas QR</h2>

    <?php if (empty($torneos)): ?>
        <div class="empty-state">
            <i class="fas fa-check-circle"></i>
            <span>No hay actas pendientes de verificación en ningún torneo.</span>
        </div>
    <?php else: ?>
        <p class="intro">
            Hay <strong><?= (int)$total_actas_pendientes ?></strong> acta(s) pendientes en <strong><?= count($torneos) ?></strong> torneo(s).
            Seleccione un torneo para verificar sus actas.
        </p>
        <div class="card-wrap">
            <div class="card-head"><i class="fas fa-list me-2"></i>Torneos con actas pendientes</div>
            <div class="table-responsive">
                <table class="table-verif">
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
                                    <span class="badge-actas"><?= (int)($t['actas_pendientes'] ?? 0) ?></span>
                                </td>
                                <td>
                                    <a href="<?= htmlspecialchars($base_url . $action_param . 'action=verificar_resultados&torneo_id=' . (int)$t['id']) ?>" class="btn-verificar">
                                        <i class="fas fa-clipboard-check"></i> Verificar actas
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
