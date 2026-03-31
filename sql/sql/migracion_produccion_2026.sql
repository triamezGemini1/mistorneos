-- ============================================================
-- MIGRACIÓN A PRODUCCIÓN - ENERO 2026
-- Sistema de Gestión de Torneos - La Estación del Dominó
-- ============================================================
-- 
-- Este script incluye todas las actualizaciones de base de datos
-- para las nuevas funcionalidades:
-- - Eventos Masivos Nacionales
-- - Sistema de Cuentas Bancarias
-- - Reportes de Pago de Usuarios
-- - Cronómetro de Ronda
-- - Podios de Equipos
-- 
-- IMPORTANTE: Ejecutar este script ANTES de subir los archivos
-- ============================================================

USE laestaci1_mistorneos;

-- ============================================================
-- 1. EVENTOS MASIVOS NACIONALES
-- ============================================================

-- Agregar columna para marcar eventos masivos
ALTER TABLE tournaments 
ADD COLUMN IF NOT EXISTS es_evento_masivo TINYINT(1) NOT NULL DEFAULT 0 
COMMENT '1 = Evento masivo con inscripción pública, 0 = Torneo normal' 
AFTER estatus;

-- Agregar índice para búsquedas rápidas
CREATE INDEX IF NOT EXISTS idx_es_evento_masivo ON tournaments(es_evento_masivo, fechator);

-- ============================================================
-- 2. SISTEMA DE CUENTAS BANCARIAS
-- ============================================================

-- Crear tabla de cuentas bancarias
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

-- Agregar campo cuenta_id a tournaments
ALTER TABLE tournaments 
ADD COLUMN IF NOT EXISTS `cuenta_id` INT NULL 
COMMENT 'ID de la cuenta bancaria asociada para pagos' 
AFTER `es_evento_masivo`;

-- Agregar índices y foreign keys para cuenta_id en tournaments
-- (Verificar si ya existen antes de crear)
SET @index_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'tournaments' 
    AND INDEX_NAME = 'idx_cuenta_id'
);

SET @sql = IF(@index_exists = 0, 
    'CREATE INDEX idx_cuenta_id ON tournaments(cuenta_id)',
    'SELECT "Índice idx_cuenta_id ya existe" AS mensaje'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Agregar foreign key para cuenta_id en tournaments
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'tournaments' 
    AND CONSTRAINT_NAME = 'fk_tournaments_cuenta'
);

SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE tournaments ADD CONSTRAINT fk_tournaments_cuenta FOREIGN KEY (cuenta_id) REFERENCES cuentas_bancarias(id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT "Foreign key fk_tournaments_cuenta ya existe" AS mensaje'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- 3. REPORTES DE PAGO DE USUARIOS
-- ============================================================

-- Crear tabla de reportes de pago
CREATE TABLE IF NOT EXISTS `reportes_pago_usuarios` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_usuario` INT NOT NULL COMMENT 'ID del usuario que reporta el pago',
  `torneo_id` INT NOT NULL COMMENT 'ID del torneo',
  `cuenta_id` INT NULL COMMENT 'ID de la cuenta bancaria donde se realizó el pago',
  `inscrito_id` INT UNSIGNED DEFAULT NULL COMMENT 'ID de la inscripción en la tabla inscritos',
  `cantidad_inscritos` INT NOT NULL DEFAULT 1 COMMENT 'Cantidad de personas inscritas (si inscribe a más de 1)',
  `fecha` DATE NOT NULL COMMENT 'Fecha del pago',
  `hora` TIME NOT NULL COMMENT 'Hora del pago',
  `tipo_pago` ENUM('transferencia', 'pagomovil', 'efectivo') NOT NULL COMMENT 'Tipo de pago',
  `banco` VARCHAR(100) DEFAULT NULL COMMENT 'Banco (para transferencia o pagomovil)',
  `monto` DECIMAL(10,2) NOT NULL COMMENT 'Monto del pago',
  `referencia` VARCHAR(100) DEFAULT NULL COMMENT 'Número de referencia de la transacción',
  `comentarios` TEXT DEFAULT NULL COMMENT 'Comentarios adicionales',
  `estatus` ENUM('pendiente', 'confirmado', 'rechazado') NOT NULL DEFAULT 'pendiente' COMMENT 'Estado del pago',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación del reporte',
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última actualización',
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_torneo` (`torneo_id`),
  KEY `idx_cuenta_id` (`cuenta_id`),
  KEY `idx_inscrito` (`inscrito_id`),
  KEY `idx_estatus` (`estatus`),
  KEY `idx_fecha` (`fecha`),
  CONSTRAINT `fk_rpu_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rpu_torneo` FOREIGN KEY (`torneo_id`) REFERENCES `tournaments`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rpu_inscrito` FOREIGN KEY (`inscrito_id`) REFERENCES `inscritos`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Reportes de pago de usuarios individuales en eventos masivos';

-- Agregar foreign key para cuenta_id en reportes_pago_usuarios (si no existe)
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reportes_pago_usuarios' 
    AND CONSTRAINT_NAME = 'fk_rpu_cuenta'
);

SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE reportes_pago_usuarios ADD CONSTRAINT fk_rpu_cuenta FOREIGN KEY (cuenta_id) REFERENCES cuentas_bancarias(id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT "Foreign key fk_rpu_cuenta ya existe" AS mensaje'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Permitir que numero_cuenta y tipo_cuenta sean NULL en cuentas_bancarias
-- (Esto puede ejecutarse múltiples veces sin problemas)
ALTER TABLE `cuentas_bancarias` 
MODIFY COLUMN `numero_cuenta` VARCHAR(50) NULL COMMENT 'Número de cuenta',
MODIFY COLUMN `tipo_cuenta` ENUM('corriente', 'ahorro', 'pagomovil') NULL COMMENT 'Tipo de cuenta';

-- ============================================================
-- 4. VERIFICACIONES Y LIMPIEZA
-- ============================================================

-- Verificar que todas las columnas se crearon correctamente
SELECT 
    'Verificación de columnas' AS tipo,
    TABLE_NAME AS tabla,
    COLUMN_NAME AS columna,
    COLUMN_TYPE AS tipo_columna
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND (
    (TABLE_NAME = 'tournaments' AND COLUMN_NAME IN ('es_evento_masivo', 'cuenta_id'))
    OR (TABLE_NAME = 'cuentas_bancarias')
    OR (TABLE_NAME = 'reportes_pago_usuarios')
)
ORDER BY TABLE_NAME, ORDINAL_POSITION;

-- ============================================================
-- FIN DE LA MIGRACIÓN
-- ============================================================
-- 
-- Después de ejecutar este script:
-- 1. Verificar que no haya errores
-- 2. Subir los archivos nuevos a producción
-- 3. Verificar permisos de archivos
-- 4. Probar las nuevas funcionalidades
-- 
-- ============================================================

