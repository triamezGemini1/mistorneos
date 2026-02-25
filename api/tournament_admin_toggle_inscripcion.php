<?php
/**
 * API para inscribir/desinscribir jugadores en tiempo real
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../lib/InscritosHelper.php';
require_once __DIR__ . '/../lib/security.php';

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
    $action = $_POST['action'] ?? ''; // 'inscribir', 'desinscribir', 'registrar_inscribir'
    $torneo_id = (int)($_POST['torneo_id'] ?? 0);
    $id_usuario = (int)($_POST['id_usuario'] ?? 0);
    $id_club = !empty($_POST['id_club']) ? (int)$_POST['id_club'] : null;
    $estatus = 1; // confirmado

    // Registrar nuevo usuario e inscribir (NIVEL 4 / persona externa).
    // Orden obligatorio: 1) INSERT en usuarios (crear cuenta), 2) INSERT en inscritos (inscribir en torneo).
    if ($action === 'registrar_inscribir') {
        if ($torneo_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Torneo inválido']);
            exit;
        }
        if (!Auth::canAccessTournament($torneo_id)) {
            echo json_encode(['success' => false, 'error' => 'Sin permiso para este torneo']);
            exit;
        }
        $nacionalidad = strtoupper(trim($_POST['nacionalidad'] ?? 'V'));
        if (!in_array($nacionalidad, ['V', 'E', 'J', 'P'], true)) {
            $nacionalidad = 'V';
        }
        $cedula = preg_replace('/\D/', '', trim($_POST['cedula'] ?? ''));
        $nombre = trim($_POST['nombre'] ?? '');
        $fechnac = trim($_POST['fechnac'] ?? '');
        $sexo = strtoupper(trim($_POST['sexo'] ?? 'M'));
        if (!in_array($sexo, ['M', 'F', 'O'], true)) {
            $sexo = 'M';
        }
        $telefono = trim($_POST['telefono'] ?? $_POST['celular'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $id_club = !empty($_POST['id_club']) ? (int)$_POST['id_club'] : null;
        $current_user = Auth::user();
        $user_club_id = $current_user['club_id'] ?? null;
        if ($id_club <= 0) {
            $id_club = $user_club_id;
        }
        if (strlen($cedula) < 4) {
            echo json_encode(['success' => false, 'error' => 'Cédula inválida']);
            exit;
        }
        if (strlen($nombre) < 2) {
            echo json_encode(['success' => false, 'error' => 'Nombre requerido']);
            exit;
        }
        if (empty($email)) {
            $email = 'user' . $cedula . '@inscrito.local';
        }
        $pdo = DB::pdo();
        // Regla de negocio: username = Nacionalidad + Cédula (ej. V12345678); si existe, sufijo _2, _3...
        $username = $nacionalidad . $cedula;
        $sufijo = '';
        $idx = 0;
        while (true) {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ?");
            $stmt->execute([$username . $sufijo]);
            if (!$stmt->fetch()) {
                break;
            }
            $idx++;
            $sufijo = '_' . $idx;
        }
        $username = $username . $sufijo;
        // Password según criterio estándar: cédula (mín. 6 caracteres)
        $password = strlen($cedula) >= 6 ? $cedula : str_pad($cedula, 6, '0', STR_PAD_LEFT);
        $create = Security::createUser([
            'username' => $username,
            'password' => $password,
            'role' => 'usuario',
            'nombre' => $nombre,
            'cedula' => $cedula,
            'nacionalidad' => $nacionalidad,
            'sexo' => $sexo,
            'fechnac' => $fechnac ?: null,
            'email' => $email,
            'celular' => $telefono,
            'club_id' => $id_club,
            '_allow_club_for_usuario' => true
        ]);
        if (!empty($create['errors'])) {
            echo json_encode(['success' => false, 'error' => implode(', ', $create['errors'])]);
            exit;
        }
        $id_usuario = (int)($create['user_id'] ?? 0);
        if ($id_usuario <= 0) {
            echo json_encode(['success' => false, 'error' => 'No se pudo crear el usuario']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT id FROM inscritos WHERE id_usuario = ? AND torneo_id = ?");
        $stmt->execute([$id_usuario, $torneo_id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'El usuario ya está inscrito en este torneo']);
            exit;
        }
        $id_inscrito = InscritosHelper::insertarInscrito($pdo, [
            'id_usuario' => $id_usuario,
            'torneo_id' => $torneo_id,
            'id_club' => $id_club,
            'estatus' => $estatus,
            'inscrito_por' => Auth::id(),
            'numero' => 0
        ]);
        echo json_encode(['success' => true, 'message' => 'Usuario registrado e inscrito correctamente', 'id' => $id_inscrito, 'id_usuario' => $id_usuario]);
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

