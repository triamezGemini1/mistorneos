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
    // estatus en BD es INT/TINYINT: valor numérico 1 = confirmado (nunca string "activo" ni con comillas)
    $estatus = (int) 1;

    // Registrar nuevo usuario e inscribir (registro manual / persona externa).
    // Transacción: INSERT usuarios + INSERT inscritos atómicos (o rollback). estatus siempre numérico (1).
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
        $email_placeholder = 'user' . $cedula . '@inscrito.local';
        if (empty($email)) {
            $email = $email_placeholder;
        }

        $pdo = DB::pdo();

        // Validación de cédula: evitar "Duplicate entry for key usuarios.cedula" (UNIQUE en tabla usuarios)
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE cedula = ? LIMIT 1");
        $stmt->execute([$cedula]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Ya existe un usuario con esta cédula. Use la pestaña "Buscar por cédula" para inscribirlo.']);
            exit;
        }

        // Validación de email: evitar "Duplicate entry" si el correo ya existe en otro usuario
        if ($email !== $email_placeholder && $email !== '') {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'El correo electrónico ya está registrado por otro usuario. Use otro correo o déjelo en blanco.']);
                exit;
            }
        }

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
        $password = strlen($cedula) >= 6 ? $cedula : str_pad($cedula, 6, '0', STR_PAD_LEFT);

        // Transacción: usuario + inscrito atómicos (evitar "usuarios fantasma")
        $pdo->beginTransaction();
        try {
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
                $pdo->rollBack();
                echo json_encode(['success' => false, 'error' => implode(', ', $create['errors'])]);
                exit;
            }
            $id_usuario = (int)($create['user_id'] ?? 0);
            if ($id_usuario <= 0) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'error' => 'No se pudo crear el usuario']);
                exit;
            }
            $id_inscrito = InscritosHelper::insertarInscrito($pdo, [
                'id_usuario' => $id_usuario,
                'torneo_id' => $torneo_id,
                'id_club' => $id_club,
                'estatus' => $estatus,
                'inscrito_por' => Auth::id(),
                'numero' => 0,
                'nacionalidad' => $nacionalidad,
                'cedula' => $cedula
            ]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Usuario registrado e inscrito correctamente', 'id' => $id_inscrito, 'id_usuario' => $id_usuario]);
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('registrar_inscribir: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'sql_error' => $e->getMessage()
            ]);
        }
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
        
        // Validar que el usuario tenga todos los campos obligatorios completos (y obtener nacionalidad/cedula para INSERT en inscritos)
        $stmt = $pdo->prepare("SELECT nombre, cedula, sexo, email, username, entidad, nacionalidad FROM usuarios WHERE id = ?");
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
        
        // Insertar inscripción (nacionalidad y cedula obligatorios para búsqueda NIVEL 1 en inscritos)
        $nac_inscrito = isset($usuario_datos['nacionalidad']) && in_array(strtoupper(trim($usuario_datos['nacionalidad'])), ['V', 'E', 'J', 'P'], true)
            ? strtoupper(trim($usuario_datos['nacionalidad'])) : 'V';
        $ced_inscrito = isset($usuario_datos['cedula']) ? preg_replace('/\D/', '', (string)$usuario_datos['cedula']) : '';
        try {
            $id_inscrito = InscritosHelper::insertarInscrito($pdo, [
                'id_usuario' => $id_usuario,
                'torneo_id' => $torneo_id,
                'id_club' => $id_club,
                'estatus' => $estatus,
                'inscrito_por' => Auth::id(),
                'numero' => 0,
                'nacionalidad' => $nac_inscrito,
                'cedula' => $ced_inscrito
            ]);
            echo json_encode(['success' => true, 'message' => 'Jugador inscrito exitosamente', 'id' => $id_inscrito]);
        } catch (Exception $e) {
            error_log("Error al inscribir jugador (API): " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage(), 'sql_error' => $e->getMessage()]);
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

