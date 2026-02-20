<?php
/**
 * API de sincronización de jugadores (Offline-First). Ubicación: desktop/
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_local.php';

function ensureSyncLocalSchema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS usuarios_local (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uuid TEXT UNIQUE NOT NULL,
            nombre TEXT,
            cedula TEXT,
            nacionalidad TEXT DEFAULT 'V',
            sexo TEXT,
            fechnac TEXT,
            email TEXT,
            username TEXT,
            club_id INTEGER DEFAULT 0,
            entidad INTEGER DEFAULT 0,
            last_updated TEXT,
            sync_status INTEGER DEFAULT 0,
            raw_json TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_usuarios_local_uuid ON usuarios_local(uuid)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_usuarios_local_last_updated ON usuarios_local(last_updated)");
}

function isNewerSync(?string $a, ?string $b): bool
{
    if ($a === null || $a === '') return false;
    if ($b === null || $b === '') return true;
    return strtotime($a) > strtotime($b);
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!is_array($data) || empty($data['jugadores'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Se espera JSON con clave "jugadores" (array de objetos)', 'errors' => []]);
    exit;
}

$jugadores = is_array($data['jugadores']) ? $data['jugadores'] : [];
$inserted = 0;
$updated = 0;
$skipped = 0;
$errors = [];

try {
    $pdo = DB_Local::pdo();
    ensureSyncLocalSchema($pdo);

    foreach ($jugadores as $index => $row) {
        if (!is_array($row)) {
            $errors[] = "Elemento {$index} no es un objeto.";
            continue;
        }
        $uuid = trim((string)($row['uuid'] ?? ''));
        if ($uuid === '') {
            $errors[] = "Elemento {$index}: falta uuid.";
            continue;
        }
        $last_updated = isset($row['last_updated']) ? trim((string)$row['last_updated']) : null;
        if ($last_updated === '') $last_updated = null;

        $stmt = $pdo->prepare("SELECT uuid, last_updated FROM usuarios_local WHERE uuid = ?");
        $stmt->execute([$uuid]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        $payload = [
            'uuid' => $uuid,
            'nombre' => $row['nombre'] ?? null,
            'cedula' => $row['cedula'] ?? null,
            'nacionalidad' => $row['nacionalidad'] ?? 'V',
            'sexo' => $row['sexo'] ?? null,
            'fechnac' => $row['fechnac'] ?? null,
            'email' => $row['email'] ?? null,
            'username' => $row['username'] ?? null,
            'club_id' => isset($row['club_id']) ? (int)$row['club_id'] : 0,
            'entidad' => isset($row['entidad']) ? (int)$row['entidad'] : 0,
            'last_updated' => $last_updated ?? date('Y-m-d H:i:s'),
            'sync_status' => isset($row['sync_status']) ? (int)$row['sync_status'] : 0,
            'raw_json' => json_encode($row),
        ];

        if ($existing === false) {
            $stmt = $pdo->prepare("INSERT INTO usuarios_local (uuid, nombre, cedula, nacionalidad, sexo, fechnac, email, username, club_id, entidad, last_updated, sync_status, raw_json) VALUES (:uuid, :nombre, :cedula, :nacionalidad, :sexo, :fechnac, :email, :username, :club_id, :entidad, :last_updated, :sync_status, :raw_json)");
            $stmt->execute($payload);
            $inserted++;
        } else {
            if (!isNewerSync($last_updated, $existing['last_updated'] ?? null)) {
                $skipped++;
                continue;
            }
            $stmt = $pdo->prepare("UPDATE usuarios_local SET nombre = :nombre, cedula = :cedula, nacionalidad = :nacionalidad, sexo = :sexo, fechnac = :fechnac, email = :email, username = :username, club_id = :club_id, entidad = :entidad, last_updated = :last_updated, sync_status = :sync_status, raw_json = :raw_json WHERE uuid = :uuid");
            $stmt->execute($payload);
            $updated++;
        }
    }

    echo json_encode(['ok' => true, 'inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage(), 'inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped, 'errors' => array_merge($errors, [$e->getMessage()])]);
}
