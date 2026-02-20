-- Agregar permite_inscripcion_linea a clubes
-- 1 = el club permite que sus afiliados se inscriban en línea en torneos de su ámbito
-- 0 = solo inscripción en sitio
-- Ejecutar: mysql -u usuario -p base_datos < add_permite_inscripcion_linea_clubes.sql

SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clubes' AND COLUMN_NAME = 'permite_inscripcion_linea'
);
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE clubes ADD COLUMN permite_inscripcion_linea TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''1=permite inscripción en línea a afiliados, 0=solo en sitio'' AFTER estatus',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
