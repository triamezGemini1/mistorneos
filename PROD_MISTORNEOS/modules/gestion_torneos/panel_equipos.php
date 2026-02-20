<?php
/**
 * Vista: Panel de Control de Torneo por Equipos
 * Replicado del panel individual con adaptaciones para equipos
 */
$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php']);
$base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';
$action_param = $use_standalone ? '?' : '&';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-2">
                <i class="fas fa-cog text-primary"></i> Panel de Control
                <small class="text-muted">- <?php echo htmlspecialchars($torneo['nombre']); ?></small>
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo htmlspecialchars($base_url . ($use_standalone ? '?' : '&') . 'action=index'); ?>">Gestión de Torneos</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($torneo['nombre']); ?></li>
                </ol>
            </nav>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    <?php endif; ?>

    <?php 
    $isLocked = (int)($torneo['locked'] ?? 0) === 1;
    $script_actual = basename($_SERVER['PHP_SELF'] ?? '');
    $use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php']);
    $base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';
    ?>
    
    <?php if ($isLocked): ?>
        <div class="alert alert-secondary">
            <i class="fas fa-lock mr-2"></i>
            <strong>Torneo cerrado:</strong> solo se permite consultar e imprimir. Las acciones de modificación están deshabilitadas.
        </div>
    <?php endif; ?>


    <style>
        .card {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        .card-body {
            padding: 0.75rem !important;
        }
        .btn-sm {
            font-size: 0.8125rem;
            font-weight: 500;
            letter-spacing: 0.01em;
            padding: 0.5rem 0.75rem;
            line-height: 1.4;
        }
        .btn-purple {
            background-color: #6f42c1;
            border-color: #6f42c1;
            color: white;
        }
        .btn-purple:hover {
            background-color: #5a32a3;
            border-color: #5a32a3;
            color: white;
        }
    </style>
    
    <!-- 6 Gadgets Compactos -->
    <div class="row g-3 mb-4">
        <!-- Gadget 1: Gestionar Inscripciones (Equipos) -->
        <div class="col-12 col-md-6 col-lg-6">
            <div class="card">
                <div class="card-body p-2">
                    <div class="bg-light rounded p-2 mb-2 text-center">
                        <div class="h5 mb-0 text-primary fw-bold"><?php echo $total_equipos ?? 0; ?></div>
                        <small class="text-muted">Equipos inscritos</small>
                    </div>
                    <?php if ($isLocked): ?>
                        <button type="button" class="btn btn-sm btn-secondary w-100" disabled>
                            <i class="fas fa-lock mr-1"></i> Gestionar Inscripciones (Cerrado)
                        </button>
                    <?php else: ?>
                        <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=gestionar_inscripciones_equipos&torneo_id=<?php echo $torneo['id']; ?>" 
                           class="btn btn-sm btn-primary w-100">
                            <i class="fas fa-clipboard-list mr-1"></i> Gestionar Inscripciones
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Gadget 2: Inscripción en Sitio (Equipos) -->
        <div class="col-12 col-md-6 col-lg-6">
            <div class="card">
                <div class="card-body p-2">
                    <?php if ($isLocked): ?>
                        <button type="button" class="btn btn-sm btn-secondary w-100" disabled>
                            <i class="fas fa-lock mr-1"></i> Inscripción en Sitio (Cerrado)
                        </button>
                    <?php else: ?>
                        <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=inscribir_equipo_sitio&torneo_id=<?php echo $torneo['id']; ?>" 
                           class="btn btn-sm btn-warning w-100">
                            <i class="fas fa-user-plus mr-1"></i> Inscribir en Sitio
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Gadget 3: Generar Ronda -->
        <div class="col-12 col-md-6 col-lg-6">
            <div class="card">
                <div class="card-body p-2">
                    <?php if (($proxima_ronda ?? 1) <= ($torneo['rondas'] ?? 0)): ?>
                        <div class="bg-light rounded p-2 mb-2 text-center">
                            <small class="text-muted d-block">Próxima:</small>
                            <div class="h4 mb-0 text-primary fw-bold"><?php echo $proxima_ronda ?? 1; ?></div>
                            <small class="text-muted">de <?php echo $torneo['rondas'] ?? 0; ?></small>
                        </div>
                        <?php if (!$puede_generar_ronda && ($ultima_ronda ?? 0) > 0): ?>
                            <div class="alert alert-warning py-2 px-2 mb-2" style="font-size: 0.75rem;">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                <?php echo $mesas_incompletas ?? 0; ?> mesa(s) pendiente(s)
                            </div>
                        <?php endif; ?>
                        <form method="POST" action="<?php echo $base_url; ?>">
                            <input type="hidden" name="action" value="generar_ronda">
                            <input type="hidden" name="csrf_token" value="<?php echo CSRF::token(); ?>">
                            <input type="hidden" name="torneo_id" value="<?php echo $torneo['id']; ?>">
                            <input type="hidden" name="num_ronda" value="<?php echo $proxima_ronda ?? 1; ?>">
                            <button type="submit" 
                                    <?php echo (!$puede_generar_ronda || $isLocked) ? 'disabled' : ''; ?>
                                    class="btn btn-sm w-100 <?php echo $puede_generar_ronda ? 'btn-success' : 'btn-secondary'; ?>">
                                <i class="fas fa-<?php echo $puede_generar_ronda ? 'play' : 'lock'; ?> mr-1"></i>
                                Generar Ronda <?php echo $proxima_ronda ?? 1; ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-success text-center py-2" style="font-size: 0.85rem;">
                            <i class="fas fa-check-circle mr-1"></i>
                            <div class="fw-bold">¡Torneo Completado!</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Gadget 4: Ver Mesas Ronda Actual -->
        <div class="col-12 col-md-6 col-lg-6">
            <div class="card">
                <div class="card-body p-2">
                    <?php if (($ultima_ronda ?? 0) > 0): ?>
                        <div class="bg-light rounded p-2 mb-2 text-center">
                            <small class="text-muted d-block">Ronda <?php echo $ultima_ronda; ?></small>
                            <?php if (isset($estadisticas['mesas_ronda'])): ?>
                                <div class="h4 mb-0 text-success fw-bold"><?php echo $estadisticas['mesas_ronda']; ?></div>
                                <small class="text-muted">mesas</small>
                            <?php endif; ?>
                        </div>
                        <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=mesas&torneo_id=<?php echo $torneo['id']; ?>&ronda=<?php echo $ultima_ronda; ?>" 
                           class="btn btn-sm btn-info w-100">
                            <i class="fas fa-eye mr-1"></i> Ver Mesas
                        </a>
                    <?php else: ?>
                        <div class="alert alert-info text-center py-2" style="font-size: 0.85rem;">
                            <i class="fas fa-info-circle mr-1"></i>
                            <div>Sin rondas</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Gadget 5: Agregar Mesa -->
        <div class="col-12 col-md-6 col-lg-6">
            <div class="card">
                <div class="card-body p-2">
                    <?php if (($ultima_ronda ?? 0) > 0): ?>
                        <div class="bg-light rounded p-2 mb-2 text-center">
                            <small class="text-muted">Ronda <?php echo $ultima_ronda; ?></small>
                        </div>
                        <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=agregar_mesa&torneo_id=<?php echo $torneo['id']; ?>&ronda=<?php echo $ultima_ronda; ?>" 
                           class="btn btn-sm btn-info w-100 <?php echo $isLocked ? 'disabled' : ''; ?>"
                           <?php echo $isLocked ? 'aria-disabled="true" onclick="return false;" style="pointer-events:none; opacity:0.6;"' : ''; ?>>
                            <i class="fas fa-plus-circle mr-1"></i> Agregar Mesa
                        </a>
                    <?php else: ?>
                        <div class="alert alert-info text-center py-2" style="font-size: 0.85rem;">
                            <i class="fas fa-info-circle mr-1"></i>
                            <div>Sin rondas</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Gadget 6: Cuadrícula / Hojas / Reportes -->
        <div class="col-12 col-md-6 col-lg-6">
            <div class="card">
                <div class="card-body p-2">
                    <?php if (($ultima_ronda ?? 0) > 0): ?>
                        <div class="d-grid gap-1">
                            <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=cuadricula&torneo_id=<?php echo $torneo['id']; ?>&ronda=<?php echo $ultima_ronda; ?>" 
                               class="btn btn-sm btn-purple" style="font-size: 0.75rem;">
                                <i class="fas fa-th mr-1"></i> Cuadrícula
                            </a>
                            <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=hojas_anotacion&torneo_id=<?php echo $torneo['id']; ?>&ronda=<?php echo $ultima_ronda; ?>" 
                               class="btn btn-sm btn-primary" style="font-size: 0.75rem;">
                                <i class="fas fa-print mr-1"></i> Hojas Anotación
                            </a>
                            <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=posiciones&torneo_id=<?php echo $torneo['id']; ?>" 
                               class="btn btn-sm btn-success" style="font-size: 0.75rem;">
                                <i class="fas fa-trophy mr-1"></i> Posiciones
                            </a>
                            <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=podios_equipos&torneo_id=<?php echo $torneo['id']; ?>" 
                               class="btn btn-sm btn-warning" style="font-size: 0.75rem;">
                                <i class="fas fa-medal mr-1"></i> Podios
                            </a>
                            <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=resultados_por_club&torneo_id=<?php echo $torneo['id']; ?>" 
                               class="btn btn-sm btn-info" style="font-size: 0.75rem;">
                                <i class="fas fa-building mr-1"></i> Resultados Clubes
                            </a>
                            <?php 
                            $puedeCerrar = !$isLocked && ($ultima_ronda ?? 0) > 0 && ($mesas_incompletas ?? 1) == 0;
                            ?>
                            <form method="POST" action="<?php echo $base_url; ?>" class="d-inline"
                                  onsubmit="event.preventDefault(); confirmarCierreTorneoBasico(event);">
                                <input type="hidden" name="action" value="cerrar_torneo">
                                <input type="hidden" name="csrf_token" value="<?php echo CSRF::token(); ?>">
                                <input type="hidden" name="torneo_id" value="<?php echo $torneo['id']; ?>">
                                <button type="submit" class="btn btn-sm <?php echo $isLocked ? 'btn-secondary' : 'btn-dark'; ?>" 
                                        style="font-size: 0.75rem;" <?php echo $puedeCerrar ? '' : 'disabled'; ?>>
                                    <i class="fas fa-lock mr-1"></i> <?php echo $isLocked ? 'Torneo Cerrado' : 'Cerrar Torneo'; ?>
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center py-2" style="font-size: 0.85rem;">
                            <i class="fas fa-info-circle mr-1"></i>
                            <div>Sin rondas</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Rondas Generadas -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list mr-2"></i> Rondas Generadas</h5>
                    <?php if (!$isLocked && $puede_generar_ronda && ($proxima_ronda ?? 1) <= ($torneo['rondas'] ?? 0)): ?>
                        <form method="POST" action="<?php echo $base_url; ?>" class="d-inline">
                            <input type="hidden" name="action" value="generar_ronda">
                            <input type="hidden" name="csrf_token" value="<?php echo CSRF::token(); ?>">
                            <input type="hidden" name="torneo_id" value="<?php echo $torneo['id']; ?>">
                            <input type="hidden" name="num_ronda" value="<?php echo $proxima_ronda ?? 1; ?>">
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="fas fa-plus mr-1"></i> Generar Ronda <?php echo $proxima_ronda ?? 1; ?>
                            </button>
                        </form>
                    <?php elseif ($isLocked): ?>
                        <span class="badge badge-secondary">
                            <i class="fas fa-lock mr-1"></i> Torneo Cerrado
                        </span>
                    <?php elseif (!$puede_generar_ronda && ($mesas_incompletas ?? 0) > 0): ?>
                        <span class="badge badge-warning">
                            Faltan resultados en <?php echo $mesas_incompletas; ?> mesa(s) de la ronda <?php echo $ultima_ronda ?? 0; ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($rondas_generadas)): ?>
                        <p class="text-muted text-center py-4">
                            <i class="fas fa-info-circle mr-2"></i>
                            Aún no se han generado rondas para este torneo.
                            <?php if (($proxima_ronda ?? 1) <= ($torneo['rondas'] ?? 0)): ?>
                                Puedes generar la primera ronda usando el botón superior.
                            <?php endif; ?>
                        </p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Ronda</th>
                                        <th>Mesas</th>
                                        <th>Jugadores</th>
                                        <th>BYE</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rondas_generadas as $ronda): ?>
                                        <tr>
                                            <td><strong>Ronda <?php echo $ronda['num_ronda']; ?></strong></td>
                                            <td><?php echo $ronda['total_mesas']; ?></td>
                                            <td><?php echo $ronda['total_jugadores']; ?></td>
                                            <td><?php echo $ronda['jugadores_bye']; ?></td>
                                            <td><?php echo $ronda['fecha_generacion'] ? date('d/m/Y H:i', strtotime($ronda['fecha_generacion'])) : 'N/A'; ?></td>
                                            <td>
                                                <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=mesas&torneo_id=<?php echo $torneo['id']; ?>&ronda=<?php echo $ronda['num_ronda']; ?>" 
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

<script>
function confirmarCierreTorneoBasico(event) {
    Swal.fire({
        title: 'Cerrar torneo (irreversible)',
        html: '<p>Esta acción cerrará definitivamente el torneo y no permitirá más cambios.</p><p><strong>Sugerencia:</strong> Espere 15 minutos tras finalizar (0 mesas pendientes) antes de cerrar, para atender reclamos.</p>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, cerrar definitivamente',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#111827',
        cancelButtonColor: '#6c757d',
        reverseButtons: true,
        focusCancel: true
    }).then((res) => {
        if (res.isConfirmed) {
            event.target.submit();
        }
    });
}
</script>
