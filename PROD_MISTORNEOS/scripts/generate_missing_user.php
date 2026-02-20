<?php
/**
 * Script para generar el usuario faltante (40 total)
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/security.php';

$club_id = 5;

// Verificar cuántos usuarios hay
$stmt = DB::pdo()->prepare("SELECT COUNT(*) FROM usuarios WHERE club_id = ?");
$stmt->execute([$club_id]);
$actual = (int)$stmt->fetchColumn();
$necesarios = 40 - $actual;

if ($necesarios <= 0) {
    echo "✅ Ya hay 40 o más usuarios en el club $club_id\n";
    exit(0);
}

echo "🔄 Generando $necesarios usuario(s) faltante(s)...\n\n";

$nombres = ['Carlos', 'María', 'José', 'Ana', 'Luis', 'Carmen', 'Pedro', 'Laura'];
$apellidos = ['García', 'Rodríguez', 'González', 'Fernández', 'López', 'Martínez', 'Sánchez', 'Pérez'];

$pdo = DB::pdo();
$pdo->beginTransaction();

try {
    for ($i = 1; $i <= $necesarios; $i++) {
        $nombre = $nombres[array_rand($nombres)];
        $apellido1 = $apellidos[array_rand($apellidos)];
        $apellido2 = $apellidos[array_rand($apellidos)];
        $nombre_completo = "$nombre $apellido1 $apellido2";
        
        // Generar cédula única
        $cedula = "V" . str_pad(rand(20000000, 29999999), 8, '0', STR_PAD_LEFT);
        $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE cedula = ?");
        $stmt_check->execute([$cedula]);
        if ($stmt_check->fetch()) {
            $cedula = "V" . str_pad(rand(30000000, 39999999), 8, '0', STR_PAD_LEFT);
        }
        
        // Generar email único
        $nombre_clean = iconv('UTF-8', 'ASCII//TRANSLIT', strtolower($nombre));
        $apellido1_clean = iconv('UTF-8', 'ASCII//TRANSLIT', strtolower($apellido1));
        $email_base = preg_replace('/[^a-z0-9]/', '', $nombre_clean . '.' . $apellido1_clean);
        $email = $email_base . time() . rand(100, 999) . '@test.com';
        
        // Generar username único
        $username_base = preg_replace('/[^a-z0-9]/', '', $nombre_clean . $apellido1_clean);
        $username = $username_base . time() . rand(100, 999);
        
        // Verificar username
        $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE username = ?");
        $stmt_check->execute([$username]);
        if ($stmt_check->fetch()) {
            $username = $username_base . time() . rand(1000, 9999);
        }
        
        $edad = rand(18, 70);
        $year = date('Y') - $edad;
        $fechnac = sprintf('%04d-%02d-%02d', $year, rand(1, 12), rand(1, 28));
        $sexo = ($i % 2 == 0) ? 'F' : 'M';
        $password_hash = Security::hashPassword('test123');
        
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (
                nombre, cedula, sexo, fechnac, email, username, password_hash,
                role, club_id, status, approved_at
            ) VALUES (
                :nombre, :cedula, :sexo, :fechnac, :email, :username, :password_hash,
                'usuario', :club_id, 'approved', NOW()
            )
        ");
        
        $stmt->execute([
            ':nombre' => $nombre_completo,
            ':cedula' => $cedula,
            ':sexo' => $sexo,
            ':fechnac' => $fechnac,
            ':email' => $email,
            ':username' => $username,
            ':password_hash' => $password_hash,
            ':club_id' => $club_id
        ]);
        
        echo "✅ Usuario creado: $nombre_completo ($username)\n";
    }
    
    $pdo->commit();
    echo "\n✅ Proceso completado. Total usuarios en club $club_id: " . ($actual + $necesarios) . "\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}












