<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/csrf.php';

// Si ya está logueado, redirigir
if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$pdo = DB::pdo();
$base_url = app_base_url();

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

// Crear tabla si no existe
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS solicitudes_afiliacion (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nacionalidad CHAR(1) DEFAULT 'V',
            cedula VARCHAR(20) NOT NULL,
            nombre VARCHAR(150) NOT NULL,
            email VARCHAR(150),
            celular VARCHAR(20),
            fechnac DATE,
            username VARCHAR(50) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            entidad INT NULL,
            rif VARCHAR(20),
            club_nombre VARCHAR(150) NOT NULL,
            club_ubicacion VARCHAR(255),
            motivo TEXT,
            estatus ENUM('pendiente', 'aprobada', 'rechazada') DEFAULT 'pendiente',
            notas_admin TEXT,
            revisado_por INT,
            revisado_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_cedula_pendiente (cedula, estatus)
        )
    ");
} catch (Exception $e) {
    // Tabla ya existe
}

// Asegurar columnas en tablas existentes
try {
    $cols = $pdo->query("SHOW COLUMNS FROM solicitudes_afiliacion")->fetchAll(PDO::FETCH_ASSOC);
    $has_entidad = false;
    $has_rif = false;
    foreach ($cols as $col) {
        $field = strtolower($col['Field'] ?? $col['field'] ?? '');
        if ($field === 'entidad') {
            $has_entidad = true;
        }
        if ($field === 'rif') {
            $has_rif = true;
        }
    }
    if (!$has_entidad) {
        $pdo->exec("ALTER TABLE solicitudes_afiliacion ADD COLUMN entidad INT NULL");
    }
    if (!$has_rif) {
        $pdo->exec("ALTER TABLE solicitudes_afiliacion ADD COLUMN rif VARCHAR(20) NULL");
    }
    $org_fields = ['org_direccion', 'org_responsable', 'org_telefono', 'org_email'];
    foreach ($org_fields as $f) {
        $has = false;
        foreach ($cols as $col) {
            if (strtolower($col['Field'] ?? $col['field'] ?? '') === $f) {
                $has = true;
                break;
            }
        }
        if (!$has) {
            if ($f === 'org_direccion') {
                $pdo->exec("ALTER TABLE solicitudes_afiliacion ADD COLUMN org_direccion VARCHAR(255) NULL AFTER club_ubicacion");
            } elseif ($f === 'org_responsable') {
                $pdo->exec("ALTER TABLE solicitudes_afiliacion ADD COLUMN org_responsable VARCHAR(100) NULL AFTER org_direccion");
            } elseif ($f === 'org_telefono') {
                $pdo->exec("ALTER TABLE solicitudes_afiliacion ADD COLUMN org_telefono VARCHAR(50) NULL AFTER org_responsable");
            } elseif ($f === 'org_email') {
                $pdo->exec("ALTER TABLE solicitudes_afiliacion ADD COLUMN org_email VARCHAR(100) NULL AFTER org_telefono");
            }
        }
    }
    $has_user_id = false;
    foreach ($cols as $col) {
        if (strtolower($col['Field'] ?? $col['field'] ?? '') === 'user_id') {
            $has_user_id = true;
            break;
        }
    }
    if (!$has_user_id) {
        $pdo->exec("ALTER TABLE solicitudes_afiliacion ADD COLUMN user_id INT NULL AFTER id");
    }
} catch (Exception $e) {
    // Ignorar errores de alteración
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    $entidad = isset($_POST['entidad']) ? (int)$_POST['entidad'] : 0;
    $rif = trim($_POST['rif'] ?? '');
    $club_nombre = trim($_POST['club_nombre'] ?? '');
    $club_ubicacion = trim($_POST['club_ubicacion'] ?? '');
    $org_direccion = trim($_POST['org_direccion'] ?? '');
    $org_responsable = trim($_POST['org_responsable'] ?? '');
    $org_telefono = trim($_POST['org_telefono'] ?? '');
    $org_email = trim($_POST['org_email'] ?? '');
    $motivo = trim($_POST['motivo'] ?? '');
    
    // Buscar si ya existe usuario por cédula (registrado) - probar con y sin prefijo V/E/J/P
    $cedula_externa = preg_replace('/^[VEJP]/i', '', $cedula);
    $cedula_externa = $cedula_externa ?: $cedula;
    $usuario_existente = null;
    try {
        $stmt = $pdo->prepare("SELECT id, nombre, username, email, celular, fechnac FROM usuarios WHERE cedula = ? OR cedula = ?");
        $stmt->execute([$cedula, $cedula_externa]);
        $usuario_existente = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}

    $es_usuario_registrado = !empty($usuario_existente);

    // Validaciones: si no está registrado, exige username y contraseña
    if (empty($cedula) || empty($nombre) || empty($club_nombre)) {
        $error = 'Todos los campos marcados con * son requeridos';
    } elseif (!$es_usuario_registrado && (empty($username) || empty($password))) {
        $error = 'Nombre de usuario y contraseña son requeridos para nuevos usuarios';
    } elseif ($entidad <= 0) {
        $error = 'Debe seleccionar la entidad';
    } elseif (!empty($rif) && strlen($rif) < 6) {
        $error = 'El RIF no es válido';
    } elseif (!$es_usuario_registrado && strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } elseif (!$es_usuario_registrado && $password !== $password_confirm) {
        $error = 'Las contraseñas no coinciden';
    } elseif (!$es_usuario_registrado) {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = 'El nombre de usuario ya está en uso';
        }
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El email no es válido';
    }

    if (empty($error)) {
        try {
            // No permitir solicitud pendiente duplicada (por cédula o por user_id si ya está registrado)
            $sql_dup = "SELECT id FROM solicitudes_afiliacion WHERE estatus = 'pendiente' AND (cedula = ?" . ($es_usuario_registrado ? " OR user_id = ?" : "") . ")";
            $params_dup = $es_usuario_registrado ? [$cedula, $usuario_existente['id']] : [$cedula];
            $stmt = $pdo->prepare($sql_dup);
            $stmt->execute($params_dup);
            if ($stmt->fetch()) {
                $error = 'Ya tienes una solicitud de afiliación pendiente de revisión';
            }
        } catch (Exception $e) {}

        if (empty($error)) {
            try {
                $user_id_solicitud = null;
                $password_hash = null;
                $username_solicitud = $username;

                if ($es_usuario_registrado) {
                    // Usuario ya registrado: crear solicitud, vincular y actualizar datos del usuario
                    $user_id_solicitud = (int) $usuario_existente['id'];
                    $username_solicitud = $usuario_existente['username'];
                    $nombre_upd = !empty($nombre) ? $nombre : ($usuario_existente['nombre'] ?? '');
                    $email_upd = !empty($email) ? $email : ($usuario_existente['email'] ?? '');
                    $celular_upd = $celular !== '' ? $celular : ($usuario_existente['celular'] ?? null);
                    $fechnac_upd = !empty($fechnac) ? $fechnac : ($usuario_existente['fechnac'] ?? null);
                    $stmt_upd = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ?, celular = ?, fechnac = ? WHERE id = ?");
                    $stmt_upd->execute([
                        $nombre_upd,
                        $email_upd,
                        $celular_upd,
                        $fechnac_upd,
                        $user_id_solicitud
                    ]);
                } else {
                    // Usuario nuevo: crear registro en usuarios (pendiente) y solicitud
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                        mt_rand(0, 0xffff),
                        mt_rand(0, 0x0fff) | 0x4000,
                        mt_rand(0, 0x3fff) | 0x8000,
                        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                    );
                    $stmt = $pdo->prepare("
                        INSERT INTO usuarios (cedula, nombre, email, celular, fechnac, username, password_hash, role, club_id, entidad, status, uuid, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'admin_club', NULL, ?, 'pending', ?, NOW())
                    ");
                    $stmt->execute([
                        $cedula,
                        $nombre,
                        $email ?: null,
                        $celular ?: null,
                        $fechnac ?: null,
                        $username,
                        $password_hash,
                        $entidad,
                        $uuid
                    ]);
                    $user_id_solicitud = (int) $pdo->lastInsertId();
                }

                $stmt = $pdo->prepare("
                    INSERT INTO solicitudes_afiliacion 
                    (user_id, nacionalidad, cedula, nombre, email, celular, fechnac, username, password_hash, entidad, rif, club_nombre, club_ubicacion, org_direccion, org_responsable, org_telefono, org_email, motivo, estatus, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', NOW())
                ");
                $stmt->execute([
                    $user_id_solicitud ?: null,
                    $nacionalidad,
                    $cedula,
                    $nombre,
                    $email ?: null,
                    $celular ?: null,
                    $fechnac ?: null,
                    $username_solicitud,
                    $password_hash,
                    $entidad,
                    $rif ?: null,
                    $club_nombre,
                    $club_ubicacion ?: null,
                    $org_direccion ?: null,
                    $org_responsable ?: null,
                    $org_telefono ?: null,
                    $org_email ?: null,
                    $motivo ?: null
                ]);

                $success = $es_usuario_registrado
                    ? 'Se creó una solicitud pendiente para registrar tu organización. Debe ser autorizada por el administrador general; al aprobarse se te asignará la nueva organización como administrador.'
                    : '¡Solicitud enviada! Se ha creado tu usuario en estado pendiente. Debe ser autorizada por el administrador general; al aprobarse podrás acceder y se creará tu organización.';
                $_POST = [];
                $nacionalidad = 'V';
                $cedula = '';
                $nombre = '';
                $email = '';
                $celular = '';
                $fechnac = '';
                $username = '';
                $password = '';
                $password_confirm = '';
                $entidad = 0;
                $rif = '';
                $club_nombre = '';
                $club_ubicacion = '';
                $org_direccion = '';
                $org_responsable = '';
                $org_telefono = '';
                $org_email = '';
                $motivo = '';
            } catch (Exception $e) {
                $error = 'Error al enviar la solicitud: ' . $e->getMessage();
                error_log("Affiliate request error: " . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#c53030">
    <title>Solicitud de Afiliación - La Estación del Dominó</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #c53030 0%, #9b2c2c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 2rem 0;
        }
        .register-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .register-header {
            background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .register-header i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }
        .register-body {
            padding: 2rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #c53030;
            box-shadow: 0 0 0 0.2rem rgba(197, 48, 48, 0.25);
        }
        .btn-register {
            background: linear-gradient(135deg, #c53030 0%, #9b2c2c 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
        }
        .btn-register:hover {
            background: linear-gradient(135deg, #9b2c2c 0%, #742a2a 100%);
        }
        .info-box {
            background: #fef5e7;
            border-left: 4px solid #ecc94b;
            padding: 1rem;
            border-radius: 0 8px 8px 0;
            margin-bottom: 1.5rem;
        }
        .search-status {
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }
        .section-title {
            color: #c53030;
            font-weight: 600;
            border-bottom: 2px solid #fed7d7;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="register-card">
                    <div class="register-header">
                        <i class="fas fa-building"></i>
                        <h3 class="mb-1">Solicitud de Afiliación</h3>
                        <p class="mb-0 opacity-75">Únete como organizador de torneos</p>
                    </div>
                    <div class="register-body">
                        <div class="info-box">
                            <i class="fas fa-info-circle text-warning me-2"></i>
                            <strong>Información:</strong> Al afiliarte podrás crear tus propios clubes, organizar torneos e invitar jugadores. Tu solicitud será revisada por el administrador del sistema.
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                            </div>
                            <div class="text-center">
                                <a href="landing.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-home me-1"></i>Volver al Inicio
                                </a>
                            </div>
                        <?php else: ?>
                            <form method="POST" id="affiliateForm">
                                <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
                                <input type="hidden" name="fechnac" id="fechnac" value="">
                                
                                <!-- Datos de la Organización -->
                                <h6 class="section-title"><i class="fas fa-building me-2"></i>Datos de la Organización</h6>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Nombre de la Organización *</label>
                                        <input type="text" name="club_nombre" id="club_nombre" class="form-control" 
                                               value="" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">RIF</label>
                                        <input type="text" name="rif" id="rif" class="form-control" 
                                               value="" placeholder="Ej: J-12345678-9">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label class="form-label">Dirección</label>
                                        <input type="text" name="org_direccion" id="org_direccion" class="form-control" 
                                               value="" placeholder="Dirección de la organización">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Responsable / Presidente *</label>
                                        <input type="text" name="org_responsable" id="org_responsable" class="form-control" 
                                               value="" required placeholder="Nombre del responsable o presidente">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Teléfono de la Organización</label>
                                        <input type="text" name="org_telefono" id="org_telefono" class="form-control" 
                                               value="" placeholder="Ej: 0212-1234567">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email de la Organización</label>
                                        <input type="email" name="org_email" id="org_email" class="form-control" 
                                               value="" placeholder="contacto@organizacion.com">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Entidad *</label>
                                        <select name="entidad" id="entidad" class="form-select" required>
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
                                </div>

                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label class="form-label">Ubicación / Ciudad</label>
                                        <input type="text" name="club_ubicacion" id="club_ubicacion" class="form-control" 
                                               value="" placeholder="Ej: Caracas, Miranda">
                                    </div>
                                </div>
                                
                                <!-- Datos Personales -->
                                <h6 class="section-title mt-4"><i class="fas fa-user me-2"></i>Datos Personales</h6>
                                
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
                                               onblur="buscarPersona()" required>
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
                                               value="">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Celular</label>
                                        <input type="text" name="celular" id="celular" class="form-control" 
                                               value="">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Motivo de la Solicitud</label>
                                    <textarea name="motivo" id="motivo" class="form-control" rows="3"
                                              placeholder="Cuéntanos por qué deseas afiliarte..."></textarea>
                                </div>
                                
                                <!-- Credenciales de Acceso (solo si no estás registrado) -->
                                <h6 class="section-title mt-4"><i class="fas fa-key me-2"></i>Credenciales de Acceso</h6>
                                <p class="text-muted small mb-2">Si ya tienes cuenta en el sistema, deja usuario y contraseña en blanco: al aprobar la solicitud se te asignará la organización como administrador.</p>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Nombre de Usuario</label>
                                        <input type="text" name="username" id="username" class="form-control" 
                                               value="" placeholder="Solo si eres nuevo">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Contraseña</label>
                                        <input type="password" name="password" id="password" class="form-control" placeholder="Solo si eres nuevo">
                                        <small class="text-muted">Mínimo 6 caracteres (solo usuarios nuevos)</small>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Confirmar Contraseña</label>
                                        <input type="password" name="password_confirm" id="password_confirm" class="form-control" placeholder="Solo si eres nuevo">
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-register btn-primary w-100 mt-3">
                                    <i class="fas fa-paper-plane me-2"></i>Enviar Solicitud de Afiliación
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if (!$success): ?>
                        <div class="text-center mt-4">
                            <p class="text-muted mb-2">¿Solo quieres participar en torneos?</p>
                            <a href="user_register.php" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-user-plus me-1"></i>Registro de Jugador
                            </a>
                            <a href="landing.php" class="btn btn-outline-secondary btn-sm ms-2">
                                <i class="fas fa-home me-1"></i>Volver al Inicio
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    <script>
        // Limpiar formulario completamente al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('affiliateForm');
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
                const entidad = document.getElementById('entidad');
                const rif = document.getElementById('rif');
                const clubNombre = document.getElementById('club_nombre');
                const clubUbicacion = document.getElementById('club_ubicacion');
                const orgDireccion = document.getElementById('org_direccion');
                const orgResponsable = document.getElementById('org_responsable');
                const orgTelefono = document.getElementById('org_telefono');
                const orgEmail = document.getElementById('org_email');
                const motivo = document.getElementById('motivo');
                
                if (cedula) cedula.value = '';
                if (nombre) nombre.value = '';
                if (email) email.value = '';
                if (celular) celular.value = '';
                if (username) username.value = '';
                if (password) password.value = '';
                if (passwordConfirm) passwordConfirm.value = '';
                if (nacionalidad) nacionalidad.value = 'V';
                if (fechnac) fechnac.value = '';
                if (entidad) entidad.value = '';
                if (rif) rif.value = '';
                if (clubNombre) clubNombre.value = '';
                if (clubUbicacion) clubUbicacion.value = '';
                if (orgDireccion) orgDireccion.value = '';
                if (orgResponsable) orgResponsable.value = '';
                if (orgTelefono) orgTelefono.value = '';
                if (orgEmail) orgEmail.value = '';
                if (motivo) motivo.value = '';
                
                // Limpiar también el resultado de búsqueda
                const busquedaResultado = document.getElementById('busqueda_resultado');
                if (busquedaResultado) busquedaResultado.innerHTML = '';
            }
        });
        
    function buscarPersona() {
        const cedula = document.getElementById('cedula').value.trim();
        const nacionalidad = document.getElementById('nacionalidad').value;
        const resultadoDiv = document.getElementById('busqueda_resultado');
        
        if (!cedula) {
            resultadoDiv.innerHTML = '';
            return;
        }
        
        resultadoDiv.innerHTML = '<span class="text-info"><i class="fas fa-spinner fa-spin me-1"></i>Buscando...</span>';
        
        const baseUrl = '<?= rtrim($base_url ?? app_base_url(), "/") ?>';
        const apiUrl = `${baseUrl}/public/api/search_user_persona.php?cedula=${encodeURIComponent(cedula)}&nacionalidad=${encodeURIComponent(nacionalidad)}`;
        
        fetch(apiUrl)
            .then(response => {
                // Verificar si la respuesta es exitosa
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                // Verificar si la respuesta es JSON
                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    throw new Error("La respuesta no es JSON válido");
                }
                return response.json();
            })
            .then(data => {
                // Verificar estructura de respuesta
                if (!data || typeof data !== 'object') {
                    throw new Error('Respuesta inválida del servidor');
                }
                
                // Si hay un error en la respuesta
                if (data.success === false) {
                    resultadoDiv.innerHTML = `<span class="text-danger"><i class="fas fa-times-circle me-1"></i>${data.error || 'Error en la búsqueda'}</span>`;
                    return;
                }
                
                // Si se encontró la persona
                if (data.success && data.data && data.data.encontrado) {
                    if (data.data.existe_usuario && data.data.usuario_existente) {
                        const u = data.data.usuario_existente;
                        document.getElementById('nombre').value = u.nombre || '';
                        document.getElementById('celular').value = u.celular || '';
                        document.getElementById('email').value = u.email || '';
                        const fechnacEl = document.getElementById('fechnac');
                        if (fechnacEl) fechnacEl.value = u.fechnac || '';
                        document.getElementById('username').value = '';
                        document.getElementById('password').value = '';
                        document.getElementById('password_confirm').value = '';
                        resultadoDiv.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Usuario encontrado. Puede enviar la solicitud de afiliación para su organización.</span>';
                    } else if (data.data.persona) {
                        const persona = data.data.persona;
                        document.getElementById('nombre').value = persona.nombre || '';
                        document.getElementById('celular').value = persona.celular || '';
                        document.getElementById('email').value = persona.email || '';
                        document.getElementById('fechnac').value = persona.fechnac || '';
                        
                        // Generar username sugerido
                        if (!document.getElementById('username').value) {
                            const nameParts = (persona.nombre || '').toLowerCase().split(' ');
                            if (nameParts.length >= 2) {
                                document.getElementById('username').value = nameParts[0] + '.' + nameParts[nameParts.length - 1];
                            }
                        }
                        
                        resultadoDiv.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Datos encontrados y completados</span>';
                    } else {
                        resultadoDiv.innerHTML = '<span class="text-muted"><i class="fas fa-info-circle me-1"></i>No encontrado. Complete los datos manualmente.</span>';
                    }
                } else {
                    resultadoDiv.innerHTML = '<span class="text-muted"><i class="fas fa-info-circle me-1"></i>No encontrado. Complete los datos manualmente.</span>';
                }
            })
            .catch(error => {
                console.error('Error en búsqueda:', error);
                resultadoDiv.innerHTML = `<span class="text-danger"><i class="fas fa-times-circle me-1"></i>Error en la búsqueda: ${error.message}</span>`;
            });
    }
    </script>
</body>
</html>
