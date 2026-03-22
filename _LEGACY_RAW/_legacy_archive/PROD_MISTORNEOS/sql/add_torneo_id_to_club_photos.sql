-- Agregar columna torneo_id a club_photos para soportar fotos de torneos
-- La tabla puede almacenar fotos de clubes (club_id) o de torneos (torneo_id)

USE mistorneos;

-- Agregar columna torneo_id si no existe
ALTER TABLE club_photos 
ADD COLUMN IF NOT EXISTS torneo_id INT NULL AFTER club_id,
ADD COLUMN IF NOT EXISTS activa TINYINT(1) DEFAULT 1 AFTER fecha_subida,
ADD COLUMN IF NOT EXISTS titulo VARCHAR(255) NULL AFTER ruta_imagen,
ADD COLUMN IF NOT EXISTS descripcion TEXT NULL AFTER titulo;

-- Agregar índice para torneo_id
ALTER TABLE club_photos 
ADD INDEX IF NOT EXISTS idx_torneo_id (torneo_id);

-- Agregar foreign key para torneo_id
ALTER TABLE club_photos 
ADD CONSTRAINT fk_club_photos_torneo 
FOREIGN KEY (torneo_id) REFERENCES tournaments(id) 
ON DELETE CASCADE ON UPDATE CASCADE;

-- Hacer que club_id sea nullable para permitir fotos solo de torneos
ALTER TABLE club_photos 
MODIFY COLUMN club_id INT NULL;




