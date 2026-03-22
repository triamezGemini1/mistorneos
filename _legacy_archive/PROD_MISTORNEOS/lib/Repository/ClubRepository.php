<?php

namespace Lib\Repository;

use PDO;

/**
 * ClubRepository - Acceso a datos de clubes
 */
class ClubRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Busca club por ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM clubes WHERE id = ?");
        $stmt->execute([$id]);
        $club = $stmt->fetch(PDO::FETCH_ASSOC);
        return $club ?: null;
    }

    /**
     * Busca club por nombre
     */
    public function findByName(string $name): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM clubes WHERE nombre = ? LIMIT 1");
        $stmt->execute([$name]);
        $club = $stmt->fetch(PDO::FETCH_ASSOC);
        return $club ?: null;
    }

    /**
     * Obtiene todos los clubes
     */
    public function findAll(int $limit = 100, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM clubes ORDER BY nombre ASC LIMIT ? OFFSET ?"
        );
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene clubes para select/dropdown
     */
    public function findForSelect(): array
    {
        $stmt = $this->pdo->query("SELECT id, nombre FROM clubes ORDER BY nombre ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta total de clubes
     */
    public function count(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM clubes");
        return (int) $stmt->fetchColumn();
    }

    /**
     * Crea un nuevo club
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO clubes (nombre, siglas, ciudad, estado, logo_path, created_at) 
             VALUES (?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $data['nombre'],
            $data['siglas'] ?? null,
            $data['ciudad'] ?? null,
            $data['estado'] ?? null,
            $data['logo_path'] ?? null
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Actualiza club
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];
        
        $allowedFields = ['nombre', 'siglas', 'ciudad', 'estado', 'logo_path', 'telefono', 'email'];
        
        foreach ($allowedFields as $field) {
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
        
        $sql = "UPDATE clubes SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Elimina club
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM clubes WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Obtiene estadísticas de un club
     */
    public function getStats(int $clubId): array
    {
        // Torneos organizados
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tournaments WHERE club_responsable = ?");
        $stmt->execute([$clubId]);
        $torneos = (int) $stmt->fetchColumn();

        // Jugadores inscritos
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM inscripciones WHERE club_id = ?");
        $stmt->execute([$clubId]);
        $jugadores = (int) $stmt->fetchColumn();

        return [
            'torneos_organizados' => $torneos,
            'jugadores_inscritos' => $jugadores
        ];
    }
}


