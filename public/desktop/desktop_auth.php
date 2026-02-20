<?php
/** Redirige a login local si no hay sesión desktop. Incluir al inicio de páginas que requieran acceso. */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['desktop_user'])) {
    $login_page = (strpos($_SERVER['PHP_SELF'], 'login_local') !== false) ? 'dashboard.php' : 'login_local.php';
    if ($login_page === 'login_local.php' && isset($_REQUEST['torneo_id']) && (int)$_REQUEST['torneo_id'] > 0) {
        $_SESSION['desktop_return_after_login'] = 'panel_torneo.php?torneo_id=' . (int)$_REQUEST['torneo_id'];
    }
    header('Location: ' . $login_page);
    exit;
}
