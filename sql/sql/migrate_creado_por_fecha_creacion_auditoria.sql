-- =============================================================================
-- Huella de registro en usuarios (jugadores) y tabla de auditoría
-- Ejecutar en MySQL de producción una sola vez.
-- =============================================================================
USE mistorneos;

-- 1) Campos creado_por y fecha_creacion en usuarios
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'mistorneos' AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'creado_por');
SET @sql = IF(@col = 0, 'ALTER TABLE usuarios ADD COLUMN creado_por INT NULL COMMENT ''ID del usuario que registró al jugador''', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col2 = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'mistorneos' AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'fecha_creacion');
SET @sql2 = IF(@col2 = 0, 'ALTER TABLE usuarios ADD COLUMN fecha_creacion DATETIME NULL COMMENT ''Fecha/hora de registro del jugador''', 'SELECT 1');
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- 2) Tabla auditoria (logs para panel y sincronización)
CREATE TABLE IF NOT EXISTS auditoria (
  id INT NOT NULL AUTO_INCREMENT,
  usuario_id INT NOT NULL COMMENT 'ID del administrador que realizó la acción',
  accion VARCHAR(64) NOT NULL COMMENT 'registro_jugador, modifico_estado_torneo, etc.',
  detalle TEXT NULL COMMENT 'Texto libre o JSON',
  entidad_tipo VARCHAR(32) NULL COMMENT 'jugador, torneo',
  entidad_id INT NULL,
  organizacion_id INT NULL COMMENT 'Para filtrar por organización',
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sync_status TINYINT NOT NULL DEFAULT 0 COMMENT '0=pendiente subir a web',
  PRIMARY KEY (id),
  KEY idx_auditoria_fecha (fecha),
  KEY idx_auditoria_usuario (usuario_id),
  KEY idx_auditoria_organizacion (organizacion_id),
  KEY idx_auditoria_sync (sync_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
