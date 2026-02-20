<?php
/**
 * Procesador de cola de notificaciones (Telegram).
 * Ejecutar en segundo plano vía Cron cada 1–2 minutos.
 *
 * Uso desde cPanel / Cron:
 *   php /ruta/al/public/procesar_envio.php
 * o por HTTP (proteger con clave):
 *   curl "https://tudominio.com/mistorneos/public/procesar_envio.php?key=TU_CRON_SECRET"
 *
 * En .env definir: NOTIFICATIONS_CRON_KEY=una_clave_secreta
 * Si no está definida, solo se permite ejecución por CLI.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/NotificationManager.php';

// Permitir solo Cron (CLI) o petición HTTP con clave
$is_cli = (php_sapi_name() === 'cli');
$cron_key = $_ENV['NOTIFICATIONS_CRON_KEY'] ?? '';
$key_ok = ($cron_key !== '' && ($_GET['key'] ?? '') === $cron_key);

if (!$is_cli && !$key_ok) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Forbidden';
    exit;
}

set_time_limit(300); // 5 minutos de margen

$pdo = DB::pdo();
$nm = new NotificationManager($pdo);
$lote_size = 30; // Límite aproximado de Telegram por segundo (~30 msg/s)

$stmt = $pdo->prepare("
    SELECT q.id, q.usuario_id, q.mensaje, u.telegram_chat_id
    FROM notifications_queue q
    INNER JOIN usuarios u ON q.usuario_id = u.id
    WHERE q.canal = 'telegram' AND q.estado = 'pendiente' AND u.telegram_chat_id IS NOT NULL AND u.telegram_chat_id != ''
    ORDER BY q.id
    LIMIT 650
");
$stmt->execute();
$pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updateStmt = $pdo->prepare("UPDATE notifications_queue SET estado = ? WHERE id = ?");

foreach ($pendientes as $index => $item) {
    $enviado = $nm->enviarTelegram($item['telegram_chat_id'], $item['mensaje']);
    $estado = $enviado ? 'enviado' : 'fallido';
    $updateStmt->execute([$estado, $item['id']]);

    // Pausa cada 30 mensajes para respetar límites de Telegram (~30 msg/s)
    if (($index + 1) % $lote_size === 0) {
        usleep(1100000); // 1.1 segundos
    }
}

if ($is_cli) {
    echo "Procesados: " . count($pendientes) . " mensajes Telegram.\n";
}
