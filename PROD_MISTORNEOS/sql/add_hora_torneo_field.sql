-- Agregar campo hora_torneo a tournaments (hora de inicio del torneo)
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'tournaments' 
    AND COLUMN_NAME = 'hora_torneo'
);
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE tournaments ADD COLUMN hora_torneo TIME NULL COMMENT "Hora de inicio del torneo" AFTER fechator',
    'SELECT "Columna hora_torneo ya existe" AS mensaje'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
