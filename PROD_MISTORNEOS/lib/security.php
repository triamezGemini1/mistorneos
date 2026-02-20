<?php


final class Security
{
    /**
     * Genera un hash seguro de contrase�a usando bcrypt
     */
    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_DEFAULT);
    }

    /**
     * Verifica una contrase�a contra su hash
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Autentica un usuario con username y password
     * Retorna array con datos del usuario o null si falla
     * Esta es la función centralizada de autenticación - TODOS los logins deben pasar por aquí
     */
    public static function authenticateUser(string $username, string $password): ?array
    {
        try {
            // Primero buscar el usuario sin filtrar por status para diagnosticar
            $stmt = DB::pdo()->prepare("
                SELECT id, username, password_hash, email, role, status, club_id, entidad, uuid, photo_path
                FROM usuarios 
                WHERE username = ? OR email = ?
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Si no existe el usuario
            if (!$user) {
                error_log("Autenticación fallida: Usuario '{$username}' no existe");
                return null;
            }

            // Solo pueden entrar usuarios activos (status = 0). 1 = inactivo
            if ((int)$user['status'] !== 0) {
                error_log("Autenticación fallida: Usuario '{$username}' inactivo (status={$user['status']})");
                return null;
            }

            // Verificar contraseña
            if (!self::verifyPassword($password, $user['password_hash'])) {
                error_log("Autenticación fallida: Contraseña incorrecta para usuario '{$username}'");
                return null;
            }

            // Usuario válido y autenticado
            return [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
                'uuid' => $user['uuid'] ?? null,
                'photo_path' => $user['photo_path'] ?? null,
                'club_id' => $user['club_id'] ? (int)$user['club_id'] : 0,
                'entidad' => isset($user['entidad']) ? (int)$user['entidad'] : 0
            ];
        } catch (Exception $e) {
            error_log("Error en autenticación: " . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Autentica un usuario admin_club espec�ficamente
     */
    public static function authenticateClubAdmin(string $username, string $password, string $expectedEmail = null): ?array
    {
        $user = self::authenticateUser($username, $password);
        
        if ($user && $user['role'] === 'admin_club') {
            // Si se especifica email, verificar que coincida
            if ($expectedEmail && $user['email'] !== $expectedEmail) {
                return null;
            }
            return $user;
        }
        
        return null;
    }

    /**
     * Genera username por defecto para clubes
     */
    public static function defaultClubUsername(int $clubId): string
    {
        return 'invitado' . $clubId;
    }

    /**
     * Contrase�a por defecto para clubes
     */
    public static function defaultClubPassword(): string
    {
        return 'invitado123';
    }

    /**
     * Crea un usuario de club con credenciales por defecto
     */
    public static function createClubUser(int $clubId, string $email, string $clubName = ''): array
    {
        $username = self::defaultClubUsername($clubId);
        $password = self::defaultClubPassword();
        $passwordHash = self::hashPassword($password);

        return [
            'username' => $username,
            'password' => $password,
            'password_hash' => $passwordHash,
            'email' => $email,
            'role' => 'admin_club',
            'status' => 0,
            'club_id' => $clubId,
            'must_change_password' => 1
        ];
    }

    /**
     * Función centralizada para crear usuarios
     * TODOS los lugares donde se crean usuarios deben usar esta función
     * para garantizar consistencia en validaciones, hash de contraseñas y campos
     * 
     * @param array $data Datos del usuario a crear
     * @return array ['success' => bool, 'user_id' => int|null, 'errors' => array]
     */
    public static function createUser(array $data): array
    {
        $errors = [];
        
        // Campos requeridos
        $required = ['username', 'password', 'role'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[] = "El campo {$field} es requerido";
            }
        }
        
        // Validar username
        if (!empty($data['username'])) {
            $username = trim($data['username']);
            if (strlen($username) < 3) {
                $errors[] = 'El nombre de usuario debe tener al menos 3 caracteres';
            }
            if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $username)) {
                $errors[] = 'El nombre de usuario solo puede contener letras, números, puntos y guiones bajos';
            }
        }
        
        // Validar password
        if (!empty($data['password'])) {
            if (strlen($data['password']) < 6) {
                $errors[] = 'La contraseña debe tener al menos 6 caracteres';
            }
        }
        
        // Validar email si se proporciona
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El email no es válido';
        }
        
        // Validar role
        $valid_roles = ['admin_general', 'admin_torneo', 'admin_club', 'usuario', 'operador'];
        if (!empty($data['role']) && !in_array($data['role'], $valid_roles)) {
            $errors[] = 'El rol seleccionado no es válido';
        }
        
        // Validar que admin_torneo y admin_club tengan club_id
        if (in_array($data['role'] ?? '', ['admin_torneo', 'admin_club']) && empty($data['club_id'])) {
            $errors[] = 'Los usuarios con rol ' . $data['role'] . ' deben tener un club asignado';
        }
        
        // Validar que admin_general y usuario NO tengan club_id (salvo _allow_club_for_usuario para scripts)
        $allow_club_usuario = !empty($data['_allow_club_for_usuario']);
        unset($data['_allow_club_for_usuario']);
        if (!$allow_club_usuario && in_array($data['role'] ?? '', ['admin_general', 'usuario']) && !empty($data['club_id'])) {
            $data['club_id'] = null; // Forzar a null
        }
        
        if (!empty($errors)) {
            return ['success' => false, 'user_id' => null, 'errors' => $errors];
        }
        
        try {
            $pdo = DB::pdo();
            
            // Verificar si el username ya existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                return ['success' => false, 'user_id' => null, 'errors' => ['El nombre de usuario ya existe']];
            }
            
            // Verificar si la cédula ya existe (si se proporciona)
            if (!empty($data['cedula'])) {
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE cedula = ?");
                $stmt->execute([$data['cedula']]);
                if ($stmt->fetch()) {
                    return ['success' => false, 'user_id' => null, 'errors' => ['Ya existe un usuario con esta cédula']];
                }
            }
            
            // Generar UUID si no se proporciona
            $uuid = $data['uuid'] ?? null;
            if (empty($uuid)) {
                $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000,
                    mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                );
            }
            
            // Hash de la contraseña usando el método centralizado
            $password_hash = self::hashPassword($data['password']);
            
            // status: 0 = activo, 1 = inactivo (entero)
            $status = isset($data['status']) ? (in_array($data['status'], ['approved', 'active', 'activo', 0, '0'], true) ? 0 : 1) : 0;
            $fields = ['username', 'password_hash', 'role', 'status'];
            $values = [$username, $password_hash, $data['role'], $status];
            $placeholders = ['?', '?', '?', '?'];
            
            // Campos opcionales
            $optional_fields = [
                'cedula' => 'cedula',
                'nombre' => 'nombre',
                'email' => 'email',
                'celular' => 'celular',
                'fechnac' => 'fechnac',
                'sexo' => 'sexo',
                'club_id' => 'club_id',
                'entidad' => 'entidad',
                'uuid' => 'uuid',
                'photo_path' => 'photo_path'
            ];
            
            foreach ($optional_fields as $key => $field) {
                if (isset($data[$key]) && $data[$key] !== null && $data[$key] !== '') {
                    $fields[] = $field;
                    $values[] = $data[$key];
                    $placeholders[] = '?';
                }
            }
            
            // Agregar created_at
            $fields[] = 'created_at';
            $values[] = date('Y-m-d H:i:s');
            $placeholders[] = '?';
            
            $sql = "INSERT INTO usuarios (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            
            $user_id = (int)$pdo->lastInsertId();
            
            return ['success' => true, 'user_id' => $user_id, 'errors' => []];
            
        } catch (Exception $e) {
            error_log("Error al crear usuario: " . $e->getMessage());
            return ['success' => false, 'user_id' => null, 'errors' => ['Error al crear el usuario: ' . $e->getMessage()]];
        }
    }
}



