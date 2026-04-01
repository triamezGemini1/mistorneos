<?php
/**
 * Indicador y control: cierre de fase de inscripción antes de generar la ronda 1.
 * Variables esperadas: $torneo, $base_url, $use_standalone, $torneo_iniciado (bool),
 * $inscripciones_finalizadas (bool), $redirect_action ('inscripciones'|'gestionar_inscripciones_equipos')
 */
if (empty($torneo['id'])) {
    return;
}
$torneo_iniciado = !empty($torneo_iniciado);
$inscripciones_finalizadas = !empty($inscripciones_finalizadas);
$redirect_action = isset($redirect_action) && $redirect_action === 'gestionar_inscripciones_equipos'
    ? 'gestionar_inscripciones_equipos'
    : 'inscripciones';
$csrf = class_exists('CSRF') ? CSRF::token() : '';
$form_action = $use_standalone ? $base_url : 'index.php?page=torneo_gestion';
?>
<div class="card border-0 shadow-sm mb-4" style="border-left: 4px solid <?php echo $inscripciones_finalizadas ? '#059669' : '#d97706'; ?> !important;">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-start gap-3">
                <span class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                      style="width: 44px; height: 44px; background: <?php echo $inscripciones_finalizadas ? 'rgba(5,150,105,0.15)' : 'rgba(217,119,6,0.15)'; ?>;">
                    <i class="fas <?php echo $inscripciones_finalizadas ? 'fa-flag-checkered text-success' : 'fa-clipboard-list text-warning'; ?> fa-lg"></i>
                </span>
                <div>
                    <h3 class="h6 mb-1 fw-bold text-dark">Fase de inscripción y competencia</h3>
                    <?php if ($torneo_iniciado): ?>
                        <p class="mb-0 text-muted small">
                            El torneo ya tiene rondas generadas. Este indicador queda como referencia.
                            <?php if ($inscripciones_finalizadas): ?>
                                <span class="badge bg-success ms-1">Inscripción cerrada para inicio</span>
                            <?php endif; ?>
                        </p>
                    <?php elseif ($inscripciones_finalizadas): ?>
                        <p class="mb-0 text-success small fw-medium">
                            <i class="fas fa-check-circle me-1"></i>
                            Inscripción cerrada para iniciar la competencia. Puede generar la <strong>primera ronda</strong> desde el panel del torneo.
                        </p>
                    <?php else: ?>
                        <p class="mb-0 text-muted small">
                            Mientras esta fase esté abierta, el botón <strong>Generar ronda</strong> del panel permanece deshabilitado.
                            Cuando termine de registrar inscripciones, confirme el cierre aquí.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!$torneo_iniciado): ?>
                <form method="post" action="<?php echo htmlspecialchars($form_action); ?>" class="d-flex align-items-center gap-2 flex-shrink-0">
                    <input type="hidden" name="action" value="set_inscripciones_finalizadas">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="torneo_id" value="<?php echo (int) $torneo['id']; ?>">
                    <input type="hidden" name="redirect_action" value="<?php echo htmlspecialchars($redirect_action); ?>">
                    <?php if ($inscripciones_finalizadas): ?>
                        <input type="hidden" name="inscripciones_finalizadas" value="0">
                        <button type="submit" class="btn btn-outline-secondary btn-sm" onclick="return confirm('¿Reabrir la fase de inscripción? No podrá generar la primera ronda hasta cerrarla de nuevo.');">
                            <i class="fas fa-undo me-1"></i> Reabrir inscripción
                        </button>
                    <?php else: ?>
                        <input type="hidden" name="inscripciones_finalizadas" value="1">
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="fas fa-play me-1"></i> Cerrar inscripción e iniciar competencia
                        </button>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
