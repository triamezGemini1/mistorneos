<?php
/**
 * Genera usuarios de prueba con datos aleatorios para clubes especificados.
 * No depende de la BD personas.
 *
 * Uso: php scripts/generar_usuarios_prueba_clubes.php
 *      php scripts/generar_usuarios_prueba_clubes.php --clubs=2,3 --count=40
 */

$opts = getopt('c:n:', ['clubs:', 'count:']);
$club_ids_raw = $opts['c'] ?? $opts['clubs'] ?? '2,3';
$count_per_club = (int)($opts['n'] ?? $opts['count'] ?? 40);
$club_ids = array_map('intval', array_filter(array_map('trim', explode(',', $club_ids_raw))));
if (empty($club_ids)) {
    $club_ids = [2, 3];
}
$count_per_club = max(1, min(100, $count_per_club));

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/Security.php';

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
$sexos = ['M', 'F', 'M', 'F', 'M', 'F', 'M', 'F', 'M', 'F'];

$pdo = DB::pdo();
$clubes_info = [];
foreach ($club_ids as $cid) {
    $stmt = $pdo->prepare("SELECT id, nombre, entidad FROM clubes WHERE id = ? AND estatus = 1");
    $stmt->execute([$cid]);
    $club = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$club) {
        die("❌ Club ID $cid no existe o está inactivo.\n");
    }
    $clubes_info[$cid] = $club;
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "  Generar Usuarios de Prueba\n";
echo "═══════════════════════════════════════════════════════════════\n\n";
echo "  Clubes: " . implode(', ', $club_ids) . " | $count_per_club usuarios por club\n\n";

$total = count($club_ids) * $count_per_club;
$pdo->beginTransaction();
$generados = 0;
$errores = 0;
$cedulas_usadas = [];
$contador_global = 0;

try {
    foreach ($club_ids as $club_id) {
        $club = $clubes_info[$club_id];
        for ($i = 1; $i <= $count_per_club; $i++) {
            $contador_global++;
            $nombre = $nombres[array_rand($nombres)];
            $apellido1 = $apellidos[array_rand($apellidos)];
            $apellido2 = $apellidos[array_rand($apellidos)];
            $nombre_completo = "$nombre $apellido1 $apellido2";

            $cedula_num = str_pad(rand(100000, 99999999), 8, '0', STR_PAD_LEFT);
            $cedula = "V" . $cedula_num;
            while (isset($cedulas_usadas[$cedula])) {
                $cedula = "V" . str_pad(rand(100000, 99999999), 8, '0', STR_PAD_LEFT) . $contador_global;
            }
            $cedulas_usadas[$cedula] = true;

            $email_base = strtolower(preg_replace('/[^a-z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $nombre . $apellido1))) . $club_id . $contador_global;
            $email = $email_base . '@test.com';

            $username_base = preg_replace('/[^a-z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', strtolower($nombre . $apellido1)));
            $username = $username_base . $club_id . $contador_global;

            $edad = rand(18, 70);
            $fechnac = sprintf('%04d-%02d-%02d', date('Y') - $edad, rand(1, 12), rand(1, 28));
            $sexo = $sexos[$i % count($sexos)];
            $password_hash = Security::hashPassword('test123');
            $entidad = (int)$club['entidad'];

            $stmt_check = $pdo->prepare("SELECT 1 FROM usuarios WHERE cedula = ? OR username = ? LIMIT 1");
            $stmt_check->execute([$cedula, $username]);
            if ($stmt_check->fetch()) {
                $username = $username_base . $club_id . $contador_global . rand(100, 999);
                $stmt_check->execute([$cedula, $username]);
                if ($stmt_check->fetch()) {
                    $errores++;
                    continue;
                }
            }

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO usuarios (nombre, cedula, sexo, fechnac, email, username, password_hash, role, club_id, entidad, status)
                    VALUES (:nombre, :cedula, :sexo, :fechnac, :email, :username, :password_hash, 'usuario', :club_id, :entidad, 0)
                ");
                $stmt->execute([
                    ':nombre' => $nombre_completo,
                    ':cedula' => $cedula,
                    ':sexo' => $sexo,
                    ':fechnac' => $fechnac,
                    ':email' => $email,
                    ':username' => $username,
                    ':password_hash' => $password_hash,
                    ':club_id' => $club_id,
                    ':entidad' => $entidad
                ]);
                $generados++;
                echo "  ✅ [$generados/$total] $nombre_completo | $username | Club {$club['nombre']}\n";
            } catch (PDOException $e) {
                $errores++;
                echo "  ❌ Error: $nombre_completo - " . $e->getMessage() . "\n";
            }
        }
    }
    $pdo->commit();

    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "  Proceso completado\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  Usuarios creados: $generados\n";
    echo "  Errores: $errores\n";
    echo "\n  Credenciales: Username según generado | Password: test123\n\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ Error fatal: " . $e->getMessage() . "\n";
    exit(1);
}
