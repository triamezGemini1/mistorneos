<?php

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../lib/validation.php';

try {
    Auth::requireRole(['admin_general','admin_torneo','admin_club']);
    CSRF::validate();

    $torneo_id = V::int($_POST['torneo_id'] ?? 0, 1);
    $club_id = V::int($_POST['club_id'] ?? 0, 1);
    $acceso1 = V::date($_POST['acceso1'] ?? null);
    $acceso2 = V::date($_POST['acceso2'] ?? null);
    if (strtotime($acceso2) < strtotime($acceso1)) { 
        throw new Exception('Rango de fechas inválido'); 
    }

    // Sistema SIN tokens - solo almacenamos link simple
    $token = ''; // Token vacío - ya no generamos tokens complejos
    $usuario_invitado = "usuario" . $club_id;

    // Verificar qué campos existen en la tabla
    $columnsStmt = DB::pdo()->query("DESCRIBE invitations");
    $existingColumns = [];
    while ($col = $columnsStmt->fetch()) {
        $existingColumns[] = $col['Field'];
    }

    try {
        // Construir INSERT dinámicamente basado en campos existentes
        $fields = ['torneo_id', 'club_id', 'acceso1', 'acceso2', 'usuario', 'token', 'estado'];
        $placeholders = [':t', ':c', ':a1', ':a2', ':u', ':tk', ':estado'];
        $values = [
            ':t' => $torneo_id, 
            ':c' => $club_id, 
            ':a1' => $acceso1, 
            ':a2' => $acceso2, 
            ':u' => $usuario_invitado, 
            ':tk' => $token,
            ':estado' => 'activa'
        ];
        
        $fieldsStr = implode(', ', $fields);
        $placeholdersStr = implode(', ', $placeholders);
        
        $stmt = DB::pdo()->prepare("INSERT INTO invitations ($fieldsStr) VALUES ($placeholdersStr)");
        $stmt->execute($values);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Integrity constraint violation
            // Verificar si es un error de duplicado
            $stmt_check = DB::pdo()->prepare("SELECT id, estado FROM invitations WHERE torneo_id = :t AND club_id = :c");
            $stmt_check->execute([':t' => $torneo_id, ':c' => $club_id]);
            $existing = $stmt_check->fetch();
            
            if ($existing) {
                // Actualizar la invitación existente SIN cambiar token (lo mantenemos vacío)
                $stmt_update = DB::pdo()->prepare("UPDATE invitations SET acceso1 = :a1, acceso2 = :a2, token = '', estado = 'activa', fecha_modificacion = NOW() WHERE id = :id");
                $stmt_update->execute([':a1' => $acceso1, ':a2' => $acceso2, ':id' => $existing['id']]);
            } else {
                throw $e; // Re-lanzar si no es un duplicado
            }
        } else {
            throw $e; // Re-lanzar otros errores
        }
    }

    // Crear usuario invitado automáticamente
    $username_invitado = "invitado" . $club_id;
    $password_invitado = "invitado123";
    require_once __DIR__ . '/../../lib/security.php';
    $password_hash = Security::hashPassword($password_invitado);

    // Obtener email del club
    $stmt_club = DB::pdo()->prepare("SELECT email FROM clubes WHERE id = :club_id");
    $stmt_club->execute([':club_id' => $club_id]);
    $club_email = $stmt_club->fetchColumn();

    // Verificar si el usuario ya existe
    $stmt_check = DB::pdo()->prepare("SELECT COUNT(*) FROM usuarios WHERE username = :username");
    $stmt_check->execute([':username' => $username_invitado]);
    $user_exists = $stmt_check->fetchColumn();

    if ($user_exists == 0) {
        // Crear el usuario invitado
        $stmt_user = DB::pdo()->prepare("
            INSERT INTO usuarios (username, password_hash, email, role, status) 
            VALUES (:username, :password_hash, :email, 'admin_club', 0)
        ");
        $stmt_user->execute([
            ':username' => $username_invitado,
            ':password_hash' => $password_hash,
            ':email' => $club_email
        ]);
    }

    // Redirigir al dashboard de invitaciones
    header('Location: ../index.php?page=invitations&success=' . urlencode('Invitación creada exitosamente'));
    exit;
    
} catch (Exception $e) {
    // Manejar errores y redirigir con mensaje de error
    error_log('Error en save.php de invitaciones: ' . $e->getMessage());
    header('Location: ../index.php?page=invitations&action=new&error=' . urlencode('Error al crear invitación: ' . $e->getMessage()));
    exit;
}

