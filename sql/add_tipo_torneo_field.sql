-- Agregar campo tipo_torneo a tournaments (interclubes, suizo, suizo_puro, etc.)
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tournaments'
    AND COLUMN_NAME = 'tipo_torneo'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE tournaments ADD COLUMN tipo_torneo VARCHAR(50) NULL COMMENT "Tipo: interclubes, suizo, suizo_puro, etc." AFTER pareclub',
    'SELECT "Columna tipo_torneo ya existe" AS mensaje'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
