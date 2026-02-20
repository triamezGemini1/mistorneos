<?php
/**
 * Dashboard Desktop: información de la organización y estadísticas. Sin listado de torneos.
 * Entrada principal tras el login.
 */
declare(strict_types=1);
require_once __DIR__ . '/desktop_auth.php';
require_once __DIR__ . '/db_local.php';
require_once __DIR__ . '/../../desktop/core/db_bridge.php';

$pdo = DB_Local::pdo();
$entidad_id = DB::getEntidadId();
$organizacion_nombre = 'Su organización';
$total_torneos = 0;
$total_jugadores = 0;
$total_inscritos = 0;

try {
    if ($entidad_id > 0) {
        $has_entidad_table = (bool) $pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='entidad' LIMIT 1")->fetch();
        if ($has_entidad_table) {
            $stmt = $pdo->prepare("SELECT nombre FROM entidad WHERE codigo = ? OR id = ? LIMIT 1");
            $stmt->execute([$entidad_id, $entidad_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['nombre'])) {
                $organizacion_nombre = $row['nombre'];
            }
        }
    }

    $sqlT = "SELECT COUNT(*) FROM tournaments";
    $paramsT = [];
    if ($entidad_id > 0) {
        $sqlT = "SELECT COUNT(*) FROM tournaments WHERE entidad = ?";
        $paramsT = [$entidad_id];
    }
    $stmtT = $paramsT ? $pdo->prepare($sqlT) : $pdo->query($sqlT);
    if ($paramsT) $stmtT->execute($paramsT);
    $total_torneos = (int) $stmtT->fetchColumn();

    $sqlU = "SELECT COUNT(*) FROM usuarios WHERE (role = 'usuario' OR role = '' OR role IS NULL)";
    $paramsU = [];
    if ($entidad_id > 0) {
        $sqlU = "SELECT COUNT(*) FROM usuarios WHERE (role = 'usuario' OR role = '' OR role IS NULL) AND entidad = ?";
        $paramsU = [$entidad_id];
    }
    $stmtU = $paramsU ? $pdo->prepare($sqlU) : $pdo->query($sqlU);
    if ($paramsU) $stmtU->execute($paramsU);
    $total_jugadores = (int) $stmtU->fetchColumn();

    $sqlI = "SELECT COUNT(*) FROM inscritos";
    $paramsI = [];
    if ($entidad_id > 0) {
        $sqlI = "SELECT COUNT(*) FROM inscritos WHERE entidad_id = ?";
        $paramsI = [$entidad_id];
    }
    $stmtI = $paramsI ? $pdo->prepare($sqlI) : $pdo->query($sqlI);
    if ($paramsI) $stmtI->execute($paramsI);
    $total_inscritos = (int) $stmtI->fetchColumn();
} catch (Throwable $e) {
}

$pageTitle = 'Dashboard';
$desktopActive = 'dashboard';
require_once __DIR__ . '/desktop_layout.php';
?>
<div class="container-fluid py-3">
    <h2 class="h4 mb-2"><i class="fas fa-desktop text-primary me-2"></i>Dashboard</h2>
    <p class="text-muted mb-4">Información y estadísticas de la organización.</p>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-building me-2"></i>Organización</h5>
        </div>
        <div class="card-body">
            <p class="mb-0 fs-5"><?= htmlspecialchars($organizacion_nombre) ?></p>
            <?php if ($entidad_id > 0): ?>
            <small class="text-muted">Entidad ID: <?= (int) $entidad_id ?></small>
            <?php endif; ?>
        </div>
    </div>

    <h5 class="mb-3">Estadísticas</h5>
    <div class="row g-3">
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title text-muted small text-uppercase"><i class="fas fa-trophy me-2"></i>Torneos</h6>
                    <p class="mb-0 display-5"><?= $total_torneos ?></p>
                    <a href="torneos.php" class="btn btn-sm btn-outline-primary mt-2">Ver torneos</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title text-muted small text-uppercase"><i class="fas fa-users me-2"></i>Jugadores registrados</h6>
                    <p class="mb-0 display-5"><?= $total_jugadores ?></p>
                    <a href="registro_jugadores.php" class="btn btn-sm btn-outline-primary mt-2">Registro de jugador</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title text-muted small text-uppercase"><i class="fas fa-user-check me-2"></i>Total inscritos</h6>
                    <p class="mb-0 display-5"><?= $total_inscritos ?></p>
                    <small class="text-muted">En todos los torneos</small>
                </div>
            </div>
        </div>
    </div>
</div>
</main></body></html>
