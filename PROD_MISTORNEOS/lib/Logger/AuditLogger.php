<?php



namespace Lib\Logger;

use PDO;

/**
 * Audit Logger - Sistema de auditoría para acciones críticas
 * 
 * Registra:
 * - Quién realizó la acción (user_id)
 * - Qué acción (create, update, delete, etc.)
 * - Sobre qué entidad (table, record_id)
 * - Cuándo (timestamp)
 * - Desde dónde (IP, user agent)
 * - Cambios realizados (JSON diff)
 * 
 * @package Lib\Logger
 * @version 1.0.0
 */
class AuditLogger
{
    private PDO $pdo;

    /**
     * Constructor
     * 
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Registra acción de auditoría
     * 
     * @param string $action Acción realizada (create, update, delete, login, logout)
     * @param string $entity Entidad afectada (users, clubs, tournaments, etc.)
     * @param int|null $entityId ID del registro afectado
     * @param array|null $oldData Datos anteriores (para updates)
     * @param array|null $newData Datos nuevos
     * @param int|null $userId ID del usuario que realizó la acción
     * @return void
     */
    public function log(
        string $action,
        string $entity,
        ?int $entityId = null,
        ?array $oldData = null,
        ?array $newData = null,
        ?int $userId = null
    ): void {
        // Si no se proporciona userId, intentar obtenerlo de sesión
        if ($userId === null && isset($_SESSION['user']['id'])) {
            $userId = $_SESSION['user']['id'];
        }

        // Calcular cambios (diff)
        $changes = $this->calculateChanges($oldData, $newData);

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_log (
                    user_id, action, entity, entity_id,
                    old_data, new_data, changes,
                    ip_address, user_agent,
                    created_at
                ) VALUES (
                    :user_id, :action, :entity, :entity_id,
                    :old_data, :new_data, :changes,
                    :ip_address, :user_agent,
                    NOW()
                )
            ");

            $stmt->execute([
                'user_id' => $userId,
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'old_data' => $oldData ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
                'new_data' => $newData ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
                'changes' => $changes ? json_encode($changes, JSON_UNESCAPED_UNICODE) : null,
                'ip_address' => $this->getClientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (\PDOException $e) {
            // Log error pero no fallar la operación principal
            error_log("Audit log failed: " . $e->getMessage());
        }
    }

    /**
     * Log de login
     * 
     * @param int $userId
     * @param bool $success
     * @param string|null $reason
     * @return void
     */
    public function logLogin(int $userId, bool $success = true, ?string $reason = null): void
    {
        $action = $success ? 'login_success' : 'login_failed';
        $data = ['reason' => $reason, 'success' => $success];
        
        $this->log($action, 'users', $userId, null, $data, $userId);
    }

    /**
     * Log de logout
     * 
     * @param int $userId
     * @return void
     */
    public function logLogout(int $userId): void
    {
        $this->log('logout', 'users', $userId, null, null, $userId);
    }

    /**
     * Log de creación
     * 
     * @param string $entity
     * @param int $entityId
     * @param array $data
     * @param int|null $userId
     * @return void
     */
    public function logCreate(string $entity, int $entityId, array $data, ?int $userId = null): void
    {
        $this->log('create', $entity, $entityId, null, $data, $userId);
    }

    /**
     * Log de actualización
     * 
     * @param string $entity
     * @param int $entityId
     * @param array $oldData
     * @param array $newData
     * @param int|null $userId
     * @return void
     */
    public function logUpdate(
        string $entity,
        int $entityId,
        array $oldData,
        array $newData,
        ?int $userId = null
    ): void {
        $this->log('update', $entity, $entityId, $oldData, $newData, $userId);
    }

    /**
     * Log de eliminación
     * 
     * @param string $entity
     * @param int $entityId
     * @param array $data
     * @param int|null $userId
     * @return void
     */
    public function logDelete(string $entity, int $entityId, array $data, ?int $userId = null): void
    {
        $this->log('delete', $entity, $entityId, $data, null, $userId);
    }

    /**
     * Calcula cambios entre old y new data
     * 
     * @param array|null $oldData
     * @param array|null $newData
     * @return array|null
     */
    private function calculateChanges(?array $oldData, ?array $newData): ?array
    {
        if ($oldData === null || $newData === null) {
            return null;
        }

        $changes = [];

        // Campos modificados
        foreach ($newData as $key => $newValue) {
            $oldValue = $oldData[$key] ?? null;
            
            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue
                ];
            }
        }

        // Campos eliminados
        foreach ($oldData as $key => $oldValue) {
            if (!array_key_exists($key, $newData)) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => null
                ];
            }
        }

        return !empty($changes) ? $changes : null;
    }

    /**
     * Obtiene IP del cliente
     * 
     * @return string
     */
    private function getClientIp(): string
    {
        $keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }

    /**
     * Obtiene logs de auditoría por entidad
     * 
     * @param string $entity
     * @param int $entityId
     * @param int $limit
     * @return array
     */
    public function getEntityLogs(string $entity, int $entityId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                a.*,
                u.username
            FROM audit_log a
            LEFT JOIN usuarios u ON a.user_id = u.id
            WHERE a.entity = :entity AND a.entity_id = :entity_id
            ORDER BY a.created_at DESC
            LIMIT :limit
        ");

        $stmt->bindValue(':entity', $entity, PDO::PARAM_STR);
        $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene logs de auditoría por usuario
     * 
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getUserLogs(int $userId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM audit_log
            WHERE user_id = :user_id
            ORDER BY created_at DESC
            LIMIT :limit
        ");

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}









