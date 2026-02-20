<?php
/**
 * Script de instalación: crea la tabla notifications_queue y opcionalmente telegram_chat_id en usuarios.
 * Ejecutar una sola vez desde el navegador: http://tu-dominio/mistorneos/public/instalar_notifications_queue.php
 * Eliminar o proteger este archivo después de usarlo.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: text/html; charset=UTF-8');

$pdo = DB::pdo();
$mensajes = [];
$ok = true;

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            canal ENUM('telegram', 'web', 'email') NOT NULL,
            mensaje TEXT NOT NULL,
            url_destino VARCHAR(255) DEFAULT '#',
            estado ENUM('pendiente', 'enviado', 'fallido') DEFAULT 'pendiente',
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_estado (estado),
            INDEX idx_canal (canal),
            INDEX idx_usuario_canal_estado (usuario_id, canal, estado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $mensajes[] = 'Tabla notifications_queue creada correctamente.';
} catch (PDOException $e) {
    $mensajes[] = 'Error al crear notifications_queue: ' . $e->getMessage();
    $ok = false;
}

if ($ok) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'telegram_chat_id'");
        $existe = (int) $stmt->fetchColumn();
        if ($existe === 0) {
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN telegram_chat_id VARCHAR(50) NULL COMMENT 'Chat ID de Telegram'");
            $mensajes[] = 'Columna telegram_chat_id añadida a usuarios.';
        } else {
            $mensajes[] = 'La columna telegram_chat_id ya existe en usuarios.';
        }
    } catch (PDOException $e) {
        $mensajes[] = 'Aviso (telegram_chat_id): ' . $e->getMessage();
    }
}

echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Instalación notifications_queue</title></head><body>';
echo '<h1>Instalación de notificaciones</h1><ul>';
foreach ($mensajes as $m) {
    echo '<li>' . htmlspecialchars($m) . '</li>';
}
echo '</ul>';
if ($ok) {
    echo '<p><strong>Listo.</strong> Ya puedes usar el sistema de notificaciones. Elimina o protege este archivo (instalar_notifications_queue.php) por seguridad.</p>';
}
echo '</body></html>';
