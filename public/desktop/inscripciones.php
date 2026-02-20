<?php
/**
 * Administración de inscripciones (Desktop).
 * Igual que web: listado con estatus (disponibles, inscritos, confirmados, retirados, pendientes),
 * estadísticas, acciones Confirmar / Retirar.
 */
declare(strict_types=1);
require_once __DIR__ . '/desktop_auth.php';
require_once __DIR__ . '/db_local.php';
require_once __DIR__ . '/../../desktop/core/db_bridge.php';
require_once __DIR__ . '/../../desktop/core/InscritosHelper.php';

$pdo = DB_Local::pdo();
$entidad_id = DB::getEntidadId();
$torneo_id_get = isset($_GET['torneo_id']) ? (int)$_GET['torneo_id'] : 0;

if ($torneo_id_get <= 0) {
    header('Location: torneos.php');
    exit;
}

$torneo = null;
$inscritos = [];
$total_inscritos = 0;
$confirmados = 0;
$pendientes = 0;
$retirados = 0;
$hombres = 0;
$mujeres = 0;
$torneo_iniciado = false;
$error = '';
$success = '';

// POST: cambiar estatus (Confirmar / Retirar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cambiar_estatus_inscrito') {
    $inscripcion_id = (int)($_POST['inscripcion_id'] ?? 0);
    $estatus = (int)($_POST['estatus'] ?? 0);
    $torneo_id_post = (int)($_POST['torneo_id'] ?? 0);
    if ($inscripcion_id > 0 && $torneo_id_post === $torneo_id_get && in_array($estatus, [1, 4], true)) {
        try {
            $stmt = $pdo->prepare("UPDATE inscritos SET estatus = ? WHERE id = ? AND torneo_id = ?");
            $stmt->execute([$estatus, $inscripcion_id, $torneo_id_get]);
            $success = $estatus === 1 ? 'Inscripción confirmada.' : 'Jugador retirado del torneo.';
        } catch (Throwable $e) {
            $error = 'Error al actualizar: ' . $e->getMessage();
        }
    }
}

try {
    $stmt = $pdo->prepare("SELECT id, nombre, fechator, estatus, rondas, COALESCE(modalidad, 0) AS modalidad FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id_get]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$torneo) {
        header('Location: torneos.php');
        exit;
    }

    $tabla_partiresul = (bool) $pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='partiresul' LIMIT 1")->fetch();
    $ultima_ronda = 0;
    if ($tabla_partiresul) {
        $st = $pdo->prepare("SELECT COALESCE(MAX(partida), 0) FROM partiresul WHERE id_torneo = ?");
        $st->execute([$torneo_id_get]);
        $ultima_ronda = (int)$st->fetchColumn();
    }
    $modalidad = (int)($torneo['modalidad'] ?? 0);
    $es_equipos = ($modalidad === 3);
    $torneo_iniciado = $es_equipos ? ($ultima_ronda >= 1) : ($ultima_ronda >= 2);

    $sql = "SELECT i.id, i.id_usuario, i.torneo_id, i.estatus, i.fecha_inscripcion, i.id_club,
            u.nombre AS nombre_completo, u.username, u.sexo
            FROM inscritos i
            INNER JOIN usuarios u ON i.id_usuario = u.id
            WHERE i.torneo_id = ?";
    $params = [$torneo_id_get];
    if ($entidad_id > 0) {
        $sql .= " AND i.entidad_id = ?";
        $params[] = $entidad_id;
    }
    $sql .= " ORDER BY u.nombre ASC, u.username ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $inscritos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_inscritos = count($inscritos);
    foreach ($inscritos as $i) {
        $e = is_numeric($i['estatus']) ? (int)$i['estatus'] : InscritosHelper::getEstatusNumero((string)$i['estatus']);
        if ($e === 1 || $e === 2 || $e === 3) $confirmados++;
        elseif ($e === 0) $pendientes++;
        elseif ($e === 4) $retirados++;
        $sexo = $i['sexo'] ?? '';
        if ($sexo === 'M' || $sexo === '1') $hombres++;
        elseif ($sexo === 'F' || $sexo === '2') $mujeres++;
    }

    $inscritos = InscritosHelper::agregarEstatusTexto($inscritos);
} catch (Throwable $e) {
    $error = $error ?: ('Error al cargar: ' . $e->getMessage());
}

$pageTitle = 'Administración de inscripciones';
$desktopActive = 'torneos';
require_once __DIR__ . '/desktop_layout.php';
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h2 class="h4 mb-1"><i class="fas fa-clipboard-list text-primary me-2"></i>Administración de inscripciones</h2>
            <p class="text-muted mb-0 small"><?= htmlspecialchars($torneo['nombre'] ?? '') ?></p>
        </div>
        <div>
            <a href="panel_torneo.php?torneo_id=<?= $torneo_id_get ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Panel</a>
            <?php if (!$torneo_iniciado): ?>
            <a href="inscribir.php?torneo_id=<?= $torneo_id_get ?>" class="btn btn-success btn-sm"><i class="fas fa-user-plus me-1"></i>Inscribir en sitio</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Estadísticas -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Total inscritos</div>
                    <div class="fs-4 fw-bold"><?= $total_inscritos ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Confirmados</div>
                    <div class="fs-4 fw-bold text-success"><?= $confirmados ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Pendientes</div>
                    <div class="fs-4 fw-bold text-warning"><?= $pendientes ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Retirados</div>
                    <div class="fs-4 fw-bold text-dark"><?= $retirados ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Hombres</div>
                    <div class="fs-4 fw-bold text-info"><?= $hombres ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Mujeres</div>
                    <div class="fs-4 fw-bold text-secondary"><?= $mujeres ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$torneo_iniciado): ?>
    <p class="mb-3"><a href="inscribir.php?torneo_id=<?= $torneo_id_get ?>" class="btn btn-primary"><i class="fas fa-user-plus me-1"></i>Inscribir nuevo jugador</a></p>
    <?php else: ?>
    <div class="alert alert-info mb-3">El torneo ya ha iniciado. No se pueden agregar nuevos jugadores.</div>
    <?php endif; ?>

    <!-- Listado de inscritos -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Listado de inscritos (<?= $total_inscritos ?>)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($inscritos)): ?>
            <div class="p-4 text-center text-muted">
                No hay inscritos. <?php if (!$torneo_iniciado): ?>Use <strong>Inscribir en sitio</strong> para agregar jugadores.<?php endif; ?>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Jugador</th>
                            <th>Username</th>
                            <th>Género</th>
                            <th>Estado</th>
                            <?php if (!$torneo_iniciado): ?><th class="text-end">Acciones</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $n = 1;
                        foreach ($inscritos as $i):
                            $estatus_num = is_numeric($i['estatus']) ? (int)$i['estatus'] : InscritosHelper::getEstatusNumero((string)$i['estatus']);
                            $es_confirmado = ($estatus_num === 1 || $estatus_num === 2 || $estatus_num === 3);
                            $es_retirado = ($estatus_num === 4);
                        ?>
                        <tr>
                            <td><?= $n++ ?></td>
                            <td><strong><?= htmlspecialchars($i['nombre_completo'] ?? $i['username'] ?? '') ?></strong></td>
                            <td><?= htmlspecialchars($i['username'] ?? '-') ?></td>
                            <td>
                                <?php
                                $s = $i['sexo'] ?? '';
                                if ($s === 'M' || $s === '1') echo '<span class="badge bg-info">M</span>';
                                elseif ($s === 'F' || $s === '2') echo '<span class="badge bg-secondary">F</span>';
                                else echo '<span class="badge bg-light text-dark">-</span>';
                                ?>
                            </td>
                            <td><span class="badge <?= $i['estatus_clase'] ?? 'bg-secondary' ?>"><?= htmlspecialchars($i['estatus_formateado'] ?? $i['estatus_texto'] ?? $i['estatus']) ?></span></td>
                            <?php if (!$torneo_iniciado): ?>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <?php if (!$es_confirmado && !$es_retirado): ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="cambiar_estatus_inscrito">
                                        <input type="hidden" name="torneo_id" value="<?= $torneo_id_get ?>">
                                        <input type="hidden" name="inscripcion_id" value="<?= (int)$i['id'] ?>">
                                        <input type="hidden" name="estatus" value="1">
                                        <button type="submit" class="btn btn-success btn-sm">Confirmar</button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if (!$es_retirado): ?>
                                    <form method="post" class="d-inline" onsubmit="return confirm('¿Retirar a este jugador del torneo?');">
                                        <input type="hidden" name="action" value="cambiar_estatus_inscrito">
                                        <input type="hidden" name="torneo_id" value="<?= $torneo_id_get ?>">
                                        <input type="hidden" name="inscripcion_id" value="<?= (int)$i['id'] ?>">
                                        <input type="hidden" name="estatus" value="4">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Retirar</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <div class="card-footer bg-light">
            <a href="panel_torneo.php?torneo_id=<?= $torneo_id_get ?>" class="btn btn-outline-secondary btn-sm">Volver al panel</a>
            <a href="torneos.php" class="btn btn-outline-secondary btn-sm ms-2">Torneos</a>
        </div>
    </div>
</div>
</main></body></html>
