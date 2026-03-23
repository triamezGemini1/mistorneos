<?php
/**
 * Entrada pública para Excel de resultados. Evita depender del despacho en index.php.
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/session_start_early.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../config/auth_service.php';
AuthService::requireAuth();
require_once __DIR__ . '/../modules/tournament_admin/resultados_export_excel.php';
