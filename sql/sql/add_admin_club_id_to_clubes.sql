-- Relación directa entre clubes y admin_club mediante admin_club_id
-- Cada club pertenece a un admin_club (usuario con role='admin_club')

ALTER TABLE clubes 
ADD COLUMN admin_club_id INT NULL DEFAULT NULL 
COMMENT 'ID del usuario admin_club que gestiona este club' 
AFTER estatus,
ADD KEY idx_admin_club_id (admin_club_id),
ADD CONSTRAINT fk_clubes_admin_club 
  FOREIGN KEY (admin_club_id) REFERENCES usuarios(id) 
  ON DELETE SET NULL ON UPDATE CASCADE;

-- Migrar datos existentes: clubes donde el admin tiene club_id
UPDATE clubes c
INNER JOIN usuarios u ON u.club_id = c.id AND u.role = 'admin_club'
SET c.admin_club_id = u.id
WHERE c.admin_club_id IS NULL;

-- (Omitido: tabla clubes_asociados ya no existe. Migración por organizacion_id.)
