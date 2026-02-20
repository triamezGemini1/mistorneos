<?php

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../lib/file_upload.php';

Auth::requireRole(['admin_general','admin_torneo','admin_club']);
CSRF::validate();

// Obtener usuario actual y permisos
$current_user = Auth::user();
$user_id = Auth::id();
$user_role = $current_user['role'];
$user_club_id = $current_user['club_id'] ?? null;
$is_admin_general = Auth::isAdminGeneral();

try {
    // Validar ID
    if (empty($_POST['id'])) {
        throw new Exception('ID del torneo es requerido');
    }
    $id = (int)$_POST['id'];
    
    // Validar campos requeridos
    if (empty($_POST['nombre'])) {
        throw new Exception('El nombre del torneo es requerido');
    }
    if (empty($_POST['fechator'])) {
        throw new Exception('La fecha del torneo es requerida');
    }
    if (empty($_POST['clase']) || !in_array((int)$_POST['clase'], [1, 2])) {
        throw new Exception('La clase del torneo es inv�lida');
    }
    if (empty($_POST['modalidad']) || !in_array((int)$_POST['modalidad'], [1, 2, 3])) {
        throw new Exception('La modalidad del torneo es inv�lida');
    }
    
    // Verificar que el torneo existe y validar permisos (obtener archivos actuales y owner_user_id si existe)
    $tiene_owner_col = false;
    $tiene_permite_inscripcion_col = false;
    $tiene_publicar_landing_col = false;
    try {
        $cols = DB::pdo()->query("SHOW COLUMNS FROM tournaments")->fetchAll(PDO::FETCH_COLUMN);
        $tiene_owner_col = in_array('owner_user_id', $cols);
        $tiene_permite_inscripcion_col = in_array('permite_inscripcion_linea', $cols);
        $tiene_publicar_landing_col = in_array('publicar_landing', $cols);
    } catch (Exception $e) {
        $cols = [];
    }
    
    $tiene_entidad_col = in_array('entidad', $cols ?? []);
    
    $select_fields = "id, club_responsable, estatus, invitacion, normas, afiche";
    if ($tiene_owner_col) {
        $select_fields .= ", owner_user_id";
    }
    if ($tiene_entidad_col) {
        $select_fields .= ", entidad";
    }
    $stmt = DB::pdo()->prepare("SELECT $select_fields FROM tournaments WHERE id = ?");
    $stmt->execute([$id]);
    $torneo_actual = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$torneo_actual) {
        throw new Exception('Torneo no encontrado');
    }
    
    // Validar permisos: admin_torneo solo puede modificar torneos futuros de su club
    if (!Auth::canModifyTournament($id)) {
        throw new Exception('No tiene permisos para modificar este torneo. Solo puede modificar torneos futuros de su club.');
    }
    
    // owner_user_id: no puede ser 0 ni diferente al admin que edita (si la columna existe)
    $owner_user_id = 0;
    if ($tiene_owner_col) {
        $owner_user_id = (int)($torneo_actual['owner_user_id'] ?? 0);
        if ($owner_user_id <= 0 && $user_id > 0) {
            $owner_user_id = $user_id; // Corregir torneos legacy sin owner
        } elseif ($owner_user_id > 0 && $owner_user_id !== $user_id && !$is_admin_general) {
            throw new Exception('No puede modificar este torneo: el propietario es otro administrador.');
        }
    }
    
    // entidad: SIEMPRE la de la organización que organiza el torneo (nunca desde POST ni del usuario)
    $entidad = 0;
    if ($tiene_entidad_col) {
        // Organización del torneo: la actual o la que admin_general está eligiendo en el formulario
        $org_id = null;
        if ($is_admin_general && !empty($_POST['club_responsable'])) {
            $org_id = (int)$_POST['club_responsable'];
            $stmt_org_check = DB::pdo()->prepare("SELECT id FROM organizaciones WHERE id = ?");
            $stmt_org_check->execute([$org_id]);
            if (!$stmt_org_check->fetch()) {
                $org_id = (int)($torneo_actual['club_responsable'] ?? 0) ?: null;
            }
        }
        if ($org_id <= 0) {
            $org_id = (int)($torneo_actual['club_responsable'] ?? 0) ?: null;
        }
        if (!$org_id) {
            throw new Exception('El torneo no tiene organización asignada. No se puede actualizar la entidad.');
        }
        $stmt_ent = DB::pdo()->prepare("SELECT entidad FROM organizaciones WHERE id = ?");
        $stmt_ent->execute([$org_id]);
        $entidad = (int)$stmt_ent->fetchColumn();
        if ($entidad <= 0) {
            throw new Exception('La organización del torneo no tiene entidad definida. Asigne la entidad a la organización para poder actualizar el torneo.');
        }
    }
    
    // Preparar datos
    $nombre = trim($_POST['nombre']);
    $fechator = $_POST['fechator'];
    $lugar = !empty($_POST['lugar']) ? trim($_POST['lugar']) : null;

    // Evitar duplicados: mismo nombre, misma fecha, mismo lugar (excluir este torneo)
    if ($lugar === null || $lugar === '') {
        $stmt_dup = DB::pdo()->prepare("SELECT id FROM tournaments WHERE nombre = ? AND fechator = ? AND (lugar IS NULL OR lugar = '') AND id != ? LIMIT 1");
        $stmt_dup->execute([$nombre, $fechator, $id]);
    } else {
        $stmt_dup = DB::pdo()->prepare("SELECT id FROM tournaments WHERE nombre = ? AND fechator = ? AND lugar = ? AND id != ? LIMIT 1");
        $stmt_dup->execute([$nombre, $fechator, $lugar, $id]);
    }
    if ($stmt_dup->fetch()) {
        throw new Exception('Ya existe otro torneo con el mismo nombre, fecha y lugar. No se permiten torneos duplicados.');
    }

    // Guardar valores numéricos directamente (la tabla usa INT, no ENUM)
    $clase = (int)$_POST['clase']; // 1 = Torneo, 2 = Campeonato
    $modalidad = (int)$_POST['modalidad']; // 1 = Individual, 2 = Parejas, 3 = Equipos
    $tiempo = (int)($_POST['tiempo'] ?? 0);
    $puntos = (int)($_POST['puntos'] ?? 0);
    if ($puntos <= 0) {
        $puntos = 200; // El torneo no puede tener 0 puntos; por defecto 200
    }
    $rondas = (int)($_POST['rondas'] ?? 0);
    $costo = (float)($_POST['costo'] ?? 0);
    $ranking = (int)($_POST['ranking'] ?? 0);
    // pareclub ahora es un entero desde 1 en adelante (jugadores por club)
    $pareclub = !empty($_POST['pareclub']) ? max(1, (int)$_POST['pareclub']) : 0;
    $estatus = (int)($_POST['estatus'] ?? 1);
    $club_responsable = !empty($_POST['club_responsable']) ? (int)$_POST['club_responsable'] : null;
    $es_evento_masivo = isset($_POST['es_evento_masivo']) ? (int)$_POST['es_evento_masivo'] : 0;
    
    // Validar que es_evento_masivo sea válido (0, 1, 2, 3, o 4)
    if (!in_array($es_evento_masivo, [0, 1, 2, 3, 4])) {
        $es_evento_masivo = 0;
    }
    
    // Si es Evento Nacional (código 1), no genera ranking (tipo polla)
    if ($es_evento_masivo == 1) {
        $ranking = 0;
    }
    // Evento Regional (2) o Local (3): puede generar ranking (se mantiene el valor del formulario)
    // Evento Privado (4): se muestra pero no permite inscripción en línea
    
    $cuenta_id = !empty($_POST['cuenta_id']) ? (int)$_POST['cuenta_id'] : null;
    $permite_inscripcion_linea = isset($_POST['permite_inscripcion_linea']) ? 1 : 0;
    $publicar_landing = isset($_POST['publicar_landing']) ? 1 : 0;
    
    // Validar: solo admin_general puede cambiar la organización del torneo
    // admin_club y admin_torneo mantienen la organización original
    if (!$is_admin_general) {
        // Mantener la organización original del torneo
        $club_responsable = $torneo_actual['club_responsable'];
    } else {
        // admin_general puede cambiar la organización
        // Si se especifica un nuevo club_responsable, verificar que sea una organización válida
        if ($club_responsable) {
            $stmt_org = DB::pdo()->prepare("SELECT id FROM organizaciones WHERE id = ? AND estatus = 1");
            $stmt_org->execute([$club_responsable]);
            if (!$stmt_org->fetch()) {
                throw new Exception('La organización seleccionada no es válida');
            }
        } else {
            // Si no se especifica, mantener la original
            $club_responsable = $torneo_actual['club_responsable'];
        }
    }
    
    // Procesar archivos si se subieron
    $file_paths = [
        'invitacion' => $torneo_actual['invitacion'] ?? '',
        'normas' => $torneo_actual['normas'] ?? '',
        'afiche' => $torneo_actual['afiche'] ?? ''
    ];
    
    $file_fields = ['invitacion', 'normas', 'afiche'];
    foreach ($file_fields as $field) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            try {
                // Eliminar archivo anterior si existe
                if (!empty($file_paths[$field])) {
                    FileUpload::deleteFile($file_paths[$field]);
                }
                
                // Subir nuevo archivo
                $file_paths[$field] = FileUpload::uploadTournamentFile($_FILES[$field], $field, $id);
            } catch (Exception $e) {
                // Si falla la subida, continuar con los dem�s archivos
                error_log("Error al subir $field para torneo $id: " . $e->getMessage());
            }
        }
    }
    
    // Actualizar en la base de datos (incluir owner_user_id si existe la columna)
    
    $update_fields = "
        nombre = :nombre,
        fechator = :fechator,
        lugar = :lugar,
        clase = :clase,
        modalidad = :modalidad,
        tiempo = :tiempo,
        puntos = :puntos,
        rondas = :rondas,
        costo = :costo,
        ranking = :ranking,
        pareclub = :pareclub,
        estatus = :estatus,
        es_evento_masivo = :es_evento_masivo,
        club_responsable = :club_responsable,
        cuenta_id = :cuenta_id,
        invitacion = :invitacion,
        normas = :normas,
        afiche = :afiche
    ";
    $params = [
        ':id' => $id,
        ':nombre' => $nombre,
        ':fechator' => $fechator,
        ':lugar' => $lugar,
        ':clase' => $clase,
        ':modalidad' => $modalidad,
        ':tiempo' => $tiempo,
        ':puntos' => $puntos,
        ':rondas' => $rondas,
        ':costo' => $costo,
        ':ranking' => $ranking,
        ':pareclub' => $pareclub,
        ':estatus' => $estatus,
        ':es_evento_masivo' => $es_evento_masivo,
        ':club_responsable' => $club_responsable,
        ':cuenta_id' => $cuenta_id,
        ':invitacion' => $file_paths['invitacion'],
        ':normas' => $file_paths['normas'],
        ':afiche' => $file_paths['afiche']
    ];
    
    if ($tiene_owner_col && $owner_user_id > 0) {
        $update_fields .= ", owner_user_id = :owner_user_id";
        $params[':owner_user_id'] = $owner_user_id;
    }
    if ($tiene_entidad_col && $entidad > 0) {
        $update_fields .= ", entidad = :entidad";
        $params[':entidad'] = $entidad;
    }
    if ($tiene_permite_inscripcion_col) {
        $update_fields .= ", permite_inscripcion_linea = :permite_inscripcion_linea";
        $params[':permite_inscripcion_linea'] = $permite_inscripcion_linea;
    }
    if ($tiene_publicar_landing_col) {
        $update_fields .= ", publicar_landing = :publicar_landing";
        $params[':publicar_landing'] = $publicar_landing;
    }
    
    $stmt = DB::pdo()->prepare("UPDATE tournaments SET $update_fields WHERE id = :id");
    $result = $stmt->execute($params);
    
    if (!$result) {
        throw new Exception('Error al actualizar el torneo');
    }
    
    // Redirigir con �xito
    $redirect_url = app_base_url() . '/public/index.php?page=tournaments&success=' . urlencode('Torneo actualizado exitosamente');
    header('Location: ' . $redirect_url);
    exit;
    
} catch (Exception $e) {
    // Redirigir con error
    $id = isset($id) ? $id : ($_POST['id'] ?? 0);
    $redirect_url = app_base_url() . '/public/index.php?page=tournaments&action=edit&id=' . $id . '&error=' . urlencode($e->getMessage());
    header('Location: ' . $redirect_url);
    exit;
}

