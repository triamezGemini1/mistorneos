<?php
/**
 * Torneos Desktop: listado de torneos de la entidad. Crear si no hay; desde cada uno acceder al panel.
 */
declare(strict_types=1);
require_once __DIR__ . '/desktop_auth.php';
require_once __DIR__ . '/db_local.php';
require_once __DIR__ . '/../../desktop/core/db_bridge.php';

$pdo = DB_Local::pdo();
$torneos = [];
$inscritos_por_torneo = [];
$entidad_id = DB::getEntidadId();

try {
    $sql = "SELECT id, nombre, fechator, estatus, rondas, COALESCE(modalidad, 0) AS modalidad, entidad
            FROM tournaments
            ORDER BY id DESC";
    $params = [];
    if ($entidad_id > 0) {
        $sql = "SELECT id, nombre, fechator, estatus, rondas, COALESCE(modalidad, 0) AS modalidad, entidad
                FROM tournaments
                WHERE entidad = ?
                ORDER BY id DESC";
        $params = [$entidad_id];
    }
    $stmt = $params ? $pdo->prepare($sql) : $pdo->query($sql);
    if ($params) $stmt->execute($params);
    $torneos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($torneos as $t) {
        $tid = (int)$t['id'];
        $st = $pdo->prepare("SELECT COUNT(*) FROM inscritos WHERE torneo_id = ?");
        $st->execute([$tid]);
        $inscritos_por_torneo[$tid] = (int)$st->fetchColumn();
    }
} catch (Throwable $e) {
}

$pageTitle = 'Torneos';
$desktopActive = 'torneos';
require_once __DIR__ . '/desktop_layout.php';
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
        <div>
            <h2 class="h4 mb-1"><i class="fas fa-trophy text-primary me-2"></i>Torneos</h2>
            <p class="text-muted mb-0 small">Listado de torneos<?= $entidad_id > 0 ? ' de su entidad' : '' ?>. Desde aquí acceda al panel de control de cada torneo.</p>
        </div>
        <a href="crear_torneo.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Crear torneo</a>
    </div>

    <div class="row mb-4">
        <div class="col-md-6 col-lg-4 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title text-muted small"><i class="fas fa-list me-2"></i>Torneos</h5>
                    <p class="mb-0 display-6"><?= count($torneos) ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title text-muted small"><i class="fas fa-users me-2"></i>Total inscritos</h5>
                    <p class="mb-0 display-6"><?= array_sum($inscritos_por_torneo) ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Listado de torneos</h5>
            <a href="crear_torneo.php" class="btn btn-sm btn-outline-primary">Crear torneo</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Fecha</th>
                            <th>Rondas</th>
                            <th>Modalidad</th>
                            <th>Inscritos</th>
                            <th>Estatus</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($torneos as $t):
                            $tid = (int)$t['id'];
                            $activo = (int)($t['estatus'] ?? 0) === 1;
                            $modalidad = (int)($t['modalidad'] ?? 0);
                            $modalidadTexto = $modalidad === 3 ? 'Equipos' : ($modalidad === 2 ? 'Parejas' : 'Individual');
                        ?>
                        <tr>
                            <td data-label="ID"><?= $tid ?></td>
                            <td data-label="Nombre"><?= htmlspecialchars($t['nombre'] ?? '') ?></td>
                            <td data-label="Fecha"><?= htmlspecialchars($t['fechator'] ?? '') ?></td>
                            <td data-label="Rondas"><?= (int)($t['rondas'] ?? 0) ?></td>
                            <td data-label="Modalidad"><?= $modalidadTexto ?></td>
                            <td data-label="Inscritos"><?= $inscritos_por_torneo[$tid] ?? 0 ?></td>
                            <td data-label="Estatus">
                                <span class="badge bg-<?= $activo ? 'success' : 'secondary' ?>"><?= $activo ? 'Activo' : 'Inactivo' ?></span>
                            </td>
                            <td class="text-end" data-label="Acciones">
                                <a href="panel_torneo.php?torneo_id=<?= $tid ?>" class="btn btn-sm btn-outline-primary me-1"><i class="fas fa-cog me-1"></i>Panel</a>
                                <a href="inscripciones.php?torneo_id=<?= $tid ?>" class="btn btn-sm btn-outline-secondary me-1"><i class="fas fa-user-plus me-1"></i>Inscribir</a>
                                <a href="posiciones.php?torneo_id=<?= $tid ?>" class="btn btn-sm btn-outline-info"><i class="fas fa-list-ol me-1"></i>Posiciones</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($torneos)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <p class="text-muted mb-2">No hay torneos creados.</p>
                                <a href="crear_torneo.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Crear primer torneo</a>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</main></body></html>
