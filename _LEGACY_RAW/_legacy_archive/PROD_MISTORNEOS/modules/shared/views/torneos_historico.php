<?php
/**
 * Vista parcial: Grid de torneos realizados (histórico).
 * Incluido por public/torneos_historico.php
 * Variables esperadas: $eventos, $tipos_evento, $clases, $modalidades
 */
if (!isset($eventos)) $eventos = [];
if (!isset($tipos_evento)) $tipos_evento = ['' => 'Todos', 1 => 'Nacional', 2 => 'Regional', 3 => 'Local', 4 => 'Privado'];
if (!isset($clases)) $clases = [1 => 'Torneo', 2 => 'Campeonato'];
if (!isset($modalidades)) $modalidades = [1 => 'Individual', 2 => 'Parejas', 3 => 'Equipos'];
?>
<?php if (empty($eventos)): ?>
    <div class="text-center py-5">
        <i class="fas fa-history fa-4x text-muted mb-3"></i>
        <h4>No hay torneos realizados</h4>
        <p class="text-muted">No se encontraron eventos con los filtros seleccionados.</p>
        <a href="torneos_historico.php" class="btn btn-primary">Ver todos</a>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($eventos as $ev): ?>
            <div class="col-md-6 col-lg-4">
                <div class="torneo-card">
                    <div class="torneo-card-header">
                        <h5 class="mb-1 fw-bold"><?= htmlspecialchars($ev['nombre']) ?></h5>
                        <div class="d-flex flex-wrap gap-2 small">
                            <span><i class="fas fa-calendar me-1"></i><?= date('d/m/Y', strtotime($ev['fechator'])) ?></span>
                            <span><i class="fas fa-building me-1"></i><?= htmlspecialchars($ev['organizacion_nombre'] ?? 'Organizador') ?></span>
                        </div>
                        <div class="mt-2">
                            <span class="badge bg-light text-dark me-1"><?= $clases[$ev['clase'] ?? 1] ?? 'Torneo' ?></span>
                            <span class="badge bg-light text-dark me-1"><?= $modalidades[$ev['modalidad'] ?? 1] ?? 'Individual' ?></span>
                            <?php $em = (int)($ev['es_evento_masivo'] ?? 0); if ($em > 0 && isset($tipos_evento[$em])): ?>
                                <span class="badge bg-info"><?= htmlspecialchars($tipos_evento[$em]) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="p-3">
                        <?php if (!empty($ev['podio'])): ?>
                            <h6 class="fw-bold mb-2"><i class="fas fa-trophy text-warning me-1"></i>Podio</h6>
                            <?php foreach ($ev['podio'] as $p): 
                                $pos = (int)$p['posicion_display'];
                                $medal_icon = $pos === 1 ? 'fa-trophy' : 'fa-medal';
                                $medal_class = $pos === 1 ? 'text-warning' : ($pos === 2 ? 'text-secondary' : 'text-danger');
                            ?>
                                <div class="podio-item">
                                    <span class="podio-pos podio-<?= $pos ?> me-2">
                                        <i class="fas <?= $medal_icon ?> podio-medal <?= $medal_class ?>"></i>
                                        <?= $pos ?>°
                                    </span>
                                    <div>
                                        <strong><?= htmlspecialchars($p['nombre']) ?></strong>
                                        <?php if (!empty($p['club_nombre'])): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($p['club_nombre']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted small mb-0"><i class="fas fa-info-circle me-1"></i>Resultados pendientes de cargar</p>
                        <?php endif; ?>
                        <div class="mt-3 pt-3 border-top">
                            <a href="<?= htmlspecialchars(UrlHelper::resultadosUrl($ev['id'], $ev['nombre'])) ?>" 
                               class="btn btn-resultados w-100">
                                <i class="fas fa-chart-bar me-2"></i>Ver Resultados Completos
                            </a>
                            <a href="torneo_detalle.php?torneo_id=<?= (int)$ev['id'] ?>" class="btn btn-outline-secondary w-100 mt-2">
                                <i class="fas fa-info-circle me-2"></i>Ver Detalles
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
