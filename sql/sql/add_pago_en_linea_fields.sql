-- Agregar campos de pago en línea a la tabla tournaments
ALTER TABLE tournaments 
ADD COLUMN pago_en_linea_habilitado TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Pago en línea habilitado, 0 = Deshabilitado' AFTER es_evento_masivo,
ADD COLUMN banco_principal VARCHAR(100) NULL COMMENT 'Nombre del banco principal' AFTER pago_en_linea_habilitado,
ADD COLUMN cuenta_principal VARCHAR(50) NULL COMMENT 'Número de cuenta principal' AFTER banco_principal,
ADD COLUMN tipo_cuenta_principal ENUM('corriente', 'ahorro', 'pagomovil') NULL COMMENT 'Tipo de cuenta principal' AFTER cuenta_principal,
ADD COLUMN telefono_pagomovil VARCHAR(20) NULL COMMENT 'Teléfono para pago móvil' AFTER tipo_cuenta_principal,
ADD COLUMN banco_secundario VARCHAR(100) NULL COMMENT 'Nombre del banco secundario (opcional)' AFTER telefono_pagomovil,
ADD COLUMN cuenta_secundaria VARCHAR(50) NULL COMMENT 'Número de cuenta secundaria (opcional)' AFTER banco_secundario,
ADD COLUMN tipo_cuenta_secundaria ENUM('corriente', 'ahorro', 'pagomovil') NULL COMMENT 'Tipo de cuenta secundaria' AFTER cuenta_secundaria,
ADD COLUMN telefono_pagomovil_secundario VARCHAR(20) NULL COMMENT 'Teléfono secundario para pago móvil' AFTER tipo_cuenta_secundaria,
ADD COLUMN api_banco_habilitada TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = API bancaria habilitada para validación, 0 = Deshabilitada' AFTER telefono_pagomovil_secundario,
ADD COLUMN api_banco_proveedor VARCHAR(50) NULL COMMENT 'Proveedor de API bancaria (banesco, mercantil, venezuela, etc.)' AFTER api_banco_habilitada,
ADD COLUMN api_banco_endpoint VARCHAR(255) NULL COMMENT 'URL del endpoint de la API bancaria' AFTER api_banco_proveedor,
ADD COLUMN api_banco_token TEXT NULL COMMENT 'Token de autenticación para la API bancaria' AFTER api_banco_endpoint,
ADD COLUMN api_banco_config TEXT NULL COMMENT 'Configuración adicional de la API en JSON' AFTER api_banco_token,
ADD COLUMN instrucciones_pago TEXT NULL COMMENT 'Instrucciones adicionales para el pago' AFTER api_banco_config;

CREATE INDEX idx_pago_en_linea ON tournaments(pago_en_linea_habilitado, es_evento_masivo);

