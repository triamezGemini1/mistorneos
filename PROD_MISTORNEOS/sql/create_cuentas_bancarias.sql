-- Tabla para cuentas bancarias receptoras de pagos
CREATE TABLE IF NOT EXISTS `cuentas_bancarias` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `cedula_propietario` VARCHAR(20) NOT NULL COMMENT 'Cédula del propietario de la cuenta',
  `nombre_propietario` VARCHAR(255) NOT NULL COMMENT 'Nombre del propietario',
  `telefono_afiliado` VARCHAR(20) NULL COMMENT 'Teléfono para pago móvil',
  `banco` VARCHAR(100) NOT NULL COMMENT 'Nombre del banco',
  `numero_cuenta` VARCHAR(50) NULL COMMENT 'Número de cuenta',
  `tipo_cuenta` ENUM('corriente', 'ahorro', 'pagomovil') NULL COMMENT 'Tipo de cuenta',
  `estatus` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = Activa, 0 = Inactiva',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_estatus` (`estatus`),
  INDEX `idx_cedula` (`cedula_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cuentas bancarias receptoras de pagos para torneos';

-- Agregar campo cuenta_id a tournaments (reemplazando los campos de pago anteriores)
ALTER TABLE tournaments 
ADD COLUMN `cuenta_id` INT NULL COMMENT 'ID de la cuenta bancaria asociada para pagos' AFTER `es_evento_masivo`,
ADD INDEX `idx_cuenta_id` (`cuenta_id`),
ADD CONSTRAINT `fk_tournaments_cuenta` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas_bancarias`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Agregar campo cuenta_id a reportes_pago_usuarios
ALTER TABLE `reportes_pago_usuarios`
ADD COLUMN `cuenta_id` INT NULL COMMENT 'ID de la cuenta bancaria donde se realizó el pago' AFTER `torneo_id`,
ADD INDEX `idx_cuenta_id` (`cuenta_id`),
ADD CONSTRAINT `fk_rpu_cuenta` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas_bancarias`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

