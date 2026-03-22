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

// Datos completos del club invitado desde directorio_clubes (para mostrar en el formulario)
$club_directorio = null;
if ($id_directorio_club > 0 && class_exists('DB')) {
    try {
        $pdo = DB::pdo();
        $sel = "id, nombre, direccion, delegado, telefono, email";
        $has_logo = @$pdo->query("SHOW COLUMNS FROM directorio_clubes LIKE 'logo'")->fetch();
        if ($has_logo) $sel .= ", logo";
        $st = $pdo->prepare("SELECT {$sel} FROM directorio_clubes WHERE id = ? LIMIT 1");
        $st->execute([$id_directorio_club]);
        $club_directorio = $st->fetch(PDO::FETCH_ASSOC);
        if ($club_directorio && !empty($club_directorio['nombre'])) {
            $club_nombre = $club_directorio['nombre'];
        }
    } catch (Throwable $e) {
        error_log("join: directorio_clubes " . $e->getMessage());
    }
}

// POST action=update_directorio: actualizar datos del delegado en directorio_clubes tras búsqueda por cédula (solo token válido; no exige CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_directorio') {
    header('Content-Type: application/json; charset=utf-8');
    $tok = trim((string)($_POST['token'] ?? ''));
    if ($tok === '') {
        echo json_encode(['success' => false, 'error' => 'Token requerido']);
        return;
    }
    $res = InvitationJoinResolver::resolve($tok);
    if ($res === null || empty($res['requiere_registro'])) {
        echo json_encode(['success' => false, 'error' => 'Token inválido o enlace ya utilizado']);
        return;
    }
    $id_dc = (int)($res['id_directorio_club'] ?? 0);
    if ($id_dc <= 0) {
        echo json_encode(['success' => false, 'error' => 'Club no encontrado']);
        return;
    }
    $delegado = trim((string)($_POST['nombre'] ?? ''));
    $telefono = trim((string)($_POST['telefono'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    try {
        $pdo = DB::pdo();
        $pdo->prepare("UPDATE directorio_clubes SET delegado = ?, telefono = ?, email = ? WHERE id = ?")
            ->execute([$delegado, $telefono, $email, $id_dc]);
        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        error_log("join update_directorio: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error al actualizar datos']);
    }
    return;
}

// Club ya tiene id_usuario en directorio_clubes → ir directamente al formulario de invitación (inscripción de jugadores)
if (!$requiere_registro) {
    $_SESSION['invitation_token'] = $token;
    $_SESSION['url_retorno'] = $baseSlash . 'invitation/register?token=' . urlencode($token);
    if (!headers_sent()) {
        setcookie('invitation_token', $token, time() + (7 * 86400), '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
    }
    $GLOBALS['join_redirect_url'] = $baseSlash . 'invitation/register?token=' . urlencode($token);
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

// ---------- POST: registro (con cédula, búsqueda previa o manual) y redirección ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        CSRF::validate();
    } catch (Throwable $e) {
        $error = 'Sesión expirada. Recargue la página e intente de nuevo.';
    }
    if ($error === '') {
        $id_usuario_enviado = !empty($_POST['id_usuario']) ? (int)$_POST['id_usuario'] : 0;
        $nacionalidad = in_array(strtoupper(trim((string)($_POST['nacionalidad'] ?? 'V'))), ['V', 'E', 'J', 'P'], true) ? strtoupper(trim($_POST['nacionalidad'])) : 'V';
        $cedula = preg_replace('/\D/', '', trim((string)($_POST['cedula'] ?? '')));
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $sexo = in_array(strtoupper(trim((string)($_POST['sexo'] ?? 'M'))), ['M', 'F', 'O'], true) ? strtoupper(trim($_POST['sexo'])) : 'M';
        $fechnac = trim((string)($_POST['fechnac'] ?? ''));
        $telefono = trim((string)($_POST['telefono'] ?? $_POST['celular'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = trim((string)($_POST['password'] ?? ''));
        $password_confirm = trim((string)($_POST['password_confirm'] ?? ''));

        if (strlen($cedula) < 4) {
            $error = 'La cédula es obligatoria (mínimo 4 dígitos).';
        } elseif (strlen($nombre) < 2) {
            $error = 'El nombre es obligatorio (mínimo 2 caracteres).';
        } elseif (strlen($password) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres.';
        } elseif ($password !== $password_confirm) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            $ctxPost = InvitationJoinResolver::getContextForRegistration($token);
            if ($ctxPost === null || empty($ctxPost['requiere_registro'])) {
                $error = 'Este enlace ya fue utilizado. Use Iniciar sesión si ya tiene cuenta.';
            } else {
                $pdo = DB::pdo();

                if ($id_usuario_enviado > 0) {
                    // Usuario existente (encontrado por búsqueda): vincular al club, actualizar datos delegado en directorio y contraseña
                    $stmt = $pdo->prepare("SELECT id, username FROM usuarios WHERE id = ? LIMIT 1");
                    $stmt->execute([$id_usuario_enviado]);
                    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$usuario) {
                        $error = 'El usuario indicado no existe. Realice la búsqueda de nuevo.';
                    } else {
                        $password_hash = Security::hashPassword($password);
                        $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?")->execute([$password_hash, $id_usuario_enviado]);
                        $cols = @$pdo->query("SHOW COLUMNS FROM directorio_clubes LIKE 'id_usuario'")->fetchAll();
                        if (!empty($cols)) {
                            $up = $pdo->prepare("UPDATE directorio_clubes SET id_usuario = ?, delegado = ?, telefono = ?, email = ? WHERE id = ?");
                            $up->execute([$id_usuario_enviado, $nombre, $telefono, $email, $id_directorio_club]);
                        }
                        $logged = Auth::login($usuario['username'], $password);
                        if ($logged) {
                            $GLOBALS['join_redirect_url'] = $baseSlash . 'invitation/register?token=' . urlencode($token);
                            return;
                        }
                        $success = 'Cuenta vinculada al club. Inicie sesión con su usuario y la contraseña que acaba de definir.';
                    }
                } else {
                    // Registro nuevo: crear usuario con cédula, nombre, etc. (igual que registro regular)
                    $username_base = $nacionalidad . $cedula;
                    $sufijo = '';
                    $idx = 0;
                    do {
                        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ?");
                        $stmt->execute([$username_base . $sufijo]);
                        if (!$stmt->fetch()) break;
                        $idx++;
                        $sufijo = '_' . $idx;
                    } while (true);
                    $username = $username_base . $sufijo;
                    $email_val = $email !== '' ? $email : ($username . '@invitado.local');
                    $userData = [
                        'username' => $username,
                        'password' => $password,
                        'nombre' => $nombre,
                        'cedula' => $cedula,
                        'nacionalidad' => $nacionalidad,
                        'sexo' => $sexo,
                        'fechnac' => $fechnac ?: null,
                        'email' => $email_val,
                        'celular' => $telefono ?: null,
                        'role' => 'usuario',
                        'status' => 'approved',
                        'entidad' => $entidad_id,
                    ];
                    if ($club_id > 0) {
                        $userData['club_id'] = $club_id;
                        $userData['_allow_club_for_usuario'] = true;
                    }
                    $result = Security::createUser($userData);
                    if (!empty($result['success']) && !empty($result['user_id'])) {
                        $new_user_id = (int) $result['user_id'];
                        try {
                            $cols = @$pdo->query("SHOW COLUMNS FROM directorio_clubes LIKE 'id_usuario'")->fetchAll();
                            if (!empty($cols)) {
                                $up = $pdo->prepare("UPDATE directorio_clubes SET id_usuario = ?, delegado = ?, telefono = ?, email = ? WHERE id = ?");
                                $up->execute([$new_user_id, $nombre, $telefono, $email, $id_directorio_club]);
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
}

// Formulario (GET o POST con error)
$form_action = $baseSlash . 'join?token=' . urlencode($token);
$api_base = rtrim($base, '/') . '/api';
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
        .card-join { width: 60%; max-width: 60vw; border: 0; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,.25); }
        @media (max-width: 768px) { .card-join { width: 95%; max-width: 95vw; } }
        .card-header-join { background: transparent; border-bottom: 1px solid rgba(0,0,0,.06); padding: 1.25rem 1.5rem; text-align: center; }
        .card-header-join h1 { font-size: 1.25rem; font-weight: 700; color: #1e3a5f; margin: 0; }
        .card-header-join p { font-size: 0.875rem; color: #6c757d; margin: 0.25rem 0 0; }
        .card-body { padding: 1.5rem 1.75rem; font-size: 15px; }
        .card-body .form-label { font-size: 15px; }
        .card-body .form-control, .card-body .form-select { font-size: 15px; }
        .club-info { background: #f8f9fa; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.25rem; font-size: 0.95rem; }
        .club-info strong { color: #1e3a5f; }
        .join-readonly-notice { background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 0.5rem 0.75rem; margin-bottom: 1rem; font-size: 14px; color: #856404; display: none; }
        .join-field-lock:disabled { background-color: #e9ecef; cursor: not-allowed; }
        .join-nacionalidad { width: 100%; max-width: 560px; min-width: 120px; }
        @media (min-width: 576px) { .join-nacionalidad { max-width: 616px; } }
        .join-cedula { width: 100%; max-width: 240px; }
        @media (min-width: 576px) { .join-cedula { max-width: 264px; } }
        .join-nombre { width: 50%; }
        .btn-join { padding: 0.65rem 1.25rem; font-weight: 600; border-radius: 10px; font-size: 15px; }
        .join-loading { display: none; font-size: 14px; color: #0d6efd; }
        .spin { display: inline-block; width: 1rem; height: 1rem; border: 2px solid rgba(0,0,0,.1); border-top-color: #1e3a5f; border-radius: 50%; animation: spin .8s linear infinite; vertical-align: middle; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <!-- join formulario delegado v2 - nacionalidad 560px, búsqueda con debounce y timeout -->
    <div class="card card-join">
        <div class="card-header card-header-join">
            <h1>Crear cuenta como delegado</h1>
            <p>Club invitado</p>
        </div>
        <div class="card-body">
            <?php if ($error && !$show_form): ?>
                <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
                <a href="<?= htmlspecialchars($baseSlash) ?>auth/login" class="btn btn-primary btn-join w-100">Ir a Iniciar sesión</a>
            <?php elseif ($show_form): ?>
                <?php if ($club_directorio): ?>
                <div class="club-info">
                    <strong><?= htmlspecialchars($club_directorio['nombre'] ?? $club_nombre) ?></strong>
                    <?php if (!empty($club_directorio['direccion'])): ?>
                        <div class="mt-1"><?= htmlspecialchars($club_directorio['direccion']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($club_directorio['delegado'])): ?>
                        <div class="mt-1">Delegado: <?= htmlspecialchars($club_directorio['delegado']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($club_directorio['telefono'])): ?>
                        <div>Tel: <?= htmlspecialchars($club_directorio['telefono']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($club_directorio['email'])): ?>
                        <div>Email: <?= htmlspecialchars($club_directorio['email']) ?></div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="club-info"><strong><?= htmlspecialchars($club_nombre) ?></strong></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <div class="join-readonly-notice" id="join-readonly-notice">
                    Los datos mostrados provienen del registro oficial. No pueden ser modificados aquí; cualquier cambio debe solicitarlo al administrador del sistema. Solo puede definir o cambiar su contraseña.
                </div>
                <?php if ($success): ?>
                    <div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div>
                    <a href="<?= htmlspecialchars($baseSlash) ?>auth/login" class="btn btn-primary btn-join w-100">Iniciar sesión</a>
                <?php else: ?>
                <form method="post" action="<?= htmlspecialchars($form_action) ?>" id="form-join">
                    <?= CSRF::input() ?>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <input type="hidden" id="id_usuario" name="id_usuario" value="<?= htmlspecialchars($_POST['id_usuario'] ?? '') ?>">
                    <!-- Fila 1: Nacionalidad (60%), Cédula (60%), Nombre -->
                    <div class="row g-2 align-items-end mb-2">
                        <div class="col-auto">
                            <label for="nacionalidad" class="form-label">Nac. *</label>
                            <select id="nacionalidad" name="nacionalidad" class="form-select join-field-lock join-nacionalidad" required>
                                <option value="">...</option>
                                <option value="V" <?= ($_POST['nacionalidad'] ?? '') === 'V' ? 'selected' : '' ?>>V</option>
                                <option value="E" <?= ($_POST['nacionalidad'] ?? '') === 'E' ? 'selected' : '' ?>>E</option>
                                <option value="J" <?= ($_POST['nacionalidad'] ?? '') === 'J' ? 'selected' : '' ?>>J</option>
                                <option value="P" <?= ($_POST['nacionalidad'] ?? '') === 'P' ? 'selected' : '' ?>>P</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <label for="cedula" class="form-label">Cédula *</label>
                            <input type="text" id="cedula" name="cedula" class="form-control join-field-lock join-cedula" required minlength="4" maxlength="8" placeholder="12345678"
                                   value="<?= htmlspecialchars($_POST['cedula'] ?? '') ?>"
                                   onblur="if(typeof window.joinSearchPersona==='function')window.joinSearchPersona();">
                            <span class="join-loading" id="join-loading"><span class="spin"></span> Buscando...</span>
                        </div>
                        <div class="col">
                            <label for="nombre" class="form-label">Nombre completo *</label>
                            <input type="text" id="nombre" name="nombre" class="form-control join-field-lock join-nombre" required minlength="2"
                                   value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>"
                                   placeholder="Ej. Juan Pérez" autocomplete="name">
                        </div>
                    </div>
                    <!-- Fila 2: Sexo, F. Nac., Teléfono, Correo -->
                    <div class="row g-2 align-items-end mb-2">
                        <div class="col-md-2 col-4">
                            <label for="sexo" class="form-label">Sexo *</label>
                            <select id="sexo" name="sexo" class="form-select join-field-lock" required>
                                <option value="">...</option>
                                <option value="M" <?= ($_POST['sexo'] ?? '') === 'M' ? 'selected' : '' ?>>M</option>
                                <option value="F" <?= ($_POST['sexo'] ?? '') === 'F' ? 'selected' : '' ?>>F</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-4">
                            <label for="fechnac" class="form-label">F. Nac.</label>
                            <input type="date" id="fechnac" name="fechnac" class="form-control join-field-lock" value="<?= htmlspecialchars($_POST['fechnac'] ?? '') ?>">
                        </div>
                        <div class="col-md-3 col-6">
                            <label for="telefono" class="form-label">Teléfono *</label>
                            <input type="tel" id="telefono" name="telefono" class="form-control join-field-lock" required
                                   value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>"
                                   placeholder="0424-1234567">
                        </div>
                        <div class="col">
                            <label for="email" class="form-label">Correo</label>
                            <input type="email" id="email" name="email" class="form-control join-field-lock"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                   placeholder="correo@ejemplo.com" autocomplete="email">
                        </div>
                    </div>
                    <!-- Fila 3: Contraseña y Confirmar contraseña -->
                    <div class="row g-2 align-items-end mb-4">
                        <div class="col-md-6">
                            <label for="password" class="form-label">Contraseña *</label>
                            <input type="password" id="password" name="password" class="form-control" required
                                   minlength="6" placeholder="Mín. 6 caracteres (puede cambiarla si ya tiene cuenta)" autocomplete="new-password">
                        </div>
                        <div class="col-md-6">
                            <label for="password_confirm" class="form-label">Confirmar contraseña *</label>
                            <input type="password" id="password_confirm" name="password_confirm" class="form-control" required
                                   minlength="6" placeholder="Repita la contraseña" autocomplete="new-password">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-join w-100" id="btn-submit">Crear cuenta y acceder</button>
                    <p class="text-center text-muted small mt-3 mb-0">
                        <a href="<?= htmlspecialchars($baseSlash) ?>auth/login">¿Ya tiene cuenta? Iniciar sesión</a>
                    </p>
                </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <script>
    window.JOIN_CONFIG = { apiBase: <?= json_encode($api_base) ?>, formAction: <?= json_encode($form_action) ?> };
    (function() {
        function getApiBase() {
            if (window.JOIN_CONFIG && window.JOIN_CONFIG.apiBase) return window.JOIN_CONFIG.apiBase;
            var path = (window.location.pathname || '').replace(/\/join.*$/, '').replace(/\/$/, '');
            return (path || '') + '/api';
        }
        function setFieldsReadonly(readonly) {
            var list = document.querySelectorAll('.join-field-lock');
            for (var i = 0; i < list.length; i++) {
                list[i].readOnly = readonly;
                list[i].disabled = readonly;
            }
            var notice = document.getElementById('join-readonly-notice');
            if (notice) notice.style.display = readonly ? 'block' : 'none';
        }
        function updateDirectorioClube(nombre, telefono, email) {
            var form = document.getElementById('form-join');
            if (!form || !window.JOIN_CONFIG || !window.JOIN_CONFIG.formAction) return Promise.resolve();
            var fd = new FormData();
            fd.append('action', 'update_directorio');
            fd.append('token', form.querySelector('input[name=token]').value);
            fd.append('nombre', nombre || '');
            fd.append('telefono', telefono || '');
            fd.append('email', email || '');
            var csrf = form.querySelector('input[name=csrf_token]');
            if (csrf) fd.append('csrf_token', csrf.value);
            return fetch(window.JOIN_CONFIG.formAction, { method: 'POST', body: fd }).then(function(r) { return r.json(); });
        }
        var form = document.getElementById('form-join');
        if (form) form.addEventListener('submit', function() {
            var list = document.querySelectorAll('.join-field-lock');
            for (var i = 0; i < list.length; i++) list[i].disabled = false;
            var btn = document.getElementById('btn-submit');
            if (btn) { btn.disabled = true; btn.textContent = 'Registrando...'; }
        });
        var cedulaEl = document.getElementById('cedula');
        if (cedulaEl) cedulaEl.addEventListener('input', function() { this.value = this.value.replace(/[^0-9]/g, ''); });
        var searchInProgress = false;
        var joinSearchDebounceTimer = null;
        function fetchWithTimeout(url, ms) {
            var ctrl = new AbortController();
            var t = setTimeout(function() { ctrl.abort(); }, ms);
            return fetch(url, { signal: ctrl.signal }).then(function(r) { clearTimeout(t); return r; }, function(err) { clearTimeout(t); throw err; });
        }
        async function searchPersona() {
            if (searchInProgress) return;
            var cedula = (document.getElementById('cedula') && document.getElementById('cedula').value || '').replace(/\D/g, '');
            var nacionalidad = (document.getElementById('nacionalidad') && document.getElementById('nacionalidad').value || '');
            if (!cedula || !nacionalidad) return;
            searchInProgress = true;
            var loading = document.getElementById('join-loading');
            if (loading) loading.style.display = 'inline';
            try {
                var url = getApiBase() + '/search_persona.php?cedula=' + encodeURIComponent(cedula) + '&nacionalidad=' + encodeURIComponent(nacionalidad);
                var r = await fetchWithTimeout(url, 15000);
                var data = await r.json();
                var status = (data.status || '').toString().toLowerCase();
                if (status === 'ya_inscrito') {
                    if (document.getElementById('id_usuario')) document.getElementById('id_usuario').value = '';
                    setFieldsReadonly(false);
                    alert(data.mensaje || 'Ya está registrado en este torneo.');
                    return;
                }
                if (status === 'no_encontrado') {
                    if (document.getElementById('id_usuario')) document.getElementById('id_usuario').value = '';
                    setFieldsReadonly(false);
                    return;
                }
                if ((data.encontrado || data.success) && (data.persona || data.data)) {
                    var p = data.persona || data.data;
                    var idEl = document.getElementById('id_usuario');
                    if (idEl && p.id) idEl.value = parseInt(p.id, 10) || '';
                    var nombre = (p.nombre || '').toString();
                    var telefono = (p.celular || p.telefono || '').toString();
                    var email = (p.email || '').toString();
                    var n = document.getElementById('nombre'); if (n) n.value = nombre;
                    var s = document.getElementById('sexo'); if (s) s.value = (p.sexo || 'M').toString().substring(0,1).toUpperCase();
                    var f = document.getElementById('fechnac'); if (f) f.value = p.fechnac || '';
                    var t = document.getElementById('telefono'); if (t) t.value = telefono;
                    var e = document.getElementById('email'); if (e) e.value = email;
                    await updateDirectorioClube(nombre, telefono, email);
                    setFieldsReadonly(true);
                }
            } catch (err) {
                if (err && err.name === 'AbortError') { if (loading) loading.style.display = 'none'; alert('Búsqueda tardó demasiado. Intente de nuevo.'); }
                else { console.error(err); }
            } finally {
                searchInProgress = false;
                if (loading) loading.style.display = 'none';
            }
        }
        function joinSearchDebounced() {
            if (joinSearchDebounceTimer) clearTimeout(joinSearchDebounceTimer);
            joinSearchDebounceTimer = setTimeout(function() { joinSearchDebounceTimer = null; searchPersona(); }, 350);
        }
        window.joinSearchPersona = joinSearchDebounced;
        if (document.getElementById('id_usuario') && parseInt(document.getElementById('id_usuario').value, 10) > 0) {
            setFieldsReadonly(true);
        }
    })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
