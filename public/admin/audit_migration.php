<?php
declare(strict_types=1);

// Desde public/admin/ la config está dos niveles arriba.
require_once __DIR__ . '/../../config/session_start_early.php';
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

/**
 * Dry-run sin login: solo loopback o host localhost (WAMP puede usar HTTP_HOST con puerto).
 * Opcional: .env AUDIT_MIGRATION_TOKEN=secreto y ?token=secreto
 */
$remoteAddr = (string)($_SERVER['REMOTE_ADDR'] ?? '');
if (str_starts_with($remoteAddr, '::ffff:')) {
    $remoteAddr = substr($remoteAddr, 7);
}
$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$host = preg_replace('/:\d+$/', '', $host);
$serverName = strtolower((string)($_SERVER['SERVER_NAME'] ?? ''));

$tokenOk = false;
if (class_exists('Env')) {
    $expected = trim((string)Env::get('AUDIT_MIGRATION_TOKEN', ''));
    $got = (string)($_GET['token'] ?? '');
    if ($expected !== '' && hash_equals($expected, $got)) {
        $tokenOk = true;
    }
}

$isLocalRequest = $tokenOk
    || in_array($remoteAddr, ['127.0.0.1', '::1'], true)
    || in_array($host, ['localhost', '127.0.0.1'], true)
    || in_array($serverName, ['localhost', '127.0.0.1'], true);

if (!$isLocalRequest) {
    Auth::requireRole(['admin_general']);
}

header('Content-Type: text/html; charset=utf-8');

/**
 * Devuelve cantidad de filas de una tabla o null si no existe.
 */
function tableCount(PDO $pdo, string $table): ?int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?"
    );
    $stmt->execute([$table]);
    if ((int)$stmt->fetchColumn() === 0) {
        return null;
    }

    $sql = sprintf("SELECT COUNT(*) FROM `%s`", str_replace('`', '``', $table));
    return (int)$pdo->query($sql)->fetchColumn();
}

/**
 * information_schema puede devolver claves en mayúsculas (TABLE_NAME) según driver/MySQL.
 */
function infoSchemaNormalizeRows(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        $norm = [];
        foreach ($row as $k => $v) {
            $norm[strtolower((string)$k)] = $v;
        }
        $out[] = $norm;
    }
    return $out;
}

try {
    $pdo = DB::pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $torneosTable = null;
    if (tableCount($pdo, 'torneos') !== null) {
        $torneosTable = 'torneos';
    } elseif (tableCount($pdo, 'tournaments') !== null) {
        $torneosTable = 'tournaments';
    }

    $countByTable = [
        'torneos' => $torneosTable ? tableCount($pdo, $torneosTable) : null,
        'partiresul' => tableCount($pdo, 'partiresul'),
        'inscritos' => tableCount($pdo, 'inscritos'),
        'clubes_atletas' => tableCount($pdo, 'clubes_atletas'),
    ];

    $clubStats = [
        'total' => 0,
        'min_id' => null,
        'max_id' => null,
        'count_ge_40' => 0,
        'future_min' => null,
        'future_max' => null,
    ];

    if (tableCount($pdo, 'clubes') !== null) {
        $row = $pdo->query(
            "SELECT
                COUNT(*) AS total,
                MIN(id) AS min_id,
                MAX(id) AS max_id,
                SUM(CASE WHEN id >= 40 THEN 1 ELSE 0 END) AS count_ge_40
             FROM clubes"
        )->fetch(PDO::FETCH_ASSOC);

        $clubStats['total'] = (int)($row['total'] ?? 0);
        $clubStats['min_id'] = $row['min_id'] !== null ? (int)$row['min_id'] : null;
        $clubStats['max_id'] = $row['max_id'] !== null ? (int)$row['max_id'] : null;
        $clubStats['count_ge_40'] = (int)($row['count_ge_40'] ?? 0);
        $clubStats['future_min'] = $clubStats['min_id'] !== null ? $clubStats['min_id'] + 40 : null;
        $clubStats['future_max'] = $clubStats['max_id'] !== null ? $clubStats['max_id'] + 40 : null;
    }

    $clubSample = [];
    if (tableCount($pdo, 'clubes') !== null) {
        $stmt = $pdo->query("SELECT id, nombre FROM clubes ORDER BY id ASC LIMIT 5");
        $clubSample = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // Detectar columnas relacionadas a club en cualquier tabla.
    $stmt = $pdo->query(
        "SELECT table_name, column_name
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND (
             column_name = 'club_id'
             OR column_name = 'id_club'
             OR column_name LIKE '%club_id%'
           )
         ORDER BY table_name, column_name"
    );
    $clubColumns = infoSchemaNormalizeRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

    // FKs que referencian clubes.
    $stmt = $pdo->query(
        "SELECT table_name, column_name, constraint_name
         FROM information_schema.key_column_usage
         WHERE table_schema = DATABASE()
           AND referenced_table_name = 'clubes'
         ORDER BY table_name, column_name"
    );
    $clubFKs = infoSchemaNormalizeRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

    // Tablas ya consideradas por el script de migración.
    $coveredTables = [
        'clubes',
        'inscritos',
        'partiresul',
        'torneos',
        'tournaments',
        'clubes_atletas',
        'torneos_hist',
        'inscritos_hist',
        'partiresul_hist',
        'clubes_atletas_hist',
    ];

    $additionalTablesMap = [];
    foreach ($clubColumns as $ref) {
        $table = (string)($ref['table_name'] ?? '');
        if ($table !== '' && !in_array($table, $coveredTables, true)) {
            $additionalTablesMap[$table] = true;
        }
    }
    foreach ($clubFKs as $fk) {
        $table = (string)($fk['table_name'] ?? '');
        if ($table !== '' && !in_array($table, $coveredTables, true)) {
            $additionalTablesMap[$table] = true;
        }
    }
    $additionalTables = array_keys($additionalTablesMap);
    sort($additionalTables);

    $verdict = empty($additionalTables)
        ? 'LISTO PARA MIGRAR'
        : 'ATENCION: Se detectaron ' . count($additionalTables) . ' tablas adicionales con referencias de club';

    $collisionWarning = null;
    if ($clubStats['count_ge_40'] > 0) {
        $collisionWarning = 'Se detectaron ' . $clubStats['count_ge_40'] . ' clubes con ID >= 40 antes del remapeo.';
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>Error en auditoria de migracion</h1>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoria Pre-Ejecucion - Migracion Clubes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6fa; }
        .container-wide { max-width: 1280px; }
        .mono { font-family: Consolas, "Courier New", monospace; }
        .card-title { font-size: 1rem; font-weight: 700; }
        .badge-soft { background: #e9eef8; color: #1e3a8a; }
    </style>
</head>
<body>
<div class="container container-wide py-4">
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <h1 class="h4 mb-0">Auditoria Pre-Ejecucion (Dry-Run)</h1>
        <span class="badge badge-soft mono">Fecha: <?= htmlspecialchars(date('Y-m-d H:i:s')) ?></span>
    </div>

    <div class="alert <?= empty($additionalTables) ? 'alert-success' : 'alert-warning' ?> mb-4">
        <strong>Veredicto:</strong> <?= htmlspecialchars($verdict) ?>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="card-title">1) Conteo por Tabla</h2>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Tabla</th>
                                <th class="text-end">Filas</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td><?= htmlspecialchars($torneosTable ?? 'torneos/tournaments') ?></td>
                                <td class="text-end mono"><?= $countByTable['torneos'] === null ? 'N/A' : number_format((int)$countByTable['torneos']) ?></td>
                            </tr>
                            <tr>
                                <td>partiresul</td>
                                <td class="text-end mono"><?= $countByTable['partiresul'] === null ? 'N/A' : number_format((int)$countByTable['partiresul']) ?></td>
                            </tr>
                            <tr>
                                <td>inscritos</td>
                                <td class="text-end mono"><?= $countByTable['inscritos'] === null ? 'N/A' : number_format((int)$countByTable['inscritos']) ?></td>
                            </tr>
                            <tr>
                                <td>clubes_atletas</td>
                                <td class="text-end mono"><?= $countByTable['clubes_atletas'] === null ? 'N/A' : number_format((int)$countByTable['clubes_atletas']) ?></td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="card-title">2) Deteccion de Colisiones / Rango</h2>
                    <ul class="mb-2">
                        <li>Total de clubes: <span class="mono"><?= number_format((int)$clubStats['total']) ?></span></li>
                        <li>Rango actual: <span class="mono"><?= $clubStats['min_id'] === null ? 'N/A' : ((int)$clubStats['min_id'] . ' -> ' . (int)$clubStats['max_id']) ?></span></li>
                        <li>Rango futuro (+40): <span class="mono"><?= $clubStats['future_min'] === null ? 'N/A' : ((int)$clubStats['future_min'] . ' -> ' . (int)$clubStats['future_max']) ?></span></li>
                    </ul>
                    <?php if ($collisionWarning !== null): ?>
                        <div class="alert alert-warning py-2 mb-0">
                            <?= htmlspecialchars($collisionWarning) ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success py-2 mb-0">
                            No se detectaron clubes con ID >= 40 antes del remapeo.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="card-title">3) Mapeo de Muestra (primeros 5 clubes)</h2>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>ID Actual</th>
                        <th>ID Futuro</th>
                        <th>Club</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($clubSample)): ?>
                        <tr><td colspan="3" class="text-muted">Sin datos en clubes.</td></tr>
                    <?php else: ?>
                        <?php foreach ($clubSample as $club): ?>
                            <tr>
                                <td class="mono"><?= (int)$club['id'] ?></td>
                                <td class="mono"><?= (int)$club['id'] + 40 ?></td>
                                <td><?= htmlspecialchars((string)($club['nombre'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="card-title">4) Check de Foreign Keys / Referencias club_id</h2>
            <div class="row g-3">
                <div class="col-12 col-xl-6">
                    <h3 class="h6">Columnas relacionadas (club_id, id_club, *club_id*)</h3>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead><tr><th>Tabla</th><th>Columna</th></tr></thead>
                            <tbody>
                            <?php if (empty($clubColumns)): ?>
                                <tr><td colspan="2" class="text-muted">No se detectaron columnas.</td></tr>
                            <?php else: ?>
                                <?php foreach ($clubColumns as $col): ?>
                                    <tr>
                                        <td class="mono"><?= htmlspecialchars((string)($col['table_name'] ?? '')) ?></td>
                                        <td class="mono"><?= htmlspecialchars((string)($col['column_name'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-12 col-xl-6">
                    <h3 class="h6">FKs que apuntan a clubes</h3>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead><tr><th>Tabla</th><th>Columna</th><th>Constraint</th></tr></thead>
                            <tbody>
                            <?php if (empty($clubFKs)): ?>
                                <tr><td colspan="3" class="text-muted">No se detectaron FKs a clubes.</td></tr>
                            <?php else: ?>
                                <?php foreach ($clubFKs as $fk): ?>
                                    <tr>
                                        <td class="mono"><?= htmlspecialchars((string)($fk['table_name'] ?? '')) ?></td>
                                        <td class="mono"><?= htmlspecialchars((string)($fk['column_name'] ?? '')) ?></td>
                                        <td class="mono"><?= htmlspecialchars((string)($fk['constraint_name'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h2 class="card-title">5) Tablas adicionales no cubiertas por script original</h2>
            <?php if (empty($additionalTables)): ?>
                <div class="alert alert-success mb-0">No se detectaron tablas adicionales con referencias de club.</div>
            <?php else: ?>
                <p class="mb-2">Se detectaron <strong><?= count($additionalTables) ?></strong> tabla(s) adicional(es):</p>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($additionalTables as $table): ?>
                        <span class="badge text-bg-warning mono"><?= htmlspecialchars($table) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
