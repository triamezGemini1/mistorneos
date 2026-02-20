-- Agregar permite_inscripcion_linea a tournaments
-- 1 = permite inscripción en línea
-- 0 = solo inscripción en sitio (mostrar opción de contactar admin club)
-- Ejecutar: mysql -u usuario -p base_datos < add_permite_inscripcion_linea_tournaments.sql

SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tournaments' AND COLUMN_NAME = 'permite_inscripcion_linea'
);
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE tournaments ADD COLUMN permite_inscripcion_linea TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''1=permite inscripción en línea, 0=solo en sitio'' AFTER estatus',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
