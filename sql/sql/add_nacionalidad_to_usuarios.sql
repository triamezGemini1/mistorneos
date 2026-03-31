-- NO EJECUTAR si la tabla usuarios ya tiene la columna nacionalidad (como en producción).
-- Solo para instalaciones antiguas donde nacionalidad no existía.

-- ALTER TABLE usuarios
--   ADD COLUMN nacionalidad CHAR(1) NOT NULL DEFAULT 'V'
--   COMMENT 'V=Venezolano, E=Extranjero, J=Jurídico, P=Pasaporte'
--   AFTER cedula;
