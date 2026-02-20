<?php
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

// Si ya hay sesión activa, redirigir al dashboard o return_url (302 para no cachear como 301)
if (isset($_SESSION['user'])) {
    require __DIR__ . '/../modules/auth/after_login_check.php';
    require_once __DIR__ . '/../lib/app_helpers.php';
    ob_end_clean();
    if ($return_url && (strpos($return_url, '?') !== false || strpos($return_url, '.php') !== false)) {
        $target = (strpos($return_url, 'http') === 0 || strpos($return_url, '/') === 0)
            ? $return_url
            : rtrim(AppHelpers::getPublicUrl(), '/') . '/' . ltrim($return_url, '/');
        header("Location: " . $target, true, 302);
    } else {
        header("Location: " . AppHelpers::url('index.php'), true, 302);
    }
    exit;
}

$error = null;
$success = !empty($_GET['registered']) ? 'Registro exitoso. Ya puedes iniciar sesión.' : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? ''; // NO hacer trim al password

    require_once __DIR__ . '/../config/auth.php';
    
    if (Auth::login($username, $password)) {
        require_once __DIR__ . '/../lib/app_helpers.php';
        ob_end_clean();
        $redirect = !empty($_POST['return_url']) ? trim($_POST['return_url']) : ($return_url ?: '');
        if ($redirect && preg_match('#^[a-zA-Z0-9_\-/\.\?=&]+$#', $redirect) && !preg_match('#^(https?|javascript|data):#i', $redirect)) {
            if (strpos($redirect, '?') !== false || strpos($redirect, '.php') !== false) {
                $target = (strpos($redirect, 'http') === 0 || strpos($redirect, '/') === 0)
                    ? $redirect
                    : rtrim(AppHelpers::getPublicUrl(), '/') . '/' . ltrim($redirect, '/');
                header("Location: " . $target, true, 302);
            } else {
                header("Location: " . AppHelpers::url('index.php'), true, 302);
            }
        } else {
            header("Location: " . AppHelpers::url('index.php'), true, 302);
        }
        exit;
    } else {
        // Verificar el motivo específico del fallo
        try {
            $pdo = DB::pdo();
            $stmt = $pdo->prepare("SELECT id, username, status, password_hash FROM usuarios WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Usuario encontrado: solo activos (status = 0) pueden entrar
                if ((int)$user['status'] !== 0) {
                    $error = "Tu cuenta está inactiva. Contacta al administrador.";
                    error_log("Login fallido - Usuario '{$username}' inactivo (status=" . ($user['status'] ?? '') . ")");
                } elseif (empty($user['password_hash'])) {
                    $error = "Tu cuenta no tiene contraseña configurada. Contacta al administrador.";
                    error_log("Login fallido - Usuario '{$username}' sin password_hash");
                } else {
                    // Activo y tiene password: contraseña incorrecta
                    $error = "Contraseña incorrecta";
                    error_log("Login fallido - Usuario '{$username}' contraseña incorrecta");
                }
            } else {
                $error = "El usuario o correo no existe en el sistema";
                error_log("Login fallido - Usuario '{$username}' no existe");
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
            <?php if ($error): ?>
              <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
              </div>
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
