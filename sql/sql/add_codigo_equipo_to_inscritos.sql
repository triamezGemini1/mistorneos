-- Agregar columna codigo_equipo a la tabla inscritos
-- Esta columna almacena el código del equipo al que pertenece el jugador
-- Formato: "ccc-sss" (club 3 dígitos + "-" + secuencial 3 dígitos)

ALTER TABLE `inscritos` 
ADD COLUMN IF NOT EXISTS `codigo_equipo` VARCHAR(10) DEFAULT NULL COMMENT 'Código del equipo al que pertenece el jugador (formato: ccc-sss)' AFTER `id_club`;

-- Agregar índice para búsquedas por código de equipo
ALTER TABLE `inscritos` 
ADD INDEX IF NOT EXISTS `idx_codigo_equipo` (`codigo_equipo`);








