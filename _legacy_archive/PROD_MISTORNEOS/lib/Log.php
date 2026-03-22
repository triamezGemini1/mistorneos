<?php

// Cargar interfaces PSR-3 si no existen (fallback)
if (!interface_exists('Psr\Log\LoggerInterface')) {
    require_once __DIR__ . '/Psr/Log/LoggerInterface.php';
}

if (!class_exists('Psr\Log\LogLevel')) {
    require_once __DIR__ . '/Psr/Log/LogLevel.php';
}

use Lib\Logger\Logger;
use Lib\Logger\FileHandler;
use Psr\Log\LogLevel;

/**
 * Log - Helper global para logging
 * 
 * Uso:
 *   Log::info('Usuario logueado', ['user_id' => 123]);
 *   Log::error('Error en pago', ['exception' => $e->getMessage()]);
 */
class Log
{
    private static ?Logger $instance = null;

    /**
     * Obtiene instancia del logger (singleton)
     */
    private static function getInstance(): Logger
    {
        if (self::$instance === null) {
            try {
                // Determinar nivel según entorno
                $level = Env::bool('APP_DEBUG') ? LogLevel::DEBUG : LogLevel::INFO;
                
                self::$instance = new Logger($level);
                
                // Agregar handler de archivo
                $logPath = defined('APP_ROOT') 
                    ? APP_ROOT . '/storage/logs' 
                    : dirname(__DIR__) . '/storage/logs';
                
                if (!is_dir($logPath)) {
                    @mkdir($logPath, 0755, true);
                }
                
                self::$instance->addHandler(new FileHandler($logPath . '/app.log'));
            } catch (Throwable $e) {
                // Si hay error al inicializar Logger, usar error_log como fallback
                error_log("Error inicializando Logger: " . $e->getMessage());
                // Crear un logger mínimo que solo use error_log
                self::$instance = new class {
                    public function emergency($msg, $ctx = []) { error_log("[EMERGENCY] $msg"); }
                    public function alert($msg, $ctx = []) { error_log("[ALERT] $msg"); }
                    public function critical($msg, $ctx = []) { error_log("[CRITICAL] $msg"); }
                    public function error($msg, $ctx = []) { error_log("[ERROR] $msg"); }
                    public function warning($msg, $ctx = []) { error_log("[WARNING] $msg"); }
                    public function notice($msg, $ctx = []) { error_log("[NOTICE] $msg"); }
                    public function info($msg, $ctx = []) { error_log("[INFO] $msg"); }
                    public function debug($msg, $ctx = []) { error_log("[DEBUG] $msg"); }
                    public function log($level, $msg, $ctx = []) { error_log("[$level] $msg"); }
                };
            }
        }
        
        return self::$instance;
    }

    /**
     * Log de emergencia
     */
    public static function emergency(string $message, array $context = []): void
    {
        self::getInstance()->emergency($message, $context);
    }

    /**
     * Log de alerta
     */
    public static function alert(string $message, array $context = []): void
    {
        self::getInstance()->alert($message, $context);
    }

    /**
     * Log crítico
     */
    public static function critical(string $message, array $context = []): void
    {
        self::getInstance()->critical($message, $context);
    }

    /**
     * Log de error
     */
    public static function error(string $message, array $context = []): void
    {
        self::getInstance()->error($message, $context);
    }

    /**
     * Log de advertencia
     */
    public static function warning(string $message, array $context = []): void
    {
        self::getInstance()->warning($message, $context);
    }

    /**
     * Log de aviso
     */
    public static function notice(string $message, array $context = []): void
    {
        self::getInstance()->notice($message, $context);
    }

    /**
     * Log informativo
     */
    public static function info(string $message, array $context = []): void
    {
        self::getInstance()->info($message, $context);
    }

    /**
     * Log de debug
     */
    public static function debug(string $message, array $context = []): void
    {
        self::getInstance()->debug($message, $context);
    }

    /**
     * Log genérico
     * Nota: Renombrado de log() a logMessage() para evitar conflicto con PHP 8.0+
     * donde métodos con el mismo nombre que la clase se consideran constructores
     * 
     * Para usar: Log::logMessage('info', 'mensaje', [])
     * O mejor: usar los métodos específicos como Log::info(), Log::error(), etc.
     */
    public static function logMessage(string $level, string $message, array $context = []): void
    {
        self::getInstance()->log($level, $message, $context);
    }

    /**
     * Log de excepción con stack trace
     */
    public static function exception(\Throwable $e, string $message = ''): void
    {
        $context = [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];

        $msg = $message ?: 'Exception: ' . $e->getMessage();
        self::getInstance()->error($msg, $context);
    }
}


