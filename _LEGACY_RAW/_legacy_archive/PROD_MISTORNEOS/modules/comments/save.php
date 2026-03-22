<?php
/**
 * Guardar comentario
 * Requiere autenticación para usuarios registrados
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/csrf.php';

// Validar CSRF
CSRF::validate();

$pdo = DB::pdo();
$user = Auth::user();

// Obtener datos del POST
$nombre = trim($_POST['nombre'] ?? '');
$email = trim($_POST['email'] ?? '');
$tipo = $_POST['tipo'] ?? 'comentario';
$contenido = trim($_POST['contenido'] ?? '');
$calificacion = isset($_POST['calificacion']) ? (int)$_POST['calificacion'] : null;

// Validaciones
$errors = [];

// Si el usuario está logueado, usar sus datos
if ($user) {
    $usuario_id = Auth::id();
    $nombre = $user['nombre'] ?? $user['username'];
    $email = $user['email'] ?? $email;
} else {
    // Si no está logueado, requerir nombre
    if (empty($nombre)) {
        $errors[] = 'El nombre es requerido';
    }
    if (strlen($nombre) < 2) {
        $errors[] = 'El nombre debe tener al menos 2 caracteres';
    }
    if (strlen($nombre) > 100) {
        $errors[] = 'El nombre no puede exceder 100 caracteres';
    }
    $usuario_id = null;
}

// Validar tipo
if (!in_array($tipo, ['comentario', 'sugerencia', 'testimonio'])) {
    $tipo = 'comentario';
}

// Validar contenido
if (empty($contenido)) {
    $errors[] = 'El contenido del comentario es requerido';
}
if (strlen($contenido) < 10) {
    $errors[] = 'El comentario debe tener al menos 10 caracteres';
}
if (strlen($contenido) > 2000) {
    $errors[] = 'El comentario no puede exceder 2000 caracteres';
}

// Validar email si se proporciona
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'El email proporcionado no es válido';
}

// Validar calificación
if ($calificacion !== null && ($calificacion < 1 || $calificacion > 5)) {
    $calificacion = null;
}

// Protección contra spam básica
$ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

// Verificar si hay muchos comentarios recientes desde la misma IP (anti-spam)
// Primero verificar si la columna ip_address existe
try {
    $check_ip = $pdo->query("SHOW COLUMNS FROM comentariossugerencias LIKE 'ip_address'")->fetch();
    if ($check_ip && $ip_address) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM comentariossugerencias 
            WHERE ip_address = ? 
            AND fecha_creacion > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$ip_address]);
        $recent_comments = $stmt->fetchColumn();
        
        if ($recent_comments >= 5) {
            $errors[] = 'Has enviado demasiados comentarios recientemente. Por favor espera un momento.';
        }
    }
} catch (Exception $e) {
    // Si no existe la columna ip_address, saltar la verificación anti-spam
    error_log("No se puede verificar IP para anti-spam: " . $e->getMessage());
}

// Si hay errores, redirigir con mensaje
if (!empty($errors)) {
    $_SESSION['comment_errors'] = $errors;
    $_SESSION['comment_form_data'] = [
        'nombre' => $nombre,
        'email' => $email,
        'tipo' => $tipo,
        'contenido' => $contenido,
        'calificacion' => $calificacion
    ];
    header('Location: landing.php#comentarios');
    exit;
}

// Sanitizar contenido (permitir saltos de línea pero prevenir XSS)
$contenido = htmlspecialchars($contenido, ENT_QUOTES, 'UTF-8');

// Insertar comentario
// Primero verificar qué columnas existen en la tabla
try {
    // Intentar obtener las columnas de la tabla
    $columns_check = [];
    $has_ip_address = false;
    $has_user_agent = false;
    
    try {
        $stmt_columns = $pdo->query("SHOW COLUMNS FROM comentariossugerencias");
        $columns_check = $stmt_columns->fetchAll(PDO::FETCH_COLUMN);
        $has_ip_address = in_array('ip_address', $columns_check);
        $has_user_agent = in_array('user_agent', $columns_check);
    } catch (Exception $e) {
        // Si falla la consulta de columnas, asumir que no existen
        error_log("No se pudieron verificar columnas en comentariossugerencias: " . $e->getMessage());
        $has_ip_address = false;
        $has_user_agent = false;
    }
    
    // Construir el INSERT dinámicamente según las columnas disponibles
    $fields = ['usuario_id', 'nombre', 'email', 'tipo', 'contenido', 'calificacion', 'estatus'];
    $values = [$usuario_id, $nombre, $email ?: null, $tipo, $contenido, $calificacion, 'pendiente'];
    
    if ($has_ip_address && $ip_address) {
        $fields[] = 'ip_address';
        $values[] = $ip_address;
    }
    
    if ($has_user_agent && $user_agent) {
        $fields[] = 'user_agent';
        $values[] = $user_agent;
    }
    
    $fields_sql = implode(', ', $fields);
    $placeholders = implode(', ', array_fill(0, count($values), '?'));
    
    $stmt = $pdo->prepare("
        INSERT INTO comentariossugerencias 
        ($fields_sql)
        VALUES ($placeholders)
    ");
    
    $stmt->execute($values);
    
    $_SESSION['comment_success'] = 'Tu comentario ha sido enviado y será revisado antes de publicarse.';
    
} catch (PDOException $e) {
    error_log("Error guardando comentario (PDO): " . $e->getMessage());
    error_log("Código SQL: " . $e->getCode());
    $_SESSION['comment_errors'] = ['Hubo un error al guardar tu comentario. Por favor intenta nuevamente.'];
} catch (Exception $e) {
    error_log("Error guardando comentario: " . $e->getMessage());
    $_SESSION['comment_errors'] = ['Hubo un error al guardar tu comentario. Por favor intenta nuevamente.'];
}

// Redirigir según desde dónde se envió
$from = $_GET['from'] ?? '';
if ($from === 'dashboard' && $user && $user['role'] === 'admin_club') {
    header('Location: ' . AppHelpers::dashboard('comments_public'));
} else {
    header('Location: landing.php#comentarios');
}
exit;

