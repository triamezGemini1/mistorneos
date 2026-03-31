-- Agrega campo entidad (ubicación geográfica) a usuarios
ALTER TABLE usuarios
  ADD COLUMN entidad INT NOT NULL DEFAULT 0 COMMENT 'Código de la tabla entidad (ubicación geográfica)' AFTER club_id;

-- Opcional: si la tabla entidad existe y usa columna codigo como PK, se puede crear el FK
-- ALTER TABLE usuarios
--   ADD CONSTRAINT fk_usuarios_entidad FOREIGN KEY (entidad) REFERENCES entidad(codigo);



