<?php
/**
 * Validación en consola: simula la respuesta del API de búsqueda para una cédula.
 * Uso: php scripts/validar_cedula_inscrita.php [torneo_id] [nacionalidad] [cedula]
 * Ejemplo: php scripts/validar_cedula_inscrita.php 1 V 4978399
 *
 * Muestra el JSON que devolvería buscar_inscribir_sitio.php para esa cédula
 * (sin comprobar sesión; solo consulta BD).
 */
if (php_sapi_name() !== 'cli') {
    die('Solo ejecución por consola.');
}

$torneo_id = (int)($argv[1] ?? 0);
$nacionalidad = strtoupper(trim($argv[2] ?? 'V'));
$cedula_raw = trim($argv[3] ?? '');

if (!in_array($nacionalidad, ['V', 'E', 'J', 'P'], true)) {
    $nacionalidad = 'V';
}
$cedula = preg_replace('/^[VEJP]/i', '', $cedula_raw);
$cedula = preg_replace('/\D/', '', $cedula);

if ($cedula === '' || $torneo_id <= 0) {
    echo "Uso: php scripts/validar_cedula_inscrita.php <torneo_id> <nacionalidad> <cedula>\n";
    echo "Ejemplo: php scripts/validar_cedula_inscrita.php 1 V 4978399\n";
    exit(1);
}

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

$pdo = DB::pdo();
$cedula_nac = $nacionalidad . $cedula;

echo "Buscando: nacionalidad=$nacionalidad, cedula=$cedula (torneo_id=$torneo_id)\n";
echo str_repeat('-', 60) . "\n";

// Igual que la API: solo roles usuario y admin_club
$stmt = $pdo->prepare("
    SELECT id, username, nombre, cedula, email, celular, fechnac, sexo, nacionalidad, club_id, role
    FROM usuarios
    WHERE (cedula = ? OR cedula = ?) AND role IN ('usuario','admin_club')
    LIMIT 1
");
$stmt->execute([$cedula, $cedula_nac]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    // Diagnóstico: ¿existe la cédula con otro rol?
    $stmt2 = $pdo->prepare("SELECT id, username, cedula, role FROM usuarios WHERE cedula = ? OR cedula = ? LIMIT 3");
    $stmt2->execute([$cedula, $cedula_nac]);
    $otros = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($otros)) {
        echo "La cédula existe pero con rol no incluido en la búsqueda (API solo busca role 'usuario' o 'admin_club'):\n";
        foreach ($otros as $o) {
            echo "  id={$o['id']} cedula={$o['cedula']} role={$o['role']}\n";
        }
        echo "Para probar 'ya_inscrito' use una cédula de un usuario con role=usuario que esté en inscritos para este torneo.\n";
        echo str_repeat('-', 60) . "\n";
    }
}

if ($usuario) {
    $user_id = (int)$usuario['id'];
    echo "Usuario encontrado: id=$user_id, " . ($usuario['nombre'] ?? $usuario['username']) . "\n";

    $stmtInscrito = $pdo->prepare("
        SELECT i.id FROM inscritos i
        WHERE i.torneo_id = ? AND i.id_usuario = ?
        LIMIT 1
    ");
    $stmtInscrito->execute([$torneo_id, $user_id]);
    if ($stmtInscrito->fetch()) {
        $respuesta = [
            'success' => true,
            'resultado' => 'ya_inscrito',
            'mensaje' => 'Este jugador ya participa en el torneo.'
        ];
        echo "Estado: YA INSCRITO en este torneo.\n";
        echo "Respuesta API (JSON):\n" . json_encode($respuesta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        exit(0);
    }

    echo "Estado: Usuario existe pero NO está inscrito en este torneo.\n";
    $respuesta = [
        'success' => true,
        'resultado' => 'usuario',
        'usuario' => [
            'id' => $user_id,
            'nombre' => $usuario['nombre'] ?? '',
            'username' => $usuario['username'] ?? '',
        ]
    ];
    echo "Respuesta API (JSON):\n" . json_encode($respuesta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(0);
}

echo "Usuario NO encontrado en tabla usuarios.\n";
echo "Respuesta API sería: resultado=no_encontrado o persona_externa (si hay BD externa).\n";
echo json_encode([
    'success' => true,
    'resultado' => 'no_encontrado',
    'mensaje' => 'No encontrado. Complete los datos para registrar e inscribir.'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
