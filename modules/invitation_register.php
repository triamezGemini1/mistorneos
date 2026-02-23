<?php
require_once __DIR__ . '/../lib/image_helper.php';
require_once __DIR__ . '/../public/simple_image_config.php';
if (!class_exists('AppHelpers')) {
    require_once __DIR__ . '/../lib/app_helpers.php';
}

$token = trim($_GET['token'] ?? '');
$torneo_id = $_GET['torneo'] ?? '';
$club_id = $_GET['club'] ?? '';

$error_message = '';
$success_message = '';
$invitation_data = null;
$tournament_data = null;
$club_data = null;
$organizer_club_data = null;
$inscripciones_abiertas = false;

$tb_inv = defined('TABLE_INVITATIONS') ? TABLE_INVITATIONS : 'invitaciones';
if (!empty($token) && strlen($token) >= 32 && (empty($torneo_id) || empty($club_id))) {
    try {
        $stmt = DB::pdo()->prepare("SELECT torneo_id, club_id FROM {$tb_inv} WHERE token = ? AND (estado = 0 OR estado = 'activa' OR estado = 'vinculado') LIMIT 1");
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $torneo_id = $row['torneo_id'];
            $club_id = $row['club_id'];
        }
    } catch (Exception $e) {
        $torneo_id = '';
        $club_id = '';
    }
}

// Validar invitación
if (empty($torneo_id) || empty($club_id)) {
    $error_message = "Parámetros de acceso inválidos";
} else {
    try {
        $stmt = DB::pdo()->prepare("
            SELECT i.*, t.nombre as tournament_name, t.fechator, t.clase, t.modalidad, t.club_responsable,
                   c.nombre as club_name, c.direccion, c.delegado, c.telefono, c.email, c.logo as club_logo,
                   COALESCE(c.delegado_user_id, 0) AS club_delegado_user_id
            FROM " . (defined('TABLE_INVITATIONS') ? TABLE_INVITATIONS : 'invitaciones') . " i
            LEFT JOIN tournaments t ON i.torneo_id = t.id 
            LEFT JOIN clubes c ON i.club_id = c.id 
            WHERE i.torneo_id = ? AND i.club_id = ? AND (i.estado = 0 OR i.estado = 'activa' OR i.estado = 'vinculado')
        ");
        $stmt->execute([$torneo_id, $club_id]);
        $invitation_data = $stmt->fetch();
        
        if (!$invitation_data) {
            $error_message = "Invitación no válida";
        } else {
            $id_vinculado = isset($invitation_data['id_usuario_vinculado']) ? (int)$invitation_data['id_usuario_vinculado'] : 0;
            $current_user = Auth::user();
            $is_admin = $current_user && in_array($current_user['role'], ['admin_general', 'admin_torneo']);
            $stand_by = false;

            if (!$current_user) {
                // Si el club tiene usuario asociado -> login (autenticarse y acceder directo). Si no -> registro.
                $base = class_exists('AppHelpers') ? rtrim(AppHelpers::getPublicUrl(), '/') : (rtrim(($GLOBALS['APP_CONFIG']['app']['base_url'] ?? ''), '/') ?: '');
                $club_tiene_usuario = (int)($invitation_data['club_delegado_user_id'] ?? 0) > 0;
                if ($base !== '' && !empty($token)) {
                    $_SESSION['url_retorno'] = $base . '/invitation/register?token=' . urlencode($token);
                    $_SESSION['invitation_token'] = $token;
                    $_SESSION['invitation_club_name'] = $invitation_data['club_name'] ?? 'Club';
                    $cookie_days = 7;
                    if (!headers_sent()) {
                        setcookie('invitation_token', $token, time() + ($cookie_days * 86400), '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
                    }
                    if ($club_tiene_usuario) {
                        header('Location: ' . $base . '/login.php');
                    } else {
                        header('Location: ' . $base . '/user_register.php?from_invitation=1');
                    }
                    exit;
                }
                $stand_by = true;
                // Fallback: mostrar stand-by si no hubo redirección
                $now = new DateTime();
                $start_date = new DateTime($invitation_data['acceso1']);
                $end_date = new DateTime($invitation_data['acceso2']);
                $stmt_organizer = DB::pdo()->prepare("SELECT nombre, logo, direccion, delegado, telefono, email FROM clubes WHERE id = ?");
                $stmt_organizer->execute([$invitation_data['club_responsable']]);
                $organizer_club_data = $stmt_organizer->fetch();
                $tournament_data = [
                    'id' => $invitation_data['torneo_id'],
                    'nombre' => $invitation_data['tournament_name'],
                    'fechator' => $invitation_data['fechator'],
                    'clase' => $invitation_data['clase'],
                    'modalidad' => $invitation_data['modalidad']
                ];
                $club_data = [
                    'id' => $invitation_data['club_id'],
                    'nombre' => $invitation_data['club_name'],
                    'direccion' => $invitation_data['direccion'],
                    'delegado' => $invitation_data['delegado'],
                    'telefono' => $invitation_data['telefono'],
                    'email' => $invitation_data['email'],
                    'logo' => $invitation_data['club_logo']
                ];
                $inscripciones_abiertas = ($now >= $start_date && $now <= $end_date);
            } elseif (!$is_admin && $id_vinculado > 0 && (int)($current_user['id']) !== $id_vinculado) {
                $error_message = "Esta invitación ya está siendo gestionada por otro delegado.";
                $invitation_data = null;
            } else {
            $stand_by = false;
            // Verificar fechas de acceso
            $now = new DateTime();
            $start_date = new DateTime($invitation_data['acceso1']);
            $end_date = new DateTime($invitation_data['acceso2']);
            
            if ($now < $start_date) {
                $error_message = "El período de inscripción aún no ha comenzado";
            } elseif ($now > $end_date) {
                $error_message = "El período de inscripción ha expirado";
            }
            // Siempre cargar datos del organizador y club (para cabecera con logos)
            $stmt_organizer = DB::pdo()->prepare("
                SELECT nombre, logo, direccion, delegado, telefono, email 
                FROM clubes 
                WHERE id = ?
            ");
            $stmt_organizer->execute([$invitation_data['club_responsable']]);
            $organizer_club_data = $stmt_organizer->fetch();
            
            $tournament_data = [
                'id' => $invitation_data['torneo_id'],
                'nombre' => $invitation_data['tournament_name'],
                'fechator' => $invitation_data['fechator'],
                'clase' => $invitation_data['clase'],
                'modalidad' => $invitation_data['modalidad']
            ];
            
            $club_data = [
                'id' => $invitation_data['club_id'],
                'nombre' => $invitation_data['club_name'],
                'direccion' => $invitation_data['direccion'],
                'delegado' => $invitation_data['delegado'],
                'telefono' => $invitation_data['telefono'],
                'email' => $invitation_data['email'],
                'logo' => $invitation_data['club_logo']
            ];
            
            $inscripciones_abiertas = ($now >= $start_date && $now <= $end_date);
            if (!$inscripciones_abiertas) {
                $error_message = '';
            }
            }
        }
    } catch (Exception $e) {
        $error_message = "Error al validar invitación: " . $e->getMessage();
    }
}


// Procesar formulario de inscripción de jugador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'retirar') {
    $cur = Auth::user();
    $can = $cur && in_array($cur['role'], ['admin_general', 'admin_torneo', 'admin_club']);
    $id_r = (int)($_POST['id_inscripcion'] ?? 0);
    if ($can && $id_r > 0 && $invitation_data) {
        $now = new DateTime();
        $st = new DateTime($invitation_data['acceso1']);
        $ed = new DateTime($invitation_data['acceso2']);
        if ($now >= $st && $now <= $ed) {
            try {
                $stmt = DB::pdo()->prepare("DELETE FROM inscritos WHERE id = ? AND torneo_id = ? AND id_club = ?");
                $stmt->execute([$id_r, $torneo_id, $club_id]);
                if ($stmt->rowCount() > 0) $success_message = "Inscripción retirada correctamente";
            } catch (Exception $e) { $error_message = "Error al retirar: " . $e->getMessage(); }
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_player') {
    $current_user = Auth::user();
    $is_admin_general = $current_user && $current_user['role'] === 'admin_general';
    $is_admin_torneo = $current_user && $current_user['role'] === 'admin_torneo';
    $is_admin_club = $current_user && $current_user['role'] === 'admin_club';
    $id_vinculado_inv = $invitation_data ? (int)($invitation_data['id_usuario_vinculado'] ?? 0) : 0;
    $es_usuario_vinculado = $current_user && $id_vinculado_inv > 0 && (int)$current_user['id'] === $id_vinculado_inv;
    $puede_inscribir = $is_admin_general || $is_admin_torneo || $is_admin_club || $es_usuario_vinculado;
    $dentro_vigencia = $invitation_data && (new DateTime() >= new DateTime($invitation_data['acceso1']) && new DateTime() <= new DateTime($invitation_data['acceso2']));
    if ($puede_inscribir && $dentro_vigencia) {
        try {
            $cedula = preg_replace('/\D/', '', trim($_POST['cedula'] ?? ''));
            $nombre = trim($_POST['nombre'] ?? '');
            $sexo = in_array($_POST['sexo'] ?? '', ['M', 'F', 'O']) ? $_POST['sexo'] : 'M';
            $telefono = trim($_POST['telefono'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $fechnac = !empty($_POST['fechnac']) ? $_POST['fechnac'] : null;
            $nacionalidad = in_array($_POST['nacionalidad'] ?? '', ['V', 'E', 'J', 'P']) ? $_POST['nacionalidad'] : 'V';
            $id_club_insc = !empty($_POST['club_id']) ? (int)$_POST['club_id'] : $club_id;

            if (empty($cedula) || empty($nombre) || empty($telefono)) {
                throw new Exception('Los campos cédula, nombre y teléfono son requeridos');
            }

            $pdo = DB::pdo();
            // 1) Buscar usuario por cedula + nacionalidad
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE cedula = ? AND nacionalidad = ? LIMIT 1");
            $stmt->execute([$cedula, $nacionalidad]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            $id_usuario = $usuario ? (int)$usuario['id'] : null;

            if (!$id_usuario) {
                // Usuario = nombre.apellido (slug); contraseña = cédula
                $partes = preg_split('/\s+/u', trim($nombre), 2);
                $nombre_part = $partes[0] ?? '';
                $apellido_part = isset($partes[1]) ? trim($partes[1]) : '';
                $slug = function ($s) {
                    $s = mb_strtolower($s, 'UTF-8');
                    $s = preg_replace('/[áàäâ]/u', 'a', $s);
                    $s = preg_replace('/[éèëê]/u', 'e', $s);
                    $s = preg_replace('/[íìïî]/u', 'i', $s);
                    $s = preg_replace('/[óòöô]/u', 'o', $s);
                    $s = preg_replace('/[úùüû]/u', 'u', $s);
                    $s = preg_replace('/[ñ]/u', 'n', $s);
                    $s = preg_replace('/[^a-z0-9]/u', '', $s);
                    return $s;
                };
                $username_base = $slug($nombre_part);
                if ($apellido_part !== '') {
                    $username_base .= '.' . $slug($apellido_part);
                }
                if ($username_base === '') {
                    $username_base = 'usuario' . $cedula;
                }
                $username = $username_base;
                $idx = 0;
                while (true) {
                    $st = $pdo->prepare("SELECT id FROM usuarios WHERE username = ? LIMIT 1");
                    $st->execute([$username]);
                    if (!$st->fetch()) break;
                    $idx++;
                    $username = $username_base . '_' . $idx;
                }
                $password_hash = password_hash($cedula, PASSWORD_DEFAULT);
                $email_val = $email !== '' ? $email : ($username . '@gmail.com');
                $cols = "nombre, cedula, nacionalidad, sexo, fechnac, email, username, password_hash, role, club_id, entidad, status";
                $placeholders = "?, ?, ?, ?, ?, ?, ?, ?, 'usuario', 0, 0, 0";
                $params = [$nombre, $cedula, $nacionalidad, $sexo, $fechnac ?: null, $email_val, $username, $password_hash];
                if (isset($GLOBALS['USUARIOS_HAS_CELULAR']) || @$pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'celular'")->fetch()) {
                    $cols .= ", celular";
                    $placeholders .= ", ?";
                    $params[] = $telefono ?: null;
                }
                $stmt = $pdo->prepare("INSERT INTO usuarios ({$cols}) VALUES ({$placeholders})");
                $stmt->execute($params);
                $id_usuario = (int) $pdo->lastInsertId();
            } else {
                // Opcional: actualizar celular/email si cambió; si no tiene email usar usuario@gmail.com
                try {
                    $st = $pdo->prepare("SELECT username FROM usuarios WHERE id = ? LIMIT 1");
                    $st->execute([$id_usuario]);
                    $row = $st->fetch(PDO::FETCH_ASSOC);
                    $email_actual = $email !== '' ? $email : (($row['username'] ?? '') . '@gmail.com');
                    if ($email_actual === '@gmail.com') $email_actual = 'inv@mistorneos.local';
                    $st = $pdo->prepare("UPDATE usuarios SET nombre = ?, sexo = ?, fechnac = ?, email = ? WHERE id = ?");
                    $st->execute([$nombre, $sexo, $fechnac ?: null, $email_actual, $id_usuario]);
                    $chk = @$pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'celular'");
                    if ($chk && $chk->fetch()) {
                        $pdo->prepare("UPDATE usuarios SET celular = ? WHERE id = ?")->execute([$telefono ?: null, $id_usuario]);
                    }
                } catch (Exception $e) { /* ignorar */ }
            }

            // 2) Verificar si ya está inscrito en este torneo
            $stmt = $pdo->prepare("SELECT id FROM inscritos WHERE id_usuario = ? AND torneo_id = ? LIMIT 1");
            $stmt->execute([$id_usuario, $torneo_id]);
            if ($stmt->fetch()) {
                throw new Exception('Ya existe una inscripción para esta cédula en este torneo');
            }

            // 3) Inscribir en inscritos con el club que inscribe
            $inscrito_por = $current_user ? (int)$current_user['id'] : null;
            $stmt = $pdo->prepare("
                INSERT INTO inscritos (id_usuario, torneo_id, id_club, estatus, inscrito_por, fecha_inscripcion)
                VALUES (?, ?, ?, 'confirmado', ?, NOW())
            ");
            $stmt->execute([$id_usuario, $torneo_id, $id_club_insc, $inscrito_por]);

            $success_message = "Jugador inscrito exitosamente";
            $_POST = [];
        } catch (Exception $e) {
            $error_message = "Error al inscribir jugador: " . $e->getMessage();
        }
    } else {
        if (isset($dentro_vigencia) && !$dentro_vigencia && $invitation_data) {
            $error_message = "El período de inscripción está cerrado";
        } elseif ($current_user) {
            $user_role = $current_user['role'];
            if (in_array($user_role, ['admin_club', 'usuario'])) {
                $error_message = "Debe autenticarse como club para inscribir jugadores";
            } else {
                $error_message = "No tiene permisos para inscribir jugadores";
            }
        } else {
            $error_message = "Debe autenticarse para inscribir jugadores";
        }
    }
}

// Verificar si el usuario está autenticado
$current_user = Auth::user();
$is_admin_general = $current_user && $current_user['role'] === 'admin_general';
$is_admin_torneo = $current_user && $current_user['role'] === 'admin_torneo';
$is_admin_club = $current_user && $current_user['role'] === 'admin_club';

// Determinar si el formulario debe estar habilitado (admin o usuario vinculado a esta invitación)
$id_vinculado_inv = $invitation_data ? (int)($invitation_data['id_usuario_vinculado'] ?? 0) : 0;
$es_usuario_vinculado = $current_user && $id_vinculado_inv > 0 && (int)$current_user['id'] === $id_vinculado_inv;
$form_enabled = $is_admin_general || $is_admin_torneo || $is_admin_club || $es_usuario_vinculado;

// Obtener inscripciones existentes del club para este torneo (inscritos + usuarios)
$existing_registrations = [];
if ($invitation_data && !$error_message) {
    try {
        $stmt = DB::pdo()->prepare("
            SELECT i.id, i.id_usuario, i.fecha_inscripcion as created_at,
                   u.cedula, u.nombre, u.sexo, u.username,
                   COALESCE(u.celular, '') as celular,
                   u.email
            FROM inscritos i
            JOIN usuarios u ON i.id_usuario = u.id
            WHERE i.torneo_id = ? AND i.id_club = ?
            ORDER BY i.fecha_inscripcion DESC
        ");
        $stmt->execute([$torneo_id, $club_id]);
        $existing_registrations = $stmt->fetchAll();
    } catch (Exception $e) {
        // Error al obtener inscripciones existentes
    }
}
if (!isset($base) || $base === '') {
    $base = rtrim(class_exists('AppHelpers') ? AppHelpers::getPublicUrl() : ($GLOBALS['APP_CONFIG']['app']['base_url'] ?? ''), '/');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#1a365d">
    <title>Inscripción por invitación - La Estación del Dominó</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1a365d 0%, #2d3748 100%);
            min-height: 100vh;
            padding: 1.5rem 0;
            color: #1f2937;
        }
        .invitation-page-header {
            background: linear-gradient(135deg, #1a365d 0%, #2d3748 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 16px 16px 0 0;
            text-align: center;
        }
        .invitation-page-header img {
            height: 56px;
            margin-bottom: 0.75rem;
        }
        .invitation-page-header h4 { font-weight: 600; margin-bottom: 0.25rem; }
        .invitation-page-header .sub { opacity: 0.9; font-size: 0.95rem; }
        .main-card-wrap {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.25);
            overflow: hidden;
            max-width: 1200px;
            margin: 0 auto;
        }
        .main-card-wrap .card { border: none; border-radius: 0; }
        .main-card-wrap .card-header { font-weight: 600; }
        .btn-mistorneos {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            border: none;
            color: #fff;
            font-weight: 500;
        }
        .btn-mistorneos:hover {
            background: linear-gradient(135deg, #38a169 0%, #2f855a 100%);
            color: #fff;
        }
        .form-control:focus, .form-select:focus {
            border-color: #48bb78;
            box-shadow: 0 0 0 0.2rem rgba(72, 187, 120, 0.25);
        }
        .badge-sm { font-size: 0.7em; }
        .table-sm th, .table-sm td { padding: 0.35rem 0.5rem; }
        .form-control:readonly { background-color: #f8f9fa; opacity: 0.8; }
        .form-select:disabled { background-color: #f8f9fa; opacity: 0.8; }
        .form-compact-invitation .inv-field { display: inline-flex; flex-direction: column; margin-bottom: 0; }
        .form-compact-invitation .inv-label { font-size: 0.7rem; margin-bottom: 0.15rem; white-space: nowrap; }
        .form-compact-invitation .form-control-sm, .form-compact-invitation .form-select-sm { font-size: 0.8rem; }
        @media (max-width: 768px) {
            .col-md-6 { margin-bottom: 1rem; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="main-card-wrap">
        <div class="invitation-page-header">
            <?php $logo_url = AppHelpers::getAppLogo(); ?>
            <img src="<?= htmlspecialchars($logo_url) ?>" alt="La Estación del Dominó">
            <h4 class="mb-0">La Estación del Dominó</h4>
            <p class="sub mb-0">Inscripción por invitación</p>
        </div>
        <div class="card-body">
<div class="fade-in">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">
                <i class="fas fa-user-plus me-2"></i>
                Registro de Jugadores
            </h1>
            <p class="text-muted mb-0">Sistema de inscripción para clubes invitados</p>
        </div>
        <?php if ($is_admin_general): ?>
        <div>
            <a href="index.php?page=invitations" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Volver a Invitaciones
            </a>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($error_message): ?>
        <!-- Error -->
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Acceso Denegado
                </h5>
            </div>
            <div class="card-body text-center py-5">
                <i class="fas fa-lock text-danger fs-1 mb-3"></i>
                <h5 class="text-danger"><?= htmlspecialchars($error_message) ?></h5>
                <p class="text-muted">Por favor, verifica que tienes acceso válido a esta página.</p>
                <?php if ($is_admin_general): ?>
                <a href="index.php?page=invitations" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Volver a Invitaciones
                </a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Header con Logos y Título -->
        <div class="card mb-4">
            <div class="card-body text-center py-4">
                <div class="row align-items-center">
                    <!-- Logo del club responsable (izquierda) -->
                    <div class="col-md-3">
                        <div class="d-flex align-items-center justify-content-center" style="height: 120px; background: #f8f9fa; border-radius: 10px; border: 2px dashed #dee2e6;">
                           <?= displayClubLogoInvitation($organizer_club_data, 'organizador') ?>
                        </div>
                    </div>
                    
                    <!-- Información central -->
                    <div class="col-md-6 text-center">
                        <h2 class="h3 text-primary mb-2">
                            <?= htmlspecialchars($organizer_club_data['nombre'] ?? 'Club Organizador') ?>
                        </h2>
                        
                        <p class="text-muted mb-3" style="font-style: italic;">
                            Le invitamos a compartir con nosotros este magno evento:
                        </p>
                        
                        <h1 class="display-6 text-success mb-3">
                            <?= htmlspecialchars($tournament_data['nombre']) ?>
                        </h1>
                        
                        <div class="mt-3">
                            <span class="badge bg-info fs-6">
                                <i class="fas fa-calendar-alt me-2"></i>
                                <?= date('d/m/Y', strtotime($tournament_data['fechator'])) ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Logo del club invitado (derecha) -->
                    <div class="col-md-3">
                        <div class="d-flex align-items-center justify-content-center" style="height: 120px; background: #f8f9fa; border-radius: 10px; border: 2px dashed #dee2e6;">
                           <?= displayClubLogoInvitation($club_data, 'invitado') ?>
                        </div>
                    </div>
                </div>
                
                <!-- Período de inscripción -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="alert alert-info text-center">
                            <i class="fas fa-clock me-2"></i>
                            <strong>Período de Inscripción:</strong> 
                            Desde <?= date('d/m/Y H:i', strtotime($invitation_data['acceso1'])) ?> 
                            hasta <?= date('d/m/Y H:i', strtotime($invitation_data['acceso2'])) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel administrador: dos líneas, sin título -->
        <?php if ($is_admin_general || $is_admin_torneo): ?>
        <div class="alert alert-primary py-2 mb-3">
            <div><strong>Usuario:</strong> <?= htmlspecialchars($current_user['username']) ?> &nbsp; <strong>Rol:</strong> <?= htmlspecialchars($current_user['role']) ?></div>
            <div class="small">Puede inscribir jugadores directamente sin autenticación del club.</div>
        </div>
        <?php elseif ($is_admin_club): ?>
        <!-- Panel de Control del Club Autenticado -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-check-circle me-2"></i>
                        Club Autenticado
                    </h5>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="user_logout">
                        <button type="submit" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-sign-out-alt me-1"></i>Cerrar Sesión
                        </button>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-success">
                    <i class="fas fa-user-check me-2"></i>
                    <strong>Bienvenido:</strong> <?= htmlspecialchars($club_data['nombre']) ?><br>
                    <strong>Usuario:</strong> <?= htmlspecialchars($current_user['username']) ?><br>
                    <strong>Delegado:</strong> <?= htmlspecialchars($club_data['delegado']) ?><br>
                    <strong>Teléfono:</strong> <?= htmlspecialchars($club_data['telefono']) ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($stand_by)): ?>
        <!-- Invitación en Stand-by: banner y formulario bloqueado -->
        <div class="alert alert-warning shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-start">
                <i class="fas fa-hourglass-half fa-3x me-4 text-warning"></i>
                <div class="flex-grow-1">
                    <h4 class="alert-heading">Invitación en espera</h4>
                    <p class="mb-4">Para inscribir a sus atletas y confirmar su participación, debe <strong>Iniciar Sesión</strong> o <strong>Registrarse</strong>.</p>
                    <?php
                    $base_inv = class_exists('AppHelpers') ? rtrim(AppHelpers::getPublicUrl(), '/') : '';
                    if ($base_inv !== ''):
                    ?>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?= htmlspecialchars($base_inv) ?>/login.php?<?= http_build_query(['return_url' => 'invitation/register?token=' . urlencode($token)]) ?>" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                        </a>
                        <a href="<?= htmlspecialchars($base_inv) ?>/user_register.php" class="btn btn-success">
                            <i class="fas fa-user-plus me-2"></i>Registrarse
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Layout: formulario arriba (dos líneas), listado inscritos abajo (una columna) -->
        <?php if (!$inscripciones_abiertas): ?>
        <div class="alert alert-warning text-center py-4 mb-4">
            <i class="fas fa-lock fa-3x mb-3"></i>
            <h4 class="alert-heading">Inscripciones Cerradas</h4>
            <p class="mb-0">El período de inscripción ha finalizado o aún no ha comenzado. Consulte el listado a continuación.</p>
        </div>
        <?php endif; ?>
        <div class="row">
            <?php if ($inscripciones_abiertas && empty($stand_by)): ?>
            <!-- Formulario compacto: una fila, sin título -->
            <div class="col-12">
                <div class="card mb-3">
                    <div class="card-body py-2 px-3">
                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show py-2 mb-2" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show py-2 mb-2" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_message) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        <?php if (!$form_enabled): ?>
                            <div class="alert alert-info py-2 mb-2 small">Para inscribir debe autenticarse primero.</div>
                        <?php endif; ?>

                        <form method="POST" id="registrationForm" class="form-compact-invitation" <?= !$form_enabled ? 'onsubmit="return false;"' : '' ?>>
                            <input type="hidden" name="action" value="register_player">
                            <input type="hidden" name="torneo_id" value="<?= htmlspecialchars($torneo_id) ?>">
                            <input type="hidden" name="club_id" value="<?= htmlspecialchars($club_id) ?>">
                            <div class="d-flex flex-wrap align-items-end gap-2 gx-2">
                                <div class="inv-field">
                                    <label for="nacionalidad" class="form-label inv-label">Nac. <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm" id="nacionalidad" name="nacionalidad" style="width:4.2rem" <?= !$form_enabled ? 'disabled' : '' ?> required title="Nacionalidad">
                                        <option value="">...</option>
                                        <option value="V" <?= ($_POST['nacionalidad'] ?? '') == 'V' ? 'selected' : '' ?>>V</option>
                                        <option value="E" <?= ($_POST['nacionalidad'] ?? '') == 'E' ? 'selected' : '' ?>>E</option>
                                    </select>
                                </div>
                                <div class="inv-field">
                                    <label for="cedula" class="form-label inv-label">Cédula <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" id="cedula" name="cedula" style="width:5.5rem" value="<?= htmlspecialchars($_POST['cedula'] ?? '') ?>" placeholder="12345678" maxlength="8" <?= !$form_enabled ? 'readonly' : '' ?> onblur="searchPersona()" required title="Al salir se buscan datos">
                                </div>
                                <div class="inv-field">
                                    <label for="nombre" class="form-label inv-label">Nombre <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" id="nombre" name="nombre" style="width:9rem" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" <?= !$form_enabled ? 'readonly' : '' ?> required>
                                </div>
                                <div class="inv-field">
                                    <label for="sexo" class="form-label inv-label">Sexo <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm" id="sexo" name="sexo" style="width:3.5rem" <?= !$form_enabled ? 'disabled' : '' ?> required>
                                        <option value="">...</option>
                                        <option value="M" <?= ($_POST['sexo'] ?? '') == 'M' ? 'selected' : '' ?>>M</option>
                                        <option value="F" <?= ($_POST['sexo'] ?? '') == 'F' ? 'selected' : '' ?>>F</option>
                                    </select>
                                </div>
                                <div class="inv-field">
                                    <label for="fechnac" class="form-label inv-label">F. Nac.</label>
                                    <input type="date" class="form-control form-control-sm" id="fechnac" name="fechnac" style="width:7rem" value="<?= htmlspecialchars($_POST['fechnac'] ?? '') ?>" <?= !$form_enabled ? 'readonly' : '' ?>>
                                </div>
                                <div class="inv-field">
                                    <label for="telefono" class="form-label inv-label">Tel. <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control form-control-sm" id="telefono" name="telefono" style="width:7.5rem" value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>" placeholder="0424-1234567" <?= !$form_enabled ? 'readonly' : '' ?> required>
                                </div>
                                <div class="inv-field">
                                    <label for="email" class="form-label inv-label">Email</label>
                                    <input type="email" class="form-control form-control-sm" id="email" name="email" style="width:10rem" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="usuario@gmail.com" <?= !$form_enabled ? 'readonly' : '' ?>>
                                </div>
                                <div class="inv-field ms-1">
                                    <?php if ($form_enabled): ?>
                                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>Inscribir</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearForm()"><i class="fas fa-eraser me-1"></i>Limpiar</button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-secondary btn-sm" disabled><i class="fas fa-lock me-1"></i>Autenticarse</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Una columna: listado de inscritos -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-check-circle me-2"></i>
                            Jugadores Inscritos
                            <span class="badge bg-light text-dark ms-2"><?= count($existing_registrations) ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($existing_registrations)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users text-muted fs-1 mb-3"></i>
                                <h6 class="text-muted">No hay jugadores inscritos aún</h6>
                                <p class="text-muted">Los jugadores inscritos aparecerán aquí</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Id usuario</th>
                                            <th>Cédula</th>
                                            <th>Nombre</th>
                                            <th>Sexo</th>
                                            <th>Teléfono</th>
                                            <th>Email</th>
                                            <?php if ($inscripciones_abiertas): ?><th>Acciones</th><?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($existing_registrations as $registration):
                                            $email_display = !empty(trim($registration['email'] ?? '')) ? $registration['email'] : ((string)($registration['username'] ?? '') . '@gmail.com');
                                        ?>
                                            <tr>
                                                <td><small><?= (int)$registration['id_usuario'] ?></small></td>
                                                <td><small><?= htmlspecialchars($registration['cedula']) ?></small></td>
                                                <td><small><?= htmlspecialchars($registration['nombre']) ?></small></td>
                                                <td><span class="badge bg-info badge-sm"><?= htmlspecialchars($registration['sexo']) ?></span></td>
                                                <td><small><?= htmlspecialchars($registration['celular'] ?: '-') ?></small></td>
                                                <td><small><?= htmlspecialchars($email_display) ?></small></td>
                                                <?php if ($inscripciones_abiertas): ?>
                                                <td>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('¿Retirar a este jugador del torneo?');">
                                                        <input type="hidden" name="action" value="retirar">
                                                        <input type="hidden" name="id_inscripcion" value="<?= (int)$registration['id'] ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-user-minus me-1"></i>Retirar</button>
                                                    </form>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
        </div>
    </div>
</div>

<style>
    /* Estilos adicionales solo si se necesitan (badge-sm, table-sm ya en head) */
</style>

<script>
    var API_BASE = '<?= (isset($base) && $base !== "") ? rtrim($base, "/") . "/api" : "" ?>';
    if (!API_BASE) API_BASE = (window.location.pathname.replace(/\/invitation\/register.*$/, "") || "/") + "api";
    
    function togglePasswordVisibility() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('passwordToggleIcon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.className = 'fas fa-eye-slash';
        } else {
            passwordInput.type = 'password';
            toggleIcon.className = 'fas fa-eye';
        }
    }
    
    function clearForm() {
        if (confirm('¿Estás seguro de que deseas limpiar todos los campos del formulario?')) {
            document.getElementById('registrationForm').reset();
        }
    }
    
    // Función para mostrar mensaje cuando se intenta enviar sin autenticación
    function showAuthRequiredMessage() {
        alert('Debe autenticarse primero para poder inscribir jugadores.');
    }
    
    // Validar solo números en cédula
    document.getElementById('cedula').addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
    
    // Validar formato de teléfono
    document.getElementById('telefono').addEventListener('input', function() {
        let value = this.value.replace(/[^0-9-]/g, '');
        // Formato: 0424-1234567
        if (value.length > 4 && value.indexOf('-') === -1) {
            value = value.substring(0, 4) + '-' + value.substring(4);
        }
        this.value = value;
    });
    
    // Función para buscar persona por cédula
    async function searchPersona() {
        const cedula = document.getElementById('cedula').value.trim();
        const nacionalidad = document.getElementById('nacionalidad').value;
        
        if (!cedula || !nacionalidad) {
            return;
        }
        
        // Construir ID de usuario (nacionalidad + cédula)
        const idusuario = nacionalidad + cedula;
        
        try {
            // Mostrar indicador de carga
            showLoadingIndicator();
            
            // Buscar en la base de datos externa
            const response = await fetch(`${API_BASE}/search_persona.php?cedula=${encodeURIComponent(cedula)}&nacionalidad=${encodeURIComponent(nacionalidad)}`);
            const result = await response.json();
            
            if ((result.encontrado || result.success) && (result.persona || result.data)) {
                // Llenar campos automáticamente
                var persona = result.persona || result.data;
                document.getElementById('nombre').value = (persona && persona.nombre) || '';
                document.getElementById('sexo').value = (persona && (String(persona.sexo || '').substring(0, 1).toUpperCase())) || '';
                document.getElementById('fechnac').value = (persona && persona.fechnac) || '';
                document.getElementById('telefono').value = (persona && (persona.celular || persona.telefono)) || '';
                var emailEl = document.getElementById('email');
                if (emailEl) emailEl.value = (persona && persona.email) || '';
                
                // Verificar si ya existe en el sistema
                await checkExistingCedula(cedula);
                
            } else {
                showMessage('No se encontraron datos para esta cédula', 'info');
            }
            
        } catch (error) {
            console.error('Error en la búsqueda:', error);
            showMessage('Error al buscar datos de la cédula', 'danger');
        } finally {
            hideLoadingIndicator();
        }
    }
    
    // Funciones auxiliares para mostrar/ocultar indicadores y mensajes
    function showLoadingIndicator() {
        // Crear o mostrar indicador de carga
        let indicator = document.getElementById('loadingIndicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'loadingIndicator';
            indicator.className = 'alert alert-info';
            indicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando datos...';
            document.querySelector('.container').insertBefore(indicator, document.querySelector('.container').firstChild);
        }
        indicator.style.display = 'block';
    }
    
    function hideLoadingIndicator() {
        const indicator = document.getElementById('loadingIndicator');
        if (indicator) {
            indicator.style.display = 'none';
        }
    }
    
    function showMessage(message, type) {
        // Crear o actualizar mensaje
        let messageDiv = document.getElementById('messageDiv');
        if (!messageDiv) {
            messageDiv = document.createElement('div');
            messageDiv.id = 'messageDiv';
            messageDiv.className = 'alert';
            document.querySelector('.container').insertBefore(messageDiv, document.querySelector('.container').firstChild);
        }
        messageDiv.className = `alert alert-${type}`;
        messageDiv.textContent = message;
        messageDiv.style.display = 'block';
        
        // Ocultar mensaje después de 5 segundos
        setTimeout(() => {
            messageDiv.style.display = 'none';
        }, 5000);
    }
    
    // Función para verificar si la cédula ya existe
    async function checkExistingCedula(cedula) {
        try {
            const response = await fetch(`${API_BASE}/check_cedula.php?cedula=${encodeURIComponent(cedula)}&torneo=<?= $torneo_id ?>`);
            const result = await response.json();
            
            if (result.success && result.exists) {
                showMessage(`Esta cédula ya está inscrita en este torneo (${result.data.nombre})`, 'warning');
                
                // Limpiar campos para permitir nueva búsqueda
                clearFormFields();
            }
        } catch (error) {
            console.error('Error verificando cédula:', error);
        }
    }
    
    // Función para limpiar campos del formulario
    function clearFormFields() {
        document.getElementById('cedula').value = '';
        document.getElementById('nombre').value = '';
        document.getElementById('sexo').value = '';
        document.getElementById('fechnac').value = '';
        document.getElementById('cedula').focus();
    }
</script>
</body>
</html>
