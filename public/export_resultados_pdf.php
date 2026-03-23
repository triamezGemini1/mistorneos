<?php
/**
 * Entrada pública para PDF de resultados (Letter). Evita depender del despacho en index.php.
 * Uso: export_resultados_pdf.php?torneo_id=1&tipo=posiciones
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/session_start_early.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../config/auth_service.php';
AuthService::requireAuth();
require_once __DIR__ . '/../modules/tournament_admin/resultados_export_pdf.php';
