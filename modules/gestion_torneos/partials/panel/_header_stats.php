<?php
/**
 * Cabecera del panel: breadcrumb, hero, mensajes flash, alerta de actas, tarjetas KPI y estado de ronda.
 * Variables: contrato PanelTorneoViewData::build() + base_url, use_standalone (controlador).
 */
$sep = $use_standalone ? '?' : '&';
$torneoNombre = htmlspecialchars($torneo['nombre'] ?? 'Torneo', ENT_QUOTES, 'UTF-8');
$labelMod = htmlspecialchars($label_modalidad ?? 'Individual', ENT_QUOTES, 'UTF-8');
$tid = (int) ($torneo['id'] ?? 0);
$fechaTor = $torneo['fechator'] ?? 'now';
$totalRondasPlan = (int) ($torneo['rondas'] ?? 0);
$ultimaR = (int) ($ultima_ronda ?? 0);
$confirmados = (int) ($inscritos_para_rondas ?? $inscritos_confirmados ?? 0);
$totalLista = (int) ($total_inscritos ?? 0);
$mesasRonda = isset($estadisticas['mesas_ronda']) ? (int) $estadisticas['mesas_ronda'] : 0;
$verif = (int) ($mesas_verificadas_count ?? 0);
$digit = (int) ($mesas_digitadas_count ?? 0);
$actas = (int) ($actas_pendientes_count ?? 0);
$puedeGen = isset($puedeGenerar) ? (bool) $puedeGenerar : (bool) ($puede_generar_ronda ?? true);
$mesasPend = (int) ($mesasInc ?? $mesas_incompletas ?? 0);
?>
<nav aria-label="breadcrumb" class="mb-3 sm:mb-4">
    <ol class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs sm:text-sm text-slate-400">
        <li>
            <a href="<?php echo htmlspecialchars($base_url . $sep . 'action=index', ENT_QUOTES, 'UTF-8'); ?>" class="hover:text-amber-400/90 transition-colors">
                Gestión de Torneos
            </a>
        </li>
        <li aria-hidden="true"><i class="fas fa-chevron-right text-[10px] opacity-60"></i></li>
        <li class="text-slate-200 font-medium truncate max-w-[min(100%,14rem)] sm:max-w-none"><?php echo $torneoNombre; ?></li>
    </ol>
</nav>

<header class="relative overflow-hidden rounded-2xl border border-amber-500/20 bg-gradient-to-br from-[#0a1628] via-[#0f2744] to-[#0c1929] shadow-xl mb-4 sm:mb-5">
    <div class="pointer-events-none absolute -right-8 -top-8 h-32 w-32 rounded-full bg-amber-500/10 blur-2xl"></div>
    <div class="pointer-events-none absolute -bottom-6 left-1/4 h-24 w-24 rounded-full bg-[#722f37]/20 blur-xl"></div>
    <div class="relative px-4 py-5 sm:px-6 sm:py-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2 mb-2">
                <span class="inline-flex items-center rounded-full border border-amber-500/40 bg-amber-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-amber-300">
                    <i class="fas fa-chess mr-1.5 text-amber-400"></i><?php echo $labelMod; ?>
                </span>
                <span class="text-xs text-slate-400 sm:text-sm">
                    <i class="far fa-calendar-alt mr-1 text-amber-500/80"></i><?php echo date('d/m/Y', strtotime($fechaTor)); ?>
                </span>
            </div>
            <h2 class="text-xl sm:text-2xl lg:text-3xl font-bold text-white tracking-tight break-words">
                <?php echo $torneoNombre; ?>
            </h2>
            <p class="mt-1 text-sm text-slate-400">
                <i class="fas fa-layer-group mr-1 text-amber-500/70"></i><?php echo $totalRondasPlan; ?> rondas programadas
            </p>
        </div>
        <div class="flex shrink-0 items-center justify-between gap-3 sm:flex-col sm:items-end sm:text-right">
            <div class="text-3xl sm:text-4xl font-black text-amber-400/95 tabular-nums leading-none">#<?php echo $tid; ?></div>
            <div class="text-[10px] sm:text-xs uppercase tracking-wider text-slate-500">ID torneo</div>
        </div>
    </div>
</header>

<?php if (isset($_SESSION['success'])): ?>
    <div role="status" class="mb-4 flex items-start justify-between gap-3 rounded-xl border border-emerald-500/30 bg-emerald-950/40 px-4 py-3 text-emerald-100">
        <span class="text-sm"><i class="fas fa-check-circle mr-2 text-emerald-400"></i><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></span>
        <button type="button" class="shrink-0 text-emerald-300 hover:text-white" onclick="this.parentElement.remove()" aria-label="Cerrar">&times;</button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div role="alert" class="mb-4 flex items-start justify-between gap-3 rounded-xl border border-[#722f37]/50 bg-[#3f0d12]/50 px-4 py-3 text-red-100">
        <span class="text-sm"><i class="fas fa-exclamation-circle mr-2 text-red-300"></i><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
        <button type="button" class="shrink-0 text-red-200 hover:text-white" onclick="this.parentElement.remove()" aria-label="Cerrar">&times;</button>
    </div>
<?php endif; ?>

<?php if ($actas > 0): ?>
    <div class="mb-4 sm:mb-5 overflow-hidden rounded-2xl border-2 border-[#8b1538] bg-gradient-to-r from-[#4a0d18] via-[#5c1220] to-[#3d0a14] shadow-lg shadow-[#722f37]/20">
        <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
            <div class="flex min-w-0 items-start gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-500/20 text-amber-300">
                    <i class="fas fa-clipboard-check text-lg"></i>
                </span>
                <div>
                    <p class="font-bold text-white">Actas pendientes de verificación</p>
                    <p class="mt-0.5 text-sm text-amber-100/80">
                        <?php echo (int) $actas; ?> acta(s) enviada(s) por QR esperan revisión.
                    </p>
                </div>
            </div>
            <a href="<?php echo htmlspecialchars($base_url . $sep . 'action=verificar_resultados&torneo_id=' . $tid, ENT_QUOTES, 'UTF-8'); ?>"
               class="inline-flex w-full min-h-[44px] items-center justify-center rounded-xl bg-amber-500 px-4 py-3 text-sm font-bold text-[#0a1628] shadow-md transition hover:bg-amber-400 sm:w-auto sm:min-h-0 sm:py-2.5">
                <i class="fas fa-eye mr-2"></i>Verificar ahora
            </a>
        </div>
    </div>
<?php endif; ?>

<section class="mb-5 sm:mb-6" aria-label="Resumen del torneo">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 sm:gap-4">
        <article class="rounded-2xl border border-amber-500/15 bg-[#0f2744]/80 p-4 shadow-md backdrop-blur-sm">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-amber-400/90">Confirmados</span>
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-500/15 text-amber-300"><i class="fas fa-user-check"></i></span>
            </div>
            <p class="mt-2 text-3xl font-bold tabular-nums text-white"><?php echo $confirmados; ?></p>
            <p class="mt-1 text-xs text-slate-400">
                <?php if ($es_modalidad_equipos ?? false): ?>
                    <?php echo (int) ($total_equipos ?? 0); ?> equipos · <?php echo (int) ($estadisticas['total_jugadores_inscritos'] ?? $total_jugadores_inscritos ?? 0); ?> jugadores
                <?php else: ?>
                    <?php if ($totalLista > 0 && $totalLista !== $confirmados): ?>
                        <?php echo $totalLista; ?> en lista total
                    <?php else: ?>
                        Atletas para rondas
                    <?php endif; ?>
                <?php endif; ?>
            </p>
        </article>

        <article class="rounded-2xl border border-amber-500/15 bg-[#0f2744]/80 p-4 shadow-md backdrop-blur-sm">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-amber-400/90">Rondas</span>
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-500/15 text-amber-300"><i class="fas fa-sync-alt"></i></span>
            </div>
            <p class="mt-2 text-3xl font-bold tabular-nums text-white">
                <?php echo $ultimaR; ?><span class="text-lg font-semibold text-slate-500"> / <?php echo $totalRondasPlan; ?></span>
            </p>
            <p class="mt-1 text-xs text-slate-400">Generadas · planificadas</p>
        </article>

        <article class="rounded-2xl border border-emerald-500/20 bg-[#0c1f14]/60 p-4 shadow-md sm:col-span-2 lg:col-span-1">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-emerald-300/90">Auditoría QR</span>
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-300"><i class="fas fa-camera"></i></span>
            </div>
            <p class="mt-2 text-3xl font-bold tabular-nums text-white"><?php echo $verif; ?></p>
            <p class="mt-1 text-xs text-slate-400">Mesas verificadas (foto / QR)</p>
        </article>

        <article class="rounded-2xl border border-sky-500/20 bg-[#0c1929]/80 p-4 shadow-md sm:col-span-2 lg:col-span-1">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-sky-300/90">Digitadas</span>
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-sky-500/15 text-sky-300"><i class="fas fa-keyboard"></i></span>
            </div>
            <p class="mt-2 text-3xl font-bold tabular-nums text-white"><?php echo $digit; ?></p>
            <p class="mt-1 text-xs text-slate-400">Registro por administración</p>
        </article>
    </div>
</section>

<?php if ($ultimaR > 0): ?>
    <div class="mb-5 sm:mb-6 rounded-2xl border border-amber-500/20 bg-[#0a1628]/90 p-4 sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                <span class="font-semibold text-slate-300">
                    Ronda actual
                    <span class="ml-1 text-2xl font-bold text-amber-400 tabular-nums"><?php echo $ultimaR; ?></span>
                </span>
                <?php if ($mesasRonda > 0): ?>
                    <span class="text-amber-200/90"><i class="fas fa-border-all mr-1"></i><?php echo $mesasRonda; ?> mesas</span>
                <?php endif; ?>
                <?php if (!empty($es_modalidad_equipos)): ?>
                    <?php if (!empty($total_equipos) && (int) $total_equipos > 0): ?>
                        <span class="text-slate-300"><i class="fas fa-users mr-1 text-amber-500/80"></i><?php echo (int) $total_equipos; ?> equipos</span>
                    <?php endif; ?>
                    <?php
                    $tj = (int) ($estadisticas['total_jugadores_inscritos'] ?? $total_jugadores_inscritos ?? 0);
                    if ($tj > 0):
                    ?>
                        <span class="text-slate-300"><i class="fas fa-user-friends mr-1 text-emerald-400/80"></i><?php echo $tj; ?> jugadores</span>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if ($confirmados > 0): ?>
                        <span class="text-slate-300">
                            <i class="fas fa-user-friends mr-1 text-emerald-400/80"></i><?php echo $confirmados; ?> inscritos
                            <?php if ($totalLista > 0 && $totalLista !== $confirmados): ?>
                                <span class="text-slate-500">(<?php echo $totalLista; ?> en lista)</span>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php if (!$puedeGen && $ultimaR > 0 && $mesasPend > 0): ?>
                <div class="flex items-center gap-2 rounded-xl border border-[#722f37]/40 bg-[#4a0d18]/40 px-3 py-2 text-sm font-semibold text-amber-100">
                    <i class="fas fa-exclamation-triangle text-amber-400"></i>
                    <span><?php echo $mesasPend; ?> mesa(s) pendiente(s)</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
