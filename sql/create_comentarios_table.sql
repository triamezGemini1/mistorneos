-- Tabla de comentarios para el sistema
-- Permite comentarios, sugerencias y testimonios con moderación

CREATE TABLE IF NOT EXISTS `comentarios` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `usuario_id` INT NULL COMMENT 'ID del usuario registrado (opcional)',
  `nombre` VARCHAR(100) NOT NULL COMMENT 'Nombre del autor (si no está registrado)',
  `email` VARCHAR(100) NULL COMMENT 'Email del autor (opcional)',
  `tipo` ENUM('comentario', 'sugerencia', 'testimonio') NOT NULL DEFAULT 'comentario',
  `contenido` TEXT NOT NULL COMMENT 'Contenido del comentario',
  `calificacion` TINYINT NULL COMMENT 'Calificación de 1 a 5 (opcional)',
  `estatus` ENUM('pendiente', 'aprobado', 'rechazado') NOT NULL DEFAULT 'pendiente',
  `ip_address` VARCHAR(45) NULL COMMENT 'IP del autor para seguridad',
  `user_agent` VARCHAR(255) NULL COMMENT 'User agent para seguridad',
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_aprobacion` TIMESTAMP NULL,
  `aprobado_por` INT NULL COMMENT 'ID del administrador que aprobó',
  `motivo_rechazo` TEXT NULL COMMENT 'Motivo del rechazo si aplica',
  PRIMARY KEY (`id`),
  KEY `idx_usuario_id` (`usuario_id`),
  KEY `idx_estatus` (`estatus`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_fecha_creacion` (`fecha_creacion`),
  CONSTRAINT `fk_comentarios_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_comentarios_aprobado_por` FOREIGN KEY (`aprobado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




