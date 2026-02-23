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

  // Reclamación de token de invitación: vincular usuario a la invitación y redirigir al formulario
  if (!empty($_SESSION['invitation_token'])) {
    require_once __DIR__ . '/../../config/db.php';
    $tb_inv = defined('TABLE_INVITATIONS') ? TABLE_INVITATIONS : 'invitaciones';
    $token = $_SESSION['invitation_token'];
    $return_url = $_SESSION['url_retorno'] ?? '';
    $user_id = (int) Auth::id();

    try {
      $stmt = DB::pdo()->prepare("SELECT id, id_usuario_vinculado, club_id FROM {$tb_inv} WHERE token = ? LIMIT 1");
      $stmt->execute([$token]);
      $inv = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($inv) {
        $id_vinculado = isset($inv['id_usuario_vinculado']) ? (int) $inv['id_usuario_vinculado'] : 0;
        if ($id_vinculado > 0 && $id_vinculado !== $user_id) {
          $_SESSION['login_error'] = 'Esta invitación ya está siendo gestionada por otro delegado.';
          $return_url = '';
        } else {
          $up = DB::pdo()->prepare("UPDATE {$tb_inv} SET id_usuario_vinculado = ?, estado = 'activa' WHERE token = ?");
          $up->execute([$user_id, $token]);
          $club_id_inv = (int)($inv['club_id'] ?? 0);
          if ($club_id_inv > 0) {
            $upClub = DB::pdo()->prepare("UPDATE clubes SET delegado_user_id = ? WHERE id = ?");
            $upClub->execute([$user_id, $club_id_inv]);
            $stmtNom = DB::pdo()->prepare("SELECT nombre FROM clubes WHERE id = ?");
            $stmtNom->execute([$club_id_inv]);
            $nom = $stmtNom->fetchColumn();
            if ($nom !== false && trim((string)$nom) !== '') {
              $cols = DB::pdo()->query("SHOW COLUMNS FROM directorio_clubes LIKE 'id_usuario'")->fetch();
              if ($cols && !headers_sent()) {
                $upDir = DB::pdo()->prepare("UPDATE directorio_clubes SET id_usuario = ? WHERE nombre = ?");
                $upDir->execute([$user_id, $nom]);
              }
            }
          }
        }
      }
    } catch (Exception $e) {
      // En caso de error (ej. columna id_usuario_vinculado no existe), no bloquear login
    }

    unset($_SESSION['invitation_token'], $_SESSION['invitation_club_name']);
    if ($return_url !== '' && !headers_sent()) {
      header('Location: ' . $return_url);
      unset($_SESSION['url_retorno']);
      exit;
    }
    unset($_SESSION['url_retorno']);
  }

  header('Location: ../../public/index.php');
  exit;
}
$_SESSION['login_error'] = 'Credenciales inválidas';
header('Location: ../../public/index.php');

