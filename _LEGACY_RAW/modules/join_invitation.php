<?php
/**
 * Punto de entrada único para invitaciones: /join?token=xyz
 * Redirige según estado del delegado en directorio_clubes:
 * - id_usuario NULL/vacío → Formulario de Registro (register-invited).
 * - id_usuario con valor   → Formulario de Inscripción de jugadores.
 */
$token = trim((string) ($_GET['token'] ?? ''));
$base = '';

try {
    require_once __DIR__ . '/../config/bootstrap.php';
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../lib/InvitationJoinResolver.php';

    $base = class_exists('AppHelpers') ? rtrim(AppHelpers::getPublicUrl(), '/') : '';
    if ($base === '' && !empty($GLOBALS['APP_CONFIG']['app']['base_url'])) {
        $base = rtrim((string) $GLOBALS['APP_CONFIG']['app']['base_url'], '/');
    }
    if ($base === '') {
        $base = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['SCRIPT_NAME'] ?? '');
        $base = rtrim(str_replace('\\', '/', $base), '/');
    }

    // Sin token → inicio
    if ($token === '') {
        header('Location: ' . $base . '/');
        exit;
    }

    $resolved = InvitationJoinResolver::resolve($token);

    if ($resolved === null) {
        header('Content-Type: text/html; charset=utf-8');
        http_response_code(404);
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Invitación no válida</title></head><body>';
        echo '<h1>Invitación no válida o expirada</h1><p>El enlace no es válido. Compruebe la URL o solicite uno nuevo.</p>';
        echo '<p><a href="' . htmlspecialchars($base . '/') . '">Volver al inicio</a></p></body></html>';
        exit;
    }

    $baseSlash = $base . '/';

    if (!empty($resolved['requiere_registro'])) {
        $_SESSION['invitation_token'] = $token;
        $_SESSION['url_retorno'] = $baseSlash . 'invitation/register?token=' . urlencode($token);
        $_SESSION['invitation_join_requires_register'] = true;
        $_SESSION['invitation_id_directorio_club'] = $resolved['id_directorio_club'] ?? 0;
        if (!headers_sent()) {
            setcookie('invitation_token', $token, time() + (7 * 86400), '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
        }
        header('Location: ' . $baseSlash . 'auth/register-invited?token=' . urlencode($token));
        exit;
    }

    $_SESSION['invitation_token'] = $token;
    $_SESSION['url_retorno'] = $baseSlash . 'invitation/register?token=' . urlencode($token);
    if (!headers_sent()) {
        setcookie('invitation_token', $token, time() + (7 * 86400), '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
    }
    header('Location: ' . $baseSlash . 'invitation/register?token=' . urlencode($token));
    exit;

} catch (Throwable $e) {
    error_log("join_invitation: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    if ($base === '') {
        $base = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        if (!empty($_SERVER['SCRIPT_NAME'])) {
            $base .= dirname($_SERVER['SCRIPT_NAME']);
        }
        $base = rtrim(str_replace('\\', '/', $base), '/');
    }
    header('Content-Type: text/html; charset=utf-8');
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error</title></head><body>';
    echo '<h1>Error al procesar la invitación</h1>';
    echo '<p>No se pudo validar el enlace. Intente de nuevo o póngase en contacto con el organizador.</p>';
    echo '<p><a href="' . htmlspecialchars($base . '/') . '">Volver al inicio</a></p>';
    echo '<p><a href="' . htmlspecialchars($base . '/auth/login') . '">Iniciar sesión</a></p></body></html>';
    exit;
}
