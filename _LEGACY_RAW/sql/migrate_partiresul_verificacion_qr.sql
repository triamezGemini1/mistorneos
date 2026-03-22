-- =====================================================
-- MIGRACIÓN: partiresul para Verificación de Actas QR
-- Fecha: 2025-02
-- Descripción: Añade/modifica columnas estatus, origen_dato, foto_acta
-- Ejecutar en orden. Si alguna columna ya existe, omitir el ALTER correspondiente.
-- =====================================================

-- 1. foto_acta: ruta de la imagen del acta subida por QR
-- Si la columna ya existe: Error 1060 Duplicate column - ignorar
ALTER TABLE partiresul ADD COLUMN foto_acta VARCHAR(255) NULL DEFAULT NULL COMMENT 'Ruta imagen acta (envío QR)';

-- 2. origen_dato: quién registró (admin o qr)
-- Si la columna ya existe: Error 1060 - ignorar
ALTER TABLE partiresul ADD COLUMN origen_dato ENUM('admin','qr') NULL DEFAULT NULL COMMENT 'Origen del registro';

-- 3. estatus: migrar de TINYINT a VARCHAR(50)
-- Valores: 'pendiente_verificacion' (por defecto), 'confirmado'
-- Si estatus es TINYINT: convertir 1 -> 'confirmado', 0/otros -> 'pendiente_verificacion'

-- 3a. Agregar columna temporal
ALTER TABLE partiresul ADD COLUMN estatus_vqr VARCHAR(50) NULL DEFAULT 'pendiente_verificacion';

-- 3b. Migrar datos (si estatus existe como numérico)
UPDATE partiresul SET estatus_vqr = CASE
    WHEN CAST(COALESCE(estatus, 0) AS SIGNED) = 1 THEN 'confirmado'
    ELSE 'pendiente_verificacion'
END;

-- 3c. Eliminar columna estatus antigua (si existe)
ALTER TABLE partiresul DROP COLUMN estatus;

-- 3d. Renombrar estatus_vqr a estatus
ALTER TABLE partiresul CHANGE COLUMN estatus_vqr estatus VARCHAR(50) NOT NULL DEFAULT 'pendiente_verificacion' COMMENT 'confirmado, pendiente_verificacion';

-- ALTERNATIVA si estatus NO existía:
-- Omitir 3b y 3c. Ejecutar solo:
--   ALTER TABLE partiresul CHANGE COLUMN estatus_vqr estatus VARCHAR(50) NOT NULL DEFAULT 'pendiente_verificacion';
