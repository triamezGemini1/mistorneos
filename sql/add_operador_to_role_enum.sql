-- Añadir 'operador' al ENUM de role en usuarios
-- El rol operador debe existir para asignar operadores de torneo

ALTER TABLE usuarios 
MODIFY COLUMN role ENUM('admin_general','admin_torneo','admin_club','usuario','operador') NOT NULL DEFAULT 'usuario';
