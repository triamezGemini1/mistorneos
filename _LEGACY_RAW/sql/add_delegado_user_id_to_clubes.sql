-- Responsable/delegado del club puede ser un usuario (admin_club o usuario registrado)
-- delegado_user_id: ID del usuario responsable. NULL = usar campo delegado (texto legacy)

ALTER TABLE clubes 
ADD COLUMN delegado_user_id INT NULL DEFAULT NULL 
COMMENT 'ID del usuario responsable del club (admin_club o usuario). NULL=usar delegado texto' 
AFTER delegado,
ADD KEY idx_delegado_user_id (delegado_user_id),
ADD CONSTRAINT fk_clubes_delegado_user 
  FOREIGN KEY (delegado_user_id) REFERENCES usuarios(id) 
  ON DELETE SET NULL ON UPDATE CASCADE;
