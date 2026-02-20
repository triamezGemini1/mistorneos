<?php



namespace Lib\Logger;

use PDO;

/**
 * Database Handler - Escribe logs a base de datos
 * 
 * Requiere tabla: logs
 * CREATE TABLE logs (
 *   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *   level VARCHAR(20) NOT NULL,
 *   message TEXT NOT NULL,
 *   context JSON,
 *   extra JSON,
 *   created_at DATETIME NOT NULL,
 *   INDEX idx_level (level),
 *   INDEX idx_created_at (created_at)
 * );
 * 
 * @package Lib\Logger
 * @version 1.0.0
 */
class DatabaseHandler implements LogHandler
{
    private PDO $pdo;

    /**
     * Constructor
     * 
     * @param PDO $pdo Conexión PDO
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $record): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO logs (level, message, context, extra, created_at)
                VALUES (:level, :message, :context, :extra, :created_at)
            ");

            $stmt->execute([
                'level' => $record['level'],
                'message' => $record['message'],
                'context' => json_encode($record['context'], JSON_UNESCAPED_UNICODE),
                'extra' => json_encode($record['extra'], JSON_UNESCAPED_UNICODE),
                'created_at' => $record['datetime']
            ]);
        } catch (\PDOException $e) {
            // Fallback a error_log si falla DB
            error_log("Failed to write log to database: " . $e->getMessage());
        }
    }
}









