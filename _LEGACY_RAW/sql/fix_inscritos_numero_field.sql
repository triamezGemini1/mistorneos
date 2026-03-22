-- =====================================================
-- FIX: Agregar DEFAULT NULL al campo numero en tabla inscritos
-- Fecha: 2025-01-17
-- Descripción: Si el campo 'numero' existe sin DEFAULT, agregarlo
-- =====================================================

-- Opción 1: Si el campo numero existe pero no tiene DEFAULT, agregarlo
-- Primero verificar si existe:
-- DESCRIBE inscritos;

-- Si existe y es NOT NULL sin DEFAULT, cambiar a permitir NULL o agregar DEFAULT:
ALTER TABLE `inscritos` 
MODIFY COLUMN `numero` int DEFAULT NULL COMMENT 'Número de jugador en el equipo (1-4)';

-- Si el campo no existe pero se necesita, crearlo:
-- ALTER TABLE `inscritos` 
-- ADD COLUMN `numero` int DEFAULT NULL COMMENT 'Número de jugador en el equipo (1-4)' AFTER `codigo_equipo`;

-- =====================================================
-- NOTA: Este script debe ejecutarse SOLO si el campo numero existe
-- y está causando problemas. Verificar primero con: DESCRIBE inscritos;
-- =====================================================
