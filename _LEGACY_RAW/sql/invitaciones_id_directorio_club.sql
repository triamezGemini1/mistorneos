-- Añadir id_directorio_club a invitaciones (trazabilidad con directorio_clubes).
-- Opcional: el módulo de invitación funciona sin esta columna (usa club_id).
-- Ejecutar una sola vez. Si la columna ya existe, omitir.

ALTER TABLE invitaciones
ADD COLUMN id_directorio_club INT NULL DEFAULT NULL COMMENT 'ID en directorio_clubes del club invitado (cuando la invitación se crea desde el directorio)'
AFTER club_id;

CREATE INDEX idx_invitaciones_id_directorio_club ON invitaciones(id_directorio_club);
