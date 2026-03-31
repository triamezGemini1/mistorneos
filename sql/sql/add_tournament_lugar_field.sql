-- Agregar campo 'lugar' a la tabla tournaments
-- Este campo almacena el lugar donde se realizará el torneo

ALTER TABLE tournaments 
ADD COLUMN lugar VARCHAR(255) NULL AFTER fechator;

-- Verificar que se agregó correctamente
DESCRIBE tournaments;


