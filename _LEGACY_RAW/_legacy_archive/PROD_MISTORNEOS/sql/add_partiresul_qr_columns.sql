-- Migración: Columnas para recepción de resultados vía QR
-- Ejecutar en la base de datos mistorneos
-- Nota: Ejecutar add_foto_acta_partiresul.sql antes si foto_acta no existe

-- foto_acta (omitir si ya existe)
-- ALTER TABLE partiresul ADD COLUMN foto_acta VARCHAR(255) NULL AFTER observaciones;

-- origen_dato: admin = panel, qr = envío público por QR
ALTER TABLE `partiresul`
  ADD COLUMN `origen_dato` ENUM('admin','qr') NOT NULL DEFAULT 'admin'
  COMMENT 'Origen: admin=panel, qr=envío público';

-- estatus: pendiente_verificacion para QR hasta que admin confirme
ALTER TABLE `partiresul`
  ADD COLUMN `estatus` ENUM('confirmado','pendiente_verificacion') NOT NULL DEFAULT 'confirmado'
  COMMENT 'confirmado=aprobado, pendiente_verificacion=QR pendiente de revisar';
