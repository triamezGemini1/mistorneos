<?php
/**
 * Punto de acceso único para invitaciones: GET y POST /join?token=...
 * - Sin redirecciones intermedias: el formulario de registro se muestra en esta misma URL.
 * - Token inválido → redirigir a Home con error.
 * - Club con user_id → redirigir a Login.
 * - Club sin user_id → mostrar formulario aquí; POST procesa registro y redirige a inscripción.
 * No usa exit(): para redirigir asigna $GLOBALS['join_redirect_url'] y return.
 */
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$success = '';
$club_id = 0;
$id_directorio_club = 0;
$entidad_id = 0;
$club_nombre = 'Club';
$show_form = false;

// Cargas mínimas (index.php ya cargó bootstrap y db)
if (!class_exists('InvitationJoinResolver')) {
    require_once __DIR__ . '/../lib/InvitationJoinResolver.php';
}
if (!class_exists('CSRF')) {
    require_once __DIR__ . '/../config/csrf.php';
}
if (!class_exists('Auth')) {
    require_once __DIR__ . '/../config/auth.php';
}
if (!class_exists('Security')) {
    require_once __DIR__ . '/../lib/security.php';
}

$base = class_exists('AppHelpers') ? rtrim((string) AppHelpers::getPublicUrl(), '/') : '';
if ($base === '' && !empty($GLOBALS['APP_CONFIG']['app']['base_url'])) {
    $base = rtrim((string) $GLOBALS['APP_CONFIG']['app']['base_url'], '/');
}
if ($base === '') {
    $scheme = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $base = $scheme . '://' . $host . (strlen($script) > 0 ? dirname($script) : '');
    $base = rtrim(str_replace('\\', '/', $base), '/');
}
$baseSlash = $base . '/';

// Sin token → Home con error
if ($token === '') {
    $GLOBALS['join_redirect_url'] = $baseSlash . '?error=invitacion_invalida';
    return;
}

$resolved = InvitationJoinResolver::resolve($token);
if ($resolved === null) {
    $GLOBALS['join_redirect_url'] = $baseSlash . '?error=invitacion_invalida';
    return;
}

$requiere_registro = !empty($resolved['requiere_registro']);
$id_directorio_club = (int) ($resolved['id_directorio_club'] ?? 0);
$ctx = InvitationJoinResolver::getContextForRegistration($token);
$club_id = (int) ($ctx['club_id'] ?? 0);
$entidad_id = (int) ($ctx['entidad_id'] ?? 0);
$club_nombre = isset($ctx['club_nombre']) && $ctx['club_nombre'] !== '' ? (string) $ctx['club_nombre'] : 'Club';

// Club ya tiene user_id → Login
if (!$requiere_registro) {
    $_SESSION['invitation_token'] = $token;
    $_SESSION['url_retorno'] = $baseSlash . 'invitation/register?token=' . urlencode($token);
    if (!headers_sent()) {
        setcookie('invitation_token', $token, time() + (7 * 86400), '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
    }
    $GLOBALS['join_redirect_url'] = $baseSlash . 'auth/login';
    return;
}

// Ya logueado → directo a inscripción
if (isset($_SESSION['user']['id']) && (int) $_SESSION['user']['id'] > 0) {
    $GLOBALS['join_redirect_url'] = $baseSlash . 'invitation/register?token=' . urlencode($token);
    return;
}

$_SESSION['invitation_token'] = $token;
$_SESSION['url_retorno'] = $baseSlash . 'invitation/register?token=' . urlencode($token);
$_SESSION['invitation_join_requires_register'] = true;
$_SESSION['invitation_id_directorio_club'] = $id_directorio_club;
$show_form = true;

// ---------- POST: registro y redirección ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        CSRF::validate();
    } catch (Throwable $e) {
        $error = 'Sesión expirada. Recargue la página e intente de nuevo.';
    }
    if ($error === '') {
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
                        error_log("join: UPDATE directorio_clubes: " . $e->getMessage());
                        $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$new_user_id]);
                        $error = 'Error al vincular el club. Intente de nuevo.';
                    }
                    if ($error === '') {
                        $logged = Auth::login($username, $password);
                        if ($logged) {
                            $GLOBALS['join_redirect_url'] = $baseSlash . 'invitation/register?token=' . urlencode($token);
                            return;
                        }
                        $success = 'Cuenta creada. Inicie sesión con su usuario y contraseña.';
                    }
                } else {
                    $error = implode(' ', $result['errors'] ?? ['Error al crear la cuenta.']);
                }
            }
        }
    }
}

// Formulario (GET o POST con error)
$form_action = $baseSlash . 'join?token=' . urlencode($token);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro — Delegado <?= htmlspecialchars($club_nombre) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%); display: flex; align-items: center; justify-content: center; padding: 1rem; margin: 0; font-family: system-ui, sans-serif; }
        .card-join { max-width: 420px; border: 0; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,.25); }
        .card-header-join { background: transparent; border-bottom: 1px solid rgba(0,0,0,.06); padding: 1.25rem 1.5rem; text-align: center; }
        .card-header-join h1 { font-size: 1.25rem; font-weight: 700; color: #1e3a5f; margin: 0; }
        .card-header-join p { font-size: 0.875rem; color: #6c757d; margin: 0.25rem 0 0; }
        .card-body { padding: 1.5rem 1.75rem; }
        .btn-join { padding: 0.65rem 1.25rem; font-weight: 600; border-radius: 10px; }
        .spin { display: inline-block; width: 1.25rem; height: 1.25rem; border: 2px solid rgba(0,0,0,.1); border-top-color: #1e3a5f; border-radius: 50%; animation: spin .8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="card card-join">
        <div class="card-header card-header-join">
            <h1>Crear cuenta</h1>
            <p>Delegado de <?= htmlspecialchars($club_nombre) ?></p>
        </div>
        <div class="card-body">
            <?php if ($error && !$show_form): ?>
                <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
                <a href="<?= htmlspecialchars($baseSlash) ?>auth/login" class="btn btn-primary btn-join w-100">Ir a Iniciar sesión</a>
            <?php elseif ($show_form): ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div>
                    <a href="<?= htmlspecialchars($baseSlash) ?>auth/login" class="btn btn-primary btn-join w-100">Iniciar sesión</a>
                <?php else: ?>
                <form method="post" action="<?= htmlspecialchars($form_action) ?>" id="form-join">
                    <?= CSRF::input() ?>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre completo *</label>
                        <input type="text" id="nombre" name="nombre" class="form-control" required minlength="2"
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
                    <button type="submit" class="btn btn-primary btn-join w-100" id="btn-submit">Registrarse</button>
                    <p class="text-center text-muted small mt-3 mb-0">
                        <a href="<?= htmlspecialchars($baseSlash) ?>auth/login">¿Ya tiene cuenta? Iniciar sesión</a>
                    </p>
                </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <script>
    document.getElementById('form-join') && document.getElementById('form-join').addEventListener('submit', function() {
        var btn = document.getElementById('btn-submit');
        if (btn) { btn.disabled = true; btn.textContent = 'Registrando...'; }
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
