<?php
/**
 * Registro de Usuario por Club
 * Paso 1: Seleccionar Administrador de organización
 * Paso 2: Seleccionar Club específico
 * Paso 3: Formulario de registro
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../lib/security.php';

/**
 * Obtiene opciones de entidad (ubicación geográfica).
 */
function getEntidadesOptions(): array {
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
        error_log("No se pudo obtener entidades: " . $e->getMessage());
        return [];
    }
}

// Si ya está logueado, redirigir
if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$pdo = DB::pdo();
$base_url = app_base_url();

$step = $_GET['step'] ?? 1;
$admin_id = isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : null;
$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : null;
$entidad = isset($_POST['entidad']) ? (int)$_POST['entidad'] : 0;
$entidades_options = getEntidadesOptions();

// Si se pasa club_id directamente (invitación específica), ir directamente al paso 3
if ($club_id && !isset($_GET['step'])) {
    $step = 3;
}

$error = '';
$success = '';

// Obtener administradores de club con sus clubes principales
$admins = $pdo->query("
    SELECT 
        u.id,
        u.nombre as admin_nombre,
        u.email as admin_email,
        u.celular as admin_celular,
        c.id as club_id,
        c.nombre as club_nombre,
        c.delegado,
        c.telefono,
        c.logo,
        (SELECT COUNT(*) FROM clubes WHERE organizacion_id = (SELECT id FROM organizaciones WHERE admin_user_id = u.id LIMIT 1) AND estatus = 1) as total_clubes,
        (SELECT COUNT(*) FROM tournaments WHERE club_responsable = u.club_id AND fechator >= CURDATE()) as torneos_activos
    FROM usuarios u
    INNER JOIN clubes c ON u.club_id = c.id
    WHERE u.role = 'admin_club' AND u.status = 0 AND c.estatus = 1
    ORDER BY c.nombre ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Si se seleccionó un admin, obtener sus clubes
$clubes = [];
$admin_info = null;
if ($admin_id) {
    // Info del admin
    $stmt = $pdo->prepare("
        SELECT u.*, c.nombre as club_principal
        FROM usuarios u
        LEFT JOIN clubes c ON u.club_id = c.id
        WHERE u.id = ? AND u.role = 'admin_club'
    ");
    $stmt->execute([$admin_id]);
    $admin_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin_info && $admin_info['club_id']) {
        // Obtener todos los clubes del admin
        $club_principal_id = $admin_info['club_id'];
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   CASE WHEN c.id = ? THEN 1 ELSE 0 END as es_principal,
                   (SELECT COUNT(*) FROM tournaments WHERE club_responsable = c.id AND fechator >= CURDATE()) as torneos_activos
            FROM clubes c
            WHERE c.estatus = 1 AND (
                c.id = ? OR 
                c.organizacion_id = (SELECT id FROM organizaciones WHERE admin_user_id = ? LIMIT 1)
            )
            ORDER BY es_principal DESC, c.nombre ASC
        ");
        $stmt->execute([$club_principal_id, $club_principal_id, $admin_info['id']]);
        $clubes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Si se seleccionó un club, obtener su info
$club_info = null;
if ($club_id) {
    $stmt = $pdo->prepare("SELECT * FROM clubes WHERE id = ? AND estatus = 1");
    $stmt->execute([$club_id]);
    $club_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Si se pasa club_id directamente pero no existe o está inactivo, mostrar error
    if (!$club_info && $step == 3) {
        $error = 'El club seleccionado no existe o está inactivo. Por favor, selecciona otro club.';
        $step = 1;
        $club_id = null;
    }
}

// Procesar registro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $club_id && $club_info) {
    require_once __DIR__ . '/../lib/RateLimiter.php';
    if (!RateLimiter::canSubmit('register_by_club', 30)) {
        $error = 'Por favor espera 30 segundos antes de intentar registrarte de nuevo.';
    } else {
    CSRF::validate();
    
    $nacionalidad = trim($_POST['nacionalidad'] ?? 'V');
    $cedula = trim($_POST['cedula'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $celular = trim($_POST['celular'] ?? '');
    $fechnac = trim($_POST['fechnac'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    if (empty($cedula) || empty($nombre) || empty($username) || empty($password) || $entidad <= 0) {
        $error = 'Todos los campos marcados con * son requeridos';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } elseif ($password !== $password_confirm) {
        $error = 'Las contraseñas no coinciden';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El email no es válido';
    } else {
        try {
            // Verificar si la cédula ya existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE cedula = ?");
            $stmt->execute([$cedula]);
            if ($stmt->fetch()) {
                $error = 'Ya existe un usuario registrado con esta cédula';
            } else {
                // Usar función centralizada para crear usuario
                $userData = [
                    'username' => $username,
                    'password' => $password,
                    'email' => $email ?: null,
                    'role' => 'usuario',
                    'cedula' => $cedula,
                    'nombre' => $nombre,
                    'celular' => $celular ?: null,
                    'fechnac' => $fechnac ?: null,
                    'club_id' => $club_id,
                    'entidad' => $entidad,
                    'status' => 0
                ];
                
                $result = Security::createUser($userData);
                
                if ($result['success']) {
                    RateLimiter::recordSubmit('register_by_club');
                    $success = '¡Registro exitoso! Ya puedes iniciar sesión y participar en cualquier torneo de la plataforma.';
                    // Limpiar todos los campos del formulario después de registro exitoso
                    $_POST = [];
                    $cedula = '';
                    $nombre = '';
                    $email = '';
                    $celular = '';
                    $fechnac = '';
                    $username = '';
                    $password = '';
                    $password_confirm = '';
                    $nacionalidad = 'V';
                } else {
                    $error = implode(', ', $result['errors']);
                }
            }
        } catch (Exception $e) {
            $error = 'Error al registrar: ' . $e->getMessage();
        }
    }
    }
    $step = 3;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#1a365d">
    <title>Registro por Club - La Estación del Dominó</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1a365d;
            --secondary: #2d3748;
            --accent: #48bb78;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        .main-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .header i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }
        
        .body-content {
            padding: 2rem;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #718096;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin: 0 0.5rem;
            position: relative;
        }
        
        .step.active {
            background: var(--accent);
            color: white;
        }
        
        .step.completed {
            background: var(--primary);
            color: white;
        }
        
        .step-line {
            width: 50px;
            height: 3px;
            background: #e2e8f0;
            align-self: center;
        }
        
        .step-line.completed {
            background: var(--primary);
        }
        
        .admin-card {
            border: 2px solid #e2e8f0;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .admin-card:hover {
            border-color: var(--accent);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .admin-card .club-logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
        }
        
        .admin-card .club-logo-placeholder {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
        }
        
        .club-card {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .club-card:hover {
            border-color: var(--accent);
            background: #f7fafc;
        }
        
        .club-card.principal {
            border-color: var(--primary);
            background: #ebf8ff;
        }
        
        .info-box {
            background: #e6fffa;
            border-left: 4px solid var(--accent);
            padding: 1rem;
            border-radius: 0 8px 8px 0;
            margin-bottom: 1.5rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 0.2rem rgba(72, 187, 120, 0.25);
        }
        
        .btn-register {
            background: linear-gradient(135deg, var(--accent) 0%, #38a169 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
        }
        
        .btn-register:hover {
            background: linear-gradient(135deg, #38a169 0%, #2f855a 100%);
        }
        
        .search-status {
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }
        
        .selected-club-badge {
            background: var(--accent);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="main-card">
                    <div class="header">
                        <i class="fas fa-user-plus"></i>
                        <h3 class="mb-1">Registro de Jugador</h3>
                        <p class="mb-0 opacity-75">Selecciona tu club y únete a la comunidad</p>
                    </div>
                    
                    <div class="body-content">
                        <!-- Indicador de pasos -->
                        <div class="step-indicator">
                            <div class="step <?= $step >= 1 ? ($step > 1 ? 'completed' : 'active') : '' ?>">1</div>
                            <div class="step-line <?= $step > 1 ? 'completed' : '' ?>"></div>
                            <div class="step <?= $step >= 2 ? ($step > 2 ? 'completed' : 'active') : '' ?>">2</div>
                            <div class="step-line <?= $step > 2 ? 'completed' : '' ?>"></div>
                            <div class="step <?= $step >= 3 ? 'active' : '' ?>">3</div>
                        </div>
                        
                        <?php if ($step == 1 || (!$admin_id && !$club_id)): ?>
                        <!-- PASO 1: Seleccionar Administrador/Club Principal -->
                        <h5 class="text-center mb-4">
                            <i class="fas fa-building me-2"></i>Selecciona un Club Principal
                        </h5>
                        
                        <?php if (empty($admins)): ?>
                            <div class="alert alert-warning text-center">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                No hay clubes disponibles en este momento
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($admins as $admin): ?>
                                    <div class="col-md-6 mb-3">
                                        <a href="?step=2&admin_id=<?= $admin['id'] ?>" class="text-decoration-none">
                                            <div class="admin-card">
                                                <div class="d-flex align-items-center">
                                                    <?php if ($admin['logo'] && file_exists(__DIR__ . '/../' . $admin['logo'])): ?>
                                                        <img src="<?= $base_url ?>/<?= htmlspecialchars($admin['logo']) ?>" alt="Logo" class="club-logo me-3">
                                                    <?php else: ?>
                                                        <div class="club-logo-placeholder me-3">
                                                            <?php 
                                                            require_once __DIR__ . '/../lib/app_helpers.php';
                                                            $logo_url = AppHelpers::getAppLogo();
                                                            ?>
                                                            <img src="<?= htmlspecialchars($logo_url) ?>" alt="La Estación del Dominó" style="height: 50px;">
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1"><?= htmlspecialchars($admin['club_nombre']) ?></h6>
                                                        <small class="text-muted d-block">
                                                            <i class="fas fa-user me-1"></i><?= htmlspecialchars($admin['delegado'] ?? $admin['admin_nombre']) ?>
                                                        </small>
                                                        <div class="mt-2">
                                                            <span class="badge bg-primary"><?= $admin['total_clubes'] ?> clubes</span>
                                                            <span class="badge bg-success"><?= $admin['torneos_activos'] ?> torneos</span>
                                                        </div>
                                                    </div>
                                                    <i class="fas fa-chevron-right text-muted"></i>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php elseif ($step == 2 && $admin_id && $admin_info): ?>
                        <!-- PASO 2: Seleccionar Club Específico -->
                        <div class="mb-4">
                            <a href="?step=1" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-arrow-left me-1"></i>Cambiar Club Principal
                            </a>
                        </div>
                        
                        <h5 class="text-center mb-2">
                            <i class="fas fa-list me-2"></i>Clubes de <?= htmlspecialchars($admin_info['club_principal']) ?>
                        </h5>
                        <p class="text-center text-muted mb-4">Selecciona el club donde deseas registrarte</p>
                        
                        <?php if (empty($clubes)): ?>
                            <div class="alert alert-warning text-center">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                No hay clubes disponibles
                            </div>
                        <?php else: ?>
                            <?php foreach ($clubes as $club): ?>
                                <a href="?step=3&admin_id=<?= $admin_id ?>&club_id=<?= $club['id'] ?>" class="text-decoration-none">
                                    <div class="club-card <?= $club['es_principal'] ? 'principal' : '' ?>">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1">
                                                    <?= htmlspecialchars($club['nombre']) ?>
                                                    <?php if ($club['es_principal']): ?>
                                                        <span class="badge bg-primary ms-2">Principal</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <?php if ($club['delegado']): ?>
                                                        <i class="fas fa-user me-1"></i><?= htmlspecialchars($club['delegado']) ?>
                                                    <?php endif; ?>
                                                    <?php if ($club['telefono']): ?>
                                                        <span class="mx-2">|</span>
                                                        <i class="fas fa-phone me-1"></i><?= htmlspecialchars($club['telefono']) ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-success"><?= $club['torneos_activos'] ?> torneos</span>
                                                <i class="fas fa-chevron-right text-muted ms-2"></i>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php elseif ($step == 3 && $club_id && $club_info): ?>
                        <!-- PASO 3: Formulario de Registro -->
                        <?php if ($admin_id): ?>
                        <div class="mb-4">
                            <a href="?step=2&admin_id=<?= $admin_id ?>" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-arrow-left me-1"></i>Cambiar Club
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="mb-4">
                            <a href="?" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-arrow-left me-1"></i>Ver Otros Clubes
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <div class="selected-club-badge">
                            <i class="fas fa-building me-2"></i><?= htmlspecialchars($club_info['nombre']) ?>
                            <?php if ($club_info['delegado']): ?>
                                <br><small class="text-muted"><i class="fas fa-user me-1"></i><?= htmlspecialchars($club_info['delegado']) ?></small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="info-box">
                            <i class="fas fa-info-circle text-success me-2"></i>
                            <strong>Invitación Especial:</strong> Has sido invitado a afiliarte a este club. 
                            Aunque te registres aquí, podrás participar en cualquier torneo de toda la plataforma sin restricciones.
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                                <div class="mt-3">
                                    <a href="login.php" class="btn btn-success">
                                        <i class="fas fa-sign-in-alt me-1"></i>Iniciar Sesión
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <form method="POST" id="registerForm">
                                <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
                                <input type="hidden" name="fechnac" id="fechnac" value="">
                                
                                <h6 class="text-muted mb-3"><i class="fas fa-user me-2"></i>Datos Personales</h6>
                                
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Nacionalidad *</label>
                                        <select name="nacionalidad" id="nacionalidad" class="form-select" required>
                                            <option value="V" selected>V</option>
                                            <option value="E">E</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Cédula *</label>
                                        <input type="text" name="cedula" id="cedula" class="form-control" 
                                               value=""
                                               onblur="debouncedBuscarPersona()" required>
                                        <div id="busqueda_resultado" class="search-status"></div>
                                    </div>
                                    <div class="col-md-5 mb-3">
                                        <label class="form-label">Nombre Completo *</label>
                                        <input type="text" name="nombre" id="nombre" class="form-control" 
                                               value="" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" id="email" class="form-control" 
                                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Celular</label>
                                        <input type="text" name="celular" id="celular" class="form-control" 
                                               value="<?= htmlspecialchars($_POST['celular'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Entidad (Ubicación) *</label>
                                    <select name="entidad" id="entidad" class="form-select" required>
                                        <option value="">-- Seleccione --</option>
                                        <?php if (!empty($entidades_options)): ?>
                                            <?php foreach ($entidades_options as $ent): ?>
                                                <option value="<?= htmlspecialchars($ent['codigo']) ?>" <?= ($entidad == $ent['codigo']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($ent['nombre'] ?? $ent['codigo']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <option value="" disabled>No hay entidades disponibles</option>
                                        <?php endif; ?>
                                    </select>
                                    <div class="form-text text-muted">Se almacenará en usuarios.entidad</div>
                                </div>
                                
                                <hr class="my-4">
                                <h6 class="text-muted mb-3"><i class="fas fa-key me-2"></i>Credenciales de Acceso</h6>
                                
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Nombre de Usuario *</label>
                                        <input type="text" name="username" id="username" class="form-control" 
                                               value="" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Contraseña *</label>
                                        <input type="password" name="password" id="password" class="form-control" required>
                                        <small class="text-muted">Mínimo 6 caracteres</small>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Confirmar *</label>
                                        <input type="password" name="password_confirm" id="password_confirm" class="form-control" required>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-register btn-primary w-100 mt-3">
                                    <i class="fas fa-user-plus me-2"></i>Registrarme en <?= htmlspecialchars($club_info['nombre']) ?>
                                </button>
                            </form>
                        <?php endif; ?>
                        <?php endif; ?>
                        
                        <!-- Enlaces inferiores -->
                        <div class="text-center mt-4 pt-3 border-top">
                            <p class="text-muted mb-2">¿Ya tienes cuenta?</p>
                            <a href="login.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-sign-in-alt me-1"></i>Iniciar Sesión
                            </a>
                            <a href="landing.php" class="btn btn-outline-secondary btn-sm ms-2">
                                <i class="fas fa-home me-1"></i>Inicio
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    <script src="<?= htmlspecialchars(rtrim($base_url ?? app_base_url(), '/') . '/assets/form-utils.js') ?>" defer></script>
    <script>
        // Limpiar formulario completamente al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registerForm');
            if (form) {
                // Limpiar todos los campos, especialmente credenciales
                const cedula = document.getElementById('cedula');
                const nombre = document.getElementById('nombre');
                const email = document.getElementById('email');
                const celular = document.getElementById('celular');
                const username = document.getElementById('username');
                const password = document.getElementById('password');
                const passwordConfirm = document.getElementById('password_confirm');
                const nacionalidad = document.getElementById('nacionalidad');
                const fechnac = document.getElementById('fechnac');
                
                if (cedula) cedula.value = '';
                if (nombre) nombre.value = '';
                if (email) email.value = '';
                if (celular) celular.value = '';
                if (username) username.value = '';
                if (password) password.value = '';
                if (passwordConfirm) passwordConfirm.value = '';
                if (nacionalidad) nacionalidad.value = 'V';
                if (fechnac) fechnac.value = '';
                
                // Limpiar también el resultado de búsqueda
                const busquedaResultado = document.getElementById('busqueda_resultado');
                if (busquedaResultado) busquedaResultado.innerHTML = '';
                if (form && typeof preventDoubleSubmit === 'function') preventDoubleSubmit(form);
                if (typeof initCedulaValidation === 'function') initCedulaValidation('cedula');
                if (typeof initEmailValidation === 'function') initEmailValidation('email');
            }
        });
    const debouncedBuscarPersona = typeof debounce === 'function' ? debounce(buscarPersona, 400) : buscarPersona;
    function buscarPersona() {
        const cedula = document.getElementById('cedula').value.trim();
        const nacionalidad = document.getElementById('nacionalidad').value;
        const resultadoDiv = document.getElementById('busqueda_resultado');
        
        if (!cedula) {
            resultadoDiv.innerHTML = '';
            return;
        }
        
        resultadoDiv.innerHTML = '<span class="text-info"><i class="fas fa-spinner fa-spin me-1"></i>Buscando...</span>';
        
        fetch(`<?= $base_url ?>/public/api/search_user_persona.php?cedula=${cedula}&nacionalidad=${nacionalidad}`)
            .then(response => response.json())
            .then(data => {
                if (data && data.success && data.data && data.data.encontrado) {
                    if (data.data.existe_usuario) {
                        resultadoDiv.innerHTML = `<span class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>${data.data.mensaje}</span>`;
                    } else {
                        const persona = data.data.persona;
                        document.getElementById('nombre').value = persona.nombre || '';
                        document.getElementById('celular').value = persona.celular || '';
                        document.getElementById('email').value = persona.email || '';
                        document.getElementById('fechnac').value = persona.fechnac || '';
                        
                        if (!document.getElementById('username').value) {
                            const nameParts = (persona.nombre || '').toLowerCase().split(' ');
                            if (nameParts.length >= 2) {
                                document.getElementById('username').value = nameParts[0] + '.' + nameParts[nameParts.length - 1];
                            }
                        }
                        
                        resultadoDiv.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Datos completados</span>';
                    }
                } else {
                    resultadoDiv.innerHTML = '<span class="text-muted"><i class="fas fa-info-circle me-1"></i>Complete manualmente</span>';
                }
            })
            .catch(error => {
                resultadoDiv.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle me-1"></i>Error</span>';
            });
    }
    </script>
</body>
</html>

