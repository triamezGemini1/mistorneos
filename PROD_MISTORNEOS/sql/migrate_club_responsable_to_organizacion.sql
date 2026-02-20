-- Migración: club_responsable ahora almacena el ID de la organización
-- En lugar de hacer referencia a clubes, ahora hace referencia a organizaciones

-- 1. Eliminar la FK existente a clubes
ALTER TABLE tournaments DROP FOREIGN KEY IF EXISTS fk_tournaments_club;

-- 2. Renombrar club_responsable a organizacion_responsable para mayor claridad
-- (Opcional: si se prefiere mantener el nombre, omitir este paso)
-- ALTER TABLE tournaments CHANGE club_responsable organizacion_responsable INT NULL;

-- 3. Migrar datos: convertir club_responsable a organizacion_id
-- Actualizar torneos existentes: usar organizacion_id si existe, sino obtener desde el club
UPDATE tournaments t
SET t.club_responsable = t.organizacion_id
WHERE t.organizacion_id IS NOT NULL;

-- Para torneos que tienen club pero no organización, obtener la organización del club
UPDATE tournaments t
INNER JOIN clubes c ON t.club_responsable = c.id
SET t.club_responsable = c.organizacion_id
WHERE t.organizacion_id IS NULL AND c.organizacion_id IS NOT NULL;

-- 4. Crear nueva FK a organizaciones (opcional, para integridad)
-- ALTER TABLE tournaments 
-- ADD CONSTRAINT fk_tournaments_organizacion_resp FOREIGN KEY (club_responsable) 
-- REFERENCES organizaciones(id) ON DELETE SET NULL ON UPDATE CASCADE;

-- Nota: La columna organizacion_id puede eliminarse posteriormente si ya no se necesita
-- ya que club_responsable ahora cumple esa función
