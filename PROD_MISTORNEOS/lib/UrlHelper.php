<?php
/**
 * Helper para generar URLs amigables (slug-based)
 * Convierte URLs como /torneo/123 a /torneo/nombre-del-torneo
 */

class UrlHelper {
    
    /**
     * Genera un slug a partir de un texto
     * @param string $text
     * @return string
     */
    public static function slugify(string $text): string {
        // Convertir a minúsculas
        $text = mb_strtolower($text, 'UTF-8');
        
        // Reemplazar caracteres especiales
        $text = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'u'],
            $text
        );
        
        // Remover caracteres especiales y espacios
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        
        // Remover guiones al inicio y final
        $text = trim($text, '-');
        
        // Limitar longitud
        if (strlen($text) > 100) {
            $text = substr($text, 0, 100);
            $text = rtrim($text, '-');
        }
        
        return $text;
    }
    
    /**
     * Genera URL para un torneo
     * @param int $torneo_id
     * @param string $torneo_nombre
     * @return string
     */
    public static function torneoUrl(int $torneo_id, string $torneo_nombre): string {
        $base_url = app_base_url();
        return "{$base_url}/public/torneo_detalle.php?torneo_id={$torneo_id}";
    }
    
    /**
     * Resuelve un slug a un ID de torneo
     * @param string $slug
     * @return int|null
     */
    public static function resolveTorneoSlug(string $slug): ?int {
        require_once __DIR__ . '/../config/db.php';
        
        try {
            $pdo = DB::pdo();
            
            // Buscar por slug en la base de datos (si existe columna slug)
            // Si no existe, buscar por ID que puede estar en la URL
            if (is_numeric($slug)) {
                return (int)$slug;
            }
            
            // Intentar buscar por nombre similar
            $stmt = $pdo->prepare("
                SELECT id FROM tournaments 
                WHERE estatus = 1 
                AND LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(nombre, 'á', 'a'), 'é', 'e'), 'í', 'i'), 'ó', 'o'), 'ú', 'u'), 'ñ', 'n'), ' ', '-')) LIKE ?
                LIMIT 1
            ");
            $search_slug = '%' . $slug . '%';
            $stmt->execute([$search_slug]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? (int)$result['id'] : null;
        } catch (Exception $e) {
            error_log("Error resolviendo slug de torneo: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Genera URL para resultados de torneo
     * @param int $torneo_id
     * @param string $torneo_nombre
     * @return string
     */
    public static function resultadosUrl(int $torneo_id, string $torneo_nombre): string {
        $base_url = app_base_url();
        return "{$base_url}/public/resultados_detalle.php?torneo_id={$torneo_id}";
    }
}


