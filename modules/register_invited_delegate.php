<?php
/**
 * Túnel de Registro Fast-Track para delegados de clubes invitados.
 * Solo se muestra cuando el token es válido y directorio_clubes.id_usuario está vacío.
 * Campos: Nombre, Email, Password, Confirmar Password. ID_CLUB y ENTIDAD vienen del token.
 * POST atómico: crear usuario → actualizar directorio_clubes → auto-login → redirigir a inscripción.
 */
$token = '';
$base = '';
$error = '';
$success = '';
$club_id = 0;
$id_directorio_club = 0;
$entidad_id = 0;
$club_nombre = 'Club';
$debug_mode = !empty($_GET['debug']) || !empty($_POST['debug']);
$show_form = false;

try {
    require_once __DIR__ . '/../config/bootstrap.php';
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../config/csrf.php';
    require_once __DIR__ . '/../config/auth.php';
    require_once __DIR__ . '/../lib/security.php';
    require_once __DIR__ . '/../lib/InvitationJoinResolver.php';

    $token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
    $base = class_exists('AppHelpers') ? rtrim(AppHelpers::getPublicUrl(), '/') : rtrim((string) ($GLOBALS['APP_CONFIG']['app']['base_url'] ?? ''), '/');
    if ($base === '') {
        $base = '/';
    }

    $base = rtrim($base, '/');
    $baseSlash = $base === '' ? '' : $base . '/';

    // Sin token → login
    if ($token === '') {
        header('Location: ' . $baseSlash . 'auth/login');
        exit;
    }

    $ctx = InvitationJoinResolver::getContextForRegistration($token);

    // Token inválido o no encontrado
    if ($ctx === null) {
        header('Location: ' . $baseSlash . 'auth/login?error=invitacion_invalida');
        exit;
    }

    // Token ya usado (delegado ya tiene user_id) → ir a login
    if (empty($ctx['requiere_registro'])) {
        $_SESSION['invitation_token'] = $token;
        $_SESSION['url_retorno'] = $baseSlash . 'invitation/register?token=' . urlencode($token);
        header('Location: ' . $baseSlash . 'auth/login');
        exit;
    }

    // Ya logueado → directo a inscripción
    if (isset($_SESSION['user']['id']) && (int) $_SESSION['user']['id'] > 0) {
        header('Location: ' . $baseSlash . 'invitation/register?token=' . urlencode($token));
        exit;
    }

    $club_id = (int) ($ctx['club_id'] ?? 0);
    $id_directorio_club = (int) ($ctx['id_directorio_club'] ?? 0);
    $entidad_id = (int) ($ctx['entidad_id'] ?? 0);
    $club_nombre = isset($ctx['club_nombre']) && $ctx['club_nombre'] !== '' ? (string) $ctx['club_nombre'] : 'Club';
    $show_form = true;

    // ---------- POST: Registro atómico + auto-login + redirección ----------
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        CSRF::validate();
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = trim((string) ($_POST['password'] ?? ''));
        $password_confirm = trim((string) ($_POST['password_confirm'] ?? ''));

        if (strlen($nombre) < 2) {
            $error = 'El nombre es obligatorio (mínimo 2 caracteres).';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Indique un email válido.';
        } elseif (strlen($password) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres.';
        } elseif ($password !== $password_confirm) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            $ctxPost = InvitationJoinResolver::getContextForRegistration($token);
            if ($ctxPost === null || empty($ctxPost['requiere_registro'])) {
                $error = 'Este enlace ya fue utilizado. Use Iniciar sesión si ya tiene cuenta.';
            } else {
                $emailPart = strpos($email, '@') !== false ? substr($email, 0, strpos($email, '@')) : $email;
                $username = strtolower(preg_replace('/[^a-zA-Z0-9_.]/', '', $emailPart));
                if (strlen($username) < 3) {
                    $username = 'usr' . bin2hex(random_bytes(4));
                }
                $pdo = DB::pdo();
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ?");
                $stmt->execute([$username]);
                $idx = 0;
                $base_username = $username;
                while ($stmt->fetch()) {
                    $idx++;
                    $username = $base_username . (string) $idx;
                    $stmt->execute([$username]);
                }

                $userData = [
                    'username' => $username,
                    'password' => $password,
                    'email' => $email,
                    'nombre' => $nombre,
                    'role' => 'usuario',
                    'status' => 'approved',
                    'entidad' => $entidad_id,
                    'cedula' => 'INV-' . bin2hex(random_bytes(6)),
                    'nacionalidad' => 'V',
                    'celular' => 'N/A',
                    'fechnac' => '1900-01-01',
                ];
                if ($club_id > 0) {
                    $userData['club_id'] = $club_id;
                    $userData['_allow_club_for_usuario'] = true;
                }

                $result = Security::createUser($userData);

                if (!empty($result['success']) && !empty($result['user_id'])) {
                    $new_user_id = (int) $result['user_id'];
                    try {
                        $cols = $pdo->query("SHOW COLUMNS FROM directorio_clubes LIKE 'id_usuario'")->fetchAll();
                        if (!empty($cols)) {
                            $up = $pdo->prepare("UPDATE directorio_clubes SET id_usuario = ? WHERE id = ?");
                            $up->execute([$new_user_id, $id_directorio_club]);
                        }
                    } catch (Exception $e) {
                        error_log("register_invited_delegate: UPDATE directorio_clubes: " . $e->getMessage());
                        $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$new_user_id]);
                        $error = 'Error al vincular el club. Intente de nuevo.';
                    }

                    if ($error === '') {
                        $logged = Auth::login($username, $password);
                        if ($logged) {
                            header('Location: ' . $baseSlash . 'invitation/register?token=' . urlencode($token));
                            exit;
                        }
                        $success = 'Cuenta creada. Inicie sesión con su usuario y contraseña.';
                    }
                } else {
                    $error = implode(' ', $result['errors'] ?? ['Error al crear la cuenta.']);
                }
            }
        }
    }
} catch (Throwable $e) {
    error_log("register_invited_delegate: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    $error = 'No se pudo cargar la invitación. Compruebe el enlace o intente más tarde.';
    $show_form = false;
    if (!isset($base) || $base === '') {
        $base = class_exists('AppHelpers') ? rtrim(AppHelpers::getPublicUrl(), '/') : '';
        $base = $base !== '' ? $base : (string) ($GLOBALS['APP_CONFIG']['app']['base_url'] ?? '');
    }
}

$base = isset($base) ? rtrim((string) $base, '/') : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro — Delegado <?= htmlspecialchars($club_nombre) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { min-height: 100vh; background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%); display: flex; align-items: center; justify-content: center; padding: 1rem; font-family: system-ui, -apple-system, sans-serif; margin: 0; }
        .card-register { max-width: 420px; border: 0; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,.25); }
        .card-header-reg { background: transparent; border-bottom: 1px solid rgba(0,0,0,.06); padding: 1.25rem 1.5rem; text-align: center; }
        .card-header-reg h1 { font-size: 1.25rem; font-weight: 700; color: #1e3a5f; margin: 0; }
        .card-header-reg p { font-size: 0.875rem; color: #6c757d; margin: 0.25rem 0 0; }
        .card-body { padding: 1.5rem 1.75rem; }
        .form-label { font-weight: 600; color: #333; }
        .btn-register { padding: 0.65rem 1.25rem; font-weight: 600; border-radius: 10px; }
        .loading-spinner { display: inline-block; width: 1.25rem; height: 1.25rem; border: 2px solid rgba(0,0,0,.1); border-top-color: #1e3a5f; border-radius: 50%; animation: spin .8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="card card-register" id="register-card">
        <div class="card-header card-header-reg">
            <h1><i class="fas fa-user-plus me-2"></i>Crear cuenta</h1>
            <p id="club-label">Delegado de <?= htmlspecialchars($club_nombre) ?></p>
        </div>
        <div class="card-body">
            <?php if (!$show_form && $error !== ''): ?>
                <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
                <a href="<?= htmlspecialchars($base) ?>/auth/login" class="btn btn-primary btn-register w-100">Ir a Iniciar sesión</a>
            <?php elseif ($show_form): ?>
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success !== ''): ?>
                    <div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div>
                    <a href="<?= htmlspecialchars($base) ?>/auth/login" class="btn btn-primary btn-register w-100">Iniciar sesión</a>
                <?php else: ?>
                <form method="post" action="" id="form-register-invited">
                    <?= CSRF::input() ?>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <input type="hidden" name="id_club" id="hidden_id_club" value="<?= (int) $club_id ?>">
                    <input type="hidden" name="entidad_id" id="hidden_entidad_id" value="<?= (int) $entidad_id ?>">

                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre completo *</label>
                        <input type="text" id="nombre" name="nombre" class="form-control" required
                               value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>"
                               placeholder="Ej. Juan Pérez" autocomplete="name">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" id="email" name="email" class="form-control" required
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               placeholder="correo@ejemplo.com" autocomplete="email">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña *</label>
                        <input type="password" id="password" name="password" class="form-control" required
                               minlength="6" placeholder="Mínimo 6 caracteres" autocomplete="new-password">
                    </div>
                    <div class="mb-4">
                        <label for="password_confirm" class="form-label">Confirmar contraseña *</label>
                        <input type="password" id="password_confirm" name="password_confirm" class="form-control" required
                               minlength="6" placeholder="Repita la contraseña" autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary btn-register w-100" id="btn-submit">
                        <i class="fas fa-check me-2"></i>Registrarse
                    </button>
                    <p class="text-center text-muted small mt-3 mb-0">
                        <a href="<?= htmlspecialchars($base) ?>/auth/login">¿Ya tiene cuenta? Iniciar sesión</a>
                    </p>
                </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <script>
(function() {
    var token = '<?= addslashes($token) ?>';
    var idClub = <?= (int) $club_id ?>;
    var entidadId = <?= (int) $entidad_id ?>;
    var debug = <?= $debug_mode ? 'true' : 'false' ?>;

    if (debug) {
        console.log('[Registro Invitado] Token en URL:', token ? token.substring(0, 12) + '...' : '(vacío)');
        console.log('[Registro Invitado] ID_CLUB (oculto):', idClub);
        console.log('[Registro Invitado] ENTIDAD_ID (oculto):', entidadId);
    }

    document.getElementById('form-register-invited') && document.getElementById('form-register-invited').addEventListener('submit', function() {
        var btn = document.getElementById('btn-submit');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="loading-spinner me-2"></span>Registrando...';
        }
    });
})();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
