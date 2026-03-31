-- =====================================================
-- (NO USAR) Este script creaba la tabla inscripciones.
-- El flujo de invitación usa: 1) registro en usuarios (entidad=0, club_id=0)
-- y 2) inscripción en inscritos (id_usuario, torneo_id, id_club).
-- Para eliminar la tabla si fue creada: ejecutar sql/drop_inscripciones_table.sql
-- =====================================================

CREATE TABLE IF NOT EXISTS `inscripciones` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `torneo_id` INT NOT NULL,
  `club_id` INT NULL DEFAULT NULL COMMENT 'Club que inscribe (invitación o registro público)',
  `cedula` VARCHAR(20) NOT NULL,
  `nombre` VARCHAR(200) NOT NULL,
  `sexo` CHAR(1) NULL COMMENT 'M, F, O',
  `fechnac` DATE NULL,
  `celular` VARCHAR(30) NULL,
  `email` VARCHAR(100) NULL,
  `nacionalidad` CHAR(1) NULL DEFAULT 'V' COMMENT 'V, E, J, P',
  `identificador` VARCHAR(30) NULL COMMENT 'Código único de inscripción',
  `categoria` VARCHAR(50) NULL,
  `estatus` TINYINT NOT NULL DEFAULT 1 COMMENT '1=activo, 0=retirado, etc.',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_inscripcion` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inscripciones_torneo_club` (`torneo_id`, `club_id`),
  KEY `idx_inscripciones_cedula_torneo` (`cedula`, `torneo_id`),
  KEY `idx_inscripciones_cedula` (`cedula`),
  KEY `idx_inscripciones_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
