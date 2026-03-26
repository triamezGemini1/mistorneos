<?php
/**
 * API para inscribir/desinscribir jugadores en tiempo real
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../lib/InscritosHelper.php';

header('Content-Type: application/json; charset=utf-8');

Auth::requireRole(['admin_general', 'admin_torneo', 'admin_club']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
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
    $action = $_POST['action'] ?? ''; // 'inscribir' o 'desinscribir'
    $torneo_id = (int)($_POST['torneo_id'] ?? 0);
    $id_usuario = (int)($_POST['id_usuario'] ?? 0);
    $id_club = !empty($_POST['id_club']) ? (int)$_POST['id_club'] : null;
    // Inscripción (en sitio o por esta API): siempre confirmado
    $estatus = 1; // confirmado
    
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
        // Verificar si ya existe registro (solo estatus 1 = confirmado cuenta como inscrito)
        $stmt = $pdo->prepare("SELECT id, estatus FROM inscritos WHERE id_usuario = ? AND torneo_id = ?");
        $stmt->execute([$id_usuario, $torneo_id]);
        $existe = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existe && InscritosHelper::esConfirmado($existe['estatus'])) {
            echo json_encode(['success' => false, 'error' => 'Este usuario ya está inscrito en el torneo']);
            exit;
        }
        if ($existe) {
            // Re-inscribir: actualizar estatus a 1 (confirmado)
            $stmt = $pdo->prepare("UPDATE inscritos SET estatus = 1 WHERE id = ?");
            $stmt->execute([$existe['id']]);
            echo json_encode(['success' => true, 'message' => 'Jugador inscrito exitosamente', 'id' => (int)$existe['id']]);
            exit;
        }
        
        // Validar que el usuario tenga todos los campos obligatorios completos
        $stmt = $pdo->prepare("SELECT nombre, cedula, sexo, email, username, entidad FROM usuarios WHERE id = ?");
        $stmt->execute([$id_usuario]);
        $usuario_datos = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario_datos) {
            echo json_encode(['success' => false, 'error' => 'No se encontró el usuario seleccionado']);
            exit;
        }
        
        // Validar campos obligatorios
        $campos_faltantes = [];
        if (empty(trim($usuario_datos['nombre'] ?? ''))) {
            $campos_faltantes[] = 'Nombre';
        }
        if (empty(trim($usuario_datos['cedula'] ?? ''))) {
            $campos_faltantes[] = 'Cédula';
        }
        if (empty($usuario_datos['sexo'] ?? '')) {
            $campos_faltantes[] = 'Sexo';
        }
        if (empty(trim($usuario_datos['email'] ?? ''))) {
            $campos_faltantes[] = 'Email';
        }
        if (empty(trim($usuario_datos['username'] ?? ''))) {
            $campos_faltantes[] = 'Username';
        }
        
        if (!empty($campos_faltantes)) {
            $campos_lista = implode(', ', $campos_faltantes);
            echo json_encode(['success' => false, 'error' => 'El usuario no puede ser inscrito porque faltan los siguientes campos obligatorios: ' . $campos_lista . '. Por favor complete la información del usuario antes de inscribirlo.']);
            exit;
        }
        
        // Si no se especificó club, usar el club del usuario o el club del administrador
        if (empty($id_club) || $id_club == 0) {
            $stmt = $pdo->prepare("SELECT club_id FROM usuarios WHERE id = ?");
            $stmt->execute([$id_usuario]);
            $usuario_club = $stmt->fetchColumn();
            $id_club = $usuario_club ?: $user_club_id;
        }
        
        // Si aún no hay club, usar NULL
        if (empty($id_club) || $id_club == 0) {
            $id_club = null;
        }
        
        // Insertar inscripción usando función centralizada
        try {
            $id_inscrito = InscritosHelper::insertarInscrito($pdo, [
                'id_usuario' => $id_usuario,
                'torneo_id' => $torneo_id,
                'id_club' => $id_club,
                'estatus' => $estatus,
                'inscrito_por' => Auth::id(),
                'numero' => 0 // Se asignará después si es necesario para equipos
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Jugador inscrito exitosamente', 'id' => $id_inscrito]);
        } catch (Exception $e) {
            error_log("Error al inscribir jugador (API): " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
        
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

