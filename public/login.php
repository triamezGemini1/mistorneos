<?php
/**
 * Login - Flujo resumido:
 * 1. GET: se muestra el formulario. El campo "Usuario o Email" (name="username") está vacío; no se toma de sesión ni de otro usuario.
 * 2. POST: $username = trim($_POST['username']) → ÚNICAMENTE lo que el usuario escribió (o el navegador autocompletó) en esta petición.
 * 3. Auth::login($username, $password) → Security::authenticateUser(); el usuario en los logs es siempre el de esta petición.
 * 4. Si OK: redirect a index.php o return_url; la sesión guarda el usuario que pasó Auth.
 * 5. Si falla: se muestra mensaje y se registra en log con "(usuario enviado en petición: 'X')" para dejar claro que X es lo enviado en ESA petición, no otro usuario.
 * Cada línea del error_log corresponde a UNA petición HTTP; si ves "ramaguza" es porque en esa petición el formulario se envió con username=ramaguza (mismo navegador con autocompletado, otra persona, o otra pestaña).
 */
// Evitar que salida accidental (BOM, espacios, warnings) anule header()
ob_start();

try {
    require __DIR__ . '/../config/bootstrap.php';
    require __DIR__ . '/../config/db.php';
} catch (Throwable $e) {
    error_log("login.php: Error cargando bootstrap/DB - " . $e->getMessage());
    ob_end_clean();
    http_response_code(503);
    include __DIR__ . '/error_service_unavailable.php';
    exit;
}

// URL de retorno permitida (solo rutas internas, sin protocolo externo)
$return_url = '';
if (!empty($_GET['return_url'])) {
    $raw = trim($_GET['return_url']);
    if (preg_match('#^[a-zA-Z0-9_\-/\.\?=&]+$#', $raw) && !preg_match('#^(https?|javascript|data):#i', $raw)) {
        $return_url = $raw; // Mantener relativa: tournament_register.php?torneo_id=2
    }
}

// Si ya hay sesión activa, redirigir al dashboard o return_url (302). Usar base de la petición para no ir a raíz del dominio.
if (isset($_SESSION['user'])) {
    require __DIR__ . '/../modules/auth/after_login_check.php';
    require_once __DIR__ . '/../lib/app_helpers.php';
    ob_end_clean();
    $entry_base = AppHelpers::getRequestEntryUrl();
    if ($return_url && (strpos($return_url, '?') !== false || strpos($return_url, '.php') !== false)) {
        $target = (strpos($return_url, 'http') === 0 || strpos($return_url, '/') === 0)
            ? $return_url
            : $entry_base . '/' . ltrim($return_url, '/');
        header("Location: " . $target, true, 302);
    } else {
        header("Location: " . $entry_base . "/index.php", true, 302);
    }
    exit;
}

// Solo tratar como "acceso por invitación" si la petición viene del flujo de invitación (return_url con invitation/register o join)
$from_invitation_flow = $return_url && (strpos($return_url, 'invitation/register') !== false || strpos($return_url, 'join') !== false);

// Captura del token de invitación desde cookie solo cuando vino del flujo de invitación (evitar confundir con login normal o admin)
if ($from_invitation_flow && empty($_SESSION['invitation_token']) && !empty($_COOKIE['invitation_token']) && strlen(trim($_COOKIE['invitation_token'])) >= 32) {
    require_once __DIR__ . '/../lib/app_helpers.php';
    $_SESSION['invitation_token'] = trim($_COOKIE['invitation_token']);
    $_SESSION['url_retorno'] = rtrim(AppHelpers::getPublicUrl(), '/') . '/invitation/register?token=' . urlencode($_SESSION['invitation_token']);
    $_SESSION['invitation_club_name'] = 'Club';
}

if (!$from_invitation_flow) {
    unset($_SESSION['invitation_token'], $_SESSION['invitation_club_name']);
    if (isset($_SESSION['url_retorno']) && strpos((string)$_SESSION['url_retorno'], 'invitation/register') !== false) {
        unset($_SESSION['url_retorno']);
    }
}

$error = null;
$success = !empty($_GET['registered']) ? 'Registro exitoso. Ya puedes iniciar sesión.' : null;
$invitation_message = null;
if ($from_invitation_flow && !empty($_SESSION['invitation_club_name'])) {
    $invitation_message = 'Has accedido mediante una invitación. Por favor, inicia sesión o regístrate para vincular tu cuenta al Club ' . htmlspecialchars($_SESSION['invitation_club_name']) . '.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // El usuario SIEMPRE viene solo del campo del formulario (name="username"). Cada línea del log corresponde a UNA petición HTTP.
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? ''; // NO hacer trim al password
    error_log("Login intent - valor enviado en esta petición como 'username': " . ($username === '' ? '(vacío)' : "'" . $username . "'"));

    require_once __DIR__ . '/../config/auth.php';
    
    if (Auth::login($username, $password)) {
        require_once __DIR__ . '/../lib/app_helpers.php';
        ob_end_clean();

        // Reclamación de token de invitación: vincular usuario y redirigir al formulario
        if (!empty($_SESSION['invitation_token'])) {
            require_once __DIR__ . '/../config/db.php';
            $tb_inv = defined('TABLE_INVITATIONS') ? TABLE_INVITATIONS : 'invitaciones';
            $token = $_SESSION['invitation_token'];
            $return_url = $_SESSION['url_retorno'] ?? '';
            $user_id = (int) (Auth::user()['id'] ?? 0);
            try {
                $stmt = DB::pdo()->prepare("SELECT * FROM {$tb_inv} WHERE token = ? LIMIT 1");
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
                        }
                        $id_dir = (int)($inv['id_directorio_club'] ?? 0);
                        $cols = @DB::pdo()->query("SHOW COLUMNS FROM directorio_clubes LIKE 'id_usuario'")->fetch();
                        if ($cols) {
                            if ($id_dir > 0) {
                                $upDir = DB::pdo()->prepare("UPDATE directorio_clubes SET id_usuario = ? WHERE id = ?");
                                $upDir->execute([$user_id, $id_dir]);
                            } else {
                                $stmtNom = DB::pdo()->prepare("SELECT nombre FROM clubes WHERE id = ?");
                                $stmtNom->execute([$club_id_inv]);
                                $nom = $stmtNom->fetchColumn();
                                if ($nom !== false && trim((string)$nom) !== '') {
                                    $upDir = DB::pdo()->prepare("UPDATE directorio_clubes SET id_usuario = ? WHERE nombre = ?");
                                    $upDir->execute([$user_id, $nom]);
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // Columna id_usuario_vinculado puede no existir aún
            }
            unset($_SESSION['invitation_token'], $_SESSION['invitation_club_name']);
            if ($return_url !== '' && !headers_sent()) {
                header('Location: ' . $return_url, true, 302);
                unset($_SESSION['url_retorno']);
                exit;
            }
            unset($_SESSION['url_retorno']);
        }

        $redirect = !empty($_POST['return_url']) ? trim($_POST['return_url']) : ($return_url ?: '');
        $entry_base = AppHelpers::getRequestEntryUrl();
        // Forzar escritura de sesión antes del redirect para que la cookie persista (evita perder sesión en subcarpeta)
        if (function_exists('session_write_close')) {
            session_write_close();
        }
        if ($redirect && preg_match('#^[a-zA-Z0-9_\-/\.\?=&]+$#', $redirect) && !preg_match('#^(https?|javascript|data):#i', $redirect)) {
            if (strpos($redirect, '?') !== false || strpos($redirect, '.php') !== false) {
                $target = (strpos($redirect, 'http') === 0 || strpos($redirect, '/') === 0)
                    ? $redirect
                    : $entry_base . '/' . ltrim($redirect, '/');
                header("Location: " . $target, true, 302);
            } else {
                header("Location: " . $entry_base . "/index.php", true, 302);
            }
        } else {
            header("Location: " . $entry_base . "/index.php", true, 302);
        }
        exit;
    } else {
        // Mensaje al usuario según motivo real (username = valor enviado en ESTA petición en el campo del formulario)
        try {
            $pdo = DB::pdo();
            $stmt = $pdo->prepare("SELECT id, username, status, password_hash FROM usuarios WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $password_ok = !empty($user['password_hash']) && password_verify($password, $user['password_hash']);
                if ($password_ok && (int)$user['status'] !== 0) {
                    $error = "Tu cuenta está inactiva. Contacta al administrador.";
                    error_log("Login fallido (usuario enviado en petición: '{$username}'): cuenta inactiva status=" . ($user['status'] ?? ''));
                } elseif (!$password_ok && !empty($user['password_hash'])) {
                    $error = "Contraseña incorrecta";
                    error_log("Login fallido (usuario enviado en petición: '{$username}'): contraseña incorrecta");
                } elseif (empty($user['password_hash'])) {
                    $error = "Tu cuenta no tiene contraseña configurada. Contacta al administrador.";
                    error_log("Login fallido (usuario enviado en petición: '{$username}'): sin password_hash");
                } else {
                    $error = "Credenciales incorrectas.";
                    error_log("Login fallido (usuario enviado en petición: '{$username}')");
                }
            } else {
                $error = "El usuario o correo no existe en el sistema";
                error_log("Login fallido (usuario enviado en petición: '{$username}'): no existe");
            }
        } catch (Exception $e) {
            $error = "Error al verificar credenciales. Por favor intenta de nuevo.";
            error_log("Login error exception: " . $e->getMessage());
        }
    }
}
ob_end_clean();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
  <meta name="theme-color" content="#1a365d">
  <title>Iniciar Sesión - La Estación del Dominó</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #1a365d 0%, #2d3748 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      padding: 1rem;
    }
    .login-card {
      border-radius: 16px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      overflow: hidden;
    }
    .login-header {
      background: linear-gradient(135deg, #1a365d 0%, #2d3748 100%);
      color: white;
      padding: 2rem;
      text-align: center;
    }
    .login-header i {
      font-size: 3rem;
      margin-bottom: 1rem;
      color: #48bb78;
    }
    .card-body {
      padding: 2rem;
    }
    @media (max-width: 576px) {
      .card-body {
        padding: 1.5rem;
      }
      .login-header {
        padding: 1.5rem;
      }
      .login-header i {
        font-size: 2.5rem;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-5 col-lg-4">
        <div class="card login-card border-0">
          <div class="login-header">
            <?php 
            require_once __DIR__ . '/../lib/app_helpers.php';
            $logo_url = AppHelpers::getAppLogo();
            ?>
            <img src="<?= htmlspecialchars($logo_url) ?>" alt="La Estación del Dominó" style="height: 60px; margin-bottom: 1rem;">
            <h4 class="mb-1">La Estación del Dominó</h4>
            <p class="mb-0 opacity-75">Iniciar Sesión</p>
          </div>
          <div class="card-body">
            <?php if ($success): ?>
              <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
              </div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['invitation_club_name']) && $invitation_message): ?>
              <div class="alert alert-info">
                <i class="fas fa-envelope-open-text me-2"></i><?= $invitation_message ?>
              </div>
            <?php endif; ?>
            <?php if ($error || !empty($_SESSION['login_error'])): ?>
              <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error ?: $_SESSION['login_error']) ?>
              </div>
              <?php unset($_SESSION['login_error']); ?>
            <?php endif; ?>
            <form method="POST">
              <?php if ($return_url): ?>
              <input type="hidden" name="return_url" value="<?= htmlspecialchars($return_url) ?>">
              <?php endif; ?>
              <div class="mb-3">
                <label class="form-label fw-semibold">Usuario o Email</label>
                <input type="text" name="username" class="form-control form-control-lg" required autofocus placeholder="Ingresa tu usuario">
              </div>
              <div class="mb-4">
                <label class="form-label fw-semibold">Contraseña</label>
                <div class="input-group input-group-lg">
                  <input type="password" name="password" id="password" class="form-control" required placeholder="Ingresa tu contraseña">
                  <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                    <i class="fas fa-eye" id="toggleIcon"></i>
                  </button>
                </div>
              </div>
              <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
              </button>
              <div class="d-flex justify-content-between mb-3">
                <a class="small text-decoration-none fw-semibold" href="<?= htmlspecialchars(AppHelpers::url('recover_user.php')) ?>">
                  <i class="fas fa-user-circle me-1"></i>Recuperar usuario
                </a>
                <a class="small text-decoration-none fw-semibold" href="<?= htmlspecialchars(AppHelpers::url('reset_password_no_email.php')) ?>">
                  <i class="fas fa-key me-1"></i>Restablecer contraseña sin correo
                </a>
              </div>
              <div class="row g-2 mb-2">
                <div class="col-12">
                  <a class="btn btn-outline-primary w-100 btn-sm" href="<?= htmlspecialchars(AppHelpers::url('recover_user.php')) ?>">
                    <i class="fas fa-user-circle me-1"></i> Recuperar usuario
                  </a>
                </div>
                <div class="col-12">
                  <a class="btn btn-outline-secondary w-100 btn-sm" href="<?= htmlspecialchars(AppHelpers::url('reset_password_no_email.php')) ?>">
                    <i class="fas fa-key me-1"></i> Restablecer contraseña sin correo
                  </a>
                </div>
              </div>
            </form>
            <div class="text-center mt-3">
              <a href="<?= htmlspecialchars(AppHelpers::url('landing.php')) ?>" class="text-muted text-decoration-none small">
                <i class="fas fa-arrow-left me-1"></i>Volver al inicio
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script>
    function togglePassword() {
      const pwd = document.getElementById('password');
      const icon = document.getElementById('toggleIcon');
      if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
      } else {
        pwd.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
      }
    }
  </script>
</body>
</html>
