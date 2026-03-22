-- Sistema de notificaciones masivas de alta velocidad (Telegram + Campanita Web)
-- Ejecutar una sola vez en la base de datos.

-- Tabla para gestionar la cola de envíos
CREATE TABLE IF NOT EXISTS notifications_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    canal ENUM('telegram', 'web', 'email') NOT NULL,
    mensaje TEXT NOT NULL,
    url_destino VARCHAR(255) DEFAULT '#',
    datos_json TEXT NULL COMMENT 'JSON para tarjeta formateada (ej. nueva_ronda)',
    estado ENUM('pendiente', 'enviado', 'fallido') DEFAULT 'pendiente',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_estado (estado),
    INDEX idx_canal (canal),
    INDEX idx_usuario_canal_estado (usuario_id, canal, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Asegurar que la tabla usuarios tiene el campo de Telegram (si no existe)
-- En MySQL 8+ puedes usar: ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS telegram_chat_id VARCHAR(50) NULL;
-- Para compatibilidad con versiones anteriores, ejecutar solo si hace falta:
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'usuarios'
    AND COLUMN_NAME = 'telegram_chat_id'
);
SET @sql_tg = IF(@col_exists = 0,
    'ALTER TABLE usuarios ADD COLUMN telegram_chat_id VARCHAR(50) NULL COMMENT ''Chat ID de Telegram''',
    'SELECT ''Columna telegram_chat_id ya existe en usuarios'' AS mensaje'
);
PREPARE stmt FROM @sql_tg;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
