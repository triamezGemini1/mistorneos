<?php
/**
 * Script para generar 40 usuarios de prueba asignados al club_id 5
 * 
 * Uso: php scripts/generate_test_users.php
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/security.php';

$club_id = 5;
$total_users = 40;

// Verificar que el club existe
try {
    $stmt = DB::pdo()->prepare("SELECT id, nombre FROM clubes WHERE id = ?");
    $stmt->execute([$club_id]);
    $club = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$club) {
        die("❌ Error: El club con ID $club_id no existe.\n");
    }
    
    echo "✅ Club encontrado: {$club['nombre']} (ID: $club_id)\n\n";
} catch (Exception $e) {
    die("❌ Error al verificar el club: " . $e->getMessage() . "\n");
}

// Nombres y apellidos para generar usuarios variados
$nombres = [
    'Carlos', 'María', 'José', 'Ana', 'Luis', 'Carmen', 'Pedro', 'Laura', 
    'Juan', 'Patricia', 'Roberto', 'Sandra', 'Miguel', 'Andrea', 'Fernando', 
    'Monica', 'Ricardo', 'Diana', 'Alejandro', 'Gloria', 'Daniel', 'Martha',
    'Francisco', 'Lucía', 'Manuel', 'Rosa', 'Antonio', 'Silvia', 'Jorge',
    'Elena', 'Rafael', 'Beatriz', 'Eduardo', 'Claudia', 'Alberto', 'Natalia',
    'Sergio', 'Adriana', 'Oscar', 'Verónica', 'Victor', 'Gabriela', 'Diego',
    'Paola', 'Andrés', 'Carolina', 'Felipe', 'Daniela', 'Mauricio', 'Valentina'
];

$apellidos = [
    'García', 'Rodríguez', 'González', 'Fernández', 'López', 'Martínez', 
    'Sánchez', 'Pérez', 'Gómez', 'Martín', 'Jiménez', 'Ruiz', 'Hernández',
    'Díaz', 'Moreno', 'Álvarez', 'Muñoz', 'Romero', 'Alonso', 'Gutiérrez',
    'Navarro', 'Torres', 'Domínguez', 'Vázquez', 'Ramos', 'Gil', 'Ramírez',
    'Serrano', 'Blanco', 'Suárez', 'Molina', 'Morales', 'Ortega', 'Delgado',
    'Castro', 'Ortiz', 'Rubio', 'Marín', 'Sanz', 'Núñez', 'Iglesias', 'Medina',
    'Garrido', 'Cortés', 'Castillo', 'Prieto', 'Calvo', 'Vidal', 'Lozano'
];

$sexos = ['M', 'F', 'M', 'F', 'M', 'F', 'M', 'F', 'M', 'F']; // Alternar para balance

echo "🔄 Generando $total_users usuarios de prueba...\n\n";

$pdo = DB::pdo();
$pdo->beginTransaction();

$generados = 0;
$errores = 0;

try {
    for ($i = 1; $i <= $total_users; $i++) {
        // Generar datos únicos
        $nombre = $nombres[array_rand($nombres)];
        $apellido1 = $apellidos[array_rand($apellidos)];
        $apellido2 = $apellidos[array_rand($apellidos)];
        $nombre_completo = "$nombre $apellido1 $apellido2";
        
        // Generar cédula única (V + número de 6-8 dígitos)
        $cedula_num = str_pad(rand(100000, 99999999), 8, '0', STR_PAD_LEFT);
        $cedula = "V" . $cedula_num;
        
        // Verificar que la cédula no exista
        $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE cedula = ?");
        $stmt_check->execute([$cedula]);
        if ($stmt_check->fetch()) {
            // Si existe, agregar un sufijo
            $cedula = "V" . $cedula_num . $i;
        }
        
        // Generar email único
        $email_base = strtolower($nombre . '.' . $apellido1 . $i);
        $email = $email_base . '@test.com';
        
        // Verificar que el email no exista
        $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt_check->execute([$email]);
        if ($stmt_check->fetch()) {
            $email = $email_base . $i . '@test.com';
        }
        
        // Generar username único (sin acentos y limitado a caracteres ASCII)
        $nombre_clean = iconv('UTF-8', 'ASCII//TRANSLIT', strtolower($nombre));
        $apellido1_clean = iconv('UTF-8', 'ASCII//TRANSLIT', strtolower($apellido1));
        $username_base = preg_replace('/[^a-z0-9]/', '', $nombre_clean . $apellido1_clean);
        $username = $username_base . $i;
        
        // Verificar que el username no exista (con reintentos)
        $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE username = ?");
        $intentos = 0;
        $username_final = $username;
        while ($intentos < 10) {
            $stmt_check->execute([$username_final]);
            if (!$stmt_check->fetch()) {
                break; // Username disponible
            }
            $username_final = $username_base . $i . rand(1000, 9999);
            $intentos++;
        }
        $username = $username_final;
        
        // Generar fecha de nacimiento (entre 18 y 70 años)
        $edad = rand(18, 70);
        $year = date('Y') - $edad;
        $month = rand(1, 12);
        $day = rand(1, 28); // Usar 28 para evitar problemas con febrero
        $fechnac = sprintf('%04d-%02d-%02d', $year, $month, $day);
        
        // Sexo alternado
        $sexo = $sexos[$i % count($sexos)];
        
        // Password por defecto: "test123" para todos
        $password_hash = Security::hashPassword('test123');
        
        // Insertar usuario
        try {
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
            
            $generados++;
            echo "✅ Usuario $i/$total_users: $nombre_completo ($username)\n";
            
        } catch (PDOException $e) {
            $errores++;
            echo "❌ Error al crear usuario $i: " . $e->getMessage() . "\n";
            // Continuar con el siguiente
        }
    }
    
    $pdo->commit();
    
    echo "\n";
    echo "═══════════════════════════════════════\n";
    echo "✅ Proceso completado\n";
    echo "═══════════════════════════════════════\n";
    echo "Usuarios generados: $generados\n";
    echo "Errores: $errores\n";
    echo "\n";
    echo "📋 Información de acceso:\n";
    echo "   - Username: [nombre][apellido][número]\n";
    echo "   - Password: test123\n";
    echo "   - Club ID: $club_id\n";
    echo "   - Status: approved (pueden iniciar sesión)\n";
    echo "\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ Error fatal: " . $e->getMessage() . "\n";
    echo "Se revirtieron todos los cambios.\n";
    exit(1);
}

