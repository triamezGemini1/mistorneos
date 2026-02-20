-- ============================================
-- Script SQL: Agregar columna slug a tournaments
-- ============================================
-- Este script es OPCIONAL. El sistema funciona sin esta columna
-- ya que genera slugs dinámicamente desde el nombre del torneo.
-- 
-- Ventajas de tener la columna slug:
-- - Búsquedas más rápidas por slug
-- - Posibilidad de tener slugs personalizados
-- - Mejor rendimiento en URLs amigables
--
-- Uso:
-- mysql -u usuario -p nombre_base_datos < sql/add_slug_to_tournaments.sql

-- Agregar columna slug si no existe
ALTER TABLE `tournaments` 
ADD COLUMN IF NOT EXISTS `slug` VARCHAR(150) NULL AFTER `nombre`,
ADD INDEX IF NOT EXISTS `idx_slug` (`slug`);

-- Generar slugs para torneos existentes
-- Nota: Esta función debe ejecutarse desde PHP usando UrlHelper::slugify()
-- ya que MySQL no tiene una función nativa para generar slugs con acentos

-- Ejemplo de actualización desde PHP:
-- UPDATE tournaments SET slug = LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(nombre, 'á', 'a'), 'é', 'e'), 'í', 'i'), 'ó', 'o'), 'ú', 'u'), 'ñ', 'n'), ' ', '-'))
-- WHERE slug IS NULL;












