-- Script de migración: eliminar campos antiguos de pago en línea de tournaments
-- Ejecutar DESPUÉS de migrar los datos a la nueva tabla cuentas_bancarias

-- Eliminar campos antiguos de pago en línea
ALTER TABLE tournaments 
DROP COLUMN IF EXISTS `pago_en_linea_habilitado`,
DROP COLUMN IF EXISTS `banco_principal`,
DROP COLUMN IF EXISTS `cuenta_principal`,
DROP COLUMN IF EXISTS `tipo_cuenta_principal`,
DROP COLUMN IF EXISTS `telefono_pagomovil`,
DROP COLUMN IF EXISTS `banco_secundario`,
DROP COLUMN IF EXISTS `cuenta_secundaria`,
DROP COLUMN IF EXISTS `tipo_cuenta_secundaria`,
DROP COLUMN IF EXISTS `telefono_pagomovil_secundario`,
DROP COLUMN IF EXISTS `api_banco_habilitada`,
DROP COLUMN IF EXISTS `api_banco_proveedor`,
DROP COLUMN IF EXISTS `api_banco_endpoint`,
DROP COLUMN IF EXISTS `api_banco_token`,
DROP COLUMN IF EXISTS `api_banco_config`,
DROP COLUMN IF EXISTS `instrucciones_pago`;

