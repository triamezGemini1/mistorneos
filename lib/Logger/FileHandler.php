<?php



namespace Lib\Logger;

/**
 * File Handler - Escribe logs a archivos
 * 
 * Características:
 * - Rotación automática por fecha
 * - Formato configurable
 * - Permisos seguros
 * 
 * @package Lib\Logger
 * @version 1.0.0
 */
class FileHandler implements LogHandler
{
    private string $logPath;
    private string $filename;
    private bool $rotateDaily;

    /**
     * Constructor
     * 
     * @param string $logPath Directorio de logs
     * @param string $filename Nombre del archivo (sin extensión)
     * @param bool $rotateDaily Rotar por fecha
     */
    public function __construct(
        string $logPath,
        string $filename = 'app',
        bool $rotateDaily = true
    ) {
        $this->logPath = rtrim($logPath, '/');
        $this->filename = $filename;
        $this->rotateDaily = $rotateDaily;

        // Crear directorio si no existe
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $record): void
    {
        $filepath = $this->getFilePath();
        
        // Formatear record
        $formatted = $this->format($record);

        // Escribir a archivo con lock
        file_put_contents($filepath, $formatted . PHP_EOL, FILE_APPEND | LOCK_EX);

        // Establecer permisos seguros
        if (file_exists($filepath)) {
            chmod($filepath, 0644);
        }
    }

    /**
     * Obtiene ruta completa del archivo de log
     * 
     * @return string
     */
    private function getFilePath(): string
    {
        $filename = $this->filename;

        if ($this->rotateDaily) {
            $filename .= '-' . date('Y-m-d');
        }

        return $this->logPath . '/' . $filename . '.log';
    }

    /**
     * Formatea el record
     * 
     * @param array $record
     * @return string
     */
    private function format(array $record): string
    {
        $level = strtoupper($record['level']);
        $datetime = $record['datetime'];
        $message = $record['message'];
        
        $line = "[{$datetime}] {$level}: {$message}";

        // Agregar contexto si existe
        if (!empty($record['context'])) {
            $line .= ' ' . json_encode($record['context'], JSON_UNESCAPED_UNICODE);
        }

        // Agregar extra si existe
        if (!empty($record['extra'])) {
            $line .= ' ' . json_encode($record['extra'], JSON_UNESCAPED_UNICODE);
        }

        return $line;
    }
}









