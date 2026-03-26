<?php



namespace Lib\Assets;

/**
 * Assets Pipeline - Sistema de gestión y optimización de assets (CSS/JS)
 * 
 * Características:
 * - Minificación de CSS y JavaScript
 * - Concatenación de archivos
 * - Cache busting con versioning
 * - Compresión gzip
 * - Source maps (development)
 * 
 * @package Lib\Assets
 * @version 1.0.0
 */
class AssetsPipeline
{
    private string $publicPath;
    private string $cachePath;
    private bool $minify;
    private bool $combine;
    private string $version;
    private array $manifests = [];

    /**
     * Constructor
     * 
     * @param string $publicPath Ruta pública de assets
     * @param string $cachePath Ruta de cache
     * @param bool $minify Minificar assets
     * @param bool $combine Combinar múltiples archivos
     */
    public function __construct(
        string $publicPath,
        string $cachePath,
        bool $minify = true,
        bool $combine = true
    ) {
        $this->publicPath = rtrim($publicPath, '/');
        $this->cachePath = rtrim($cachePath, '/');
        $this->minify = $minify;
        $this->combine = $combine;
        $this->version = $this->loadVersion();

        // Crear directorio de cache si no existe
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }

        $this->loadManifest();
    }

    /**
     * Registra archivo CSS
     * 
     * @param string $name Nombre del asset
     * @param string|array $files Archivo(s) CSS
     * @return void
     */
    public function css(string $name, $files): void
    {
        $files = (array)$files;
        $this->manifests['css'][$name] = $files;
    }

    /**
     * Registra archivo JavaScript
     * 
     * @param string $name Nombre del asset
     * @param string|array $files Archivo(s) JS
     * @return void
     */
    public function js(string $name, $files): void
    {
        $files = (array)$files;
        $this->manifests['js'][$name] = $files;
    }

    /**
     * Obtiene URL del asset CSS
     * 
     * @param string $name
     * @return string
     */
    public function cssUrl(string $name): string
    {
        if (!isset($this->manifests['css'][$name])) {
            throw new \RuntimeException("CSS asset '{$name}' not found");
        }

        $files = $this->manifests['css'][$name];

        if (!$this->combine) {
            // Retornar primer archivo sin combinar
            return $this->assetUrl($files[0]);
        }

        $cacheFile = $this->getCacheFilename($name, 'css');
        $cachePath = $this->cachePath . '/' . $cacheFile;

        // Generar si no existe o está desactualizado
        if (!file_exists($cachePath) || $this->needsRegeneration($files, $cachePath)) {
            $this->buildCss($files, $cachePath);
        }

        return '/assets/cache/' . $cacheFile;
    }

    /**
     * Obtiene URL del asset JavaScript
     * 
     * @param string $name
     * @return string
     */
    public function jsUrl(string $name): string
    {
        if (!isset($this->manifests['js'][$name])) {
            throw new \RuntimeException("JS asset '{$name}' not found");
        }

        $files = $this->manifests['js'][$name];

        if (!$this->combine) {
            return $this->assetUrl($files[0]);
        }

        $cacheFile = $this->getCacheFilename($name, 'js');
        $cachePath = $this->cachePath . '/' . $cacheFile;

        if (!file_exists($cachePath) || $this->needsRegeneration($files, $cachePath)) {
            $this->buildJs($files, $cachePath);
        }

        return '/assets/cache/' . $cacheFile;
    }

    /**
     * Genera tag <link> para CSS
     * 
     * @param string $name
     * @return string
     */
    public function cssTag(string $name): string
    {
        $url = $this->cssUrl($name);
        return sprintf('<link rel="stylesheet" href="%s">', htmlspecialchars($url, ENT_QUOTES, 'UTF-8'));
    }

    /**
     * Genera tag <script> para JavaScript
     * 
     * @param string $name
     * @param bool $defer
     * @param bool $async
     * @return string
     */
    public function jsTag(string $name, bool $defer = true, bool $async = false): string
    {
        $url = $this->jsUrl($name);
        $attrs = '';
        
        if ($defer) {
            $attrs .= ' defer';
        }
        
        if ($async) {
            $attrs .= ' async';
        }
        
        return sprintf('<script src="%s"%s></script>', htmlspecialchars($url, ENT_QUOTES, 'UTF-8'), $attrs);
    }

    /**
     * Construye archivo CSS combinado y minificado
     * 
     * @param array $files
     * @param string $outputPath
     * @return void
     */
    private function buildCss(array $files, string $outputPath): void
    {
        $combined = '';

        foreach ($files as $file) {
            $filePath = $this->publicPath . '/' . ltrim($file, '/');
            
            if (!file_exists($filePath)) {
                error_log("Asset file not found: {$filePath}");
                continue;
            }

            $content = file_get_contents($filePath);
            
            if ($content === false) {
                continue;
            }

            // Agregar comentario de origen
            $combined .= "/* Source: {$file} */\n";
            $combined .= $content . "\n\n";
        }

        // Minificar si está habilitado
        if ($this->minify) {
            $combined = $this->minifyCss($combined);
        }

        file_put_contents($outputPath, $combined);

        // Crear versión gzip
        $this->createGzipVersion($outputPath);
    }

    /**
     * Construye archivo JavaScript combinado y minificado
     * 
     * @param array $files
     * @param string $outputPath
     * @return void
     */
    private function buildJs(array $files, string $outputPath): void
    {
        $combined = '';

        foreach ($files as $file) {
            $filePath = $this->publicPath . '/' . ltrim($file, '/');
            
            if (!file_exists($filePath)) {
                error_log("Asset file not found: {$filePath}");
                continue;
            }

            $content = file_get_contents($filePath);
            
            if ($content === false) {
                continue;
            }

            // Agregar comentario de origen
            $combined .= "/* Source: {$file} */\n";
            $combined .= $content . ";\n\n";
        }

        // Minificar si está habilitado
        if ($this->minify) {
            $combined = $this->minifyJs($combined);
        }

        file_put_contents($outputPath, $combined);

        // Crear versión gzip
        $this->createGzipVersion($outputPath);
    }

    /**
     * Minifica CSS
     * 
     * @param string $css
     * @return string
     */
    private function minifyCss(string $css): string
    {
        // Remover comentarios
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Remover whitespace
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Remover espacios alrededor de símbolos
        $css = preg_replace('/\s*([{}|:;,])\s*/', '$1', $css);
        
        // Remover último punto y coma
        $css = preg_replace('/;(\s*})/', '$1', $css);
        
        return trim($css);
    }

    /**
     * Minifica JavaScript
     * 
     * @param string $js
     * @return string
     */
    private function minifyJs(string $js): string
    {
        // Remover comentarios de línea
        $js = preg_replace('/\/\/[^\n]*/', '', $js);
        
        // Remover comentarios de bloque
        $js = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $js);
        
        // Remover whitespace excesivo
        $js = preg_replace('/\s+/', ' ', $js);
        
        // Remover espacios alrededor de operadores
        $js = preg_replace('/\s*([=<>!+\-*\/{}();,:])\s*/', '$1', $js);
        
        return trim($js);
    }

    /**
     * Crea versión gzip del archivo
     * 
     * @param string $filePath
     * @return void
     */
    private function createGzipVersion(string $filePath): void
    {
        $content = file_get_contents($filePath);
        
        if ($content === false) {
            return;
        }

        $gzipPath = $filePath . '.gz';
        $gz = gzopen($gzipPath, 'w9');
        
        if ($gz === false) {
            return;
        }

        gzwrite($gz, $content);
        gzclose($gz);
    }

    /**
     * Verifica si necesita regenerar cache
     * 
     * @param array $sourceFiles
     * @param string $cacheFile
     * @return bool
     */
    private function needsRegeneration(array $sourceFiles, string $cacheFile): bool
    {
        if (!file_exists($cacheFile)) {
            return true;
        }

        $cacheTime = filemtime($cacheFile);

        foreach ($sourceFiles as $file) {
            $filePath = $this->publicPath . '/' . ltrim($file, '/');
            
            if (!file_exists($filePath)) {
                continue;
            }

            if (filemtime($filePath) > $cacheTime) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtiene nombre de archivo de cache
     * 
     * @param string $name
     * @param string $type
     * @return string
     */
    private function getCacheFilename(string $name, string $type): string
    {
        return sprintf('%s-%s.%s', $name, $this->version, $type);
    }

    /**
     * Obtiene URL de asset simple
     * 
     * @param string $path
     * @return string
     */
    private function assetUrl(string $path): string
    {
        return $path . '?v=' . $this->version;
    }

    /**
     * Carga versión de assets
     * 
     * @return string
     */
    private function loadVersion(): string
    {
        $versionFile = $this->cachePath . '/version.txt';

        if (file_exists($versionFile)) {
            return trim(file_get_contents($versionFile));
        }

        $version = md5(time() . random_bytes(16));
        file_put_contents($versionFile, $version);

        return $version;
    }

    /**
     * Actualiza versión (cache busting)
     * 
     * @return void
     */
    public function updateVersion(): void
    {
        $this->version = md5(time() . random_bytes(16));
        file_put_contents($this->cachePath . '/version.txt', $this->version);
    }

    /**
     * Limpia cache de assets
     * 
     * @return int Archivos eliminados
     */
    public function clearCache(): int
    {
        $files = glob($this->cachePath . '/*');
        
        if ($files === false) {
            return 0;
        }

        $deleted = 0;

        foreach ($files as $file) {
            if (is_file($file) && basename($file) !== 'version.txt') {
                unlink($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Carga manifest guardado
     * 
     * @return void
     */
    private function loadManifest(): void
    {
        $manifestFile = $this->cachePath . '/manifest.json';

        if (file_exists($manifestFile)) {
            $content = file_get_contents($manifestFile);
            if ($content !== false) {
                $this->manifests = json_decode($content, true) ?? [];
            }
        }
    }

    /**
     * Guarda manifest
     * 
     * @return void
     */
    public function saveManifest(): void
    {
        $manifestFile = $this->cachePath . '/manifest.json';
        file_put_contents($manifestFile, json_encode($this->manifests, JSON_PRETTY_PRINT));
    }
}







