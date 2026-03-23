-- Permitir invitaciones solo desde directorio (sin vincular a tabla clubes).
-- El directorio de clubes es auxiliar: solo crea la invitación y la prepara para envío al celular.
-- Ejecutar una sola vez. Si club_id ya es NULL, ignorar el error.

ALTER TABLE invitaciones
MODIFY COLUMN club_id INT NULL DEFAULT NULL COMMENT 'Opcional: club en sistema. NULL cuando la invitación es solo por directorio_clubes.';
