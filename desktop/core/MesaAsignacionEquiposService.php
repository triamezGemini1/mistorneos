<?php
/**
 * Torneos por equipos (modalidad 3): delega en la implementación canónica del proyecto.
 *
 * Debe cargarse después de db_bridge.php (logica_torneo.php, generar_ronda.php) para que
 * DB::pdo() sea SQLite local; si no, se carga config/db.php (MySQL).
 *
 * @see config/MesaAsignacionEquiposService.php
 */
require_once __DIR__ . '/db_bridge.php';
if (!class_exists('DB', false)) {
    require_once dirname(__DIR__, 2) . '/config/db.php';
}
require_once dirname(__DIR__, 2) . '/config/MesaAsignacionEquiposService.php';
