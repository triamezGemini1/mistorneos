<?php
/**
 * Redirección al landing con directriz de cierre de sesión
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../lib/app_helpers.php';

session_write_close();
header('Location: ' . AppHelpers::url('landing-spa.php'));
exit;
