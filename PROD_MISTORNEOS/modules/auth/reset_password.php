<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';

$token = $_GET['token'] ?? '';
if (empty($token)) { exit('Token inválido'); }

$pdo = DB::pdo();
$stmt = $pdo->prepare("SELECT id FROM usuarios WHERE recovery_token = ?");
$stmt->execute([$token]);
$user = $stmt->fetch();
if (!$user) { exit('Token inválido o expirado'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    if (strlen($new_password) < 8) { $error = 'La contraseña debe tener al menos 8 caracteres'; }
    else {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE usuarios SET password_hash = ?, recovery_token = NULL WHERE id = ?")->execute([$hash, Auth::id()]);
        header('Location: /modules/auth/login.php?reset=1');
        exit;
    }
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Restablecer contraseña</title></head><body>
<h1>Restablecer contraseña</h1>
<?php if (isset($error)): ?><p style="color:red"><?=$error?></p><?php endif; ?>
<form method="post">
  <label>Nueva contraseña</label><br>
  <input type="password" name="new_password" minlength="8" required><br><br>
  <button type="submit">Restablecer</button>
</form>
</body></html>
