<?php
/**
 * Login local (Desktop): valida contra SQLite (mistorneos_local.db).
 * Incluye conexión centralizada (db_bridge). Al iniciar sesión define el entidad_id en sesión
 * para la restricción de datos por entidad (DESKTOP_ENTIDAD_ID efectivo vía getEntidadId()).
 */
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../desktop/core/db_bridge.php';

$roles_permitidos = ['admin_general', 'admin_torneo', 'admin_club', 'operador'];
$error = '';

if (isset($_SESSION['desktop_user'])) {
    $go = isset($_SESSION['desktop_return_after_login']) ? $_SESSION['desktop_return_after_login'] : 'dashboard.php';
    if (isset($_SESSION['desktop_return_after_login'])) {
        unset($_SESSION['desktop_return_after_login']);
    }
    header('Location: ' . $go);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    if ($user === '' || $pass === '') {
        $error = 'Usuario y contraseña son obligatorios.';
    } else {
        try {
            $pdo = DB::pdo();
            $stmt = $pdo->prepare("
                SELECT id, username, password_hash, role, nombre, email, club_id, COALESCE(entidad, 0) AS entidad
                FROM usuarios
                WHERE (username = ? OR email = ?) AND role IN ('admin_general','admin_torneo','admin_club','operador')
            ");
            $stmt->execute([$user, $user]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $error = 'Usuario no encontrado o sin permisos de administrador.';
            } else {
                $is_active = 1;
                try {
                    $stmt2 = $pdo->prepare("SELECT is_active FROM usuarios WHERE id = ?");
                    $stmt2->execute([$row['id']]);
                    $r = $stmt2->fetch(PDO::FETCH_ASSOC);
                    if ($r !== false && isset($r['is_active'])) {
                        $is_active = (int)$r['is_active'];
                    }
                } catch (Throwable $e) {
                }
                if ($is_active !== 1) {
                    $error = 'Tu cuenta de administrador está desactivada. Contacta al Super Admin.';
                } elseif (!password_verify($pass, $row['password_hash'] ?? '')) {
                    $error = 'Contraseña incorrecta.';
                } else {
                    $entidad_id = (int) ($row['entidad'] ?? 0);
                    $_SESSION['desktop_user'] = [
                        'id' => (int)$row['id'],
                        'username' => $row['username'],
                        'role' => $row['role'],
                        'nombre' => $row['nombre'] ?? '',
                        'email' => $row['email'] ?? '',
                        'entidad_id' => $entidad_id,
                    ];
                    $_SESSION['desktop_entidad_id'] = $entidad_id;
                    $go = isset($_SESSION['desktop_return_after_login']) ? $_SESSION['desktop_return_after_login'] : 'dashboard.php';
                    if (isset($_SESSION['desktop_return_after_login'])) {
                        unset($_SESSION['desktop_return_after_login']);
                    }
                    header('Location: ' . $go);
                    exit;
                }
            }
        } catch (Throwable $e) {
            $error = 'Error al validar. ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Desktop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center min-vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-desktop me-2"></i>Acceso Desktop</h5>
                        <small>Validación local (SQLite - mistorneos_local.db)</small>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Usuario o email</label>
                                <input type="text" name="username" class="form-control" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contraseña</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Entrar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
