-- Agregar campo tipo_torneo a tournaments (índice entero: 0=no definido, 1=interclubes, 2=suizo_puro, 3=suizo_sin_repetir)
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tournaments'
    AND COLUMN_NAME = 'tipo_torneo'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE tournaments ADD COLUMN tipo_torneo TINYINT NOT NULL DEFAULT 0 COMMENT "0=no definido, 1=interclubes, 2=suizo_puro, 3=suizo_sin_repetir" AFTER pareclub',
    'SELECT "Columna tipo_torneo ya existe" AS mensaje'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
