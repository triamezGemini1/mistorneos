<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../lib/validation.php';

// Si ya está logueado, redirigir
$user = Auth::user();
if ($user) {
    header("Location: index.php");
    exit;
}

$success_message = $_GET['success'] ?? null;
$error_message = $_GET['error'] ?? null;

/**
 * Carga opciones de entidad (codigo, nombre) de forma resiliente.
 */
function loadEntidadesOptions(): array {
    try {
        $pdo = DB::pdo();
        $columns = $pdo->query("SHOW COLUMNS FROM entidad")->fetchAll(PDO::FETCH_ASSOC);
        if (!$columns) {
            return [];
        }
        $codeCandidates = ['codigo', 'cod_entidad', 'id', 'code'];
        $nameCandidates = ['nombre', 'descripcion', 'entidad', 'nombre_entidad'];
        $codeCol = null;
        $nameCol = null;
        foreach ($columns as $col) {
            $field = strtolower($col['Field'] ?? $col['field'] ?? '');
            if (!$codeCol && in_array($field, $codeCandidates, true)) {
                $codeCol = $col['Field'] ?? $col['field'];
            }
            if (!$nameCol && in_array($field, $nameCandidates, true)) {
                $nameCol = $col['Field'] ?? $col['field'];
            }
        }
        if (!$codeCol && isset($columns[0]['Field'])) {
            $codeCol = $columns[0]['Field'];
        }
        if (!$nameCol && isset($columns[1]['Field'])) {
            $nameCol = $columns[1]['Field'];
        }
        if (!$codeCol || !$nameCol) {
            return [];
        }
        $stmt = $pdo->query("SELECT {$codeCol} AS codigo, {$nameCol} AS nombre FROM entidad ORDER BY {$nameCol} ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

$entidades_options = loadEntidadesOptions();

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../lib/RateLimiter.php';
    if (!RateLimiter::canSubmit('register_request', 60)) {
        $error_message = 'Por favor espera 1 minuto antes de enviar otra solicitud.';
    } else {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!$csrf_token || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
        header("Location: register_request.php?error=token_invalido");
        exit;
    }

    $nombre = trim($_POST['nombre'] ?? '');
    $cedula = trim($_POST['cedula'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim((string)($_POST['password'] ?? ''));
    $confirm_password = trim((string)($_POST['confirm_password'] ?? ''));
    $role = $_POST['role'] ?? 'usuario';
    $entidad = isset($_POST['entidad']) ? (int)$_POST['entidad'] : 0;

    // Validaciones
    $errors = [];
    if (empty($nombre)) $errors[] = "Nombre es requerido";
    if (empty($cedula)) $errors[] = "Cédula es requerida";
    if (!V::email($email)) $errors[] = "Email inválido";
    if (empty($username)) $errors[] = "Username es requerido";
    if (strlen($password) < 6) $errors[] = "Contraseña debe tener al menos 6 caracteres";
    if ($password !== $confirm_password) $errors[] = "Las contraseñas no coinciden";
    if (!in_array($role, ['usuario', 'admin_club'])) $errors[] = "Rol inválido";
    if ($entidad <= 0) $errors[] = "Debe seleccionar la entidad";

    if (empty($errors)) {
        try {
            $pdo = DB::pdo();
            $has_entidad = false;
            try {
                $cols = $pdo->query("SHOW COLUMNS FROM user_requests")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cols as $col) {
                    if (strtolower($col['Field'] ?? $col['field'] ?? '') === 'entidad') {
                        $has_entidad = true;
                        break;
                    }
                }
                if (!$has_entidad) {
                    $pdo->exec("ALTER TABLE user_requests ADD COLUMN entidad INT NULL");
                    $has_entidad = true;
                }
            } catch (Exception $e) {
                $has_entidad = false;
            }
            
            // Verificar si ya existe solicitud pendiente o usuario
            $stmt = $pdo->prepare("SELECT id FROM user_requests WHERE (email = ? OR username = ?) AND status = 'pending'");
            $stmt->execute([$email, $username]);
            if ($stmt->fetch()) {
                $errors[] = "Ya existe una solicitud pendiente con este email o username";
            } else {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
                $stmt->execute([$email, $username]);
                if ($stmt->fetch()) {
                    $errors[] = "Ya existe un usuario con este email o username";
                }
            }
        } catch (Exception $e) {
            $errors[] = "Error de base de datos";
        }
    }

    if (empty($errors)) {
        try {
            $password_hash = Security::hashPassword($password);
            if (!isset($has_entidad) || !$has_entidad) {
                throw new Exception("No se pudo registrar la entidad");
            }
            $stmt = $pdo->prepare("INSERT INTO user_requests (nombre, cedula, email, username, password_hash, role, entidad) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nombre, $cedula, $email, $username, $password_hash, $role, $entidad]);
            RateLimiter::recordSubmit('register_request');
            header("Location: register_request.php?success=solicitud_enviada");
            exit;
        } catch (Exception $e) {
            $errors[] = "Error al guardar la solicitud";
        }
    }

    $error_message = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitar Registro - Serviclubes LED</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-user-plus me-2"></i>Solicitar Registro</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($success_message): ?>
                            <div class="alert alert-success" role="status" aria-live="polite">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php if ($success_message === 'solicitud_enviada'): ?>
                                    Solicitud enviada exitosamente. Un administrador revisará su solicitud.
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger" role="alert" aria-live="assertive">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?= $error_message ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="register_request.php">
                            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">

                            <div class="mb-3">
                                <label for="nombre" class="form-label">Nombre Completo *</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required>
                            </div>

                            <div class="mb-3">
                                <label for="cedula" class="form-label">Cédula *</label>
                                <input type="text" class="form-control" id="cedula" name="cedula" required>
                            </div>

                            <div class="mb-3">
                                <label for="entidad" class="form-label">Entidad *</label>
                                <select class="form-select" id="entidad" name="entidad" required>
                                    <option value="">Seleccionar Entidad</option>
                                    <?php if (!empty($entidades_options)): ?>
                                        <?php foreach ($entidades_options as $ent): ?>
                                            <option value="<?= htmlspecialchars($ent['codigo']) ?>">
                                                <?= htmlspecialchars($ent['nombre'] ?? $ent['codigo']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No hay entidades disponibles</option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>

                            <div class="mb-3">
                                <label for="username" class="form-label">Username *</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Contraseña *</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirmar Contraseña *</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>

                            <div class="mb-3">
                                <label for="role" class="form-label">Tipo de Registro *</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="usuario">Usuario Normal</option>
                                    <option value="admin_club">Administrador de organización</option>
                                </select>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>Enviar Solicitud
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="text-center mt-3">
                    <a href="landing.php" class="btn btn-link">← Volver al Inicio</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    <?php $req_base = (function_exists('app_base_url') ? app_base_url() : '/'); ?>
    <script src="<?= htmlspecialchars(rtrim($req_base, '/') . '/assets/form-utils.js') ?>" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form[action="register_request.php"]');
            if (form && typeof preventDoubleSubmit === 'function') preventDoubleSubmit(form);
            if (typeof initCedulaValidation === 'function') initCedulaValidation('cedula');
            if (typeof initEmailValidation === 'function') initEmailValidation('email');
        });
    </script>
</body>
</html>