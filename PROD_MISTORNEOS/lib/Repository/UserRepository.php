<?php

namespace Lib\Repository;

use PDO;

/**
 * UserRepository - Acceso a datos de usuarios
 */
class UserRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Busca usuario por username
     */
    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    /**
     * Busca usuario por ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    /**
     * Busca usuario por email
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    /**
     * Busca usuario por UUID
     */
    public function findByUuid(string $uuid): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE uuid = ? LIMIT 1");
        $stmt->execute([$uuid]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    /**
     * Actualiza contraseña de usuario
     */
    public function updatePassword(int $userId, string $passwordHash): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE users SET password_hash = ?, must_change_password = 0, updated_at = NOW() WHERE id = ?"
        );
        return $stmt->execute([$passwordHash, $userId]);
    }

    /**
     * Obtiene todos los usuarios con paginación
     */
    public function findAll(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, username, email, role, club_id, created_at, updated_at 
             FROM users 
             ORDER BY created_at DESC 
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta total de usuarios
     */
    public function count(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM users");
        return (int) $stmt->fetchColumn();
    }

    /**
     * Crea un nuevo usuario
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (username, email, password_hash, role, club_id, uuid, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $data['username'],
            $data['email'],
            $data['password_hash'],
            $data['role'] ?? 'usuario',
            $data['club_id'] ?? null,
            $data['uuid'] ?? $this->generateUuid()
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Actualiza usuario
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];
        
        foreach (['username', 'email', 'role', 'club_id', 'photo_path'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $fields[] = "updated_at = NOW()";
        $values[] = $id;
        
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Elimina usuario
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Genera UUID v4
     */
    private function generateUuid(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}


