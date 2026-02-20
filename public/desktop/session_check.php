<?php
/**
 * Verificación de sesión Desktop: asegura que desktop_user y entidad_id siguen activos
 * mientras el usuario navega. Útil para llamadas AJAX o para validar que la sesión no expiró.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ok = !empty($_SESSION['desktop_user']);
$entidad_id = $ok && isset($_SESSION['desktop_entidad_id'])
    ? (int) $_SESSION['desktop_entidad_id']
    : 0;

echo json_encode([
    'active' => $ok,
    'entidad_id' => $entidad_id,
    'user_id' => $ok ? (int) ($_SESSION['desktop_user']['id'] ?? 0) : 0,
    'username' => $ok ? (string) ($_SESSION['desktop_user']['username'] ?? '') : '',
]);
