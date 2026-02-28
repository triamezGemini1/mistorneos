-- admin_club_id: valor por defecto 0. Debe guardarse el id del admin/organización que hace la invitación.
-- Ejecutar si la tabla invitaciones tiene admin_club_id NOT NULL sin default.

ALTER TABLE invitaciones
MODIFY COLUMN admin_club_id INT NULL DEFAULT 0 COMMENT 'ID del usuario admin_club o de la organización que hace la invitación. 0 si no aplica.';
