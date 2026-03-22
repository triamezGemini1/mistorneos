<?php
/**
 * Script de Diagnóstico y Corrección de Usuarios
 * Ejecutar en producción para verificar y corregir status de usuarios
 * 
 * USO: php scripts/fix_user_status.php
 * O acceder via web (BORRAR DESPUÉS): /scripts/fix_user_status.php
 */

// Seguridad: Solo ejecutar en CLI o con parámetro secreto
$is_cli = php_sapi_name() === 'cli';
$secret_key = $_GET['key'] ?? '';
$valid_key = 'fix_users_2025'; // Cambiar esta clave

if (!$is_cli && $secret_key !== $valid_key) {
    die("Acceso denegado. Usar: ?key={$valid_key}");
}

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

echo $is_cli ? "" : "<pre>";
echo "===========================================\n";
echo "DIAGNÓSTICO DE USUARIOS - La Estación del Dominó\n";
echo "===========================================\n\n";

try {
    $pdo = DB::pdo();
    echo "✅ Conexión a base de datos exitosa\n\n";
    
    // 1. Verificar estructura de la tabla usuarios
    echo "--- ESTRUCTURA DE TABLA USUARIOS ---\n";
    $stmt = $pdo->query("DESCRIBE usuarios");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $has_status = false;
    $has_password_hash = false;
    
    foreach ($columns as $col) {
        if ($col['Field'] === 'status') {
            $has_status = true;
            echo "✅ Columna 'status' existe (Tipo: {$col['Type']})\n";
        }
        if ($col['Field'] === 'password_hash') {
            $has_password_hash = true;
            echo "✅ Columna 'password_hash' existe\n";
        }
    }
    
    if (!$has_status) {
        echo "❌ Columna 'status' NO existe - Creando...\n";
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN status VARCHAR(20) DEFAULT 'approved'");
        echo "✅ Columna 'status' creada\n";
    }
    
    // 2. Mostrar estadísticas de status
    echo "\n--- ESTADÍSTICAS DE STATUS ---\n";
    $stmt = $pdo->query("
        SELECT 
            status, 
            COUNT(*) as total,
            GROUP_CONCAT(CONCAT(username, ' (', role, ')') SEPARATOR ', ') as usuarios
        FROM usuarios 
        GROUP BY status
    ");
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($stats as $stat) {
        $status_display = $stat['status'] ?: 'NULL';
        echo "  Status '{$status_display}': {$stat['total']} usuarios\n";
        if ($stat['total'] <= 10) {
            echo "    → {$stat['usuarios']}\n";
        }
    }
    
    // 3. Mostrar usuarios administradores
    echo "\n--- USUARIOS ADMINISTRADORES ---\n";
    $stmt = $pdo->query("
        SELECT id, username, email, role, status, 
               CASE WHEN password_hash IS NOT NULL AND password_hash != '' THEN 'Sí' ELSE 'No' END as tiene_password
        FROM usuarios 
        WHERE role IN ('admin_general', 'admin_torneo', 'admin_club')
        ORDER BY role, username
    ");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($admins as $admin) {
        $status_icon = $admin['status'] === 'approved' ? '✅' : '❌';
        echo "  {$status_icon} [{$admin['role']}] {$admin['username']} - Status: {$admin['status']} - Password: {$admin['tiene_password']}\n";
    }
    
    // 4. Buscar usuarios con problemas
    echo "\n--- USUARIOS CON PROBLEMAS ---\n";
    $stmt = $pdo->query("
        SELECT id, username, role, status
        FROM usuarios 
        WHERE status IS NULL 
           OR status = '' 
           OR status NOT IN ('approved', 'pending', 'suspended')
           OR password_hash IS NULL 
           OR password_hash = ''
    ");
    $problemas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($problemas)) {
        echo "  ✅ No se encontraron usuarios con problemas\n";
    } else {
        foreach ($problemas as $prob) {
            echo "  ⚠️ {$prob['username']} (ID: {$prob['id']}) - Status: '{$prob['status']}'\n";
        }
    }
    
    // 5. CORRECCIÓN AUTOMÁTICA (si se pasa parámetro)
    $fix = ($is_cli && isset($argv[1]) && $argv[1] === '--fix') || isset($_GET['fix']);
    
    if ($fix) {
        echo "\n===========================================\n";
        echo "EJECUTANDO CORRECCIONES\n";
        echo "===========================================\n";
        
        // Corregir usuarios sin status o con status vacío
        $stmt = $pdo->prepare("UPDATE usuarios SET status = 'approved' WHERE status IS NULL OR status = ''");
        $stmt->execute();
        $affected = $stmt->rowCount();
        echo "✅ {$affected} usuarios actualizados a status='approved'\n";
        
        // Corregir admin_general si no tiene status approved
        $stmt = $pdo->prepare("UPDATE usuarios SET status = 'approved' WHERE role = 'admin_general' AND status != 'approved'");
        $stmt->execute();
        $affected = $stmt->rowCount();
        echo "✅ {$affected} admin_general actualizados a approved\n";
        
    } else {
        echo "\n💡 Para aplicar correcciones automáticas:\n";
        if ($is_cli) {
            echo "   php scripts/fix_user_status.php --fix\n";
        } else {
            echo "   Agregar &fix=1 a la URL\n";
        }
    }
    
    // 6. Verificar un usuario específico (si se proporciona)
    $check_user = $_GET['user'] ?? ($argv[2] ?? null);
    if ($check_user) {
        echo "\n--- VERIFICACIÓN DE USUARIO: {$check_user} ---\n";
        $stmt = $pdo->prepare("
            SELECT id, username, email, role, status, 
                   SUBSTRING(password_hash, 1, 20) as password_preview,
                   created_at, club_id
            FROM usuarios 
            WHERE username = ? OR email = ?
        ");
        $stmt->execute([$check_user, $check_user]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo "  ID: {$user['id']}\n";
            echo "  Username: {$user['username']}\n";
            echo "  Email: {$user['email']}\n";
            echo "  Role: {$user['role']}\n";
            echo "  Status: {$user['status']}\n";
            echo "  Password Hash: {$user['password_preview']}...\n";
            echo "  Club ID: {$user['club_id']}\n";
            echo "  Creado: {$user['created_at']}\n";
            
            if ($user['status'] !== 'approved') {
                echo "\n  ⚠️ Este usuario NO puede iniciar sesión (status != 'approved')\n";
            } else {
                echo "\n  ✅ Este usuario PUEDE iniciar sesión (status = 'approved')\n";
            }
        } else {
            echo "  ❌ Usuario no encontrado\n";
        }
    }
    
    echo "\n===========================================\n";
    echo "FIN DEL DIAGNÓSTICO\n";
    echo "===========================================\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo $is_cli ? "" : "</pre>";











