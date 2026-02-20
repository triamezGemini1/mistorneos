-- Migración: Agregar campo sexo a la tabla users
-- Fecha: 2025-01-XX

USE mistorneos;

-- Agregar columna sexo a la tabla users
ALTER TABLE users 
ADD COLUMN sexo ENUM('M','F','O') NULL DEFAULT NULL 
AFTER fechnac;

-- Crear índice para mejorar consultas por género
CREATE INDEX idx_users_sexo ON users(sexo);

-- Comentario de la columna
ALTER TABLE users MODIFY COLUMN sexo ENUM('M','F','O') NULL DEFAULT NULL COMMENT 'Género del usuario: M=Masculino, F=Femenino, O=Otro';


