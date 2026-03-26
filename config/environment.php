<?php

/**
 * Environment Configuration
 * Determines the current environment and loads appropriate configuration
 */

class Environment {
    public static ?string $current = null;
    
    /**
     * Get current environment
     */
    public static function get(): string {
        if (self::$current === null) {
            self::$current = self::detect();
        }
        return self::$current;
    }
    
    /**
     * Detect current environment
     */
    private static function detect(): string {
        // Check for environment variable
        if (isset($_ENV['APP_ENV'])) {
            return $_ENV['APP_ENV'];
        }
        
        // Check for .env file
        $env_file = __DIR__ . '/../.env';
        if (file_exists($env_file)) {
            $env_content = file_get_contents($env_file);
            if (preg_match('/APP_ENV=(\w+)/', $env_content, $matches)) {
                return $matches[1];
            }
        }
        
        // Auto-detect based on server characteristics
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $server_name = $_SERVER['SERVER_NAME'] ?? '';
        
        // Production indicators
        if (
            strpos($host, 'laestacion') !== false ||
            strpos($host, 'laestaciondeldomino.com') !== false ||
            strpos($host, 'laestaciondeldominohoy.com') !== false ||
            strpos($host, 'mistorneos.com') !== false ||
            strpos($server_name, 'laestacion') !== false ||
            (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' && !strpos($host, 'localhost'))
        ) {
            return 'production';
        }
        
        // Default to development
        return 'development';
    }
    
    /**
     * Check if current environment is production
     */
    public static function isProduction(): bool {
        return self::get() === 'production';
    }
    
    /**
     * Check if current environment is development
     */
    public static function isDevelopment(): bool {
        return self::get() === 'development';
    }
    
    /**
     * Get environment-specific configuration
     */
    public static function getConfig(): array {
        $env = self::get();
        $config_file = __DIR__ . "/config.{$env}.php";
        
        if (file_exists($config_file)) {
            return require $config_file;
        }
        
        // Fallback to default config
        return require __DIR__ . '/config.php';
    }
}
?>
