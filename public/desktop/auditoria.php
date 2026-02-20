<?php
/**
 * Panel de Auditoría: lista cronológica de acciones (registro de jugadores, cambio de estado de torneos).
 * Filtros: por organización y por administrador. Los registros se sincronizan a la web.
 */
declare(strict_types=1);
require_once __DIR__ . '/desktop_auth.php';
require_once __DIR__ . '/db_local.php';

$pdo = DB_Local::pdo();

$filtro_org = isset($_GET['organizacion_id']) ? (int)$_GET['organizacion_id'] : null;
$filtro_admin = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : null;

$where = ['1=1'];
$params = [];
if ($filtro_org > 0) {
    $where[] = 'a.organizacion_id = ?';
    $params[] = $filtro_org;
}
if ($filtro_admin > 0) {
    $where[] = 'a.usuario_id = ?';
    $params[] = $filtro_admin;
}
$sql = "
    SELECT a.id, a.usuario_id, a.accion, a.detalle, a.entidad_tipo, a.entidad_id, a.organizacion_id, a.fecha, a.sync_status,
           u.nombre AS admin_nombre, u.username AS admin_username
    FROM auditoria a
    LEFT JOIN usuarios u ON u.id = a.usuario_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY a.fecha DESC
    LIMIT 500
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$organizaciones = [];
$admins = [];
try {
    $organizaciones = $pdo->query("SELECT id, nombre FROM organizaciones ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    $admins = $pdo->query("
        SELECT DISTINCT u.id, u.nombre, u.username
        FROM auditoria a
        JOIN usuarios u ON u.id = a.usuario_id
        ORDER BY COALESCE(NULLIF(TRIM(u.nombre), ''), u.username)
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
}

function formatoMensaje(array $row): string {
    $admin = trim($row['admin_nombre'] ?? '') !== '' ? $row['admin_nombre'] : ($row['admin_username'] ?? 'N/A');
    $hora = date('d/m/Y H:i', strtotime($row['fecha'] ?? 'now'));
    if (($row['accion'] ?? '') === 'registro_jugador') {
        $jugador = $row['detalle'] ?? 'Jugador';
        return "El administrador {$admin} registró al jugador {$jugador} a las {$hora}.";
    }
    if (($row['accion'] ?? '') === 'modifico_estado_torneo') {
        $torneo = $row['detalle'] ?? 'Torneo';
        return "El administrador {$admin} modificó el estado del torneo {$torneo} a las {$hora}.";
    }
    return "El administrador {$admin} realizó una acción ({$row['accion']}) a las {$hora}.";
}

$pageTitle = 'Auditoría';
$desktopActive = 'auditoria';
require_once __DIR__ . '/desktop_layout.php';
?>
<div class="container-fluid py-3">
    <h2 class="h4 mb-3"><i class="fas fa-clipboard-list text-primary me-2"></i>Panel de Auditoría</h2>
    <p class="text-muted">Lista cronológica de las últimas acciones. Filtra por organización o por administrador.</p>

    <form method="get" action="auditoria.php" class="row g-3 mb-4">
        <div class="col-auto">
            <label class="form-label mb-0">Organización</label>
            <select name="organizacion_id" class="form-select form-select-sm">
                <option value="">Todas</option>
                <?php foreach ($organizaciones as $o): ?>
                <option value="<?= (int)$o['id'] ?>" <?= $filtro_org === (int)$o['id'] ? 'selected' : '' ?>><?= htmlspecialchars($o['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label mb-0">Administrador</label>
            <select name="usuario_id" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($admins as $a): ?>
                <option value="<?= (int)$a['id'] ?>" <?= $filtro_admin === (int)$a['id'] ? 'selected' : '' ?>><?= htmlspecialchars(trim($a['nombre'] ?? '') !== '' ? $a['nombre'] : $a['username']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto d-flex align-items-end">
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
            <a href="auditoria.php" class="btn btn-outline-secondary btn-sm ms-2">Limpiar</a>
        </div>
    </form>

    <div class="card">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <strong>Últimas acciones</strong>
            <span class="badge bg-secondary"><?= count($logs) ?> registros</span>
        </div>
        <div class="list-group list-group-flush">
            <?php foreach ($logs as $row): ?>
            <div class="list-group-item d-flex justify-content-between align-items-start">
                <div>
                    <span class="text-muted small me-2"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($row['fecha'] ?? ''))) ?></span>
                    <?= htmlspecialchars(formatoMensaje($row)) ?>
                </div>
                <?php if (!empty($row['sync_status'])): ?>
                <span class="badge bg-success" title="Sincronizado a la web">Web</span>
                <?php else: ?>
                <span class="badge bg-warning text-dark" title="Pendiente de subir">Pendiente</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?>
            <div class="list-group-item text-center text-muted py-4">No hay registros de auditoría.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
</main></body></html>
