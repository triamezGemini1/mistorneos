<?php

/**
 * Helper centralizado para la aplicaci�n
 * Detecta autom�ticamente el entorno y simplifica la generaci�n de URLs
 */
class AppHelpers {
    public static ?bool $is_production = null;
    public static ?string $base_url = null;
    
    /**
     * Detecta si estamos en producci�n
     */
    public static function isProduction(): bool {
        if (self::$is_production === null) {
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $server_name = $_SERVER['SERVER_NAME'] ?? '';
            
            // Indicadores de producci�n
            self::$is_production = (
                strpos($host, 'laestacion') !== false ||
                strpos($host, 'laestaciondeldomino.com') !== false ||
                strpos($host, 'laestaciondeldominohoy.com') !== false ||
                strpos($host, 'mistorneos.com') !== false ||
                strpos($server_name, 'laestacion') !== false ||
                (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' && !strpos($host, 'localhost'))
            );
        }
        
        return self::$is_production;
    }
    
    /**
     * Obtiene la URL base de la aplicación (raíz del proyecto, sin /public).
     * Detecta automáticamente localhost vs producción: en localhost usa /mistorneos
     * si APP_URL no está definida; en producción se recomienda definir APP_URL en .env.
     */
    public static function getBaseUrl(): string {
        if (self::$base_url === null) {
            $fromEnv = class_exists('Env') ? Env::get('APP_URL') : null;
            $fromConfig = $GLOBALS['APP_CONFIG']['app']['base_url'] ?? null;

            if (!empty($fromEnv)) {
                self::$base_url = rtrim($fromEnv, '/');
            } elseif (!empty($fromConfig) && $fromConfig !== '/') {
                $cfg = $fromConfig;
                if (!preg_match('#^https?://#', $cfg)) {
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    self::$base_url = $protocol . '://' . $host . $cfg;
                } else {
                    self::$base_url = rtrim($cfg, '/');
                }
            } else {
                // Auto-detección: localhost/127.0.0.1 → /mistorneos; producción → raíz o APP_URL en .env
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $hostLower = strtolower($host);
                $isLocalhost = ($hostLower === 'localhost' || $hostLower === '127.0.0.1'
                    || strpos($hostLower, 'localhost:') === 0 || strpos($hostLower, '127.0.0.1:') === 0);
                $path = $isLocalhost ? '/mistorneos' : '';
                self::$base_url = $protocol . '://' . $host . $path;
            }
            if (str_ends_with(self::$base_url, '/public')) {
                self::$base_url = rtrim(substr(self::$base_url, 0, -7), '/');
            }
        }
        return self::$base_url;
    }

    /**
     * URL de la carpeta public/ (assets, index.php, etc.)
     */
    public static function getPublicUrl(): string {
        return rtrim(self::getBaseUrl(), '/') . '/public';
    }
    
    /**
     * Genera URL para cualquier archivo de la aplicaci�n
     * SIMPLIFICADO: Siempre usar /public/ para archivos PHP (como en desarrollo)
     */
    public static function url(string $path = '', array $params = []): string {
        $base = self::getPublicUrl();
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }
        $url = $base . ($path !== '' ? '/' . $path : '');
        
        // Agregar par�metros si existen
        if (!empty($params)) {
            $query_string = http_build_query($params);
            $url .= '?' . $query_string;
        }
        
        return $url;
    }
    
    /**
     * Genera URL para el dashboard
     */
    public static function dashboard(string $page = 'home', array $params = []): string {
        $params['page'] = $page;
        return self::url('index.php', $params);
    }
    
    /**
     * Genera URL para archivos espec�ficos
     */
    public static function file(string $filename, array $params = []): string {
        return self::url($filename, $params);
    }
    
    /**
     * Genera URL para logout
     */
    public static function logout(): string {
        return self::url('logout.php');
    }
    
    /**
     * Genera URL para login
     */
    public static function login(): string {
        return self::url('login.php');
    }
    
    /**
     * Genera URL para invitaciones simples
     */
    public static function simpleInvitation(int $torneoId, int $clubId): string {
        return self::url('simple_invitation_login.php', [
            'torneo' => $torneoId,
            'club' => $clubId
        ]);
    }
    
    /**
     * Genera URL para archivos de torneo
     */
    public static function tournamentFile(string $filePath): string {
        return self::url('view_tournament_files.php', ['file' => $filePath]);
    }
    
    /**
     * Genera URL para endpoints de API
     */
    public static function api(string $endpoint, array $params = []): string {
        return self::url('api/' . ltrim($endpoint, '/'), $params);
    }
    
    /**
     * Obtiene el path relativo correcto para archivos públicos
     * (usado en JavaScript para AJAX calls)
     */
    public static function getPublicPath(): string {
        $base = self::getBaseUrl();
        $parsed = parse_url($base);
        $path = $parsed['path'] ?? '/mistorneos';
        return rtrim($path, '/') . '/public/';
    }
    
    /**
     * Redirige a una URL
     */
    public static function redirect(string $url): void {
        header('Location: ' . $url);
        exit;
    }
    
    /**
     * Redirige al dashboard
     */
    public static function redirectToDashboard(string $page = 'home', array $params = []): void {
        self::redirect(self::dashboard($page, $params));
    }
    
    /**
     * Obtiene informaci�n del entorno para debugging
     */
    public static function getEnvironmentInfo(): array {
        return [
            'is_production' => self::isProduction(),
            'base_url' => self::getBaseUrl(),
            'host' => $_SERVER['HTTP_HOST'] ?? 'localhost',
            'https' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'server_name' => $_SERVER['SERVER_NAME'] ?? '',
        ];
    }
    
    /**
     * Obtiene la URL del logo principal (logo4.png en lib/Assets).
     * Se sirve vía view_image.php porque lib/ está fuera de public/.
     */
    public static function getAppLogo(): string {
        return self::getPublicUrl() . '/view_image.php?path=' . rawurlencode('lib/Assets/mislogos/logo4.png');
    }
    
    /**
     * Genera el HTML para mostrar el logo de la aplicación
     * @param string $class Clases CSS adicionales
     * @param string $alt Texto alternativo
     * @param int $height Altura en píxeles (por defecto 40)
     * @param bool $priority Si true, añade fetchpriority="high" para LCP (logo principal del dashboard)
     */
    public static function appLogo(string $class = '', string $alt = 'La Estación del Dominó', int $height = 40, bool $priority = false): string {
        $logo_url = self::getAppLogo();
        $class_attr = $class ? ' class="' . htmlspecialchars($class) . '"' : '';
        $priority_attr = $priority ? ' fetchpriority="high"' : '';
        return '<img src="' . htmlspecialchars($logo_url) . '" alt="' . htmlspecialchars($alt) . '" height="' . $height . '"' . $class_attr . $priority_attr . '>';
    }

    /**
     * URL centralizada para mostrar cualquier imagen (logos, fotos, etc.).
     * Usa view_image.php para servir la imagen de forma segura; la URL es relativa
     * al documento actual, así que funciona con cualquier base (public/, mistorneos/public/, etc.).
     * @param string|null $path Ruta relativa al proyecto, ej: upload/logos/logo_1.jpg
     * @return string URL para usar en src="..." o string vacío si no hay path
     */
    public static function imageUrl(?string $path): string {
        if ($path === null || $path === '') {
            return '';
        }
        if (strpos($path, 'http') === 0) {
            return $path;
        }
        $path = ltrim($path, '/\\');
        return 'view_image.php?path=' . rawurlencode($path);
    }
}

?>
