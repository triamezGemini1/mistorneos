<?php
/**
 * Página independiente del Administrador de Torneos
 * Diseño moderno, práctico y responsive
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../config/auth_service.php';
require_once __DIR__ . '/../config/auth.php';
AuthService::requireAuth();
$user = Auth::user();

// Verificar permisos
Auth::requireRole(['admin_general', 'admin_torneo', 'admin_club']);

// Obtener acción
$action = $_GET['action'] ?? 'index';
$torneo_id = isset($_GET['torneo_id']) ? (int)$_GET['torneo_id'] : null;

// Incluir el módulo de gestión de torneos
require_once __DIR__ . '/../modules/torneo_gestion.php';

