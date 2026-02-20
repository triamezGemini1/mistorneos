-- Migración: usuarios.status de ENUM('pending','approved','rejected') a TINYINT
-- 0 = activo, 1 = inactivo
-- Ejecutar solo si la columna status es actualmente ENUM. Si ya es TINYINT, normalizar con el UPDATE final.

-- Paso 1: añadir columna temporal
ALTER TABLE usuarios
  ADD COLUMN status_new TINYINT NOT NULL DEFAULT 0 COMMENT '0=activo, 1=inactivo' AFTER entidad;

-- Paso 2: migrar valores (approved/activo -> 0, resto -> 1)
UPDATE usuarios SET status_new = CASE
  WHEN BINARY status IN ('approved', 'active', 'activo') OR status = 1 OR status = '1' THEN 0
  ELSE 1
END;

-- Paso 3: eliminar columna antigua y renombrar (si falla por "Unknown column status", la columna ya es status_new: solo ejecutar el CHANGE)
ALTER TABLE usuarios DROP COLUMN status;
ALTER TABLE usuarios CHANGE COLUMN status_new status TINYINT NOT NULL DEFAULT 0 COMMENT '0=activo, 1=inactivo';
ALTER TABLE usuarios ADD KEY idx_status (status);
