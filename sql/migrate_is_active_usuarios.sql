-- =============================================================================
-- Añadir is_active a usuarios (MySQL) — control de administradores (toggle)
-- 1 = activo, 0 = desactivado por Super Admin (no puede acceder ni offline ni web)
-- =============================================================================
USE mistorneos;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'mistorneos' AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'is_active');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE usuarios ADD COLUMN is_active TINYINT NOT NULL DEFAULT 1 COMMENT ''0=desactivado por admin, 1=activo''',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
