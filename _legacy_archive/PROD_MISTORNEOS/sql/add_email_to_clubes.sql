-- Agregar columna email a la tabla clubes para invitaciones
-- Ejecutar: mysql -u usuario -p base_datos < add_email_to_clubes.sql

ALTER TABLE clubes ADD COLUMN email VARCHAR(255) NULL AFTER telefono;
