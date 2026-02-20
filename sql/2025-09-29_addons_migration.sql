USE mistorneos;
ALTER TABLE users
  ADD COLUMN club_id INT NULL AFTER email,
  ADD COLUMN must_change_password TINYINT NOT NULL DEFAULT 0 AFTER status,
  ADD CONSTRAINT fk_users_club FOREIGN KEY (club_id) REFERENCES clubs(id)
    ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE invitations
  ADD COLUMN invitado_delegado VARCHAR(100) NULL AFTER club_id,
  ADD COLUMN invitado_email    VARCHAR(120) NULL AFTER invitado_delegado;
CREATE INDEX idx_users_club ON users(club_id);
CREATE INDEX idx_inv_email ON invitations(invitado_email);
