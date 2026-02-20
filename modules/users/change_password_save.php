<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

if (empty($_SESSION['user'])) { 
  header('Location: ../../public/login.php'); 
  exit; 
}

// Validar CSRF
CSRF::validate();

$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$isForced = ($_POST['forced'] ?? '0') === '1';

// Validaciones
if (strlen($new_password) < 8) { 
  $_SESSION['password_error'] = 'La contraseña debe tener al menos 8 caracteres';
  header('Location: change_password.php' . ($isForced ? '?force=1' : ''));
  exit;
}

if ($new_password !== $confirm_password) {
  $_SESSION['password_error'] = 'Las contraseñas no coinciden';
  header('Location: change_password.php' . ($isForced ? '?force=1' : ''));
  exit;
}

// Verificar que no sea una contraseña débil/común
$weakPasswords = ['password', '12345678', 'admin123', 'password123', 'qwerty123'];
if (in_array(strtolower($new_password), $weakPasswords)) {
  $_SESSION['password_error'] = 'Por favor, elige una contraseña más segura. Evita contraseñas comunes.';
  header('Location: change_password.php' . ($isForced ? '?force=1' : ''));
  exit;
}

try {
  $hash = password_hash($new_password, PASSWORD_DEFAULT);
  $pdo = DB::pdo();
  // Verificar si la columna must_change_password existe
  $checkColumn = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'must_change_password'");
  if ($checkColumn->rowCount() > 0) {
    $stmt = $pdo->prepare("UPDATE usuarios SET password_hash = :hash, must_change_password = 0, updated_at = NOW() WHERE id = :id");
  } else {
    $stmt = $pdo->prepare("UPDATE usuarios SET password_hash = :hash, updated_at = NOW() WHERE id = :id");
  }
  $stmt->execute([':hash' => $hash, ':id' => $_SESSION['user']['id']]);
  
  // Limpiar flag de cambio forzado
  Auth::clearPasswordChangeFlag();
  
  $_SESSION['password_success'] = 'Contraseña actualizada correctamente';
  
  // Si era forzado, redirigir al dashboard
  if ($isForced) {
    header('Location: ../../public/index.php');
  } else {
    header('Location: profile.php?pwd_ok=1');
  }
  exit;
  
} catch (Exception $e) {
  $_SESSION['password_error'] = 'Error al actualizar la contraseña. Por favor, intenta de nuevo.';
  header('Location: change_password.php' . ($isForced ? '?force=1' : ''));
  exit;
}
