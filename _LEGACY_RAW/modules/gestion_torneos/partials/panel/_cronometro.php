<?php
/**
 * Cronómetro de ronda (overlay) + cuenta atrás de cierre de torneo + botón activar.
 * Variables: $torneo, $mostrar_aviso_20min, $countdown_fin_timestamp, $puedeCerrar,
 *            $use_standalone, $base_url, $tiempo_ronda_minutos (opcional).
 */
$tiempo_ronda_min = isset($tiempo_ronda_minutos) ? (int) $tiempo_ronda_minutos : (int) ($torneo['tiempo'] ?? 35);
if ($tiempo_ronda_min < 1) {
    $tiempo_ronda_min = 35;
}
?>
    <!-- Cronómetro Finalizar Torneo (mismo diseño que cronómetro de ronda, encima de él) -->
    <?php if ($mostrar_aviso_20min && $countdown_fin_timestamp): ?>
    <div class="mb-4 text-center cronometro-finalizar-torneo" id="countdown-cierre-torneo-top">
        <p class="cron-finalizar-label"><i class="fas fa-lock mr-2"></i>El torneo se cerrará oficialmente en</p>
        <p class="countdown-tiempo-restante tabular-nums" data-fin="<?php echo (int) $countdown_fin_timestamp; ?>">--:--</p>
        <p class="cron-finalizar-hint">Tras este tiempo se habilitará el botón Finalizar torneo</p>
    </div>
    <?php elseif ($puedeCerrar): ?>
    <div class="mb-4 text-center cronometro-finalizar-torneo">
        <p class="cron-finalizar-label"><i class="fas fa-check-circle mr-2"></i>Puede finalizar el torneo</p>
        <form method="POST" action="<?php echo $use_standalone ? $base_url : 'index.php?page=torneo_gestion'; ?>" class="inline mt-2" onsubmit="event.preventDefault(); confirmarCierreTorneo(event);">
            <input type="hidden" name="action" value="cerrar_torneo">
            <input type="hidden" name="csrf_token" value="<?php echo CSRF::token(); ?>">
            <input type="hidden" name="torneo_id" value="<?php echo $torneo['id']; ?>">
            <button type="submit" class="d-inline-block font-bold py-2 px-5 rounded-lg text-lg border-0 shadow lvd-panel-finalizar-torneo-btn">
                <i class="fas fa-lock mr-2"></i>Finalizar torneo
            </button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Botón Activar/Retornar Cronómetro (compacto) -->
    <div class="mb-2 text-center">
        <button type="button" id="btnCronometro"
           class="d-inline-block font-bold py-2 px-5 rounded-lg text-lg transition-all transform shadow border-0 lvd-panel-btn-activar-cron">
            <i class="fas fa-clock mr-2"></i><span id="lblCronometro">ACTIVAR CRONÓMETRO DE RONDA</span>
        </button>
    </div>

    <!-- Overlay Cronómetro - pantalla completa en la misma página (tiempo definido en el torneo) -->
    <div id="cronometroOverlay" data-tiempo-minutos="<?php echo $tiempo_ronda_min; ?>">
        <div class="cron-box">
            <div class="cron-header">
                <h1><i class="fas fa-clock me-2"></i>Cronómetro - <?= htmlspecialchars($torneo['nombre'] ?? 'Ronda', ENT_QUOTES, 'UTF-8') ?></h1>
                <div style="display:flex;gap:0.5rem;align-items:center;">
                    <button type="button" class="btn-retornar" onclick="ocultarCronometroOverlay()">
                        <i class="fas fa-arrow-left me-1"></i> Retornar al Panel
                    </button>
                    <button type="button" class="btn-retornar" style="background:rgba(255,255,255,0.2)" onclick="toggleConfigCron()"><i class="fas fa-cog me-1"></i>Configurar</button>
                </div>
            </div>
            <div id="configPanelCron">
                <div class="config-grid">
                    <div><label>Minutos</label><input type="number" id="configMinutosCron" min="1" max="99" value="<?php echo $tiempo_ronda_min; ?>"></div>
                    <div><label>Segundos</label><input type="number" id="configSegundosCron" min="0" max="59" value="0"></div>
                </div>
                <button type="button" class="btn-retornar" style="width:100%;background:#22c55e" onclick="aplicarConfigCron()"><i class="fas fa-check me-1"></i>APLICAR</button>
            </div>
            <div class="cron-display">
                <div id="tiempoDisplayCron"><?php echo str_pad((string) $tiempo_ronda_min, 2, '0', STR_PAD_LEFT); ?>:00</div>
                <div id="estadoDisplayCron"><i class="fas fa-pause-circle me-1"></i>DETENIDO</div>
                <div class="cron-controles">
                    <button id="btnIniciarCron" onclick="iniciarCronometro()" title="Iniciar"><i class="fas fa-play"></i></button>
                    <button id="btnDetenerCron" onclick="detenerCronometro()" title="Detener" disabled><i class="fas fa-stop"></i></button>
                </div>
            </div>
        </div>
    </div>
