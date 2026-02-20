<?php
/**
 * Punto de entrada principal de la aplicación
 * 
 * Sistema híbrido:
 * - Rutas modernas: /auth/login, /dashboard, /api/... (usando Router)
 * - Rutas legacy: ?page=xxx (compatibilidad hacia atrás)
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/auth.php';

// Cargar DB con manejo de errores: mostrar página de error en vez de morir silenciosamente
try {
    require_once __DIR__ . '/../config/db.php';
} catch (Throwable $e) {
    error_log("index.php: Error cargando DB - " . $e->getMessage());
    http_response_code(503);
    include __DIR__ . '/error_service_unavailable.php';
    exit;
}

// Constante para el directorio raíz de la aplicación
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// =================================================================
// MODO 1: RUTAS MODERNAS (Router)
// =================================================================

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$uriPath = parse_url($uri, PHP_URL_PATH);

// Lista de rutas que usa el Router moderno (sin ?page=)
$modernRoutes = [
    '/auth/',
    '/invitation/',
    '/actions/',
    '/dashboard',
    '/api/',
    '/admin/',
];

$useModernRouter = false;
foreach ($modernRoutes as $prefix) {
    if (strpos($uriPath, $prefix) === 0) {
        $useModernRouter = true;
        break;
    }
}

if ($useModernRouter) {
    // Cargar autoloader de Composer si existe
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }
    
    // Inicializar Container y Router
    $container = new \Core\Container\Container();
    $router = new \Core\Routing\Router($container);
    
    // Cargar definiciones de rutas
    $routeDefinitions = require __DIR__ . '/../config/routes.php';
    $routeDefinitions($router);
    
    // Capturar request y despachar
    $request = \Core\Http\Request::capture();
    $response = $router->dispatch($request);
    $response->send();
    exit;
}

// =================================================================
// MODO 2: RUTAS LEGACY (?page=xxx) - Compatibilidad
// =================================================================

// Verificar autenticación (con manejo de errores)
try {
    $user = Auth::user();
    if (!$user) {
        // Landing: SPA si API disponible, sino legacy (evita "Error al cargar los datos")
        include __DIR__ . '/landing.php';
        exit;
    }
} catch (Throwable $e) {
    // Si hay error fatal (ej: MySQL no disponible), mostrar página de error
    error_log("Error en index.php: " . $e->getMessage());
    http_response_code(503);
    include __DIR__ . '/error_service_unavailable.php';
    exit;
}

// Restringir dashboard a roles válidos del sistema
$allowed_roles = ['admin_general', 'admin_torneo', 'admin_club', 'usuario', 'operador'];
if (!in_array($user['role'] ?? '', $allowed_roles, true)) {
    Auth::logout();
    header("Location: " . AppHelpers::url('login.php', ['error' => 'requiere_autenticacion']));
    exit;
}

// Redirigir usuarios normales al portal de jugador, salvo vistas permitidas (resumen/posiciones desde notificación)
if ($user['role'] === 'usuario') {
    $page = $_GET['page'] ?? '';
    $action = $_GET['action'] ?? '';
    $torneo_id = (int)($_GET['torneo_id'] ?? 0);
    $inscrito_id = (int)($_GET['inscrito_id'] ?? 0);
    $allow_view = ($page === 'torneo_gestion' && $torneo_id > 0 && in_array($action, ['resumen_individual', 'posiciones']));
    if ($allow_view && $action === 'resumen_individual') {
        $allow_view = ($inscrito_id > 0 && $inscrito_id === Auth::id());
    }
    if (!$allow_view) {
        header("Location: " . AppHelpers::url('user_portal.php'));
        exit;
    }
}

// Obtener página solicitada
$page = $_GET['page'] ?? 'home';

// Sanitizar nombre de página (solo letras, números, guiones y barras)
$page = preg_replace('/[^a-zA-Z0-9_\/\-]/', '', $page);

// Pre-despacho para solicitudes POST y GET con acciones que requieren redirección
$action = $_GET['action'] ?? '';
$actions_requiring_redirect = ['delete', 'save', 'update'];

// Acciones que redirigen sin layout (evitan acceso directo a modules/ bloqueado por .htaccess)
if ($page === 'admin_clubs' && $action === 'send_notification') {
    require_once __DIR__ . '/../modules/admin_clubs/send_notification.php';
    exit;
}

// Desactivar/Reactivar organización: delegado a admin_org (centraliza responsabilidades)
if ($page === 'mi_organizacion' && isset($_GET['id']) && in_array($action, ['desactivar', 'reactivar'], true)) {
    require_once __DIR__ . '/../modules/admin_org/organizacion/actions/' . $action . '.php';
    exit;
}

// Manejar endpoints especiales (sin layout)
$special_endpoints = [
    'invitations_send_email',
    'send_invitation_email',
    'send_invitation_whatsapp',
    'send_invitation_whatsapp_pdf',
    'whatsapp_templates',
    'whatsapp_config',
    'generate_invitation_pdf',
    'clubs/send_friend_invitation',
];

if (in_array($page, $special_endpoints, true)) {
    $module = __DIR__ . "/../modules/{$page}.php";
    if (file_exists($module)) {
        include $module;
        exit;
    }
}

// POST clubs: actualizar o guardar club (evita 404 cuando la URL base no es public/)
if ($page === 'clubs' && (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')) {
    $club_action = $_GET['action'] ?? '';
    if ($club_action === 'update') {
        require_once __DIR__ . '/../modules/clubs/update.php';
        exit;
    }
    if ($club_action === 'save') {
        require_once __DIR__ . '/../modules/clubs/save.php';
        exit;
    }
}

// Manejar POST y acciones que requieren redirección
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' || in_array($action, $actions_requiring_redirect, true)) {
    $module = __DIR__ . "/../modules/{$page}.php";
    if (file_exists($module)) {
        include $module;
        exit;
    }
}

// Manejar sub-rutas que son endpoints de procesamiento (POST o terminan en /save, /delete, etc.)
$processing_endpoints = ['save', 'delete', 'update', 'send', 'upload', 'process'];
$is_processing_endpoint = false;
if (strpos($page, '/') !== false) {
    $parts = explode('/', $page);
    $last_part = end($parts);
    if (in_array($last_part, $processing_endpoints, true) || ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $sub_module = __DIR__ . "/../modules/{$page}.php";
        if (file_exists($sub_module)) {
            include $sub_module;
            exit;
        }
    }
}

// Podios / Podios equipos / Resultados por club: mostrar vista dedicada (sin header ni sidebar del dashboard)
if ($page === 'torneo_gestion') {
    $action = $_GET['action'] ?? '';
    $torneo_id = (int)($_GET['torneo_id'] ?? 0);
    if ($torneo_id > 0 && in_array($action, ['podios', 'podios_equipos', 'resultados_por_club'], true)) {
        include __DIR__ . '/includes/layout_podios.php';
        exit;
    }
}

// Incluir layout principal (para GET normal y páginas de visualización)
include __DIR__ . "/includes/layout.php";
