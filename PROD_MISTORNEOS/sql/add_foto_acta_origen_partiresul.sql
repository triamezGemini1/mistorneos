-- =====================================================
-- MIGRACIÓN: Columnas foto_acta, origen_dato, estatus en partiresul
-- Para envío público de resultados vía QR
-- =====================================================

-- Columna foto_acta: ruta relativa del archivo de imagen del acta
-- (Si la columna ya existe, ignorar el error)
ALTER TABLE `partiresul` ADD COLUMN `foto_acta` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Ruta imagen acta (envío QR)' AFTER `observaciones`;

-- Columna origen_dato: quién registró (admin o qr)
ALTER TABLE `partiresul` ADD COLUMN `origen_dato` ENUM('admin', 'qr') NULL DEFAULT NULL COMMENT 'Origen del registro' AFTER `foto_acta`;

-- Columna estatus: pendiente_verificacion para envíos QR
ALTER TABLE `partiresul` ADD COLUMN `estatus` VARCHAR(50) NULL DEFAULT NULL COMMENT 'confirmado, pendiente_verificacion' AFTER `origen_dato`;
