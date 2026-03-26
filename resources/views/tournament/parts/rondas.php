<?php
/**
 * Vista: Gestión de Rondas
 */

// Obtener base URL para el botón de retorno
$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php']);
$base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-2">
                <i class="fas fa-layer-group text-primary"></i> Gestión de Rondas
                <small class="text-muted">- <?php echo htmlspecialchars($torneo['nombre']); ?></small>
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo $base_url . ($use_standalone ? '?action=index' : '&action=index'); ?>">Gestión de Torneos</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=panel&torneo_id=<?php echo $torneo['id']; ?>"><?php echo htmlspecialchars($torneo['nombre']); ?></a></li>
                    <li class="breadcrumb-item active">Rondas</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-12">
            <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=panel&torneo_id=<?php echo $torneo['id']; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i> Volver al Panel de Control
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Rondas del Torneo</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($rondas_generadas)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle mr-2"></i>
                            Aún no se han generado rondas para este torneo.
                            <?php if ($proxima_ronda <= $torneo['rondas']): ?>
                                Puedes generar la primera ronda desde el panel de control.
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Ronda</th>
                                        <th>Mesas</th>
                                        <th>Jugadores</th>
                                        <th>BYE</th>
                                        <th>Fecha Generación</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rondas_generadas as $ronda_data): ?>
                                        <tr>
                                            <td>
                                                <strong>Ronda <?php echo $ronda_data['num_ronda']; ?></strong>
                                                <?php if ($ronda_data['num_ronda'] == $torneo['rondas']): ?>
                                                    <span class="badge badge-info">Final</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $ronda_data['total_mesas']; ?></td>
                                            <td><?php echo $ronda_data['total_jugadores']; ?></td>
                                            <td>
                                                <?php if ($ronda_data['jugadores_bye'] > 0): ?>
                                                    <span class="badge badge-warning"><?php echo $ronda_data['jugadores_bye']; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo $ronda_data['fecha_generacion'] ? date('d/m/Y H:i', strtotime($ronda_data['fecha_generacion'])) : 'N/A'; ?>
                                            </td>
                                            <td>
                                                <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=mesas&torneo_id=<?php echo $torneo['id']; ?>&ronda=<?php echo $ronda_data['num_ronda']; ?>" 
                                                   class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye mr-1"></i> Ver Mesas
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>










