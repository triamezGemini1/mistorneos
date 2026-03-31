<?php
/**
 * Debug de sesión: solo escribe en error_log si SESSION_DEBUG está activo.
 * Activar en .env: SESSION_DEBUG=1
 * Para ver logs en session_start_early.php (antes de cargar .env) definir en el servidor: SetEnv SESSION_DEBUG 1
 * Buscar en el log: grep "[SESSION_DEBUG]" o tail -f error.log
 */
function session_debug_log(string $step, array $data = []): void {
    if (!getenv('SESSION_DEBUG') && !defined('SESSION_DEBUG')) return;
    $msg = '[SESSION_DEBUG] ' . $step;
    if (!empty($data)) $msg .= ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    error_log($msg);
}
