<?php
/**
 * API de sincronización de jugadores (Offline-First).
 * Escribe en la tabla usuarios (misma que el resto del sistema). Filtro por DESKTOP_ENTIDAD_ID.
 *
 * Uso:
 *   POST /desktop/sync_api.php
 *   Content-Type: application/json
 *   Body: { "jugadores": [ { "uuid": "...", "last_updated": "...", ... }, ... ] }
 *
 * Respuesta JSON: { "ok": true, "inserted": n, "updated": n, "skipped": n, "errors": [] }
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_local.php';
require_once __DIR__ . '/core/db_bridge.php';

$entidadId = DB::getEntidadId();

/**
 * Compara dos timestamps (string ISO o Y-m-d H:i:s). Retorna true si $a es más reciente que $b.
 */
function isNewer(?string $a, ?string $b): bool
{
    if ($a === null || $a === '') {
        return false;
    }
    if ($b === null || $b === '') {
        return true;
    }
    return strtotime($a) > strtotime($b);
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!is_array($data) || empty($data['jugadores'])) {
    http_response_code(400);
    echo json_encode([
        'ok'      => false,
        'message' => 'Se espera JSON con clave "jugadores" (array de objetos)',
        'errors'  => [],
    ]);
    exit;
}

$jugadores = $data['jugadores'];
if (!is_array($jugadores)) {
    $jugadores = [];
}

$inserted = 0;
$updated = 0;
$skipped = 0;
$errors = [];

try {
    $pdo = DB_Local::pdo();

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
        if ($last_updated === '') {
            $last_updated = null;
        }

        $stmt = $pdo->prepare("SELECT uuid, last_updated FROM usuarios WHERE uuid = ?");
        $stmt->execute([$uuid]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        $entidad = $entidadId > 0 ? $entidadId : (isset($row['entidad']) ? (int)$row['entidad'] : 0);
        $payload = [
            'uuid'          => $uuid,
            'nombre'        => $row['nombre'] ?? null,
            'cedula'        => $row['cedula'] ?? null,
            'nacionalidad'  => $row['nacionalidad'] ?? 'V',
            'sexo'          => $row['sexo'] ?? null,
            'fechnac'       => $row['fechnac'] ?? null,
            'email'         => $row['email'] ?? null,
            'username'      => $row['username'] ?? null,
            'club_id'       => isset($row['club_id']) ? (int)$row['club_id'] : 0,
            'entidad'       => $entidad,
            'last_updated'  => $last_updated ?? date('Y-m-d H:i:s'),
            'sync_status'   => isset($row['sync_status']) ? (int)$row['sync_status'] : 0,
        ];

        if ($existing === false) {
            $sql = "INSERT INTO usuarios (uuid, nombre, cedula, nacionalidad, sexo, fechnac, email, username, club_id, entidad, last_updated, sync_status)
                    VALUES (:uuid, :nombre, :cedula, :nacionalidad, :sexo, :fechnac, :email, :username, :club_id, :entidad, :last_updated, :sync_status)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($payload);
            $inserted++;
        } else {
            if (!isNewer($last_updated, $existing['last_updated'] ?? null)) {
                $skipped++;
                continue;
            }
            $sql = "UPDATE usuarios SET nombre = :nombre, cedula = :cedula, nacionalidad = :nacionalidad, sexo = :sexo, fechnac = :fechnac,
                    email = :email, username = :username, club_id = :club_id, entidad = :entidad, last_updated = :last_updated, sync_status = :sync_status
                    WHERE uuid = :uuid";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($payload);
            $updated++;
        }
    }

    echo json_encode([
        'ok'       => true,
        'inserted' => $inserted,
        'updated'  => $updated,
        'skipped'  => $skipped,
        'errors'   => $errors,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'message' => $e->getMessage(),
        'inserted' => $inserted,
        'updated'  => $updated,
        'skipped'  => $skipped,
        'errors'   => array_merge($errors, [$e->getMessage()]),
    ]);
}
