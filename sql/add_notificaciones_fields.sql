-- ============================================================
-- Campos para sistema de notificaciones masivas
-- WhatsApp, Email, Telegram
-- ============================================================

-- 1. Tabla usuarios: celular (si no existe) y telegram_chat_id
-- Verificar y agregar celular
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'usuarios' 
    AND COLUMN_NAME = 'celular'
);
SET @sql_celular = IF(@col_exists = 0, 
    'ALTER TABLE usuarios ADD COLUMN celular VARCHAR(20) NULL AFTER email',
    'SELECT "Columna celular ya existe en usuarios" AS mensaje'
);
PREPARE stmt FROM @sql_celular;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Agregar telegram_chat_id a usuarios
SET @col_tg = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'usuarios' 
    AND COLUMN_NAME = 'telegram_chat_id'
);
SET @sql_tg = IF(@col_tg = 0, 
    'ALTER TABLE usuarios ADD COLUMN telegram_chat_id VARCHAR(50) NULL COMMENT "Chat ID de Telegram para notificaciones" AFTER celular',
    'SELECT "Columna telegram_chat_id ya existe en usuarios" AS mensaje'
);
PREPARE stmt2 FROM @sql_tg;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- 2. Tabla solicitudes_afiliacion: telegram_chat_id (si se usa)
SET @col_sol = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'solicitudes_afiliacion' 
    AND COLUMN_NAME = 'telegram_chat_id'
);
SET @sql_sol = IF(@col_sol = 0, 
    'ALTER TABLE solicitudes_afiliacion ADD COLUMN telegram_chat_id VARCHAR(50) NULL COMMENT "Chat ID Telegram" AFTER celular',
    'SELECT "Columna telegram_chat_id ya existe en solicitudes_afiliacion" AS mensaje'
);
PREPARE stmt3 FROM @sql_sol;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;
