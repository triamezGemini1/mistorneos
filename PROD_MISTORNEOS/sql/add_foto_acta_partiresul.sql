-- Agregar columna foto_acta a partiresul para almacenar la ruta del acta (foto) de la mesa
-- Ejecutar en la base de datos mistorneos

ALTER TABLE `partiresul`
  ADD COLUMN `foto_acta` VARCHAR(255) NULL DEFAULT NULL
  COMMENT 'Ruta relativa del acta/foto de la mesa (ej: upload/actas_torneos/xxx.jpg)' 
  AFTER `observaciones`;
