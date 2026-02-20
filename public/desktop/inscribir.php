<?php
/**
 * Inscripción en sitio (Desktop).
 * Un clic en Disponibles = inscribir y confirmar (pasa a Inscritos).
 * Un clic en Inscritos = retirar (pasa a Disponibles). Sin formulario extra.
 */
declare(strict_types=1);
require_once __DIR__ . '/desktop_auth.php';
require_once __DIR__ . '/db_local.php';
require_once __DIR__ . '/../../desktop/core/db_bridge.php';
require_once __DIR__ . '/../../desktop/core/InscritosHelper.php';

$pdo = DB_Local::pdo();
$entidad_id = DB::getEntidadId();
$torneo_id_get = isset($_GET['torneo_id']) ? (int)$_GET['torneo_id'] : 0;

$torneo = null;
$usuarios_disponibles = [];
$usuarios_inscritos = [];
$torneo_estado_ok = false;
$error = '';
$success = '';
if (isset($_GET['success'])) {
    $success = $_GET['success'] === 'inscrito' ? 'Jugador inscrito y confirmado.' : ($_GET['success'] === 'retirado' ? 'Jugador retirado del torneo.' : '');
}
if (isset($_GET['error']) && $_GET['error'] === 'ya_inscrito') {
    $error = 'Ese jugador ya está inscrito.';
}

if ($torneo_id_get <= 0) {
    header('Location: torneos.php');
    exit;
}

// POST: inscribir (desde Disponibles) → confirmado; o retirar (desde Inscritos)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $torneo_id = (int)($_POST['torneo_id'] ?? 0);
    $id_usuario = (int)($_POST['id_usuario'] ?? 0);
    $redirect = 'inscribir.php?torneo_id=' . $torneo_id_get;

    if (!empty($_POST['inscribir']) && $torneo_id === $torneo_id_get && $id_usuario > 0) {
        try {
            $stmt = $pdo->prepare("SELECT id, estatus FROM inscritos WHERE torneo_id = ? AND id_usuario = ? LIMIT 1");
            $stmt->execute([$torneo_id, $id_usuario]);
            $existe = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existe) {
                $est = is_numeric($existe['estatus']) ? (int)$existe['estatus'] : 4;
                if ($est !== 4) {
                    header('Location: ' . $redirect . '&error=ya_inscrito');
                    exit;
                }
                $pdo->prepare("UPDATE inscritos SET estatus = 1, fecha_inscripcion = datetime('now') WHERE id = ?")->execute([$existe['id']]);
            } else {
                $eid = DB::getEntidadId();
                $stmt = $pdo->prepare("INSERT INTO inscritos (id_usuario, torneo_id, estatus, entidad_id, fecha_inscripcion) VALUES (?, ?, 1, ?, datetime('now'))");
                $stmt->execute([$id_usuario, $torneo_id, $eid]);
            }
            header('Location: ' . $redirect . '&success=inscrito');
            exit;
        } catch (Throwable $e) {
            $error = 'Error al inscribir: ' . $e->getMessage();
        }
    } elseif (!empty($_POST['retirar']) && $torneo_id === $torneo_id_get && $id_usuario > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE inscritos SET estatus = 4 WHERE torneo_id = ? AND id_usuario = ?");
            $stmt->execute([$torneo_id, $id_usuario]);
            header('Location: ' . $redirect . '&success=retirado');
            exit;
        } catch (Throwable $e) {
            $error = 'Error al retirar: ' . $e->getMessage();
        }
    }
}

try {
    $stmt = $pdo->prepare("SELECT id, nombre, fechator, rondas, COALESCE(modalidad, 0) AS modalidad FROM tournaments WHERE id = ?");
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
    $torneo_estado_ok = $es_equipos ? ($ultima_ronda < 1) : ($ultima_ronda < 2);

    $sqlU = "SELECT u.id, u.nombre, u.username, u.cedula FROM usuarios u WHERE (u.role = 'usuario' OR u.role = '' OR u.role IS NULL)";
    $paramsU = [];
    if ($entidad_id > 0) {
        $sqlU .= " AND u.entidad = ?";
        $paramsU[] = $entidad_id;
    }
    $sqlU .= " ORDER BY u.nombre ASC, u.username ASC";
    $stmtU = $paramsU ? $pdo->prepare($sqlU) : $pdo->query($sqlU);
    if ($paramsU) $stmtU->execute($paramsU);
    $todos_usuarios = ($paramsU ? $stmtU->fetchAll(PDO::FETCH_ASSOC) : $stmtU->fetchAll(PDO::FETCH_ASSOC));

    $stmtI = $pdo->prepare("SELECT id_usuario FROM inscritos WHERE torneo_id = ? AND (estatus != 4 AND estatus != 'retirado')");
    $stmtI->execute([$torneo_id_get]);
    $ids_inscritos = array_column($stmtI->fetchAll(PDO::FETCH_ASSOC), 'id_usuario');

    $usuarios_disponibles = array_filter($todos_usuarios, function ($u) use ($ids_inscritos) {
        return !in_array((int)$u['id'], $ids_inscritos, true);
    });
    $usuarios_disponibles = array_values($usuarios_disponibles);

    $sqlI2 = "SELECT i.id_usuario, i.estatus, u.id, u.nombre, u.username, u.cedula
              FROM inscritos i
              INNER JOIN usuarios u ON i.id_usuario = u.id
              WHERE i.torneo_id = ? AND i.estatus != 4";
    $paramsI2 = [$torneo_id_get];
    if ($entidad_id > 0) {
        $sqlI2 .= " AND i.entidad_id = ?";
        $paramsI2[] = $entidad_id;
    }
    $sqlI2 .= " ORDER BY u.nombre ASC";
    $stmtI2 = $pdo->prepare($sqlI2);
    $stmtI2->execute($paramsI2);
    $usuarios_inscritos = $stmtI2->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = $error ?: ('Error al cargar: ' . $e->getMessage());
}

$pageTitle = 'Inscribir en sitio';
$desktopActive = 'torneos';
require_once __DIR__ . '/desktop_layout.php';
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h2 class="h4 mb-1"><i class="fas fa-user-plus text-primary me-2"></i>Inscribir en sitio</h2>
            <p class="text-muted mb-0 small"><?= htmlspecialchars($torneo['nombre'] ?? '') ?></p>
        </div>
        <div>
            <a href="inscripciones.php?torneo_id=<?= $torneo_id_get ?>" class="btn btn-outline-primary btn-sm">Gestionar inscripciones</a>
            <a href="panel_torneo.php?torneo_id=<?= $torneo_id_get ?>" class="btn btn-outline-secondary btn-sm">Panel</a>
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

    <?php if (!$torneo_estado_ok): ?>
    <div class="alert alert-warning">Las inscripciones para este torneo están cerradas (ronda ya iniciada).</div>
    <?php endif; ?>

    <div class="row">
        <!-- Disponibles -->
        <div class="col-md-6 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="fas fa-list me-2"></i>Atletas disponibles <span class="badge bg-light text-dark ms-2"><?= count($usuarios_disponibles) ?></span></h6>
                </div>
                <div class="card-body p-0" style="max-height: 420px; overflow-y: auto;">
                    <?php if (empty($usuarios_disponibles)): ?>
                    <p class="p-3 text-muted mb-0 small">No hay jugadores disponibles para inscribir (todos están inscritos o no hay usuarios de la entidad).</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light"><tr><th>Nombre</th><th>Usuario</th><th>Cédula</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($usuarios_disponibles as $u):
                                    $nombre = $u['nombre'] ?? $u['username'] ?? '';
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($nombre) ?></strong></td>
                                    <td><code><?= htmlspecialchars($u['username'] ?? '') ?></code></td>
                                    <td><?= htmlspecialchars($u['cedula'] ?? '') ?></td>
                                    <td class="text-end">
                                        <?php if ($torneo_estado_ok): ?>
                                        <form method="post" action="inscribir.php?torneo_id=<?= $torneo_id_get ?>" class="d-inline">
                                            <input type="hidden" name="inscribir" value="1">
                                            <input type="hidden" name="torneo_id" value="<?= $torneo_id_get ?>">
                                            <input type="hidden" name="id_usuario" value="<?= (int)$u['id'] ?>">
                                            <button type="submit" class="btn btn-success btn-sm">Inscribir</button>
                                        </form>
                                        <?php endif; ?>
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

        <!-- Inscritos -->
        <div class="col-md-6 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="fas fa-check-circle me-2"></i>Atletas inscritos <span class="badge bg-light text-dark ms-2"><?= count($usuarios_inscritos) ?></span></h6>
                </div>
                <div class="card-body p-0" style="max-height: 420px; overflow-y: auto;">
                    <?php if (empty($usuarios_inscritos)): ?>
                    <p class="p-3 text-muted mb-0 small">Aún no hay inscritos en este torneo.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light"><tr><th>Nombre</th><th>Usuario</th><th>Estado</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($usuarios_inscritos as $i):
                                    $est = is_numeric($i['estatus']) ? (int)$i['estatus'] : InscritosHelper::getEstatusNumero((string)$i['estatus']);
                                    $est_label = InscritosHelper::getEstatusFormateado($est);
                                    $est_class = InscritosHelper::getEstatusClaseCSS($est);
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($i['nombre'] ?? $i['username'] ?? '') ?></strong></td>
                                    <td><code><?= htmlspecialchars($i['username'] ?? '') ?></code></td>
                                    <td><span class="badge <?= $est_class ?>"><?= htmlspecialchars($est_label) ?></span></td>
                                    <td class="text-end">
                                        <?php if ($torneo_estado_ok): ?>
                                        <form method="post" action="inscribir.php?torneo_id=<?= $torneo_id_get ?>" class="d-inline" onsubmit="return confirm('¿Retirar a este jugador del torneo?');">
                                            <input type="hidden" name="retirar" value="1">
                                            <input type="hidden" name="torneo_id" value="<?= $torneo_id_get ?>">
                                            <input type="hidden" name="id_usuario" value="<?= (int)$i['id_usuario'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Retirar</button>
                                        </form>
                                        <?php endif; ?>
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

    <p class="mt-3">
        <a href="inscripciones.php?torneo_id=<?= $torneo_id_get ?>" class="btn btn-outline-secondary btn-sm">Gestionar inscripciones</a>
        <a href="panel_torneo.php?torneo_id=<?= $torneo_id_get ?>" class="btn btn-outline-secondary btn-sm">Volver al panel</a>
        <a href="torneos.php" class="btn btn-outline-secondary btn-sm">Torneos</a>
    </p>
</div>
</main></body></html>
