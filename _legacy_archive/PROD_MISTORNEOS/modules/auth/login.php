<?php

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/auth.php';

CSRF::validate();

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (!$username || !$password) {
  header('Location: ../../public/index.php');
  exit;
}

if (Auth::login($username, $password)) {
  // Verificar si está usando credenciales por defecto
  if (Auth::isUsingDefaultCredentials($username, $password)) {
    $_SESSION['force_password_change'] = true;
    $_SESSION['password_change_reason'] = 'Estás usando las credenciales por defecto. Por seguridad, debes cambiar tu contraseña.';
    header('Location: ../../public/index.php?page=users/change_password&force=1');
    exit;
  }
  
  header('Location: ../../public/index.php');
  exit;
}
$_SESSION['login_error'] = 'Credenciales inválidas';
header('Location: ../../public/index.php');

