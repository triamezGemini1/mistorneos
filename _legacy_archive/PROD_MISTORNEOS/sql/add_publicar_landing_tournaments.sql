-- Migración: Agregar campo publicar_landing a la tabla tournaments
-- Control de publicación en el landing page. Por defecto 1 (publicado).

-- Verificar si la columna ya existe antes de agregar
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tournaments' AND COLUMN_NAME = 'publicar_landing'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE tournaments ADD COLUMN publicar_landing TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''1=publicar en landing, 0=no publicar'' AFTER permite_inscripcion_linea',
    'SELECT ''Columna publicar_landing ya existe'' AS mensaje'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
