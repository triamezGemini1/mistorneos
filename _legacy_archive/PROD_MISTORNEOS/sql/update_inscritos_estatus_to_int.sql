-- =====================================================
-- MIGRACIÓN: Cambiar campo estatus de ENUM a INT en tabla inscripciones
-- Fecha: 2025-01-XX
-- Descripción: Convierte el campo estatus de ENUM/TINYINT a INT para mayor flexibilidad
-- =====================================================
-- NOTA: La base de datos se selecciona automáticamente por la conexión PDO

-- Paso 1: Crear tabla temporal para respaldar datos
CREATE TABLE IF NOT EXISTS `inscripciones_backup_estatus` AS 
SELECT id, estatus FROM `inscripciones` WHERE 1=0;

-- Paso 2: Si la tabla inscripciones ya existe con estatus ENUM, migrar datos
-- Mapeo: pendiente=0, confirmado=1, solvente=2, no_solvente=3, retirado=4
-- Nota: Si estatus es TINYINT con valores 0/1, se mantiene como está
UPDATE `inscripciones` 
SET `estatus` = CASE 
    WHEN `estatus` = 'pendiente' THEN 0
    WHEN `estatus` = 'confirmado' THEN 1
    WHEN `estatus` = 'solvente' THEN 2
    WHEN `estatus` = 'no_solvente' THEN 3
    WHEN `estatus` = 'retirado' THEN 4
    WHEN CAST(`estatus` AS CHAR) IN ('0', '1', '2', '3', '4') THEN CAST(`estatus` AS UNSIGNED)
    ELSE 0
END
WHERE `estatus` IN ('pendiente', 'confirmado', 'solvente', 'no_solvente', 'retirado')
   OR CAST(`estatus` AS CHAR) IN ('0', '1', '2', '3', '4');

-- Paso 3: Modificar columna estatus de ENUM/TINYINT a INT
ALTER TABLE `inscripciones` 
MODIFY COLUMN `estatus` int DEFAULT '0' COMMENT 'Estatus: 0=pendiente, 1=confirmado, 2=solvente, 3=no_solvente, 4=retirado';

-- =====================================================
-- NOTAS IMPORTANTES:
-- =====================================================
-- 1. Los valores se mapean así:
--    - 0 = pendiente
--    - 1 = confirmado
--    - 2 = solvente
--    - 3 = no_solvente
--    - 4 = retirado
-- 2. El valor por defecto es 0 (pendiente)
-- 3. Usar InscritosHelper para convertir entre número y texto
-- 4. En formularios, usar valores numéricos pero mostrar texto
-- =====================================================

