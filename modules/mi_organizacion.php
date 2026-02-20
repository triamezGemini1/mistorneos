<?php
/**
 * Gestión de Mi Organización
 * Permite al admin_club editar los datos de su organización
 */

if (!defined('APP_BOOTSTRAPPED')) { 
    require_once __DIR__ . '/../config/bootstrap.php'; 
}
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/file_upload.php';

// Solo admin_club y admin_general pueden acceder
Auth::requireRole(['admin_club', 'admin_general']);

$current_user = Auth::user();
$is_admin_general = Auth::isAdminGeneral();
$message = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Obtener la organización del usuario
$organizacion = null;
$organizacion_id = null;

$action_get = $_GET['action'] ?? '';

// Desactivar/Reactivar: manejado por index.php -> admin_org/organizacion/actions/

if ($is_admin_general) {
    // Admin general puede ver/editar cualquier organización
    $organizacion_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    if ($action_get === 'new') {
        $organizacion_id = null;
        $organizacion = [];
    } elseif ($organizacion_id) {
        $stmt = DB::pdo()->prepare("
            SELECT o.*, e.nombre as entidad_nombre, u.nombre as admin_nombre, u.email as admin_email
            FROM organizaciones o
            LEFT JOIN entidad e ON o.entidad = e.id
            LEFT JOIN usuarios u ON o.admin_user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->execute([$organizacion_id]);
        $organizacion = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} else {
    // Admin club solo puede ver su organización
    $stmt = DB::pdo()->prepare("
        SELECT o.*, e.nombre as entidad_nombre
        FROM organizaciones o
        LEFT JOIN entidad e ON o.entidad = e.id
        WHERE o.admin_user_id = ? AND o.estatus = 1
        LIMIT 1
    ");
    $stmt->execute([$current_user['id']]);
    $organizacion = $stmt->fetch(PDO::FETCH_ASSOC);
    $organizacion_id = $organizacion['id'] ?? null;
}

// Procesar creación de organización (solo admin_general)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear' && $is_admin_general) {
    try {
        $nombre = trim($_POST['nombre'] ?? '');
        $admin_user_id = (int)($_POST['admin_user_id'] ?? 0);
        if (empty($nombre)) {
            throw new Exception('El nombre de la organización es requerido');
        }
        if ($admin_user_id <= 0) {
            throw new Exception('Debe seleccionar el administrador de la organización');
        }
        $stmt = DB::pdo()->prepare("SELECT id FROM organizaciones WHERE admin_user_id = ?");
        $stmt->execute([$admin_user_id]);
        if ($stmt->fetch()) {
            throw new Exception('Ese usuario ya tiene una organización asignada');
        }
        $responsable = trim($_POST['responsable'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $entidad = (int)($_POST['entidad'] ?? 0);

        $stmt = DB::pdo()->prepare("
            INSERT INTO organizaciones (nombre, direccion, responsable, telefono, email, entidad, admin_user_id, estatus, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
        ");
        $stmt->execute([$nombre, $direccion, $responsable, $telefono, $email, $entidad, $admin_user_id]);
        header('Location: index.php?page=mi_organizacion&success=' . urlencode('Organización creada correctamente'));
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Procesar formulario de actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'actualizar') {
    try {
        $org_id = (int)($_POST['organizacion_id'] ?? 0);
        
        // Validar permisos
        if (!$is_admin_general) {
            if (!$organizacion || $organizacion['id'] != $org_id) {
                throw new Exception('No tiene permisos para editar esta organización');
            }
        }
        
        // Validar datos
        $nombre = trim($_POST['nombre'] ?? '');
        if (empty($nombre)) {
            throw new Exception('El nombre de la organización es requerido');
        }
        
        $responsable = trim($_POST['responsable'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        
        // Procesar logo si se subió
        $logo_path = null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            // Validar tipo de archivo
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = mime_content_type($_FILES['logo']['tmp_name']);
            
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception('Tipo de archivo no permitido. Use JPG, PNG, GIF o WEBP.');
            }
            
            // Crear directorio si no existe
            $upload_dir = __DIR__ . '/../upload/organizaciones/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generar nombre único
            $extension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $filename = 'org_' . $org_id . '_' . time() . '.' . $extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $filepath)) {
                $logo_path = 'upload/organizaciones/' . $filename;
                
                // Eliminar logo anterior si existe
                $stmt = DB::pdo()->prepare("SELECT logo FROM organizaciones WHERE id = ?");
                $stmt->execute([$org_id]);
                $old_logo = $stmt->fetchColumn();
                if ($old_logo && file_exists(__DIR__ . '/../' . $old_logo)) {
                    @unlink(__DIR__ . '/../' . $old_logo);
                }
            } else {
                throw new Exception('Error al subir el archivo');
            }
        }
        
        // Actualizar en base de datos
        $sql = "UPDATE organizaciones SET nombre = ?, responsable = ?, telefono = ?, email = ?, direccion = ?, updated_at = NOW()";
        $params = [$nombre, $responsable, $telefono, $email, $direccion];
        
        if ($logo_path) {
            $sql .= ", logo = ?";
            $params[] = $logo_path;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $org_id;
        
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute($params);
        
        // Redirigir con mensaje de éxito
        $redirect = 'index.php?page=mi_organizacion';
        if ($is_admin_general) {
            $redirect .= '&id=' . $org_id;
        }
        $redirect .= '&success=' . urlencode('Organización actualizada correctamente');
        header('Location: ' . $redirect);
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Recargar datos después de actualización
if ($organizacion_id && empty($organizacion)) {
    $stmt = DB::pdo()->prepare("
        SELECT o.*, e.nombre as entidad_nombre, u.nombre as admin_nombre, u.email as admin_email
        FROM organizaciones o
        LEFT JOIN entidad e ON o.entidad = e.id
        LEFT JOIN usuarios u ON o.admin_user_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$organizacion_id]);
    $organizacion = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Si admin_general y no hay ID, mostrar lista de organizaciones
$lista_organizaciones = [];
$admin_sin_organizacion = [];
$entidades_options = [];
if ($is_admin_general) {
    if (!$organizacion_id && $action_get !== 'new') {
        $stmt = DB::pdo()->query("
            SELECT o.*, e.nombre as entidad_nombre, u.nombre as admin_nombre,
                   (SELECT COUNT(*) FROM clubes WHERE organizacion_id = o.id) as total_clubes,
                   (SELECT COUNT(*) FROM tournaments WHERE club_responsable = o.id) as total_torneos
            FROM organizaciones o
            LEFT JOIN entidad e ON o.entidad = e.id
            LEFT JOIN usuarios u ON o.admin_user_id = u.id
            ORDER BY o.estatus DESC, COALESCE(e.nombre, '') ASC, o.nombre ASC
        ");
        $lista_organizaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if ($action_get === 'new') {
        $stmt = DB::pdo()->query("
            SELECT u.id, u.nombre, u.username, u.email
            FROM usuarios u
            LEFT JOIN organizaciones o ON o.admin_user_id = u.id
            WHERE u.role = 'admin_club' AND u.status = 0 AND o.id IS NULL
            ORDER BY u.nombre ASC
        ");
        $admin_sin_organizacion = $stmt->fetchAll(PDO::FETCH_ASSOC);
        try {
            $cols = DB::pdo()->query("SHOW COLUMNS FROM entidad")->fetchAll(PDO::FETCH_ASSOC);
            $codeCol = 'id';
            $nameCol = 'nombre';
            foreach ($cols as $col) {
                $f = strtolower($col['Field'] ?? '');
                if ($f === 'codigo' || $f === 'id') $codeCol = $col['Field'];
                if ($f === 'nombre') $nameCol = $col['Field'];
            }
            $stmt = DB::pdo()->query("SELECT {$codeCol} AS id, {$nameCol} AS nombre FROM entidad ORDER BY {$nameCol} ASC");
            $entidades_options = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $entidades_options = [];
        }
    }
}

// Obtener estadísticas de la organización
$stats = ['clubes' => 0, 'torneos' => 0, 'afiliados' => 0];
if ($organizacion) {
    $stmt = DB::pdo()->prepare("SELECT COUNT(*) FROM clubes WHERE organizacion_id = ?");
    $stmt->execute([$organizacion['id']]);
    $stats['clubes'] = (int)$stmt->fetchColumn();
    
    $stmt = DB::pdo()->prepare("SELECT COUNT(*) FROM tournaments WHERE club_responsable = ?");
    $stmt->execute([$organizacion['id']]);
    $stats['torneos'] = (int)$stmt->fetchColumn();
    
    $stmt = DB::pdo()->prepare("
        SELECT COUNT(*) FROM usuarios u 
        INNER JOIN clubes c ON u.club_id = c.id 
        WHERE c.organizacion_id = ? AND u.role = 'usuario' AND u.status = 0
    ");
    $stmt->execute([$organizacion['id']]);
    $stats['afiliados'] = (int)$stmt->fetchColumn();
}
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3">
                <i class="fas fa-building text-primary me-2"></i>
                <?= $is_admin_general && !$organizacion ? 'Gestión de Organizaciones' : 'Mi Organización' ?>
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php?page=home">Inicio</a></li>
                    <?php if ($is_admin_general && $organizacion): ?>
                        <li class="breadcrumb-item"><a href="index.php?page=mi_organizacion">Organizaciones</a></li>
                        <li class="breadcrumb-item active"><?= htmlspecialchars($organizacion['nombre']) ?></li>
                    <?php else: ?>
                        <li class="breadcrumb-item active">Mi Organización</li>
                    <?php endif; ?>
                </ol>
            </nav>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($is_admin_general && $action_get === 'new'): ?>
        <?php include __DIR__ . '/admin_org/organizacion/views/mi_organizacion_form_nueva.php'; ?>
    <?php elseif ($is_admin_general && !$organizacion): ?>
        <?php include __DIR__ . '/admin_org/organizacion/views/mi_organizacion_lista.php'; ?>
    <?php elseif ($organizacion): ?>
        <?php include __DIR__ . '/admin_org/organizacion/views/mi_organizacion_form_editar.php'; ?>
    <?php else: ?>
        <?php include __DIR__ . '/admin_org/organizacion/views/mi_organizacion_sin_org.php'; ?>
    <?php endif; ?>
</div>
