-- Agregar campo entidad a la tabla tournaments
-- La entidad se hereda del administrador del club que crea el torneo

ALTER TABLE tournaments 
ADD COLUMN IF NOT EXISTS entidad INT NOT NULL DEFAULT 0 
COMMENT 'Código de entidad (organización/federación) - heredado del admin_club'
AFTER club_responsable;

-- Crear índice para búsquedas por entidad
CREATE INDEX IF NOT EXISTS idx_tournaments_entidad ON tournaments(entidad);

-- Actualizar torneos existentes con la entidad del admin del club responsable
UPDATE tournaments t
INNER JOIN clubes c ON t.club_responsable = c.id
INNER JOIN usuarios u ON c.admin_club_id = u.id
SET t.entidad = u.entidad
WHERE t.entidad = 0 AND u.entidad > 0;
