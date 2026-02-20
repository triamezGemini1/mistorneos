-- Script para agregar columnas de tasa de cambio a la tabla relacion_pagos
-- Fecha: 2025-11-12
-- Descripción: Agrega columnas para manejar pagos en bolívares con tasa de cambio BCV

-- Verificar y agregar columna 'tasa_cambio' si no existe
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'relacion_pagos'
    AND COLUMN_NAME = 'tasa_cambio'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE relacion_pagos ADD COLUMN tasa_cambio DECIMAL(10,2) DEFAULT 0 COMMENT ''Tasa de cambio BCV si el pago es en bolívares''',
    'SELECT ''La columna tasa_cambio ya existe'' AS resultado'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar y agregar columna 'monto_dolares' si no existe
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'relacion_pagos'
    AND COLUMN_NAME = 'monto_dolares'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE relacion_pagos ADD COLUMN monto_dolares DECIMAL(10,2) DEFAULT 0 COMMENT ''Monto equivalente en dólares (siempre se descuenta en dólares)''',
    'SELECT ''La columna monto_dolares ya existe'' AS resultado'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar y modificar columna 'moneda' si es necesario
-- (cambiar tipo de dato si existe y es diferente)
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'relacion_pagos'
    AND COLUMN_NAME = 'moneda'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE relacion_pagos ADD COLUMN moneda VARCHAR(3) DEFAULT ''USD'' COMMENT ''Moneda del pago: USD o BS''',
    'ALTER TABLE relacion_pagos MODIFY COLUMN moneda VARCHAR(3) DEFAULT ''USD'' COMMENT ''Moneda del pago: USD o BS'''
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Poblar monto_dolares con monto_total para registros existentes que no lo tengan
UPDATE relacion_pagos 
SET monto_dolares = monto_total 
WHERE monto_dolares = 0 OR monto_dolares IS NULL;

SELECT 'Script ejecutado exitosamente. Columnas agregadas/actualizadas.' AS resultado;

