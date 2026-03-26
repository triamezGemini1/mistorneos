-- =====================================================
-- MIGRACIÓN: Cambiar campo estatus de ENUM a INT en tabla inscritos
-- Fecha: 2025-01-17
-- Descripción: Convierte el campo estatus de ENUM a INT para evitar problemas de truncamiento
-- =====================================================

-- Paso 1: Convertir valores ENUM existentes a números INT
-- Mapeo: pendiente=0, confirmado=1, solvente=2, no_solvente=3, retirado=4
UPDATE `inscritos` 
SET `estatus` = CASE 
    WHEN `estatus` = 'pendiente' THEN 0
    WHEN `estatus` = 'confirmado' THEN 1
    WHEN `estatus` = 'solvente' THEN 2
    WHEN `estatus` = 'no_solvente' THEN 3
    WHEN `estatus` = 'retirado' THEN 4
    ELSE 0
END
WHERE `estatus` IN ('pendiente', 'confirmado', 'solvente', 'no_solvente', 'retirado');

-- Paso 2: Modificar columna estatus de ENUM a INT
ALTER TABLE `inscritos` 
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
-- 3. Después de esta migración, el código debe usar números directamente
-- =====================================================
