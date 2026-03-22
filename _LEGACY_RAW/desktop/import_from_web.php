<?php
/**
 * Pull inicial: obtiene jugadores del endpoint web e inserta/actualiza en SQLite local.
 * Si el UUID ya existe, actualiza solo si last_updated de la web es más reciente.
 *
 * Uso (CLI): php import_from_web.php
 * Uso (navegador): abrir /desktop/import_from_web.php (muestra mensaje de éxito).
 *
 * Configuración: crear desktop/config_sync.php con SYNC_WEB_URL y SYNC_API_KEY,
 * o definir las constantes antes de incluir este archivo.
 */
declare(strict_types=1);

require_once __DIR__ . '/db_local.php';
require_once __DIR__ . '/core/config.php';

$isCli = php_sapi_name() === 'cli';
if (file_exists(__DIR__ . '/config_sync.php')) {
    require __DIR__ . '/config_sync.php';
}
$webUrl = defined('SYNC_WEB_URL') ? SYNC_WEB_URL : (getenv('SYNC_WEB_URL') ?: '');
$apiKey = defined('SYNC_API_KEY') ? SYNC_API_KEY : (getenv('SYNC_API_KEY') ?: '');
$useLocalMysql = defined('SYNC_USE_LOCAL_MYSQL') && SYNC_USE_LOCAL_MYSQL;
if ($isCli && isset($argv[1]) && $argv[1] === '--local') {
    $useLocalMysql = true;
}

if (!$useLocalMysql && ($webUrl === '' || $apiKey === '')) {
    $msg = 'Configura SYNC_WEB_URL y SYNC_API_KEY en desktop/config_sync.php, o ejecuta con --local para usar el MySQL local.';
    if ($isCli) {
        fwrite(STDERR, $msg . PHP_EOL);
        exit(1);
    }
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(400);
    echo $msg;
    exit;
}

/**
 * Compara timestamps: true si $a es más reciente que $b.
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

/**
 * Obtiene JSON del endpoint con API Key (HTTPS).
 * Envía la clave en Header X-API-Key y en URL ?api_key=... para cubrir todas las posibilidades.
 * SSL: CURLOPT_SSL_VERIFYPEER = false cuando SYNC_SSL_VERIFY es false (desarrollo local).
 */
function fetchJugadoresFromWeb(string $url, string $apiKey): array
{
    $apiKey = trim($apiKey);
    $fullUrl = rtrim($url, '/') . (strpos($url, '?') !== false ? '&' : '?') . 'api_key=' . urlencode($apiKey);
    $sslVerify = defined('SYNC_SSL_VERIFY') ? (bool) SYNC_SSL_VERIFY : false;
    $headers = [
        'X-API-Key: ' . $apiKey,
        'Accept: application/json',
    ];

    $json = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($fullUrl);
        $curlOpts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT       => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER    => $headers,
        ];
        if (!$sslVerify) {
            $curlOpts[CURLOPT_SSL_VERIFYPEER] = false;
            $curlOpts[CURLOPT_SSL_VERIFYHOST] = 0;
        }
        curl_setopt_array($ch, $curlOpts);
        $json = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($json === false || $json === '') {
            throw new RuntimeException($err ?: 'No se pudo conectar al servidor.');
        }
    }
    if ($json === false) {
        $opts = [
            'http' => [
                'method'  => 'GET',
                'header'  => implode("\r\n", $headers) . "\r\n",
                'timeout' => 30,
            ],
            'ssl' => [
                'verify_peer'     => $sslVerify,
                'verify_peer_name' => $sslVerify,
            ],
        ];
        $ctx = stream_context_create($opts);
        $json = @file_get_contents($fullUrl, false, $ctx);
    }
    if ($json === false || $json === '') {
        throw new RuntimeException('No se pudo conectar al servidor.');
    }
    $data = json_decode($json, true);
    if (!is_array($data) || !isset($data['jugadores'])) {
        $msg = 'Respuesta inválida del servidor.';
        if (is_array($data)) {
            $msg = $data['message'] ?? $data['error'] ?? $msg;
            if (isset($data['error']) && $data['error'] === 'Not Found') {
                $msg = 'URL no encontrada (404). Revisa SYNC_WEB_URL en desktop/config_sync.php: la ruta debe ser la del endpoint de jugadores en tu servidor.';
            }
        }
        $hint = is_string($json) && strlen($json) > 0 ? ' Respuesta: ' . substr(strip_tags($json), 0, 150) : '';
        throw new RuntimeException($msg . $hint);
    }
    return $data['jugadores'];
}

/**
 * Obtiene JSON de una URL (misma lógica que fetchJugadoresFromWeb pero devuelve el objeto completo).
 */
function fetchJsonFromUrl(string $url, string $apiKey): array
{
    $apiKey = trim($apiKey);
    $fullUrl = rtrim($url, '/') . (strpos($url, '?') !== false ? '&' : '?') . 'api_key=' . urlencode($apiKey);
    $sslVerify = defined('SYNC_SSL_VERIFY') ? (bool) SYNC_SSL_VERIFY : false;
    $headers = ['X-API-Key: ' . $apiKey, 'Accept: application/json'];
    $json = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($fullUrl);
        $curlOpts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTPHEADER => $headers];
        if (!$sslVerify) { $curlOpts[CURLOPT_SSL_VERIFYPEER] = false; $curlOpts[CURLOPT_SSL_VERIFYHOST] = 0; }
        curl_setopt_array($ch, $curlOpts);
        $json = curl_exec($ch);
        curl_close($ch);
    }
    if ($json === false || $json === '') {
        return [];
    }
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

try {
    if ($useLocalMysql) {
        $base = dirname(__DIR__);
        if (!is_file($base . '/config/bootstrap.php')) {
            throw new RuntimeException('No se encontró config/bootstrap.php. Ejecuta desde la raíz del proyecto.');
        }
        require_once $base . '/config/bootstrap.php';
        require_once $base . '/config/db.php';
        $pdoMysql = DB::pdo();
        $columns = ['id', 'uuid', 'nombre', 'cedula', 'nacionalidad', 'sexo', 'fechnac', 'email', 'username', 'club_id', 'entidad', 'status', 'role'];
        try {
            if ($pdoMysql->query("SHOW COLUMNS FROM usuarios LIKE 'last_updated'")->fetch()) $columns[] = 'last_updated';
            if ($pdoMysql->query("SHOW COLUMNS FROM usuarios LIKE 'sync_status'")->fetch()) $columns[] = 'sync_status';
            if ($pdoMysql->query("SHOW COLUMNS FROM usuarios LIKE 'is_active'")->fetch()) $columns[] = 'is_active';
        } catch (Throwable $e) {
        }
        $cols = implode(', ', $columns);
        $rows = $pdoMysql->query("SELECT {$cols} FROM usuarios WHERE uuid IS NOT NULL AND uuid != '' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            if (isset($row['fechnac']) && $row['fechnac'] !== null) {
                $row['fechnac'] = (string)$row['fechnac'];
            }
            if (isset($row['last_updated']) && $row['last_updated'] !== null) {
                $row['last_updated'] = (string)$row['last_updated'];
            }
        }
        unset($row);
        $jugadores = $rows;
        if ($isCli) {
            echo "Modo local: leyendo desde MySQL (config/db)..." . PHP_EOL;
        }
        $staff = [];
        foreach ($rows as $r) {
            if (in_array($r['role'] ?? '', ['admin_general', 'admin_torneo', 'admin_club', 'operador'], true)) {
                $staff[] = $r;
            }
        }
    } else {
        $jugadores = fetchJugadoresFromWeb($webUrl, $apiKey);
        $staffUrl = preg_replace('#fetch_jugadores\.php#', 'fetch_staff.php', $webUrl);
        $dataStaff = fetchJsonFromUrl($staffUrl, $apiKey);
        $staff = $dataStaff['staff'] ?? [];
    }
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

$pdo = DB_Local::pdo();
$inserted = 0;
$updated = 0;

// Campos que insertamos/actualizamos en SQLite (usuarios)
$fields = ['uuid', 'nombre', 'cedula', 'nacionalidad', 'sexo', 'fechnac', 'email', 'username', 'club_id', 'entidad', 'status', 'role', 'last_updated', 'sync_status', 'is_active'];

foreach ($jugadores as $row) {
    if (!is_array($row)) {
        continue;
    }
    $uuid = trim((string)($row['uuid'] ?? ''));
    if ($uuid === '') {
        continue;
    }

    $last_updated = isset($row['last_updated']) ? trim((string)$row['last_updated']) : null;
    if ($last_updated === '') {
        $last_updated = date('Y-m-d H:i:s');
    }

    $stmt = $pdo->prepare("SELECT uuid, last_updated FROM usuarios WHERE uuid = ?");
    $stmt->execute([$uuid]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    $params = [
        'uuid'          => $uuid,
        'nombre'        => $row['nombre'] ?? null,
        'cedula'        => $row['cedula'] ?? null,
        'nacionalidad'  => $row['nacionalidad'] ?? 'V',
        'sexo'          => $row['sexo'] ?? null,
        'fechnac'       => isset($row['fechnac']) ? (string)$row['fechnac'] : null,
        'email'         => $row['email'] ?? null,
        'username'      => $row['username'] ?? null,
        'club_id'       => isset($row['club_id']) ? (int)$row['club_id'] : 0,
        'entidad'       => (defined('DESKTOP_ENTIDAD_ID') && (int)DESKTOP_ENTIDAD_ID > 0) ? (int)DESKTOP_ENTIDAD_ID : (isset($row['entidad']) ? (int)$row['entidad'] : 0),
        'status'        => isset($row['status']) ? (int)$row['status'] : 0,
        'role'          => $row['role'] ?? 'usuario',
        'last_updated'  => $last_updated,
        'sync_status'   => isset($row['sync_status']) ? (int)$row['sync_status'] : 0,
        'is_active'     => isset($row['is_active']) ? (int)$row['is_active'] : 1,
    ];

    if ($existing === false) {
        $cols = implode(', ', $fields);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO usuarios ({$cols}) VALUES ({$placeholders})";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $inserted++;
    } else {
        if (!isNewer($last_updated, $existing['last_updated'] ?? null)) {
            continue;
        }
        $set = [];
        foreach ($fields as $f) {
            if ($f === 'uuid') {
                continue;
            }
            $set[] = "{$f} = :{$f}";
        }
        $sql = "UPDATE usuarios SET " . implode(', ', $set) . " WHERE uuid = :uuid";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $updated++;
    }
}

// Importar también staff (administradores) a SQLite
$staffInserted = 0;
$staffUpdated = 0;
foreach ($staff as $row) {
    if (!is_array($row)) continue;
    $uuid = trim((string)($row['uuid'] ?? ''));
    if ($uuid === '') continue;
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
        'entidad' => (defined('DESKTOP_ENTIDAD_ID') && (int)DESKTOP_ENTIDAD_ID > 0) ? (int)DESKTOP_ENTIDAD_ID : (isset($row['entidad']) ? (int)$row['entidad'] : 0),
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
        $staffInserted++;
    } else {
        if (!isNewer($last_updated, $existing['last_updated'] ?? null)) continue;
        $set = [];
        foreach ($fields as $f) { if ($f === 'uuid') continue; $set[] = "{$f} = :{$f}"; }
        $pdo->prepare("UPDATE usuarios SET " . implode(', ', $set) . " WHERE uuid = :uuid")->execute($params);
        $staffUpdated++;
    }
}

$total = $inserted + $updated + $staffInserted + $staffUpdated;
$received = count($jugadores);
$message = 'Sincronización completada: ' . ($inserted + $updated) . ' jugador(es), ' . ($staffInserted + $staffUpdated) . ' staff. Total ' . $total . ' registro(s) actualizado(s).';
if ($received === 0 && empty($staff)) {
    $message .= ' El servidor devolvió 0 jugadores y 0 staff. Comprueba la base remota (MySQL).';
} elseif ($total === 0) {
    $message .= ' Nada nuevo (ya estaban sincronizados).';
}

if ($isCli) {
    echo $message . PHP_EOL;
    exit(0);
}

header('Content-Type: text/plain; charset=utf-8');
echo $message;
