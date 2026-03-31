<?php



namespace Lib\Database\Repositories;

use Lib\Database\Repository;

/**
 * User Repository - Acceso a datos de usuarios
 * 
 * @package Lib\Database\Repositories
 * @version 1.0.0
 */
class UserRepository extends Repository
{
    protected string $table = 'users';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'username',
        'email',
        'password',
        'role',
        'club_id',
        'status',
        'created_at',
        'updated_at'
    ];
    
    protected array $hidden = [
        'password'
    ];
    
    protected array $casts = [
        'id' => 'int',
        'club_id' => 'int',
        'status' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Encuentra usuario por username
     * 
     * @param string $username
     * @return array|null
     */
    public function findByUsername(string $username): ?array
    {
        return $this->findOneBy('username', $username);
    }

    /**
     * Encuentra usuario por email
     * 
     * @param string $email
     * @return array|null
     */
    public function findByEmail(string $email): ?array
    {
        return $this->findOneBy('email', $email);
    }

    /**
     * Obtiene usuarios por rol
     * 
     * @param string $role
     * @return array
     */
    public function findByRole(string $role): array
    {
        return $this->findBy('role', $role);
    }

    /**
     * Obtiene usuarios de un club
     * 
     * @param int $clubId
     * @return array
     */
    public function findByClub(int $clubId): array
    {
        return $this->findBy('club_id', $clubId);
    }

    /**
     * Obtiene usuarios activos
     * 
     * @return array
     */
    public function getActive(): array
    {
        return $this->query()
            ->where('status', '=', 1)
            ->orderBy('username', 'ASC')
            ->get();
    }

    /**
     * Verifica si username existe
     * 
     * @param string $username
     * @param int|null $excludeId ID a excluir (útil para updates)
     * @return bool
     */
    public function usernameExists(string $username, ?int $excludeId = null): bool
    {
        $query = $this->query()->where('username', '=', $username);
        
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        
        return $query->count() > 0;
    }

    /**
     * Verifica si email existe
     * 
     * @param string $email
     * @param int|null $excludeId
     * @return bool
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $query = $this->query()->where('email', '=', $email);
        
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        
        return $query->count() > 0;
    }

    /**
     * Actualiza contraseña de usuario
     * 
     * @param int $userId
     * @param string $hashedPassword
     * @return bool
     */
    public function updatePassword(int $userId, string $hashedPassword): bool
    {
        return $this->update($userId, [
            'password' => $hashedPassword,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Obtiene estadísticas de usuarios
     * 
     * @return array
     */
    public function getStatistics(): array
    {
        $total = $this->count();
        $active = $this->query()->where('status', '=', 1)->count();
        
        $byRole = $this->raw("
            SELECT role, COUNT(*) as count 
            FROM {$this->table} 
            GROUP BY role
        ");
        
        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'by_role' => $byRole
        ];
    }
}







