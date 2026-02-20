-- =============================================================================
-- Migración Offline-First: columnas uuid, last_updated, sync_status
-- Ejecutar en MySQL (base de datos mistorneos)
-- Uso: mysql -u USUARIO -p mistorneos < sql/migrate_offline_first_columns.sql
-- =============================================================================

USE mistorneos;

-- -----------------------------------------------------------------------------
-- 1. USUARIOS (jugadores/usuarios del sistema)
-- La tabla usuarios puede tener ya uuid. Se añaden last_updated y sync_status si no existen.
-- -----------------------------------------------------------------------------
-- last_updated en usuarios:
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'mistorneos' AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'last_updated');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE usuarios ADD COLUMN last_updated TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'mistorneos' AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'sync_status');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE usuarios ADD COLUMN sync_status TINYINT NOT NULL DEFAULT 0 COMMENT ''0=sin cambios, 1=pendiente envío, 2=conflicto''', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Asegurar que uuid existe y es UNIQUE en usuarios (puede que ya exista):
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'mistorneos' AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'uuid');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE usuarios ADD COLUMN uuid CHAR(36) NULL UNIQUE COMMENT ''UUID v4 para sincronización''', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 2. TORNEOS (tournaments)
-- -----------------------------------------------------------------------------
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'mistorneos' AND TABLE_NAME = 'tournaments' AND COLUMN_NAME = 'uuid');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE tournaments ADD COLUMN uuid CHAR(36) NULL UNIQUE COMMENT ''UUID v4 para sincronización''', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'mistorneos' AND TABLE_NAME = 'tournaments' AND COLUMN_NAME = 'last_updated');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE tournaments ADD COLUMN last_updated TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'mistorneos' AND TABLE_NAME = 'tournaments' AND COLUMN_NAME = 'sync_status');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE tournaments ADD COLUMN sync_status TINYINT NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 3. INSCRITOS (transacciones inscripción usuario-torneo)
-- -----------------------------------------------------------------------------
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'mistorneos' AND TABLE_NAME = 'inscritos' AND COLUMN_NAME = 'uuid');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE inscritos ADD COLUMN uuid CHAR(36) NULL UNIQUE COMMENT ''UUID v4 para sincronización''', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'mistorneos' AND TABLE_NAME = 'inscritos' AND COLUMN_NAME = 'last_updated');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE inscritos ADD COLUMN last_updated TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'mistorneos' AND TABLE_NAME = 'inscritos' AND COLUMN_NAME = 'sync_status');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE inscritos ADD COLUMN sync_status TINYINT NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 4. PAYMENTS (transacciones de pago)
-- -----------------------------------------------------------------------------
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'mistorneos' AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'uuid');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE payments ADD COLUMN uuid CHAR(36) NULL UNIQUE COMMENT ''UUID v4 para sincronización''', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'mistorneos' AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'last_updated');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE payments ADD COLUMN last_updated TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'mistorneos' AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'sync_status');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE payments ADD COLUMN sync_status TINYINT NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índices para sincronización (omitir si ya existen)
-- CREATE INDEX idx_usuarios_uuid ON usuarios(uuid);
-- CREATE INDEX idx_usuarios_last_updated ON usuarios(last_updated);
-- CREATE INDEX idx_tournaments_uuid ON tournaments(uuid);
-- CREATE INDEX idx_tournaments_last_updated ON tournaments(last_updated);
-- CREATE INDEX idx_inscritos_uuid ON inscritos(uuid);
-- CREATE INDEX idx_inscritos_last_updated ON inscritos(last_updated);
-- CREATE INDEX idx_payments_uuid ON payments(uuid);
-- CREATE INDEX idx_payments_last_updated ON payments(last_updated);
