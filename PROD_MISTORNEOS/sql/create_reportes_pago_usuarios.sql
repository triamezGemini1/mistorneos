-- Tabla para reportes de pago de usuarios individuales en eventos masivos
CREATE TABLE IF NOT EXISTS reportes_pago_usuarios (
  id INT NOT NULL AUTO_INCREMENT,
  id_usuario INT NOT NULL COMMENT 'ID del usuario que reporta el pago',
  torneo_id INT NOT NULL COMMENT 'ID del torneo',
  inscrito_id INT UNSIGNED DEFAULT NULL COMMENT 'ID de la inscripción en la tabla inscritos',
  cantidad_inscritos INT NOT NULL DEFAULT 1 COMMENT 'Cantidad de personas inscritas (si inscribe a más de 1)',
  fecha DATE NOT NULL COMMENT 'Fecha del pago',
  hora TIME NOT NULL COMMENT 'Hora del pago',
  tipo_pago ENUM('transferencia', 'pagomovil', 'efectivo') NOT NULL COMMENT 'Tipo de pago',
  banco VARCHAR(100) DEFAULT NULL COMMENT 'Banco (para transferencia o pagomovil)',
  monto DECIMAL(10,2) NOT NULL COMMENT 'Monto del pago',
  referencia VARCHAR(100) DEFAULT NULL COMMENT 'Número de referencia de la transacción',
  comentarios TEXT DEFAULT NULL COMMENT 'Comentarios adicionales',
  estatus ENUM('pendiente', 'confirmado', 'rechazado') NOT NULL DEFAULT 'pendiente' COMMENT 'Estado del pago',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación del reporte',
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última actualización',
  PRIMARY KEY (id),
  KEY idx_usuario (id_usuario),
  KEY idx_torneo (torneo_id),
  KEY idx_inscrito (inscrito_id),
  KEY idx_estatus (estatus),
  KEY idx_fecha (fecha),
  CONSTRAINT fk_rpu_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_rpu_torneo FOREIGN KEY (torneo_id) REFERENCES tournaments(id) ON DELETE CASCADE,
  CONSTRAINT fk_rpu_inscrito FOREIGN KEY (inscrito_id) REFERENCES inscritos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Reportes de pago de usuarios individuales en eventos masivos';

