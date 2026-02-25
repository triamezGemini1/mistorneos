<?php
/**
 * API: Búsqueda expedita para inscripción en sitio.
 * Orden: 1) inscritos (ya inscrito) → 2) usuarios → 3) base externa → 4) no encontrado.
 * Parámetros: torneo_id, nacionalidad, cedula (solo número).
 */
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

$torneo_id = (int)($_GET['torneo_id'] ?? 0);
$nacionalidad = strtoupper(trim($_GET['nacionalidad'] ?? 'V'));
$cedula = trim($_GET['cedula'] ?? '');

if (!in_array($nacionalidad, ['V', 'E', 'J', 'P'], true)) {
    $nacionalidad = 'V';
}
$cedula = preg_replace('/^[VEJP]/i', '', $cedula);
$cedula = preg_replace('/\D/', '', $cedula);

if ($cedula === '' || $torneo_id <= 0) {
    echo json_encode(['success' => false, 'resultado' => 'error', 'mensaje' => 'Faltan torneo_id, nacionalidad o cédula.']);
    exit;
}

Auth::requireRole(['admin_general', 'admin_torneo', 'admin_club']);
if (!Auth::canAccessTournament($torneo_id)) {
    echo json_encode(['success' => false, 'resultado' => 'error', 'mensaje' => 'Sin permiso para este torneo.']);
    exit;
}

$pdo = DB::pdo();
$cedula_nac = $nacionalidad . $cedula;

// 1) ¿Ya inscrito en este torneo?
$stmt = $pdo->prepare("
    SELECT i.id FROM inscritos i
    INNER JOIN usuarios u ON i.id_usuario = u.id
    WHERE i.torneo_id = ? AND (u.cedula = ? OR u.cedula = ?)
    LIMIT 1
");
$stmt->execute([$torneo_id, $cedula, $cedula_nac]);
if ($stmt->fetch()) {
    echo json_encode([
        'success' => true,
        'resultado' => 'ya_inscrito',
        'mensaje' => 'El jugador con cédula ' . $nacionalidad . $cedula . ' ya está inscrito en este torneo.'
    ]);
    exit;
}

// 2) Buscar en usuarios
$stmt = $pdo->prepare("
    SELECT id, username, nombre, cedula, email, celular, fechnac, sexo, nacionalidad, club_id
    FROM usuarios
    WHERE (cedula = ? OR cedula = ?) AND role IN ('usuario','admin_club')
    LIMIT 1
");
$stmt->execute([$cedula, $cedula_nac]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);
if ($usuario) {
    $fechnac = $usuario['fechnac'] ?? '';
    if ($fechnac && !preg_match('/^\d{4}-\d{2}-\d{2}/', $fechnac) && strtotime($fechnac) !== false) {
        $fechnac = date('Y-m-d', strtotime($fechnac));
    }
    echo json_encode([
        'success' => true,
        'resultado' => 'usuario',
        'usuario' => [
            'id' => (int)$usuario['id'],
            'username' => $usuario['username'],
            'nombre' => $usuario['nombre'],
            'cedula' => $usuario['cedula'],
            'email' => $usuario['email'] ?? '',
            'celular' => $usuario['celular'] ?? '',
            'telefono' => $usuario['celular'] ?? '',
            'fechnac' => $fechnac,
            'sexo' => $usuario['sexo'] ?? '',
            'nacionalidad' => $usuario['nacionalidad'] ?? $nacionalidad,
            'club_id' => (int)($usuario['club_id'] ?? 0)
        ]
    ]);
    exit;
}

// 3) Base de datos externa
if (file_exists(__DIR__ . '/../../config/persona_database.php')) {
    require_once __DIR__ . '/../../config/persona_database.php';
    try {
        $database = new PersonaDatabase();
        $result = $database->searchPersonaById($nacionalidad, $cedula);
        if (!empty($result['encontrado']) && !empty($result['persona'])) {
            $p = $result['persona'];
            echo json_encode([
                'success' => true,
                'resultado' => 'persona_externa',
                'persona' => [
                    'nacionalidad' => $p['nacionalidad'] ?? $nacionalidad,
                    'cedula' => $cedula,
                    'nombre' => $p['nombre'] ?? '',
                    'sexo' => $p['sexo'] ?? '',
                    'fechnac' => $p['fechnac'] ?? '',
                    'telefono' => $p['celular'] ?? $p['telefono'] ?? '',
                    'email' => $p['email'] ?? ''
                ]
            ]);
            exit;
        }
    } catch (Throwable $e) {
        error_log('buscar_inscribir_sitio externa: ' . $e->getMessage());
    }
}

// 4) No encontrado: permitir registro desde formulario
echo json_encode([
    'success' => true,
    'resultado' => 'no_encontrado',
    'mensaje' => 'No encontrado en inscritos ni en usuarios. Complete el formulario para registrar e inscribir.'
]);
