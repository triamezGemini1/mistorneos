<?php
/**
 * Reporte de actividad (auditoría) — Solo Admin General.
 * Lista cronológica de acciones (registro de jugadores, cambio de estado de torneos).
 * Filtros: por organización y por administrador. Datos desde MySQL (sincronizados desde desktop).
 */
if (!defined('APP_BOOTSTRAPPED')) {
    require __DIR__ . '/../config/bootstrap.php';
}
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

Auth::requireRole(['admin_general']);
$current_user = Auth::user();

$filtro_org = isset($_GET['organizacion_id']) ? (int)$_GET['organizacion_id'] : null;
$filtro_admin = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : null;

$logs = [];
$organizaciones = [];
$admins = [];
$tableExists = false;

try {
    $pdo = DB::pdo();

    // Comprobar si la tabla auditoria existe (migración puede no estar aplicada)
    $tableExists = $pdo->query("SHOW TABLES LIKE 'auditoria'")->rowCount() > 0;

    if ($tableExists) {
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
            SELECT a.id, a.usuario_id, a.accion, a.detalle, a.entidad_tipo, a.entidad_id, a.organizacion_id, a.fecha,
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

        $organizaciones = $pdo->query("SELECT id, nombre FROM organizaciones ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
        $admins = $pdo->query("
            SELECT DISTINCT u.id, u.nombre, u.username
            FROM auditoria a
            JOIN usuarios u ON u.id = a.usuario_id
            ORDER BY COALESCE(NULLIF(TRIM(u.nombre), ''), u.username)
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $logs = [];
}

function formatoMensajeAuditoria(array $row): string {
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
    return "El administrador {$admin} realizó una acción (" . ($row['accion'] ?? '') . ") a las {$hora}.";
}

$page_title = 'Reporte de actividad (Auditoría)';
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><i class="fas fa-clipboard-list text-primary me-2"></i>Reporte de actividad</h1>
    <p class="text-muted">Lista cronológica de acciones sincronizadas desde el desktop. Consultable desde la web (p. ej. desde el celular).</p>

    <?php if (!$tableExists): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        La tabla de auditoría aún no existe en el servidor. Ejecuta la migración <code>sql/migrate_creado_por_fecha_creacion_auditoria.sql</code> en MySQL y sincroniza desde el desktop para ver los registros aquí.
    </div>
    <?php else: ?>

    <form method="get" action="<?= htmlspecialchars(AppHelpers::dashboard('auditoria')) ?>" class="row g-3 mb-4">
        <input type="hidden" name="page" value="auditoria">
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
            <a href="<?= htmlspecialchars(AppHelpers::dashboard('auditoria')) ?>" class="btn btn-outline-secondary btn-sm ms-2">Limpiar</a>
        </div>
    </form>

    <div class="card">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <strong>Últimas acciones</strong>
            <span class="badge bg-secondary"><?= count($logs) ?> registros</span>
        </div>
        <div class="list-group list-group-flush">
            <?php foreach ($logs as $row): ?>
            <div class="list-group-item">
                <span class="text-muted small me-2"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($row['fecha'] ?? ''))) ?></span>
                <?= htmlspecialchars(formatoMensajeAuditoria($row)) ?>
            </div>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?>
            <div class="list-group-item text-center text-muted py-4">No hay registros de auditoría. Sincroniza desde el desktop para subir los logs.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
