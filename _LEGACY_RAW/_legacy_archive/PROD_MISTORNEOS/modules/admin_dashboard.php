<?php
/**
 * Router del Dashboard de administradores
 * - Verifica autenticación y roles (session + Auth::requireRole)
 * - Carga datos vía DashboardData::loadAll()
 * - Usa switch($_GET['view']) para incluir la vista correspondiente
 * - Los scripts (DataTables, modales) se cargan solo en el layout footer
 */
require_once __DIR__ . '/../config/auth.php';

Auth::requireRole(['admin_general', 'admin_torneo', 'admin_club']);

$success_message = $_GET['success'] ?? null;
$error_message = $_GET['error'] ?? null;

$current_user = Auth::user();
$user_role = $current_user['role'];

require_once __DIR__ . '/../lib/DashboardData.php';

$data = DashboardData::loadAll($user_role, $current_user);

$view = $_GET['view'] ?? 'home';

// Dashboard admin_general: home solo muestra tarjetas de estadísticas (sin tablas de torneos/entidades)
if ($user_role === 'admin_general' && $view === 'home') {
    require_once __DIR__ . '/../lib/OrganizacionesData.php';
    $data['stats'] = array_merge($data['stats'] ?? [], OrganizacionesData::loadStatsGlobales());
}

$view_file = __DIR__ . '/../public/includes/views/dashboard/' . basename($view) . '.php';

if (!is_file($view_file)) {
    $view_file = __DIR__ . '/../public/includes/views/dashboard/home.php';
}

extract(array_merge($data, [
    'success_message' => $success_message,
    'error_message' => $error_message,
    'user_role' => $user_role,
    'current_user' => $current_user,
]));

include $view_file;
