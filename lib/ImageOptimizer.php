<?php
/**
 * Helper para optimización de imágenes
 * - Comprime imágenes (JPEG, PNG)
 * - Convierte a WebP cuando es posible
 * - Genera versiones optimizadas
 */

class ImageOptimizer {
    
    /**
     * Optimiza una imagen (comprime y/o convierte a WebP)
     * 
     * @param string $source_path Ruta del archivo original
     * @param string $destination_path Ruta donde guardar (opcional, si no se especifica sobrescribe)
     * @param array $options Opciones: ['quality' => 85, 'max_width' => 1920, 'create_webp' => true]
     * @return array ['success' => bool, 'original_size' => int, 'optimized_size' => int, 'webp_path' => string|null]
     */
    public static function optimize(string $source_path, ?string $destination_path = null, array $options = []): array {
        if (!file_exists($source_path)) {
            return ['success' => false, 'error' => 'Archivo no encontrado'];
        }
        
        $default_options = [
            'quality' => 85,           // Calidad JPEG (0-100)
            'png_quality' => 9,        // Calidad PNG (0-9, donde 9 es máxima compresión)
            'max_width' => 1920,       // Ancho máximo
            'max_height' => 1080,      // Alto máximo
            'create_webp' => true,      // Crear versión WebP
            'webp_quality' => 80        // Calidad WebP (0-100)
        ];
        
        $options = array_merge($default_options, $options);
        $destination_path = $destination_path ?? $source_path;
        
        $image_info = getimagesize($source_path);
        if (!$image_info) {
            return ['success' => false, 'error' => 'No es una imagen válida'];
        }
        
        $mime_type = $image_info['mime'];
        $original_size = filesize($source_path);
        
        // Cargar imagen según tipo
        switch ($mime_type) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($source_path);
                break;
            case 'image/png':
                $image = imagecreatefrompng($source_path);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($source_path);
                break;
            case 'image/webp':
                $image = imagecreatefromwebp($source_path);
                break;
            default:
                return ['success' => false, 'error' => 'Formato no soportado: ' . $mime_type];
        }
        
        if (!$image) {
            return ['success' => false, 'error' => 'Error al cargar la imagen'];
        }
        
        // Redimensionar si es necesario
        $width = imagesx($image);
        $height = imagesy($image);
        $needs_resize = false;
        $new_width = $width;
        $new_height = $height;
        
        if ($width > $options['max_width'] || $height > $options['max_height']) {
            $needs_resize = true;
            $ratio = min($options['max_width'] / $width, $options['max_height'] / $height);
            $new_width = (int)($width * $ratio);
            $new_height = (int)($height * $ratio);
        }
        
        if ($needs_resize) {
            $resized = imagecreatetruecolor($new_width, $new_height);
            
            // Preservar transparencia para PNG
            if ($mime_type === 'image/png') {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                imagefill($resized, 0, 0, $transparent);
            }
            
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }
        
        // Guardar imagen optimizada
        $result = ['success' => false];
        
        switch ($mime_type) {
            case 'image/jpeg':
                $result['success'] = imagejpeg($image, $destination_path, $options['quality']);
                break;
            case 'image/png':
                // PNG: quality es 0-9 (compresión), convertimos de 0-100 a 0-9
                $png_quality = (int)(9 - ($options['png_quality'] / 100) * 9);
                $result['success'] = imagepng($image, $destination_path, $png_quality);
                break;
            case 'image/gif':
                $result['success'] = imagegif($image, $destination_path);
                break;
            case 'image/webp':
                $result['success'] = imagewebp($image, $destination_path, $options['webp_quality']);
                break;
        }
        
        if (!$result['success']) {
            imagedestroy($image);
            return ['success' => false, 'error' => 'Error al guardar la imagen optimizada'];
        }
        
        $optimized_size = filesize($destination_path);
        imagedestroy($image);
        
        $result['original_size'] = $original_size;
        $result['optimized_size'] = $optimized_size;
        $result['savings'] = $original_size - $optimized_size;
        $result['savings_percent'] = round((($original_size - $optimized_size) / $original_size) * 100, 2);
        
        // Crear versión WebP si está habilitado y no es ya WebP
        $webp_path = null;
        if ($options['create_webp'] && $mime_type !== 'image/webp' && function_exists('imagewebp')) {
            $webp_path = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $destination_path);
            
            // Recargar imagen para WebP
            switch ($mime_type) {
                case 'image/jpeg':
                    $webp_image = imagecreatefromjpeg($destination_path);
                    break;
                case 'image/png':
                    $webp_image = imagecreatefrompng($destination_path);
                    imagealphablending($webp_image, false);
                    imagesavealpha($webp_image, true);
                    break;
                case 'image/gif':
                    $webp_image = imagecreatefromgif($destination_path);
                    break;
                default:
                    $webp_image = null;
            }
            
            if ($webp_image) {
                if (imagewebp($webp_image, $webp_path, $options['webp_quality'])) {
                    $result['webp_path'] = $webp_path;
                    $result['webp_size'] = filesize($webp_path);
                }
                imagedestroy($webp_image);
            }
        }
        
        return $result;
    }
    
    /**
     * Optimiza todas las imágenes en un directorio
     * 
     * @param string $directory Directorio a procesar
     * @param array $options Opciones de optimización
     * @param bool $recursive Procesar subdirectorios
     * @return array Estadísticas del proceso
     */
    public static function optimizeDirectory(string $directory, array $options = [], bool $recursive = false): array {
        if (!is_dir($directory)) {
            return ['success' => false, 'error' => 'Directorio no encontrado'];
        }
        
        $stats = [
            'processed' => 0,
            'optimized' => 0,
            'failed' => 0,
            'total_savings' => 0,
            'files' => []
        ];
        
        $iterator = $recursive 
            ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory))
            : new DirectoryIterator($directory);
        
        foreach ($iterator as $file) {
            if ($file->isDir() || $file->isDot()) {
                continue;
            }
            
            $path = $file->getPathname();
            $mime = mime_content_type($path);
            
            if (strpos($mime, 'image/') === 0 && in_array($mime, ['image/jpeg', 'image/png', 'image/gif'])) {
                $stats['processed']++;
                
                $result = self::optimize($path, null, $options);
                
                if ($result['success']) {
                    $stats['optimized']++;
                    $stats['total_savings'] += $result['savings'] ?? 0;
                    $stats['files'][] = [
                        'file' => $path,
                        'original_size' => $result['original_size'],
                        'optimized_size' => $result['optimized_size'],
                        'savings' => $result['savings'],
                        'savings_percent' => $result['savings_percent'],
                        'webp_created' => !empty($result['webp_path'])
                    ];
                } else {
                    $stats['failed']++;
                }
            }
        }
        
        $stats['success'] = true;
        $stats['total_savings_mb'] = round($stats['total_savings'] / 1024 / 1024, 2);
        
        return $stats;
    }
    
    /**
     * Obtiene la mejor versión de una imagen (WebP si existe y es compatible)
     * 
     * @param string $image_path Ruta de la imagen original
     * @param bool $check_browser Verificar si el navegador acepta WebP
     * @return string Ruta de la mejor versión disponible
     */
    public static function getBestVersion(string $image_path, bool $check_browser = true): string {
        // Si se verifica el navegador y acepta WebP
        if ($check_browser && isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false) {
            $webp_path = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $image_path);
            if (file_exists($webp_path)) {
                return $webp_path;
            }
        }
        
        // Devolver original si no hay WebP o navegador no lo soporta
        return $image_path;
    }
    
    /**
     * Genera HTML para imagen con soporte WebP automático
     * 
     * @param string $image_path Ruta de la imagen
     * @param string $alt Texto alternativo
     * @param array $attributes Atributos HTML adicionales
     * @return string HTML de la imagen
     */
    public static function imageTag(string $image_path, string $alt = '', array $attributes = []): string {
        $webp_path = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $image_path);
        $has_webp = file_exists($webp_path);
        
        $default_attrs = [
            'loading' => 'lazy',
            'decoding' => 'async',
            'alt' => $alt
        ];
        
        $attributes = array_merge($default_attrs, $attributes);
        $attrs_string = '';
        foreach ($attributes as $key => $value) {
            $attrs_string .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
        }
        
        if ($has_webp) {
            // Usar <picture> para soporte WebP con fallback
            $base_url = app_base_url();
            $webp_url = $base_url . '/' . ltrim($webp_path, '/');
            $original_url = $base_url . '/' . ltrim($image_path, '/');
            
            return '<picture>' .
                   '<source srcset="' . htmlspecialchars($webp_url) . '" type="image/webp">' .
                   '<img src="' . htmlspecialchars($original_url) . '"' . $attrs_string . '>' .
                   '</picture>';
        } else {
            // Solo imagen original
            $base_url = app_base_url();
            $image_url = $base_url . '/' . ltrim($image_path, '/');
            return '<img src="' . htmlspecialchars($image_url) . '"' . $attrs_string . '>';
        }
    }
}












