<?php
require_once __DIR__ . '/../lib/image_helper.php';
require_once __DIR__ . '/../public/simple_image_config.php';

$token = $_GET['token'] ?? '';
$torneo_id = $_GET['torneo'] ?? '';
$club_id = $_GET['club'] ?? '';

$error_message = '';
$success_message = '';
$invitation_data = null;
$tournament_data = null;
$club_data = null;
$organizer_club_data = null;

// Validar invitaci�n
if (empty($torneo_id) || empty($club_id)) {
    $error_message = "Par�metros de acceso inv�lidos";
} else {
    try {
        // Verificar invitaci�n v�lida
        $stmt = DB::pdo()->prepare("
            SELECT i.*, t.nombre as tournament_name, t.fechator, t.clase, t.modalidad, t.club_responsable,
                   c.nombre as club_name, c.direccion, c.delegado, c.telefono, c.email, c.logo as club_logo
            FROM invitations i 
            LEFT JOIN tournaments t ON i.torneo_id = t.id 
            LEFT JOIN clubes c ON i.club_id = c.id 
            WHERE i.torneo_id = ? AND i.club_id = ? AND i.estado = 0
        ");
        $stmt->execute([$torneo_id, $club_id]);
        $invitation_data = $stmt->fetch();
        
        if (!$invitation_data) {
            $error_message = "Invitaci�n no v�lida";
        } else {
            // Verificar fechas de acceso
            $now = new DateTime();
            $start_date = new DateTime($invitation_data['acceso1']);
            $end_date = new DateTime($invitation_data['acceso2']);
            
            if ($now < $start_date) {
                $error_message = "El per�odo de inscripci�n a�n no ha comenzado";
            } elseif ($now > $end_date) {
                $error_message = "El per�odo de inscripci�n ha expirado";
            } else {
                // Obtener datos del club organizador
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
            }
        }
    } catch (Exception $e) {
        $error_message = "Error al validar invitaci�n: " . $e->getMessage();
    }
}


// Procesar formulario de inscripci�n de jugador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_player') {
    // Verificar si el usuario est� autenticado
    $current_user = Auth::user();
    
    // Verificar si el usuario es administrador general o de torneo
    $is_admin_general = $current_user && $current_user['role'] === 'admin_general';
    $is_admin_torneo = $current_user && $current_user['role'] === 'admin_torneo';
    $is_admin_club = $current_user && $current_user['role'] === 'admin_club';
    
    // Permitir procesamiento si:
    // 1. Es admin_general (puede inscribir sin autenticaci�n del club)
    // 2. Es admin_torneo (puede inscribir sin autenticaci�n del club)
    // 3. Es admin_club autenticado
    if ($is_admin_general || $is_admin_torneo || $is_admin_club) {
        try {
            $cedula = $_POST['cedula'] ?? '';
            $nombre = $_POST['nombre'] ?? '';
            $sexo = $_POST['sexo'] ?? '';
            $telefono = $_POST['telefono'] ?? '';
            $email = $_POST['email'] ?? '';
            
            if (empty($cedula) || empty($nombre) || empty($sexo) || empty($telefono)) {
                throw new Exception('Los campos c�dula, nombre, sexo y tel�fono son requeridos');
            }
            
            // Verificar si ya existe una inscripci�n para esta c�dula en este torneo
            $stmt = DB::pdo()->prepare("
                SELECT id FROM inscripciones 
                WHERE cedula = ? AND torneo_id = ?
            ");
            $stmt->execute([$cedula, $torneo_id]);
            
            if ($stmt->fetch()) {
                throw new Exception('Ya existe una inscripci�n para esta c�dula en este torneo');
            }
            
            // Insertar inscripci�n
            $stmt = DB::pdo()->prepare("
                INSERT INTO inscripciones (
                    torneo_id, cedula, nombre, sexo, celular, email, club_id, 
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $torneo_id, $cedula, $nombre, $sexo, $telefono, $email, $club_id
            ]);
            
            $success_message = "Jugador inscrito exitosamente";
            
            // Limpiar formulario
            $_POST = [];
            
        } catch (Exception $e) {
            $error_message = "Error al inscribir jugador: " . $e->getMessage();
        }
    } else {
        // Determinar el mensaje de error seg�n el rol del usuario
        if ($current_user) {
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

// Verificar si el usuario est� autenticado
$current_user = Auth::user();
$is_admin_general = $current_user && $current_user['role'] === 'admin_general';
$is_admin_torneo = $current_user && $current_user['role'] === 'admin_torneo';
$is_admin_club = $current_user && $current_user['role'] === 'admin_club';

// Determinar si el formulario debe estar habilitado
$form_enabled = $is_admin_general || $is_admin_torneo || $is_admin_club;

// Obtener inscripciones existentes del club para este torneo
$existing_registrations = [];
if ($invitation_data && !$error_message && $form_enabled) {
    try {
        $stmt = DB::pdo()->prepare("
            SELECT cedula, nombre, sexo, celular, email, created_at
            FROM inscripciones 
            WHERE torneo_id = ? AND club_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$torneo_id, $club_id]);
        $existing_registrations = $stmt->fetchAll();
    } catch (Exception $e) {
        // Error al obtener inscripciones existentes
    }
}
?>

<div class="fade-in">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">
                <i class="fas fa-user-plus me-2"></i>
                Registro de Jugadores
            </h1>
            <p class="text-muted mb-0">Sistema de inscripci�n para clubes invitados</p>
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
                <p class="text-muted">Por favor, verifica que tienes acceso v�lido a esta p�gina.</p>
                <?php if ($is_admin_general): ?>
                <a href="index.php?page=invitations" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Volver a Invitaciones
                </a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Header con Logos y T�tulo -->
        <div class="card mb-4">
            <div class="card-body text-center py-4">
                <div class="row align-items-center">
                    <!-- Logo del club responsable (izquierda) -->
                    <div class="col-md-3">
                        <div class="d-flex align-items-center justify-content-center" style="height: 120px; background: #f8f9fa; border-radius: 10px; border: 2px dashed #dee2e6;">
                           <?= displayClubLogoInvitation($organizer_club_data, 'organizador') ?>
                        </div>
                    </div>
                    
                    <!-- Informaci�n central -->
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
                
                <!-- Per�odo de inscripci�n -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="alert alert-info text-center">
                            <i class="fas fa-clock me-2"></i>
                            <strong>Per�odo de Inscripci�n:</strong> 
                            Desde <?= date('d/m/Y H:i', strtotime($invitation_data['acceso1'])) ?> 
                            hasta <?= date('d/m/Y H:i', strtotime($invitation_data['acceso2'])) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel de Estado de Autenticaci�n -->
        <?php if ($is_admin_general || $is_admin_torneo): ?>
        <!-- Panel de Administrador -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-user-shield me-2"></i>
                    Acceso de Administrador
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-primary">
                    <i class="fas fa-crown me-2"></i>
                    <strong>Administrador:</strong> <?= htmlspecialchars($current_user['username']) ?><br>
                    <strong>Rol:</strong> <?= htmlspecialchars($current_user['role']) ?><br>
                    <strong>Puede inscribir jugadores directamente sin autenticaci�n del club.</strong>
                </div>
            </div>
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
                            <i class="fas fa-sign-out-alt me-1"></i>Cerrar Sesi�n
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
                    <strong>Tel�fono:</strong> <?= htmlspecialchars($club_data['telefono']) ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Layout de dos columnas: Formulario izquierda, Listado derecha -->
        <div class="row">
            <!-- Columna izquierda: Formulario de Inscripci�n -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user-plus me-2"></i>
                            Formulario de Inscripci�n de Jugadores
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_message) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!$form_enabled): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Formulario visible:</strong> Puede ver todos los campos del formulario, pero para inscribir jugadores debe autenticarse primero.
                            </div>
                        <?php endif; ?>

                        <form method="POST" id="registrationForm" <?= !$form_enabled ? 'onsubmit="return false;"' : '' ?>>
                            <input type="hidden" name="action" value="register_player">
                            <input type="hidden" name="torneo_id" value="<?= htmlspecialchars($torneo_id) ?>">
                            <input type="hidden" name="club_id" value="<?= htmlspecialchars($club_id) ?>">
                            
                            <div class="row g-3">
                                <!-- Nacionalidad -->
                                <div class="col-12">
                                    <label for="nacionalidad" class="form-label">Nacionalidad <span class="text-danger">*</span></label>
                                    <select class="form-select" id="nacionalidad" name="nacionalidad" <?= !$form_enabled ? 'disabled' : '' ?> required>
                                        <option value="">Seleccionar nacionalidad...</option>
                                        <option value="V" <?= ($_POST['nacionalidad'] ?? '') == 'V' ? 'selected' : '' ?>>Venezolano (V)</option>
                                        <option value="E" <?= ($_POST['nacionalidad'] ?? '') == 'E' ? 'selected' : '' ?>>Extranjero (E)</option>
                                    </select>
                                </div>
                                
                                <!-- C�dula -->
                                <div class="col-12">
                                    <label for="cedula" class="form-label">C�dula <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="cedula" 
                                           name="cedula" 
                                           value="<?= htmlspecialchars($_POST['cedula'] ?? '') ?>"
                                           placeholder="12345678"
                                           maxlength="8"
                                           <?= !$form_enabled ? 'readonly' : '' ?>
                                           onblur="searchPersona()"
                                           required>
                                    <small class="text-muted">Al salir de este campo se buscar�n autom�ticamente los datos</small>
                                </div>
                                
                                <!-- Nombre -->
                                <div class="col-12">
                                    <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="nombre" 
                                           name="nombre" 
                                           value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>"
                                           <?= !$form_enabled ? 'readonly' : '' ?>
                                           required>
                                </div>
                                
                                <!-- Sexo -->
                                <div class="col-12">
                                    <label for="sexo" class="form-label">Sexo <span class="text-danger">*</span></label>
                                    <select class="form-select" id="sexo" name="sexo" <?= !$form_enabled ? 'disabled' : '' ?> required>
                                        <option value="">Seleccionar sexo...</option>
                                        <option value="M" <?= ($_POST['sexo'] ?? '') == 'M' ? 'selected' : '' ?>>Masculino</option>
                                        <option value="F" <?= ($_POST['sexo'] ?? '') == 'F' ? 'selected' : '' ?>>Femenino</option>
                                    </select>
                                </div>
                                
                                <!-- Fecha de Nacimiento -->
                                <div class="col-12">
                                    <label for="fechnac" class="form-label">Fecha de Nacimiento</label>
                                    <input type="date" 
                                           class="form-control" 
                                           id="fechnac" 
                                           name="fechnac" 
                                           value="<?= htmlspecialchars($_POST['fechnac'] ?? '') ?>"
                                           <?= !$form_enabled ? 'readonly' : '' ?>>
                                </div>
                                
                                <!-- Tel�fono -->
                                <div class="col-12">
                                    <label for="telefono" class="form-label">N�mero de Tel�fono <span class="text-danger">*</span></label>
                                    <input type="tel" 
                                           class="form-control" 
                                           id="telefono" 
                                           name="telefono" 
                                           value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>"
                                           placeholder="0424-1234567"
                                           <?= !$form_enabled ? 'readonly' : '' ?>
                                           required>
                                </div>
                                
                                <!-- Email -->
                                <div class="col-12">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" 
                                           class="form-control" 
                                           id="email" 
                                           name="email" 
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                           placeholder="ejemplo@correo.com"
                                           <?= !$form_enabled ? 'readonly' : '' ?>>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <?php if ($form_enabled): ?>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-save me-2"></i>Inscribir Jugador
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary w-100 mt-2" onclick="clearForm()">
                                        <i class="fas fa-eraser me-2"></i>Limpiar Formulario
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-secondary w-100" disabled>
                                        <i class="fas fa-lock me-2"></i>Autent�quese para Inscribir
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary w-100 mt-2" onclick="clearForm()">
                                        <i class="fas fa-eraser me-2"></i>Limpiar Formulario
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Columna derecha: Listado de Inscritos -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            Jugadores Inscritos (<?= count($existing_registrations) ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($existing_registrations)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users text-muted fs-1 mb-3"></i>
                                <h6 class="text-muted">No hay jugadores inscritos a�n</h6>
                                <p class="text-muted">Los jugadores inscritos aparecer�n aqu�</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>C�dula</th>
                                            <th>Nombre</th>
                                            <th>Sexo</th>
                                            <th>Tel�fono</th>
                                            <th>Email</th>
                                            <th>Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($existing_registrations as $registration): ?>
                                            <tr>
                                                <td><small><?= htmlspecialchars($registration['cedula']) ?></small></td>
                                                <td><small><?= htmlspecialchars($registration['nombre']) ?></small></td>
                                                <td>
                                                    <span class="badge bg-info badge-sm"><?= htmlspecialchars($registration['sexo']) ?></span>
                                                </td>
                                                <td><small><?= htmlspecialchars($registration['celular']) ?></small></td>
                                                <td><small><?= htmlspecialchars($registration['email'] ?: '-') ?></small></td>
                                                <td><small><?= date('d/m/Y', strtotime($registration['created_at'])) ?></small></td>
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

<style>
    .badge-sm {
        font-size: 0.7em;
    }
    
    .table-sm th,
    .table-sm td {
        padding: 0.3rem;
    }
    
    .card {
        height: fit-content;
    }
    
    .form-control:readonly {
        background-color: #f8f9fa;
        opacity: 0.7;
    }
    
    .form-select:disabled {
        background-color: #f8f9fa;
        opacity: 0.7;
    }
    
    @media (max-width: 768px) {
        .col-md-6 {
            margin-bottom: 1rem;
        }
    }
</style>

<script>
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
        if (confirm('�Est�s seguro de que deseas limpiar todos los campos del formulario?')) {
            document.getElementById('registrationForm').reset();
        }
    }
    
    // Funci�n para mostrar mensaje cuando se intenta enviar sin autenticaci�n
    function showAuthRequiredMessage() {
        alert('Debe autenticarse primero para poder inscribir jugadores.');
    }
    
    // Validar solo n�meros en c�dula
    document.getElementById('cedula').addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
    
    // Validar formato de tel�fono
    document.getElementById('telefono').addEventListener('input', function() {
        let value = this.value.replace(/[^0-9-]/g, '');
        // Formato: 0424-1234567
        if (value.length > 4 && value.indexOf('-') === -1) {
            value = value.substring(0, 4) + '-' + value.substring(4);
        }
        this.value = value;
    });
    
    // Funci�n para buscar persona por c�dula
    async function searchPersona() {
        const cedula = document.getElementById('cedula').value.trim();
        const nacionalidad = document.getElementById('nacionalidad').value;
        
        if (!cedula || !nacionalidad) {
            return;
        }
        
        // Construir ID de usuario (nacionalidad + c�dula)
        const idusuario = nacionalidad + cedula;
        
        try {
            // Mostrar indicador de carga
            showLoadingIndicator();
            
            // Buscar en la base de datos externa
            const response = await fetch(`../../public/api/search_persona.php?idusuario=${encodeURIComponent(idusuario)}`);
            const result = await response.json();
            
            if (result.success && result.data) {
                // Llenar campos autom�ticamente
                document.getElementById('nombre').value = result.data.nombre || '';
                document.getElementById('sexo').value = result.data.sexo || '';
                document.getElementById('fechnac').value = result.data.fechnac || '';
                
                // Verificar si ya existe en el sistema
                await checkExistingCedula(cedula);
                
            } else {
                showMessage('No se encontraron datos para esta c�dula', 'info');
            }
            
        } catch (error) {
            console.error('Error en la b�squeda:', error);
            showMessage('Error al buscar datos de la c�dula', 'danger');
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
        
        // Ocultar mensaje despu�s de 5 segundos
        setTimeout(() => {
            messageDiv.style.display = 'none';
        }, 5000);
    }
    
    // Funci�n para verificar si la c�dula ya existe
    async function checkExistingCedula(cedula) {
        try {
            const response = await fetch(`../../public/api/check_cedula.php?cedula=${encodeURIComponent(cedula)}&torneo=<?= $torneo_id ?>`);
            const result = await response.json();
            
            if (result.success && result.exists) {
                showMessage(`Esta c�dula ya est� inscrita en este torneo (${result.data.nombre})`, 'warning');
                
                // Limpiar campos para permitir nueva b�squeda
                clearFormFields();
            }
        } catch (error) {
            console.error('Error verificando c�dula:', error);
        }
    }
    
    // Funci�n para limpiar campos del formulario
    function clearFormFields() {
        document.getElementById('cedula').value = '';
        document.getElementById('nombre').value = '';
        document.getElementById('sexo').value = '';
        document.getElementById('fechnac').value = '';
        document.getElementById('cedula').focus();
    }
</script>
