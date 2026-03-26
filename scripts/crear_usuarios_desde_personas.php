<?php
/**
 * Script reutilizable para crear usuarios desde la tabla dbo_persona (BD externa personas).
 * Asigna usuarios a clubes especificados usando cedulas aleatorias de la BD externa.
 *
 * Usa el procedimiento de registro (Security::createUser) para garantizar consistencia.
 *
 * Uso:
 *   php scripts/crear_usuarios_desde_personas.php
 *   php scripts/crear_usuarios_desde_personas.php --clubs=2,3 --count=40
 *   php scripts/crear_usuarios_desde_personas.php -c 2,3 -n 40
 *
 * Parámetros:
 *   --clubs, -c   IDs de clubes separados por coma (ej: 2,3). Por defecto: 2,3
 *   --count, -n   Cantidad de usuarios por club. Por defecto: 40
 *   --password    Contraseña por defecto. Por defecto: test123
 *   --dry-run     Solo simular, no crear usuarios
 */

$opts = getopt('c:n:', ['clubs:', 'count:', 'password:', 'dry-run']);
$club_ids_raw = $opts['c'] ?? $opts['clubs'] ?? '2,3';
$count_per_club = (int)($opts['n'] ?? $opts['count'] ?? 40);
$password_default = $opts['password'] ?? 'test123';
$dry_run = isset($opts['dry-run']);

$club_ids = array_map('intval', array_filter(array_map('trim', explode(',', $club_ids_raw))));
if (empty($club_ids)) {
    $club_ids = [2, 3];
}
$count_per_club = max(1, min(500, $count_per_club));
$total_needed = count($club_ids) * $count_per_club;

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/persona_database.php';
require_once __DIR__ . '/../lib/Security.php';

echo "═══════════════════════════════════════════════════════════════\n";
echo "  Crear Usuarios desde BD Personas (dbo_persona)\n";
echo "═══════════════════════════════════════════════════════════════\n\n";
echo "  Clubes: " . implode(', ', $club_ids) . "\n";
echo "  Usuarios por club: $count_per_club\n";
echo "  Total a crear: $total_needed\n";
if ($dry_run) {
    echo "  [MODO DRY-RUN: No se creará ningún usuario]\n";
}
echo "\n";

// 1. Verificar que los clubes existan y obtener entidad
$pdo = DB::pdo();
$clubes_info = [];
foreach ($club_ids as $cid) {
    $stmt = $pdo->prepare("SELECT id, nombre, entidad FROM clubes WHERE id = ? AND estatus = 1");
    $stmt->execute([$cid]);
    $club = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$club) {
        die("❌ Error: El club con ID $cid no existe o está inactivo.\n");
    }
    $clubes_info[$cid] = $club;
    echo "✅ Club {$club['nombre']} (ID: $cid, entidad: {$club['entidad']})\n";
}
echo "\n";

// 2. Obtener personas aleatorias de la BD externa
$personaDb = new PersonaDatabase();
$personas = $personaDb->getRandomPersonasForSeed($total_needed + 50); // Pedir más por si hay duplicados

if (empty($personas)) {
    die("❌ No se pudieron obtener personas de la base de datos externa (dbo_persona).\n" .
        "   Verifique la conexión a la BD 'personas' y que la tabla exista.\n");
}
echo "✅ Personas obtenidas de dbo_persona: " . count($personas) . "\n";

// 3. Cédula en usuarios = solo dígitos (nacionalidad va en su propio campo)
$cedulaDigitos = function (array $p) {
    $num = preg_replace('/\D/', '', (string)($p['id_usuario'] ?? ''));
    return $num;
};
$cedulas_existentes = [];
$stmt_check = $pdo->prepare("SELECT 1 FROM usuarios WHERE cedula = ? LIMIT 1");
foreach ($personas as $p) {
    $ced = $cedulaDigitos($p);
    if (strlen($ced) < 5) {
        continue;
    }
    $stmt_check->execute([$ced]);
    if ($stmt_check->fetch()) {
        $cedulas_existentes[$ced] = true;
    }
}
$personas = array_values(array_filter($personas, function ($p) use ($cedulas_existentes, $cedulaDigitos) {
    $ced = $cedulaDigitos($p);
    return strlen($ced) >= 5 && !isset($cedulas_existentes[$ced]);
}));

if (count($personas) < $total_needed) {
    echo "⚠️  Solo hay " . count($personas) . " personas no registradas (se necesitan $total_needed)\n";
    echo "   Se crearán " . count($personas) . " usuarios.\n";
    $total_needed = count($personas);
} else {
    $personas = array_slice($personas, 0, $total_needed);
}
echo "✅ Personas a registrar: " . count($personas) . "\n\n";

// 4. Mapeo directo: un registro persona → datos para crear usuario (un solo lugar)
function personaAUsuario(array $persona, int $club_id, int $entidad, string $username, string $email, string $password): array {
    $cedula = preg_replace('/\D/', '', (string)($persona['id_usuario'] ?? ''));
    $nacionalidad = strtoupper(trim((string)($persona['nac'] ?? 'V')));
    if (!in_array($nacionalidad, ['V', 'E', 'J', 'P'], true)) {
        $nacionalidad = 'V';
    }
    $sexo = strtoupper(trim((string)($persona['sexo'] ?? 'M')));
    if (!in_array($sexo, ['M', 'F'], true)) {
        $sexo = ($sexo === 'F' || $sexo === '2') ? 'F' : 'M';
    }
    return [
        'username'   => $username,
        'password'   => $password,
        'email'      => $email,
        'role'       => 'usuario',
        'cedula'     => $cedula,
        'nacionalidad' => $nacionalidad,
        'nombre'     => $persona['nombre'] ?? 'Sin nombre',
        'celular'    => null,
        'fechnac'    => $persona['fechnac'] ?? null,
        'sexo'       => $sexo,
        'club_id'    => $club_id,
        'entidad'    => $entidad,
        'status'     => 0,
        '_allow_club_for_usuario' => true,
    ];
}

// 5. Función para generar username único
function generarUsername($persona, $usados) {
    $nombre = $persona['nombre'] ?? '';
    $nombre1 = $persona['nombre1'] ?? '';
    $apellido1 = $persona['apellido1'] ?? '';
    $cedulaSoloDigitos = preg_replace('/\D/', '', (string)($persona['id_usuario'] ?? ''));

    if (strlen($nombre1) >= 2 && strlen($apellido1) >= 2) {
        $base = strtolower(mb_substr($nombre1, 0, 2) . mb_substr($apellido1, 0, 2));
    } else {
        $partes = preg_split('/\s+/', trim($nombre), 2);
        $base = 'usr';
        if (!empty($partes[0])) {
            $base = strtolower(mb_substr($partes[0], 0, 2));
        }
        if (!empty($partes[1])) {
            $base .= strtolower(mb_substr($partes[1], 0, 2));
        }
        $base = preg_replace('/[^a-z]/', '', $base) ?: 'usr';
    }
    $base = iconv('UTF-8', 'ASCII//TRANSLIT', $base);
    $base = preg_replace('/[^a-z0-9]/', '', strtolower($base)) ?: 'usr';
    $sufijo = substr($cedulaSoloDigitos, -4) ?: (string)rand(1000, 9999);
    $username = $base . $sufijo;
    $contador = 0;
    while (isset($usados[$username]) && $contador < 100) {
        $username = $base . $sufijo . ($contador > 0 ? $contador : '');
        $contador++;
    }
    return $username;
}

// 6. Asignar personas a clubes (N por club)
$asignacion = [];
$idx = 0;
foreach ($club_ids as $cid) {
    for ($j = 0; $j < $count_per_club && $idx < count($personas); $j++, $idx++) {
        $asignacion[] = [
            'persona' => $personas[$idx],
            'club_id' => $cid,
            'club' => $clubes_info[$cid],
        ];
    }
}

// 7. Crear usuarios: persona → usuario en un solo paso
$generados = 0;
$errores = 0;
$usernames_usados = [];
$dominio_email = '@usuarios.local';

echo "🔄 Creando usuarios...\n\n";

foreach ($asignacion as $i => $item) {
    $persona = $item['persona'];
    $club_id = $item['club_id'];
    $club = $item['club'];
    $nombre = $persona['nombre'] ?? 'Sin nombre';

    $username = generarUsername($persona, $usernames_usados);
    $usernames_usados[$username] = true;

    $partes = preg_split('/\s+/', trim($nombre), 2);
    $iniciales = '';
    foreach ($partes as $p) {
        if (strlen($p) > 0) {
            $iniciales .= mb_substr($p, 0, 1);
        }
    }
    $iniciales = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $iniciales));
    $iniciales = preg_replace('/[^a-z]/', '', $iniciales) ?: 'us';
    $email = $iniciales . $username . $dominio_email;

    if ($dry_run) {
        echo "  [DRY-RUN] $nombre | $username | Club {$club['nombre']}\n";
        $generados++;
        continue;
    }

    // Un registro persona → un registro usuario (mapeo en personaAUsuario)
    $userData = personaAUsuario($persona, $club_id, (int)$club['entidad'], $username, $email, $password_default);
    $result = Security::createUser($userData);

    if ($result['success']) {
        $generados++;
        echo "  ✅ [" . ($generados) . "/" . count($asignacion) . "] $nombre | $username | Club {$club['nombre']}\n";
    } else {
        $errores++;
        echo "  ❌ Error: $nombre - " . implode(', ', $result['errors']) . "\n";
    }
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "  Proceso completado\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "  Usuarios creados: $generados\n";
echo "  Errores: $errores\n";
echo "\n";
echo "  📋 Credenciales:\n";
echo "     - Username: [iniciales]+[sufijo cédula]\n";
echo "     - Contraseña: $password_default\n";
echo "     - Email: [iniciales][username]$dominio_email\n";
echo "     - Clubes: " . implode(', ', array_map(fn($c) => $c['nombre'], $clubes_info)) . "\n";
echo "\n";
