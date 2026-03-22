-- Permitir que numero_cuenta y tipo_cuenta sean NULL en cuentas_bancarias
ALTER TABLE `cuentas_bancarias` 
MODIFY COLUMN `numero_cuenta` VARCHAR(50) NULL COMMENT 'Número de cuenta',
MODIFY COLUMN `tipo_cuenta` ENUM('corriente', 'ahorro', 'pagomovil') NULL COMMENT 'Tipo de cuenta';

