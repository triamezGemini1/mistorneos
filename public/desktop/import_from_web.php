<?php
/**
 * Pull inicial: obtiene jugadores y tablas maestras (entidad, organizaciones, clubes) del servidor e inserta/actualiza en SQLite local.
 */
declare(strict_types=1);

require_once __DIR__ . '/db_local.php';

if (file_exists(__DIR__ . '/config_sync.php')) {
    require __DIR__ . '/config_sync.php';
}
$webUrl = defined('SYNC_WEB_URL') ? SYNC_WEB_URL : (getenv('SYNC_WEB_URL') ?: '');
$apiKey = defined('SYNC_API_KEY') ? SYNC_API_KEY : (getenv('SYNC_API_KEY') ?: '');

if ($webUrl === '' || $apiKey === '') {
    $msg = 'Configura SYNC_WEB_URL y SYNC_API_KEY en desktop/config_sync.php o variables de entorno.';
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, $msg . PHP_EOL);
        exit(1);
    }
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(400);
    echo $msg;
    exit;
}

$maestrosUrl = preg_replace('#fetch_jugadores\.php.*#', 'api_fetch_maestros.php', $webUrl);
$staffUrl = preg_replace('#fetch_jugadores\.php.*#', 'fetch_staff.php', $webUrl);

function isNewer(?string $a, ?string $b): bool
{
    if ($a === null || $a === '') return false;
    if ($b === null || $b === '') return true;
    return strtotime($a) > strtotime($b);
}

function fetchJson(string $url, string $apiKey): array
{
    $fullUrl = rtrim($url, '/') . (strpos($url, '?') !== false ? '&' : '?') . 'api_key=' . urlencode($apiKey);
    $opts = [
        'http' => [
            'method'  => 'GET',
            'header'  => "X-API-Key: " . $apiKey . "\r\nAccept: application/json\r\n",
            'timeout' => 30,
        ],
    ];
    $ctx = stream_context_create($opts);
    $json = @file_get_contents($fullUrl, false, $ctx);
    if ($json === false && function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT       => 30,
            CURLOPT_HTTPHEADER    => ['X-API-Key: ' . $apiKey, 'Accept: application/json'],
        ]);
        $json = curl_exec($ch);
        curl_close($ch);
    }
    if ($json === false || $json === '') {
        throw new RuntimeException('No se pudo conectar al servidor.');
    }
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

$isCli = php_sapi_name() === 'cli';
$pdo = DB_Local::pdo();

// 1) Descargar y guardar maestros (entidad, organizaciones, clubes)
$maestrosOk = false;
try {
    $dataM = fetchJson($maestrosUrl, $apiKey);
    if (!empty($dataM['ok'])) {
        if (!empty($dataM['entidades'])) {
            $pdo->exec("DELETE FROM entidad");
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO entidad (codigo, nombre) VALUES (?, ?)");
            foreach ($dataM['entidades'] as $e) {
                $codigo = isset($e['codigo']) ? (int)$e['codigo'] : (int)($e['id'] ?? 0);
                $nombre = $e['nombre'] ?? '';
                if ($nombre !== '') $stmt->execute([$codigo, $nombre]);
            }
        }
        if (!empty($dataM['organizaciones'])) {
            $pdo->exec("DELETE FROM organizaciones");
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO organizaciones (id, nombre, entidad, estatus) VALUES (?, ?, ?, ?)");
            foreach ($dataM['organizaciones'] as $o) {
                $stmt->execute([(int)$o['id'], $o['nombre'] ?? '', (int)($o['entidad'] ?? 0), (int)($o['estatus'] ?? 1)]);
            }
        }
        if (!empty($dataM['clubes'])) {
            $pdo->exec("DELETE FROM clubes");
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO clubes (id, nombre, organizacion_id, entidad, estatus) VALUES (?, ?, ?, ?, ?)");
            foreach ($dataM['clubes'] as $c) {
                $stmt->execute([(int)$c['id'], $c['nombre'] ?? '', isset($c['organizacion_id']) ? (int)$c['organizacion_id'] : null, (int)($c['entidad'] ?? 0), (int)($c['estatus'] ?? 1)]);
            }
        }
        $maestrosOk = true;
    }
} catch (Throwable $e) {
    // seguir con jugadores aunque falle maestros
}

// 2) Jugadores
try {
    $data = fetchJson($webUrl, $apiKey);
    if (!isset($data['jugadores'])) {
        throw new RuntimeException($data['message'] ?? 'Respuesta inválida del servidor.');
    }
    $jugadores = $data['jugadores'];
} catch (Throwable $e) {
    $msg = 'Error al obtener datos: ' . $e->getMessage();
    if ($isCli) {
        fwrite(STDERR, $msg . PHP_EOL);
        exit(1);
    }
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(502);
    echo $msg;
    exit;
}

$inserted = 0;
$updated = 0;
$fields = ['uuid', 'nombre', 'cedula', 'nacionalidad', 'sexo', 'fechnac', 'email', 'username', 'club_id', 'entidad', 'status', 'role', 'last_updated', 'sync_status', 'is_active'];

function upsertUsuario(PDO $pdo, array $row, array $fields, callable $isNewer): int {
    $uuid = trim((string)($row['uuid'] ?? ''));
    if ($uuid === '') return 0;
    $last_updated = isset($row['last_updated']) ? trim((string)$row['last_updated']) : null;
    if ($last_updated === '') $last_updated = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("SELECT uuid, last_updated FROM usuarios WHERE uuid = ?");
    $stmt->execute([$uuid]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    $params = [
        'uuid' => $uuid,
        'nombre' => $row['nombre'] ?? null,
        'cedula' => $row['cedula'] ?? null,
        'nacionalidad' => $row['nacionalidad'] ?? 'V',
        'sexo' => $row['sexo'] ?? null,
        'fechnac' => isset($row['fechnac']) ? (string)$row['fechnac'] : null,
        'email' => $row['email'] ?? null,
        'username' => $row['username'] ?? null,
        'club_id' => isset($row['club_id']) ? (int)$row['club_id'] : 0,
        'entidad' => isset($row['entidad']) ? (int)$row['entidad'] : 0,
        'status' => isset($row['status']) ? (int)$row['status'] : 0,
        'role' => $row['role'] ?? 'usuario',
        'last_updated' => $last_updated,
        'sync_status' => isset($row['sync_status']) ? (int)$row['sync_status'] : 0,
        'is_active' => isset($row['is_active']) ? (int)$row['is_active'] : 1,
    ];
    if ($existing === false) {
        $cols = implode(', ', $fields);
        $placeholders = ':' . implode(', :', $fields);
        $pdo->prepare("INSERT INTO usuarios ({$cols}) VALUES ({$placeholders})")->execute($params);
        return 1;
    }
    if (!call_user_func($isNewer, $last_updated, $existing['last_updated'] ?? null)) return 0;
    $set = [];
    foreach ($fields as $f) {
        if ($f === 'uuid') continue;
        $set[] = "{$f} = :{$f}";
    }
    $pdo->prepare("UPDATE usuarios SET " . implode(', ', $set) . " WHERE uuid = :uuid")->execute($params);
    return 2;
}

foreach ($jugadores as $row) {
    if (!is_array($row)) continue;
    $r = upsertUsuario($pdo, $row, $fields, 'isNewer');
    if ($r === 1) $inserted++;
    elseif ($r === 2) $updated++;
}

// 3) Staff (administradores): descargar e insertar/actualizar en usuarios
$staffInserted = 0;
$staffUpdated = 0;
try {
    $dataS = fetchJson($staffUrl, $apiKey);
    if (!empty($dataS['ok']) && !empty($dataS['staff'])) {
        foreach ($dataS['staff'] as $row) {
            if (!is_array($row)) continue;
            $r = upsertUsuario($pdo, $row, $fields, 'isNewer');
            if ($r === 1) $staffInserted++;
            elseif ($r === 2) $staffUpdated++;
        }
    }
} catch (Throwable $e) {
    // seguir sin fallar
}

$total = $inserted + $updated + $staffInserted + $staffUpdated;
$message = 'Sincronización completada: ' . ($inserted + $updated) . ' jugador(es), ' . ($staffInserted + $staffUpdated) . ' staff. Total ' . $total . ' registro(s) actualizado(s).';
if ($maestrosOk) {
    $message .= ' Tablas maestras (entidad, organizaciones, clubes) actualizadas.';
}

if ($isCli) {
    echo $message . PHP_EOL;
    exit(0);
}
header('Content-Type: text/plain; charset=utf-8');
echo $message;
