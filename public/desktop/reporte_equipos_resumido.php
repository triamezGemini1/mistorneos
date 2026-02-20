<?php
/**
 * Reporte de resultados por equipos - Resumido (Desktop).
 * Stub Enterprise White. Solo para torneos modalidad equipos. Recibe torneo_id por GET.
 */
declare(strict_types=1);
require_once __DIR__ . '/desktop_auth.php';
require_once __DIR__ . '/db_local.php';

$torneo_id = isset($_GET['torneo_id']) ? (int)$_GET['torneo_id'] : 0;
$pdo = DB_Local::pdo();
$torneo = null;
$torneos = [];
try {
    $torneos = $pdo->query("SELECT id, nombre, rondas, COALESCE(modalidad, 0) AS modalidad FROM tournaments ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    if ($torneo_id > 0) {
        $stmt = $pdo->prepare("SELECT id, nombre, rondas, COALESCE(modalidad, 0) AS modalidad FROM tournaments WHERE id = ?");
        $stmt->execute([$torneo_id]);
        $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
}

$pageTitle = 'Reporte equipos (resumido)';
$desktopActive = 'panel';
require_once __DIR__ . '/desktop_layout.php';
?>
<div class="container-fluid py-3">
    <h2 class="h4 mb-3"><i class="fas fa-list text-primary me-2"></i>Reporte equipos (resumido)</h2>
    <p class="text-muted">Resumen de posiciones y resultados por equipo. Solo aplica a torneos por equipos.</p>
    <div class="card border-0 shadow-sm" style="border-radius: 12px; border: 1px solid #E9ECEF;">
        <div class="card-body">
            <form method="get" action="reporte_equipos_resumido.php" class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Torneo</label>
                    <select name="torneo_id" class="form-select">
                        <option value="0">-- Seleccione torneo --</option>
                        <?php foreach ($torneos as $t): ?>
                        <option value="<?= (int)$t['id'] ?>" <?= $torneo_id === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nombre'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>Ver reporte</button>
                </div>
            </form>
            <?php if ($torneo): ?>
            <p class="mb-0 text-muted small"><strong><?= htmlspecialchars($torneo['nombre'] ?? '') ?></strong> - Rondas: <?= (int)($torneo['rondas'] ?? 0) ?>. Contenido del reporte resumido por equipos se cargará aquí.</p>
            <?php else: ?>
            <p class="text-muted small mb-0">Seleccione un torneo por equipos para ver el reporte resumido.</p>
            <?php endif; ?>
            <?php if ($torneo_id > 0): ?><a href="panel_torneo.php?torneo_id=<?= $torneo_id ?>" class="btn btn-outline-secondary btn-sm mt-3">Volver al Panel</a><?php else: ?><a href="torneos.php" class="btn btn-outline-secondary btn-sm mt-3">Torneos</a><?php endif; ?>
        </div>
    </div>
</div>
</main></body></html>
