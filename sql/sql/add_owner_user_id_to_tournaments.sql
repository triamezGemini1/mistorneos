-- Agregar owner_user_id y entidad a tournaments
-- owner_user_id = ID del usuario admin que registra el torneo (no puede ser 0 ni diferente al admin)
-- entidad = Código de entidad del admin (no puede ser 0, debe ser la misma del admin)
-- Ejecutar: mysql -u usuario -p base_datos < add_owner_user_id_to_tournaments.sql

-- owner_user_id
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tournaments' AND COLUMN_NAME = 'owner_user_id'
);
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE tournaments ADD COLUMN owner_user_id INT NULL COMMENT ''ID del usuario admin que registra el torneo'' AFTER club_responsable',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- entidad
SET @col_ent = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tournaments' AND COLUMN_NAME = 'entidad'
);
SET @sql2 = IF(@col_ent = 0, 
    'ALTER TABLE tournaments ADD COLUMN entidad INT NULL DEFAULT 0 COMMENT ''Código de entidad del admin que registra'' AFTER club_responsable',
    'SELECT 1'
);
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
