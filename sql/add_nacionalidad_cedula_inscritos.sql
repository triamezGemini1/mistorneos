-- Añadir columnas nacionalidad y cedula a inscritos para búsqueda directa (NIVEL 1).
-- Ejecutar una sola vez. Si las columnas ya existen, omitir o usar el script PHP que comprueba.

-- 1) Añadir columnas (valores por defecto para filas existentes)
ALTER TABLE `inscritos`
  ADD COLUMN IF NOT EXISTS `nacionalidad` CHAR(1) NOT NULL DEFAULT 'V' COMMENT 'V, E, J, P',
  ADD COLUMN IF NOT EXISTS `cedula` VARCHAR(20) NOT NULL DEFAULT '' COMMENT 'Cédula del inscrito (réplica para búsqueda)';

-- 2) Rellenar filas existentes desde usuarios (ejecutar solo si hay registros con cedula vacía)
-- UPDATE inscritos i
-- INNER JOIN usuarios u ON u.id = i.id_usuario
-- SET i.nacionalidad = COALESCE(NULLIF(TRIM(u.nacionalidad), ''), 'V'),
--     i.cedula = COALESCE(NULLIF(TRIM(u.cedula), ''), '')
-- WHERE i.cedula = '' OR i.cedula IS NULL;

-- 3) Índice para búsqueda estricta: WHERE torneo_id = ? AND nacionalidad = ? AND cedula = ?
-- ALTER TABLE `inscritos` ADD INDEX `idx_inscritos_torneo_nac_cedula` (`torneo_id`, `nacionalidad`, `cedula`);
