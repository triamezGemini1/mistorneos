<?php
/**
 * Panel táctico de acciones del torneo (mobile-first, 3 columnas en md+).
 * Contrato: PanelTorneoViewData + base_url, use_standalone (controlador).
 * Formularios POST (generar ronda, eliminar ronda, estadísticas, cierre) viven aquí.
 */
$sep = $use_standalone ? '?' : '&';
$tid = (int) ($torneo['id'] ?? 0);
$lockedTorneo = (bool) ($is_locked ?? $isLocked ?? false);
$puedeGenContract = (bool) ($puede_generar_ronda ?? $puedeGenerar ?? $puedeGenerarRonda ?? true);
$generarRondaBloqueado = !$puedeGenContract || $lockedTorneo;
$proximaR = (int) ($proximaRonda ?? $proxima_ronda ?? 0);
$totalR = (int) ($totalRondas ?? $torneo['rondas'] ?? 0);
$podiosAct = isset($podios_action) ? (string) $podios_action : (($es_modalidad_equipos ?? false) ? 'podios_equipos' : 'podios');
$url_podios = htmlspecialchars($base_url . $sep . 'action=' . $podiosAct . '&torneo_id=' . $tid, ENT_QUOTES, 'UTF-8');

$tactile = 'transition-all duration-150 ease-out select-none touch-manipulation hover:-translate-y-0.5 active:scale-[0.97] active:translate-y-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/40';
$tactileLink = 'transition-all duration-150 ease-out select-none touch-manipulation active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/40';

$btnNav = 'inline-flex w-full min-h-[3.5rem] items-center justify-center gap-3 rounded-xl border border-amber-500/30 bg-slate-800 px-5 py-4 text-lg font-semibold leading-snug text-amber-100 shadow-md sm:min-h-[3.75rem] sm:px-6 sm:py-5 sm:text-xl ' . $tactileLink . ' hover:border-amber-400/55 hover:bg-slate-700 hover:text-white';

$btnPrimaryGold = 'inline-flex w-full min-h-[3.75rem] items-center justify-center gap-3 rounded-xl border-2 border-amber-500 bg-slate-800 px-5 py-5 text-lg font-bold leading-snug text-amber-400 shadow-lg shadow-amber-900/20 sm:min-h-[4rem] sm:px-6 sm:py-5 sm:text-xl ' . $tactile . ' hover:border-amber-400 hover:bg-slate-700 hover:shadow-amber-500/25';

$btnGenerarOff = 'inline-flex w-full min-h-[3.75rem] cursor-not-allowed items-center justify-center gap-3 rounded-xl border-2 border-amber-500/40 bg-slate-800 px-5 py-5 text-lg font-bold leading-snug text-amber-300/80 opacity-50 pointer-events-none sm:min-h-[4rem] sm:px-6 sm:py-5 sm:text-xl';

$btnMuted = 'inline-flex w-full min-h-[3.5rem] cursor-not-allowed items-center justify-center gap-3 rounded-xl border border-slate-600 bg-slate-700/70 px-5 py-4 text-lg font-semibold text-slate-400 opacity-90 sm:min-h-[3.75rem] sm:px-6 sm:py-5 sm:text-xl';

$btnDanger = 'inline-flex w-full min-h-[3.5rem] items-center justify-center gap-3 rounded-xl border border-[#8b2942]/70 bg-[#4a0d18] px-5 py-4 text-lg font-semibold leading-snug text-red-50 shadow-md ' . $tactile . ' hover:border-red-500/50 hover:bg-[#5c1020] focus-visible:ring-red-900/50 disabled:cursor-not-allowed disabled:opacity-50 sm:min-h-[3.75rem] sm:px-6 sm:py-5 sm:text-xl';

$btnUrgent = 'inline-flex w-full min-h-[3.5rem] items-center justify-center gap-3 rounded-xl border border-rose-700/55 bg-[#5c1220] px-5 py-4 text-lg font-semibold leading-snug text-rose-50 shadow-md ' . $tactileLink . ' hover:border-amber-400/40 hover:bg-[#722f37] sm:min-h-[3.75rem] sm:px-6 sm:py-5 sm:text-xl';

$btnCierre = 'inline-flex w-full min-h-[3.5rem] items-center justify-center gap-3 rounded-xl border px-5 py-4 text-lg font-bold leading-snug transition-all duration-150 ease-out select-none touch-manipulation active:scale-[0.98] sm:min-h-[3.75rem] sm:px-6 sm:py-5 sm:text-xl disabled:cursor-not-allowed disabled:opacity-50';

$cardHead = 'border-b border-amber-500/20 bg-slate-900/90 px-4 py-4 sm:px-5';
$cardShell = 'rounded-2xl border border-amber-500/15 bg-[#0a1628]/95 shadow-lg overflow-hidden h-full flex flex-col';
?>
<!-- Panel de acciones — 1 col móvil · 3 cols tablet/desktop -->
<div class="grid grid-cols-1 gap-4 md:grid-cols-3 md:gap-5" role="region" aria-label="Acciones del torneo">

    <!-- Columna 1: Gestión de mesas y ronda -->
    <div class="flex flex-col min-w-0">
        <div class="<?php echo $cardShell; ?>">
            <div class="<?php echo $cardHead; ?>">
                <h3 class="text-lg sm:text-xl font-bold text-white flex items-center gap-2 mb-0">
                    <i class="fas fa-table text-amber-400 text-xl sm:text-2xl" aria-hidden="true"></i>
                    Gestión de mesas
                </h3>
                <p class="text-sm text-slate-400 mt-2 mb-0">Ronda, impresión y distribución de mesas</p>
            </div>
            <div class="p-4 sm:p-6 space-y-4 flex-1 flex flex-col">

                <?php if ($proximaR <= $totalR): ?>
                    <form method="POST" action="<?php echo $use_standalone ? ($base_url . '?torneo_id=' . $tid) : 'index.php?page=torneo_gestion'; ?>" id="form-generar-ronda" class="w-full">
                        <input type="hidden" name="action" value="generar_ronda">
                        <input type="hidden" name="csrf_token" value="<?php echo CSRF::token(); ?>">
                        <input type="hidden" name="torneo_id" value="<?php echo $tid; ?>">
                        <button type="submit" id="btn-generar-ronda"
                                <?php echo $generarRondaBloqueado ? 'disabled aria-disabled="true"' : ''; ?>
                                class="<?php echo $generarRondaBloqueado ? $btnGenerarOff : $btnPrimaryGold; ?> w-full">
                            <i class="fas fa-<?php echo $generarRondaBloqueado ? 'lock' : 'play'; ?> text-amber-400 text-xl sm:text-2xl" aria-hidden="true"></i>
                            Generar ronda <?php echo $proximaR; ?>
                        </button>
                    </form>
                <?php else: ?>
                    <div class="rounded-xl border border-emerald-700/40 bg-emerald-950/40 px-5 py-4 text-center text-lg font-semibold text-emerald-100 sm:text-xl">
                        <i class="fas fa-check-circle text-emerald-400 mr-2" aria-hidden="true"></i>
                        Todas las rondas generadas
                    </div>
                <?php endif; ?>

                <?php if ($ultima_ronda > 0): ?>
                    <a href="<?php echo $base_url . $sep; ?>action=hojas_anotacion&torneo_id=<?php echo $torneo['id']; ?>&ronda=<?php echo $ultima_ronda; ?>"
                       class="<?php echo $btnNav; ?>">
                        <i class="fas fa-print text-amber-400 text-xl" aria-hidden="true"></i>
                        Re-imprimir hojas / actas
                    </a>
                    <a href="<?php echo $base_url . $sep; ?>action=mesas&torneo_id=<?php echo $torneo['id']; ?>&ronda=<?php echo $ultima_ronda; ?>"
                       class="<?php echo $btnNav; ?>">
                        <i class="fas fa-eye text-amber-400 text-xl" aria-hidden="true"></i>
                        Mostrar asignaciones
                    </a>
                    <a href="<?php echo $base_url . $sep; ?>action=asignar_mesas_operador&torneo_id=<?php echo $torneo['id']; ?>&ronda=<?php echo $ultima_ronda; ?>"
                       class="<?php echo $btnNav; ?>">
                        <i class="fas fa-user-cog text-amber-400 text-xl" aria-hidden="true"></i>
                        Asignar mesas al operador
                    </a>
                    <?php if ($lockedTorneo): ?>
                        <button type="button" disabled class="<?php echo $btnMuted; ?> w-full">
                            <i class="fas fa-lock text-slate-500 text-xl" aria-hidden="true"></i>
                            Agregar mesa (cerrado)
                        </button>
                    <?php elseif ($ultima_ronda >= 2): ?>
                        <button type="button" disabled class="<?php echo $btnMuted; ?> w-full" title="Solo disponible en la ronda 1">
                            <i class="fas fa-plus-circle text-slate-500 text-xl" aria-hidden="true"></i>
                            Agregar mesa (solo ronda 1)
                        </button>
                    <?php else: ?>
                        <a href="<?php echo $base_url . $sep; ?>action=agregar_mesa&torneo_id=<?php echo $torneo['id']; ?>&ronda=<?php echo $ultima_ronda; ?>"
                           class="<?php echo $btnNav; ?>">
                            <i class="fas fa-plus-circle text-amber-400 text-xl" aria-hidden="true"></i>
                            Agregar mesa
                        </a>
                    <?php endif; ?>

                    <form method="POST" action="<?php echo $use_standalone ? $base_url : 'index.php?page=torneo_gestion'; ?>"
                          class="w-full"
                          onsubmit="event.preventDefault(); eliminarRondaConfirmar(event, <?php echo $ultima_ronda; ?>, <?php echo $ultima_ronda_tiene_resultados ? 'true' : 'false'; ?>);">
                        <input type="hidden" name="action" value="eliminar_ultima_ronda">
                        <input type="hidden" name="csrf_token" value="<?php echo CSRF::token(); ?>">
                        <input type="hidden" name="torneo_id" value="<?php echo $torneo['id']; ?>">
                        <input type="hidden" name="confirmar_eliminar_con_resultados" id="confirmar_eliminar_con_resultados" value="">
                        <button type="submit" <?php echo $lockedTorneo ? 'disabled' : ''; ?>
                                class="<?php echo $lockedTorneo ? $btnMuted : $btnDanger; ?> w-full"
                                title="<?php echo $lockedTorneo ? 'Torneo cerrado.' : ($ultima_ronda_tiene_resultados ? 'Eliminar ronda (la ronda tiene resultados en mesas; se pedirá confirmación estricta).' : 'Eliminar la última ronda.'); ?>">
                            <i class="fas fa-trash-alt text-red-200 text-xl" aria-hidden="true"></i>
                            Eliminar ronda
                        </button>
                    </form>
                <?php else: ?>
                    <div class="rounded-xl border border-slate-600/60 bg-slate-800/50 px-5 py-5 text-center text-base text-slate-400 sm:text-lg">
                        <i class="fas fa-info-circle text-amber-500/80 mr-2" aria-hidden="true"></i>
                        Genera la primera ronda para habilitar mesas e impresión
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- Columna 2: Operaciones e inscripciones -->
    <div class="flex flex-col min-w-0">
        <div class="<?php echo $cardShell; ?>">
            <div class="<?php echo $cardHead; ?>">
                <h3 class="text-lg sm:text-xl font-bold text-white flex items-center gap-2 mb-0">
                    <i class="fas fa-cogs text-amber-400 text-xl sm:text-2xl" aria-hidden="true"></i>
                    Operaciones
                </h3>
                <p class="text-sm text-slate-400 mt-2 mb-0">Inscripciones, auditoría QR y herramientas</p>
            </div>
            <div class="p-4 sm:p-6 space-y-4 flex-1 flex flex-col">

                <a href="index.php?page=invitacion_clubes&torneo_id=<?php echo $tid; ?>" class="<?php echo $btnNav; ?>">
                    <i class="fas fa-paper-plane text-amber-400 text-xl" aria-hidden="true"></i>
                    Invitar clubes
                </a>

                <?php if ($lockedTorneo): ?>
                    <button type="button" disabled class="<?php echo $btnMuted; ?> w-full">
                        <i class="fas fa-lock text-slate-500 text-xl" aria-hidden="true"></i>
                        Inscripciones (cerrado)
                    </button>
                <?php elseif ($torneo_bloqueado_inscripciones): ?>
                    <a href="index.php?page=registrants&torneo_id=<?php echo $torneo['id']; ?><?php echo $use_standalone ? '&return_to=panel_torneo' : ''; ?>" class="<?php echo $btnNav; ?>">
                        <i class="fas fa-clipboard-list text-amber-400 text-xl" aria-hidden="true"></i>
                        Gestionar inscripciones (retirar)
                    </a>
                    <a href="<?php echo $base_url . $sep; ?>action=activar_participantes&torneo_id=<?php echo $tid; ?>" class="<?php echo $btnNav; ?>">
                        <i class="fas fa-user-check text-amber-400 text-xl" aria-hidden="true"></i>
                        Activar participantes
                    </a>
                    <?php if (!$es_modalidad_equipos && $ultima_ronda >= 1): ?>
                        <a href="index.php?page=torneo_gestion&action=sustituir_jugador&torneo_id=<?php echo $tid; ?>" class="<?php echo $btnNav; ?>">
                            <i class="fas fa-user-exchange text-amber-400 text-xl" aria-hidden="true"></i>
                            Sustituir jugador retirado
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="flex flex-col gap-3">
                        <?php if ($es_modalidad_equipos_o_parejas): ?>
                            <a href="<?php echo $base_url . $sep; ?>action=gestionar_inscripciones_equipos&torneo_id=<?php echo $torneo['id']; ?>" class="<?php echo $btnNav; ?>">
                                <i class="fas fa-clipboard-list text-amber-400 text-xl" aria-hidden="true"></i>
                                <?php echo $es_modalidad_parejas ? 'Gestionar inscripciones (parejas)' : 'Gestionar inscripciones'; ?>
                            </a>
                            <a href="<?php echo $base_url . $sep; ?>action=inscribir_equipo_sitio&torneo_id=<?php echo $torneo['id']; ?>" class="<?php echo $btnNav; ?>">
                                <i class="fas fa-user-plus text-amber-400 text-xl" aria-hidden="true"></i>
                                <?php echo $es_modalidad_parejas ? 'Inscribir pareja' : 'Inscribir en sitio'; ?>
                            </a>
                            <?php if ($es_modalidad_equipos): ?>
                                <a href="<?php echo $base_url . $sep; ?>action=carga_masiva_equipos_sitio&torneo_id=<?php echo $torneo['id']; ?>" class="<?php echo $btnNav; ?>">
                                    <i class="fas fa-file-upload text-amber-400 text-xl" aria-hidden="true"></i>
                                    Carga masiva
                                </a>
                            <?php endif; ?>
                        <?php elseif ($es_modalidad_parejas_fijas): ?>
                            <a href="<?php echo $base_url . $sep; ?>action=gestionar_inscripciones_parejas_fijas&torneo_id=<?php echo $torneo['id']; ?>" class="<?php echo $btnNav; ?>">
                                <i class="fas fa-clipboard-list text-amber-400 text-xl" aria-hidden="true"></i>
                                Gestionar inscripciones
                            </a>
                            <a href="<?php echo $base_url . $sep; ?>action=inscribir_pareja_sitio&torneo_id=<?php echo $torneo['id']; ?>" class="<?php echo $btnNav; ?>">
                                <i class="fas fa-user-plus text-amber-400 text-xl" aria-hidden="true"></i>
                                Inscribir pareja en sitio
                            </a>
                        <?php else: ?>
                            <a href="index.php?page=registrants&torneo_id=<?php echo $torneo['id']; ?><?php echo $use_standalone ? '&return_to=panel_torneo' : ''; ?>" class="<?php echo $btnNav; ?>">
                                <i class="fas fa-clipboard-list text-amber-400 text-xl" aria-hidden="true"></i>
                                Gestionar inscripciones
                            </a>
                            <a href="<?php echo $base_url . $sep; ?>action=inscribir_sitio&torneo_id=<?php echo $torneo['id']; ?>" class="<?php echo $btnNav; ?>">
                                <i class="fas fa-user-check text-amber-400 text-xl" aria-hidden="true"></i>
                                Inscripción en sitio
                            </a>
                            <button type="button" class="<?php echo $btnNav; ?>" data-bs-toggle="modal" data-bs-target="#modalImportacionMasiva" id="btnAbrirImportacionMasiva">
                                <i class="fas fa-file-csv text-amber-400 text-xl" aria-hidden="true"></i>
                                Importación masiva
                            </button>
                        <?php endif; ?>
                        <a href="<?php echo $base_url . $sep; ?>action=activar_participantes&torneo_id=<?php echo $tid; ?>" class="<?php echo $btnNav; ?>">
                            <i class="fas fa-user-check text-amber-400 text-xl" aria-hidden="true"></i>
                            Activar participantes
                        </a>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo $use_standalone ? $base_url : 'index.php?page=torneo_gestion'; ?>"
                      onsubmit="event.preventDefault(); actualizarEstadisticasConfirmar(event);">
                    <input type="hidden" name="action" value="actualizar_estadisticas">
                    <input type="hidden" name="csrf_token" value="<?php echo CSRF::token(); ?>">
                    <input type="hidden" name="torneo_id" value="<?php echo $torneo['id']; ?>">
                    <button type="submit" <?php echo $lockedTorneo ? 'disabled' : ''; ?>
                            class="<?php echo $lockedTorneo ? $btnMuted : $btnNav; ?> w-full">
                        <i class="fas fa-sync-alt text-amber-400 text-xl" aria-hidden="true"></i>
                        Actualizar estadísticas
                    </button>
                </form>

                <?php if ($actas_pendientes_count > 0): ?>
                    <a href="<?php echo $base_url . $sep; ?>action=verificar_resultados&torneo_id=<?php echo $tid; ?>"
                       class="<?php echo $btnUrgent; ?> w-full">
                        <i class="fas fa-check-double text-amber-300 text-xl" aria-hidden="true"></i>
                        Auditoría — verificar mesas
                        <span class="ml-1 inline-flex min-w-[1.75rem] items-center justify-center rounded-full bg-amber-500/25 px-2 py-1 text-base font-bold text-amber-100"><?php echo (int) $actas_pendientes_count; ?></span>
                    </a>
                <?php else: ?>
                    <button type="button" disabled class="<?php echo $btnMuted; ?> w-full">
                        <i class="fas fa-check-double text-slate-500 text-xl" aria-hidden="true"></i>
                        Verificar mesas
                        <span class="ml-2 text-base opacity-75">(sin envíos QR pendientes)</span>
                    </button>
                <?php endif; ?>

                <?php if ($ultima_ronda > 0 && $primera_mesa): ?>
                    <?php if ($lockedTorneo): ?>
                        <button type="button" disabled class="<?php echo $btnMuted; ?> w-full">
                            <i class="fas fa-lock text-slate-500 text-xl" aria-hidden="true"></i>
                            Ingresar resultados (cerrado)
                        </button>
                    <?php else: ?>
                        <a href="<?php echo $base_url . $sep; ?>action=registrar_resultados&torneo_id=<?php echo $torneo['id']; ?>&ronda=<?php echo $ultima_ronda; ?>&mesa=<?php echo $primera_mesa; ?>"
                           class="<?php echo $btnNav; ?>">
                            <i class="fas fa-keyboard text-amber-400 text-xl" aria-hidden="true"></i>
                            Ingresar resultados
                        </a>
                    <?php endif; ?>
                <?php elseif ($ultima_ronda > 0): ?>
                    <button type="button" disabled class="<?php echo $btnMuted; ?> w-full">
                        <i class="fas fa-info-circle text-slate-500 text-xl" aria-hidden="true"></i>
                        Sin mesas registradas
                    </button>
                <?php endif; ?>

                <?php if ($ultima_ronda > 0): ?>
                    <a href="<?php echo $base_url . $sep; ?>action=cuadricula&torneo_id=<?php echo $torneo['id']; ?>&ronda=<?php echo $ultima_ronda; ?>"
                       class="<?php echo $btnNav; ?>">
                        <i class="fas fa-th text-amber-400 text-xl" aria-hidden="true"></i>
                        Cuadrícula
                    </a>
                <?php endif; ?>

                <a href="index.php?page=tournament_admin&torneo_id=<?php echo $tid; ?>&action=generar_qr"
                   class="<?php echo $btnNav; ?>" target="_blank" rel="noopener">
                    <i class="fas fa-qrcode text-amber-400 text-xl" aria-hidden="true"></i>
                    Generar e imprimir QR del torneo
                </a>

            </div>
        </div>
    </div>

    <!-- Columna 3: Resultados, reportes y cierre -->
    <div class="flex flex-col min-w-0">
        <div class="<?php echo $cardShell; ?>">
            <div class="<?php echo $cardHead; ?>">
                <h3 class="text-lg sm:text-xl font-bold text-white flex items-center gap-2 mb-0">
                    <i class="fas fa-trophy text-amber-400 text-xl sm:text-2xl" aria-hidden="true"></i>
                    Resultados y reportes
                </h3>
                <p class="text-sm text-slate-400 mt-2 mb-0">Clasificación, podios y exportación</p>
            </div>
            <div class="p-4 sm:p-6 space-y-4 flex-1 flex flex-col">

                <?php if ($es_modalidad_equipos): ?>
                    <a href="<?php echo $base_url . $sep; ?>action=resultados_equipos_resumido&torneo_id=<?php echo $torneo['id']; ?>" class="<?php echo $btnNav; ?>">
                        <i class="fas fa-list-ol text-amber-400 text-xl" aria-hidden="true"></i>
                        Resultados equipos (resumido)
                    </a>
                    <a href="<?php echo $base_url . $sep; ?>action=resultados_equipos_detallado&torneo_id=<?php echo $torneo['id']; ?>" class="<?php echo $btnNav; ?>">
                        <i class="fas fa-list-ul text-amber-400 text-xl" aria-hidden="true"></i>
                        Resultados equipos (detallado)
                    </a>
                    <a href="<?php echo $base_url . $sep; ?>action=posiciones&torneo_id=<?php echo $torneo['id']; ?>" class="<?php echo $btnNav; ?>">
                        <i class="fas fa-users-cog text-amber-400 text-xl" aria-hidden="true"></i>
                        Ver clasificación / posiciones
                    </a>
                <?php else: ?>
                    <a href="<?php echo $base_url . $sep; ?>action=posiciones&torneo_id=<?php echo $torneo['id']; ?>" class="<?php echo $btnNav; ?>">
                        <i class="fas fa-list-ol text-amber-400 text-xl" aria-hidden="true"></i>
                        <?php echo ($es_modalidad_parejas || $es_modalidad_parejas_fijas) ? 'Ver clasificación (parejas)' : 'Ver clasificación'; ?>
                    </a>
                    <a href="<?php echo $base_url . $sep; ?>action=resultados_por_club&torneo_id=<?php echo $torneo['id']; ?>" class="<?php echo $btnNav; ?>">
                        <i class="fas fa-building text-amber-400 text-xl" aria-hidden="true"></i>
                        Resultados por club
                    </a>
                    <a href="<?php echo $base_url . $sep; ?>action=resultados_reportes&torneo_id=<?php echo $torneo['id']; ?>" class="<?php echo $btnNav; ?>">
                        <i class="fas fa-file-export text-amber-400 text-xl" aria-hidden="true"></i>
                        Exportar PDF / Excel
                    </a>
                <?php endif; ?>

                <a href="<?php echo $url_podios; ?>"
                   class="<?php echo $btnNav; ?> ring-1 ring-amber-500/30"
                   title="Ver podios del torneo">
                    <i class="fas fa-medal text-amber-400 text-xl" aria-hidden="true"></i>
                    Podios
                </a>

                <hr class="border-amber-500/15 my-2" aria-hidden="true">

                <?php if ($mostrar_aviso_20min && $countdown_fin_timestamp): ?>
                    <div id="countdown-cierre-torneo" class="mb-1 rounded-xl border border-fuchsia-900/50 bg-[#2a0a24]/80 px-5 py-4">
                        <p class="text-base font-medium mb-1 text-fuchsia-200 sm:text-lg">
                            <i class="fas fa-clock text-amber-400/90 mr-1" aria-hidden="true"></i>
                            El torneo se cerrará oficialmente en:
                        </p>
                        <p class="countdown-tiempo-restante text-3xl font-bold tabular-nums text-fuchsia-100 sm:text-4xl" data-fin="<?php echo (int) $countdown_fin_timestamp; ?>">
                            --:--
                        </p>
                        <p class="text-sm mt-2 text-fuchsia-200/80">Tras este tiempo se habilitará el botón <strong>Finalizar torneo</strong>.</p>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo $use_standalone ? $base_url : 'index.php?page=torneo_gestion'; ?>"
                      onsubmit="event.preventDefault(); confirmarCierreTorneo(event);">
                    <input type="hidden" name="action" value="cerrar_torneo">
                    <input type="hidden" name="csrf_token" value="<?php echo CSRF::token(); ?>">
                    <input type="hidden" name="torneo_id" value="<?php echo $torneo['id']; ?>">
                    <button type="submit" <?php echo $puedeCerrar ? '' : 'disabled'; ?>
                            class="<?php echo $btnCierre; ?> w-full border-slate-600 <?php echo $lockedTorneo ? 'bg-slate-700 text-slate-300' : 'bg-slate-800 text-white border-amber-500/30 hover:bg-slate-700'; ?>">
                        <i class="fas fa-lock <?php echo $lockedTorneo ? 'text-slate-400' : 'text-amber-400'; ?> text-xl" aria-hidden="true"></i>
                        <?php echo $lockedTorneo ? 'Torneo finalizado' : 'Finalizar torneo'; ?>
                    </button>
                </form>

            </div>
        </div>
    </div>

</div>
