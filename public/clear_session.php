<?php
require __DIR__ . '/../config/bootstrap.php';

// Limpiar todas las variables de sesi�n
$_SESSION = array();

// Destruir la cookie de sesi�n
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir la sesi�n
session_destroy();

echo "<h1>Sesi�n Limpiada</h1>";
echo "<p>La sesi�n ha sido limpiada exitosamente.</p>";
echo "<p><a href='login.php'>Ir al Login</a></p>";
echo "<p><a href='test_auth.php'>Probar Autenticaci�n</a></p>";
?>
