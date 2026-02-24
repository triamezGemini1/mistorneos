<?php
/**
 * Endpoint para la campanita: devuelve el número de notificaciones web pendientes del usuario.
 * Con ?format=json devuelve también la última notificación pendiente (para toast/Push).
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function notif_wants_json() {
    return (isset($_GET['format']) && $_GET['format'] === 'json')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
}

$user = $_SESSION['user'] ?? null;
$uid = $user ? Auth::id() : 0;
if ($uid <= 0) {
    if (notif_wants_json()) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['count' => 0, 'latest' => null]);
    } else {
        header('Content-Type: text/plain; charset=UTF-8');
        echo '0';
    }
    exit;
}

$pdo = DB::pdo();
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM notifications_queue WHERE usuario_id = ? AND canal = 'web' AND estado = 'pendiente'"
);
$stmt->execute([$uid]);
$count = (int) $stmt->fetchColumn();

if (notif_wants_json()) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    $latest = null;
    if ($count > 0) {
        // Columna datos_json ya existe en el schema, evitamos SHOW COLUMNS cada vez
        $hasDatosJson = true;
        $stmtLatest = $pdo->prepare("
            SELECT id, mensaje, url_destino, datos_json
            FROM notifications_queue
            WHERE usuario_id = ? AND canal = 'web' AND estado = 'pendiente'
            ORDER BY fecha_creacion DESC
            LIMIT 1
        ");
        $stmtLatest->execute([$uid]);
        $row = $stmtLatest->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $titulo = 'Nueva notificación';
            $mensaje = $row['mensaje'] ?? '';
            $datosEstructurados = null;
            if ($hasDatosJson && !empty($row['datos_json'])) {
                $datosEstructurados = @json_decode($row['datos_json'], true);
            }
            if (!$datosEstructurados) {
                if (mb_strlen($mensaje) > 80) {
                    $titulo = mb_substr($mensaje, 0, 50) . '…';
                } elseif (preg_match('/^(.{1,50})(?:\s|$)/u', $mensaje, $m)) {
                    $titulo = trim($m[1]);
                }
            }
            $latest = [
                'id' => (int) $row['id'],
                'titulo' => $titulo,
                'mensaje' => $mensaje,
                'url_destino' => $row['url_destino'] ?? '#',
                'datos_estructurados' => $datosEstructurados,
            ];
        }
    }
    echo json_encode(['count' => $count, 'latest' => $latest]);
} else {
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo $count;
}
