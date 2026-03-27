<?php
/**
 * Configuración de Evento / Vinculación de Torneos
 */
$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php'], true);
$base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';
$action_sep = $use_standalone ? '?' : '&';

$torneo = isset($torneo) && is_array($torneo) ? $torneo : ['id' => (int)($torneo_id ?? 0), 'nombre' => 'Torneo'];
$torneos_padre = isset($torneos_padre) && is_array($torneos_padre) ? $torneos_padre : [];
$torneos_disponibles = isset($torneos_disponibles) && is_array($torneos_disponibles) ? $torneos_disponibles : [];
$torneos_vinculados = isset($torneos_vinculados) && is_array($torneos_vinculados) ? $torneos_vinculados : [];
$parent_event_ref = (int)($parent_event_ref ?? ($torneo['id'] ?? 0));
?>

<link rel="stylesheet" href="assets/css/design-system.css">
<link rel="stylesheet" href="assets/css/modern-panel.css">

<div class="tw-panel ds-root">
    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="flex items-center space-x-2 text-sm text-gray-500">
            <li><a href="<?php echo $base_url . $action_sep; ?>action=panel&torneo_id=<?php echo (int)($torneo['id'] ?? 0); ?>" class="hover:text-blue-600">Panel de Torneo</a></li>
            <li><i class="fas fa-chevron-right text-xs mx-2"></i></li>
            <li class="text-gray-700 font-medium">Configuración de Evento</li>
        </ol>
    </nav>

    <div class="panel-header bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl shadow-lg p-4 mb-3 text-white">
        <div class="panel-header-inner flex justify-between items-center flex-wrap gap-3">
            <div class="panel-header-grow">
                <h2 class="titulo-torneo">Vinculación de Torneos</h2>
                <div class="meta flex flex-wrap gap-3">
                    <span><i class="fas fa-sitemap mr-1"></i> Evento Padre: #<?php echo $parent_event_ref; ?></span>
                    <span><i class="fas fa-building mr-1"></i> Organización: <?php echo (int)($torneo['club_responsable'] ?? 0); ?></span>
                </div>
            </div>
            <a href="<?php echo $base_url . $action_sep; ?>action=panel&torneo_id=<?php echo (int)($torneo['id'] ?? 0); ?>" class="tw-btn tw-btn--ghost-white">
                <i class="fas fa-arrow-left"></i> Volver al panel
            </a>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-md border border-gray-200 p-4 mb-3">
        <form method="GET" action="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>" class="d-flex flex-wrap align-items-center gap-2 mb-0">
            <?php if (!$use_standalone): ?>
                <input type="hidden" name="page" value="torneo_gestion">
            <?php endif; ?>
            <input type="hidden" name="action" value="vincular_torneos">
            <label class="mb-0 font-semibold text-gray-700" for="parent_torneo_id">Torneo principal:</label>
            <select id="parent_torneo_id" name="torneo_id" class="form-control form-control-sm" style="max-width: 420px;">
                <?php foreach ($torneos_padre as $tp): ?>
                    <option value="<?php echo (int)$tp['id']; ?>" <?php echo ((int)$tp['id'] === (int)($torneo['id'] ?? 0)) ? 'selected' : ''; ?>>
                        #<?php echo (int)$tp['id']; ?> · <?php echo htmlspecialchars((string)$tp['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-sync-alt mr-1"></i>Cambiar</button>
        </form>
    </div>

    <div class="row">
        <div class="col-lg-7 mb-3">
            <div class="bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden h-100">
                <div class="px-3 py-2" style="background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%); color: #fff;">
                    <h5 class="mb-0"><i class="fas fa-link mr-2"></i>Torneos disponibles (sin padre)</h5>
                </div>
                <div class="p-3">
                    <form method="POST" action="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="vincular_torneos_evento">
                        <input type="hidden" name="csrf_token" value="<?php echo CSRF::token(); ?>">
                        <input type="hidden" name="parent_torneo_id" value="<?php echo (int)($torneo['id'] ?? 0); ?>">
                        <div class="table-responsive" style="max-height: 58vh;">
                            <table class="table table-sm table-hover mb-2">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width: 38px;">Sel</th>
                                        <th style="width: 78px;">ID</th>
                                        <th>Nombre</th>
                                        <th style="width: 130px;">Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($torneos_disponibles)): ?>
                                        <tr><td colspan="4" class="text-center text-muted py-3">No hay torneos huérfanos disponibles en esta organización.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($torneos_disponibles as $td): ?>
                                            <tr>
                                                <td><input type="checkbox" name="torneos_ids[]" value="<?php echo (int)$td['id']; ?>"></td>
                                                <td>#<?php echo (int)$td['id']; ?></td>
                                                <td><?php echo htmlspecialchars((string)$td['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo !empty($td['fechator']) ? htmlspecialchars((string)date('d/m/Y', strtotime((string)$td['fechator'])), ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm" <?php echo empty($torneos_disponibles) ? 'disabled' : ''; ?>>
                            <i class="fas fa-check mr-1"></i>Vincular seleccionados al evento #<?php echo (int)($torneo['id'] ?? 0); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5 mb-3">
            <div class="bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden h-100">
                <div class="px-3 py-2" style="background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%); color: #fff;">
                    <h5 class="mb-0"><i class="fas fa-unlink mr-2"></i>Torneos vinculados</h5>
                </div>
                <div class="p-3">
                    <div class="table-responsive" style="max-height: 58vh;">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 78px;">ID</th>
                                    <th>Nombre</th>
                                    <th style="width: 94px;">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($torneos_vinculados)): ?>
                                    <tr><td colspan="3" class="text-center text-muted py-3">No hay torneos vinculados en este evento.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($torneos_vinculados as $tv): ?>
                                        <tr>
                                            <td>#<?php echo (int)$tv['id']; ?></td>
                                            <td><?php echo htmlspecialchars((string)$tv['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <form method="POST" action="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>" class="mb-0" onsubmit="return confirm('¿Desvincular este torneo del evento?');">
                                                    <input type="hidden" name="action" value="desvincular_torneo_evento">
                                                    <input type="hidden" name="csrf_token" value="<?php echo CSRF::token(); ?>">
                                                    <input type="hidden" name="parent_torneo_id" value="<?php echo (int)($torneo['id'] ?? 0); ?>">
                                                    <input type="hidden" name="target_torneo_id" value="<?php echo (int)$tv['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-unlink"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
