<?php
/**
 * Diagnóstico: comprobar qué versión de guardar_equipo.php está en el servidor
 * y si la sesión se inicia correctamente. ELIMINAR después de usar en producción.
 *
 * Uso: https://tu-dominio.com/.../public/api/diagnostico_guardar_equipo.php
 */
header('Content-Type: text/plain; charset=utf-8');

$apiDir = __DIR__;
$guardar = $apiDir . '/guardar_equipo.php';
$out = [];

// 1. ¿Existe el archivo?
$out[] = '=== Archivo guardar_equipo.php ===';
$out[] = 'Ruta: ' . $guardar;
$out[] = 'Existe: ' . (file_exists($guardar) ? 'Sí' : 'No');

if (!file_exists($guardar)) {
    echo implode("\n", $out);
    exit;
}

// 2. ¿Contiene session_start_early?
$content = file_get_contents($guardar);
$tiene_session_early = (strpos($content, 'session_start_early.php') !== false);
$out[] = 'Carga session_start_early: ' . ($tiene_session_early ? 'Sí' : 'No');

// 3. ¿Qué texto de log usa? (nuevo = "POST/input recibido", antiguo = "POST recibido")
$tiene_post_input = (strpos($content, 'POST/input recibido') !== false);
$tiene_post_recibido = (strpos($content, 'POST recibido') !== false);
$out[] = 'Log "POST/input recibido": ' . ($tiene_post_input ? 'Sí' : 'No');
$out[] = 'Log "POST recibido" (antiguo): ' . ($tiene_post_recibido ? 'Sí' : 'No');

if ($tiene_session_early && $tiene_post_input) {
    $out[] = "\n>>> VERSIÓN EN DISCO: NUEVA (correcta)";
} else {
    $out[] = "\n>>> VERSIÓN EN DISCO: ANTIGUA o incompleta. Sube el guardar_equipo.php actual del repo.";
}

// 4. Probar sesión (sin afectar guardar_equipo)
$out[] = "\n=== Sesión ===";
$session_start_early = dirname($apiDir, 2) . '/config/session_start_early.php';
$out[] = 'session_start_early.php existe: ' . (file_exists($session_start_early) ? 'Sí' : 'No');

if (file_exists($session_start_early)) {
    try {
        require_once $session_start_early;
        $out[] = 'session_status: ' . session_status() . ' (2 = activa)';
        $out[] = 'session_id: ' . (session_id() ?: '(vacío)');
        $out[] = 'csrf_token en sesión: ' . (isset($_SESSION['csrf_token']) && $_SESSION['csrf_token'] !== '' ? 'Sí' : 'No');
    } catch (Throwable $e) {
        $out[] = 'Error al cargar session_start_early: ' . $e->getMessage();
    }
}

$out[] = "\n---";
$out[] = 'Si la versión en disco es NUEVA pero el log sigue mostrando "POST recibido", ejecuta clear_cache.php y vuelve a intentar guardar un equipo.';

echo implode("\n", $out);
