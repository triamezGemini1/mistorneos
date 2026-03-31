<?php
/**
 * API para inscribir/desinscribir jugadores en tiempo real
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../lib/InscritosHelper.php';
require_once __DIR__ . '/../lib/Tournament/Handlers/RegistrationHandler.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$u = Auth::user();
if (!$u) {
    echo json_encode(['success' => false, 'error' => 'Debe iniciar sesión para realizar esta acción.']);
    exit;
}

// Autorización por organización: quien puede inscribir es quien pertenece a la organización que gestiona el torneo (los clubes son solo informativos).
$torneo_id = (int)($_POST['torneo_id'] ?? 0);
$permiso = Auth::isAdminGeneral()
    || ($torneo_id > 0 && (
        Auth::canAccessTournament($torneo_id)
        || (($org_torneo = Auth::getTournamentOrganizacionId($torneo_id)) && Auth::userIsInOrganizacion($org_torneo))
    ));
if (!$permiso) {
    echo json_encode(['success' => false, 'error' => 'No autorizado para esta sección. Debe pertenecer a la organización que gestiona el torneo.']);
    exit;
}

// Validar CSRF
$csrf_token = $_POST['csrf_token'] ?? '';
$session_token = $_SESSION['csrf_token'] ?? '';
if (!$csrf_token || !$session_token || !hash_equals($session_token, $csrf_token)) {
    echo json_encode(['success' => false, 'error' => 'Token CSRF inválido']);
    exit;
}

try {
    $action = $_POST['action'] ?? ''; // 'inscribir', 'desinscribir', 'registrar_inscribir'
    $id_usuario = (int)($_POST['id_usuario'] ?? 0);
    $id_club = !empty($_POST['id_club']) ? (int)$_POST['id_club'] : null;
    // REGLA CRÍTICA: estatus forzado a (int) 1 para que "Gestionar Inscripciones" reconozca al jugador como activo de inmediato
    $estatus = 1;

    // Registrar nuevo usuario e inscribir (registro manual / persona externa).
    if ($action === 'registrar_inscribir') {
        if ($torneo_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Torneo inválido']);
            exit;
        }
        if (!Auth::canAccessTournament($torneo_id)) {
            echo json_encode(['success' => false, 'error' => 'Sin permiso para este torneo']);
            exit;
        }
        $pdo = DB::pdo();
        $out = \Tournament\Handlers\RegistrationHandler::apiRegistrarEInscribir($pdo, $torneo_id, $_POST, Auth::id());
        echo json_encode($out);
        exit;
    }

    if ($torneo_id <= 0 || $id_usuario <= 0) {
        echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
        exit;
    }
    
    // Verificar acceso al torneo
    if (!Auth::canAccessTournament($torneo_id)) {
        echo json_encode(['success' => false, 'error' => 'No tiene permisos para acceder a este torneo']);
        exit;
    }
    
    $pdo = DB::pdo();
    $current_user = Auth::user();
    $user_club_id = $current_user['club_id'] ?? null;
    
    if ($action === 'inscribir') {
        $out = \Tournament\Handlers\RegistrationHandler::apiInscribirUsuarioExistente(
            $pdo,
            $torneo_id,
            $id_usuario,
            $id_club,
            $estatus,
            Auth::id(),
            $user_club_id !== null ? (int) $user_club_id : null
        );
        echo json_encode($out);
    } elseif ($action === 'desinscribir') {
        // Marcar como retirado (estatus 4) en lugar de eliminar
        $stmt = $pdo->prepare("UPDATE inscritos SET estatus = ? WHERE id_usuario = ? AND torneo_id = ?");
        $stmt->execute([InscritosHelper::ESTATUS_RETIRADO_NUM, $id_usuario, $torneo_id]);
        
        echo json_encode(['success' => true, 'message' => 'Jugador desinscrito exitosamente']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Acción inválida']);
    }
    
} catch (Exception $e) {
    error_log("Error en toggle_inscripcion: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}

