<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../lib/Pagination.php';

// Verificar permisos
Auth::requireRole(['admin_general', 'admin_torneo']);

// Obtener informaci�n del usuario actual
$current_user = Auth::user();
$user_role = $current_user['role'] ?? '';
$user_club_id = Auth::getUserClubId();
$is_admin_general = Auth::isAdminGeneral();
$is_admin_torneo = Auth::isAdminTorneo();

// Validar que admin_torneo tenga club asignado
if ($is_admin_torneo && !$user_club_id) {
    $error_message = "Error: Su usuario no tiene un club asignado. Contacte al administrador general.";
}

// Obtener datos para la vista
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$success_message = $_GET['success'] ?? null;
$error_message = $_GET['error'] ?? $error_message ?? null;

// Procesar eliminaci�n si se solicita
if ($action === 'delete' && $id) {
    try {
        $invitation_id = (int)$id;
        
        // Obtener datos de la invitaci�n antes de eliminarla
        $stmt = DB::pdo()->prepare("
            SELECT i.*, t.nombre as torneo_nombre, t.club_responsable, c.nombre as club_nombre
            FROM invitations i
            INNER JOIN tournaments t ON i.torneo_id = t.id
            INNER JOIN clubes c ON i.club_id = c.id
            WHERE i.id = ?
        ");
        $stmt->execute([$invitation_id]);
        $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invitation) {
            throw new Exception('Invitaci�n no encontrada');
        }
        
        // Verificar permisos y que el torneo no haya pasado
        if (!Auth::canModifyTournament((int)$invitation['torneo_id'])) {
            throw new Exception('No tiene permisos para eliminar invitaciones de este torneo. Solo puede eliminar invitaciones de torneos futuros de su club.');
        }
        
        // Eliminar la invitaci�n
        $stmt = DB::pdo()->prepare("DELETE FROM invitations WHERE id = ?");
        $result = $stmt->execute([$invitation_id]);
        
        if ($result && $stmt->rowCount() > 0) {
            $filter_param = isset($_GET['filter_torneo']) ? '&filter_torneo=' . (int)$_GET['filter_torneo'] : '';
            header('Location: index.php?page=invitations' . $filter_param . '&success=' . urlencode('Invitaci�n eliminada exitosamente'));
        } else {
            throw new Exception('No se pudo eliminar la invitaci�n');
        }
    } catch (Exception $e) {
        $filter_param = isset($_GET['filter_torneo']) ? '&filter_torneo=' . (int)$_GET['filter_torneo'] : '';
        header('Location: index.php?page=invitations' . $filter_param . '&error=' . urlencode('Error al eliminar: ' . $e->getMessage()));
    }
    exit;
}

// Obtener datos para edici�n
$invitation = null;
if ($action === 'edit' && $id) {
    try {
        $stmt = DB::pdo()->prepare("
            SELECT i.*, t.nombre as torneo_nombre, t.club_responsable, c.nombre as club_nombre
            FROM invitations i
            INNER JOIN tournaments t ON i.torneo_id = t.id
            INNER JOIN clubes c ON i.club_id = c.id
            WHERE i.id = ?
        ");
        $stmt->execute([(int)$id]);
        $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invitation) {
            $error_message = "Invitaci�n no encontrada";
            $action = 'list';
        } else {
            // Verificar permisos para editar
            if (!Auth::canModifyTournament((int)$invitation['torneo_id'])) {
                header('Location: index.php?page=invitations&error=' . urlencode('No tiene permisos para editar invitaciones de este torneo. Solo puede editar invitaciones de torneos futuros de su club.'));
                exit;
            }
        }
    } catch (Exception $e) {
        $error_message = "Error al cargar la invitaci�n: " . $e->getMessage();
        $action = 'list';
    }
}

// Obtener listas para formularios
$tournaments_list = [];
$clubs_list = [];
if (in_array($action, ['new', 'edit'])) {
    try {
        // Filtrar torneos seg�n el rol del usuario
        $tournament_filter = Auth::getTournamentFilterForRole('');
        $where_clause = "WHERE estatus = 1";
        
        if (!empty($tournament_filter['where'])) {
            $where_clause .= " AND " . $tournament_filter['where'];
        }
        
        $stmt = DB::pdo()->prepare("
            SELECT id, nombre, fechator,
                   CASE WHEN fechator < CURDATE() THEN 1 ELSE 0 END as pasado
            FROM tournaments 
            {$where_clause}
            ORDER BY fechator DESC
        ");
        $stmt->execute($tournament_filter['params']);
        $tournaments_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = DB::pdo()->query("SELECT id, nombre FROM clubes WHERE estatus = 1 ORDER BY nombre ASC");
        $clubs_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error_message = "Error al cargar datos: " . $e->getMessage();
    }
}

// Obtener lista para vista de lista con paginaci�n Y FILTRO DE TORNEO
$invitations_list = [];
$pagination = null;
$filter_torneo = $_GET['filter_torneo'] ?? '';
$stats = ['total' => 0, 'activas' => 0, 'expiradas' => 0, 'canceladas' => 0];

// Validar que el admin_torneo solo filtre por torneos de su club
if (!empty($filter_torneo)) {
    if (!Auth::canAccessTournament((int)$filter_torneo)) {
        header('Location: index.php?page=invitations&error=' . urlencode('No tiene permisos para acceder a este torneo'));
        exit;
    }
}

// Obtener lista de torneos para el filtro
$tournaments_filter = [];
try {
    $tournament_filter = Auth::getTournamentFilterForRole('');
    $where_clause = "";
    
    if (!empty($tournament_filter['where'])) {
        $where_clause = "WHERE " . $tournament_filter['where'];
    }
    
    $stmt = DB::pdo()->prepare("
        SELECT id, nombre, fechator,
               CASE WHEN fechator < CURDATE() THEN 1 ELSE 0 END as pasado
        FROM tournaments 
        {$where_clause}
        ORDER BY fechator DESC
    ");
    $stmt->execute($tournament_filter['params']);
    $tournaments_filter = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Silencio
}

if ($action === 'list' && !empty($filter_torneo)) {
    try {
        // Configurar paginaci�n
        $current_page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
        $per_page = isset($_GET['per_page']) ? max(10, min(100, (int)$_GET['per_page'])) : 25;
        
        // Construir query con filtro de torneo
        $where = "WHERE i.torneo_id = ?";
        $params = [(int)$filter_torneo];
        
        // Contar total de registros con filtro
        $stmt = DB::pdo()->prepare("SELECT COUNT(*) FROM invitations i $where");
        $stmt->execute($params);
        $total_records = (int)$stmt->fetchColumn();
        
        // Crear objeto de paginaci�n
        $pagination = new Pagination($total_records, $current_page, $per_page);
        
        // Obtener registros de la p�gina actual
        $stmt = DB::pdo()->prepare("
            SELECT 
                i.*,
                t.nombre as torneo_nombre,
                t.fechator as torneo_fecha,
                c.nombre as club_nombre,
                c.delegado as club_delegado,
                c.telefono as club_telefono
            FROM invitations i
            INNER JOIN tournaments t ON i.torneo_id = t.id
            INNER JOIN clubes c ON i.club_id = c.id
            $where
            ORDER BY i.fecha_creacion DESC
            LIMIT {$pagination->getLimit()} OFFSET {$pagination->getOffset()}
        ");
        $stmt->execute($params);
        $invitations_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Estad�sticas del torneo filtrado
        $stmt = DB::pdo()->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'activa' THEN 1 ELSE 0 END) as activas,
                SUM(CASE WHEN estado = 'expirada' THEN 1 ELSE 0 END) as expiradas,
                SUM(CASE WHEN estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas
            FROM invitations i
            $where
        ");
        $stmt->execute($params);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error_message = "Error al cargar las invitaciones: " . $e->getMessage();
        $stats = ['total' => 0, 'activas' => 0, 'expiradas' => 0, 'canceladas' => 0];
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="fas fa-envelope me-2"></i>Invitaciones
                    </h1>
                    <p class="text-muted mb-0">Gesti�n de invitaciones a torneos</p>
                </div>
                <div>
                    <?php if ($action === 'list'): ?>
                        <a href="index.php?page=invitations&action=new" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Nueva Invitaci�n
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Alertas -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($is_admin_torneo && $user_club_id): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Modo Administrador de Torneo:</strong> Solo puede gestionar invitaciones de torneos asignados a su club (ID: <?= $user_club_id ?>).
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($action === 'list'): ?>
    <!-- Panel de Filtros -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-filter me-2"></i>Filtrar Invitaciones por Torneo
            </h5>
        </div>
        <div class="card-body">
            <form method="GET" action="index.php" id="filterForm">
                <input type="hidden" name="page" value="invitations">
                
                <div class="row g-3">
                    <div class="col-md-10">
                        <label class="form-label"><i class="fas fa-trophy me-1"></i>Seleccione un Torneo *</label>
                        <select name="filter_torneo" class="form-select form-select-lg" id="filterTorneo" onchange="this.form.submit()">
                            <option value="">-- Seleccione un torneo para ver las invitaciones --</option>
                            <?php foreach ($tournaments_filter as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= $filter_torneo == $t['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['nombre']) ?> - <?= date('d/m/Y', strtotime($t['fechator'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <?php if (!empty($filter_torneo)): ?>
                            <a href="index.php?page=invitations" class="btn btn-secondary w-100">
                                <i class="fas fa-times me-2"></i>Limpiar
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
            
            <?php if (empty($filter_torneo)): ?>
                <div class="alert alert-info mt-3 mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Por favor seleccione un torneo</strong> para ver las invitaciones correspondientes.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!empty($filter_torneo)): ?>
    <!-- Estad�sticas -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h2 class="mb-0"><?= $stats['total'] ?></h2>
                    <p class="mb-0">Total Invitaciones</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h2 class="mb-0"><?= $stats['activas'] ?></h2>
                    <p class="mb-0">Activas</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h2 class="mb-0"><?= $stats['expiradas'] ?></h2>
                    <p class="mb-0">Expiradas</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h2 class="mb-0"><?= $stats['canceladas'] ?></h2>
                    <p class="mb-0">Canceladas</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Vista de Lista -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($invitations_list)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>No hay invitaciones registradas.
                    <a href="index.php?page=invitations&action=new" class="alert-link">Crear la primera invitaci�n</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Torneo</th>
                                <th>Club</th>
                                <th>Delegado</th>
                                <th>Vigencia</th>
                                <th>Token</th>
                                <th>Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invitations_list as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)$item['id']) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($item['torneo_nombre']) ?></strong><br>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?= date('d/m/Y', strtotime($item['torneo_fecha'])) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($item['club_nombre']) ?></strong>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($item['club_delegado'] ?? 'N/A') ?><br>
                                        <small class="text-muted"><?= htmlspecialchars($item['club_telefono'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        <small>
                                            <i class="fas fa-calendar-check text-success"></i> 
                                            <?= date('d/m/Y', strtotime($item['acceso1'])) ?>
                                            <br>
                                            <i class="fas fa-calendar-times text-danger"></i> 
                                            <?= date('d/m/Y', strtotime($item['acceso2'])) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <code class="small"><?= substr($item['token'], 0, 12) ?>...</code>
                                        <button class="btn btn-sm btn-outline-secondary" 
                                                onclick="copyToken('<?= htmlspecialchars($item['token']) ?>')" 
                                                title="Copiar token completo">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </td>
                                    <td>
                                        <?php
                                        $badge_color = 'secondary';
                                        if ($item['estado'] === 'activa') $badge_color = 'success';
                                        elseif ($item['estado'] === 'expirada') $badge_color = 'warning';
                                        elseif ($item['estado'] === 'cancelada') $badge_color = 'danger';
                                        ?>
                                        <span class="badge bg-<?= $badge_color ?>">
                                            <?= htmlspecialchars(ucfirst($item['estado'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <a href="../modules/invitations/inscripciones/login.php?token=<?= htmlspecialchars($item['token']) ?>" 
                                               class="btn btn-sm btn-outline-success" title="Acceder como invitado">
                                                <i class="fas fa-sign-in-alt"></i>
                                            </a>
                                            <a href="index.php?page=invitations&action=edit&id=<?= $item['id'] ?>&filter_torneo=<?= $filter_torneo ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-info" 
                                                    title="Copiar Token"
                                                    onclick="copyToken('<?= htmlspecialchars($item['token']) ?>')">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <a href="index.php?page=invitations&action=delete&id=<?= $item['id'] ?>&filter_torneo=<?= $filter_torneo ?>" 
                                               class="btn btn-sm btn-outline-danger" title="Eliminar"
                                               onclick="return confirm('�Est� seguro de eliminar esta invitaci�n para <?= htmlspecialchars($item['club_nombre']) ?>?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginaci�n -->
                <?php if ($pagination): ?>
                    <?= $pagination->render() ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; // Fin del if filter_torneo ?>

<?php elseif ($action === 'new' || $action === 'edit'): ?>
    <!-- Formulario -->
    <div class="card">
        <div class="card-header bg-<?= $action === 'edit' ? 'warning' : 'success' ?> text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-<?= $action === 'edit' ? 'edit' : 'plus-circle' ?> me-2"></i>
                <?= $action === 'edit' ? 'Editar' : 'Nueva' ?> Invitaci�n
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" action="../modules/invitations/<?= $action === 'edit' ? 'edit' : 'create' ?>.php">
                <?= CSRF::input(); ?>
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?= (int)$invitation['id'] ?>">
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="torneo_id" class="form-label">Torneo *</label>
                            <select class="form-select" id="torneo_id" name="torneo_id" required <?= $action === 'edit' ? 'disabled' : '' ?>>
                                <option value="">Seleccionar torneo...</option>
                                <?php foreach ($tournaments_list as $tournament): ?>
                                    <option value="<?= (int)$tournament['id'] ?>" 
                                            data-fecha="<?= $tournament['fechator'] ?>"
                                            <?= ($action === 'edit' && $invitation['torneo_id'] == $tournament['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tournament['nombre']) ?> - <?= date('d/m/Y', strtotime($tournament['fechator'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($action === 'edit'): ?>
                                <input type="hidden" name="torneo_id" value="<?= (int)$invitation['torneo_id'] ?>">
                                <small class="text-muted">El torneo no puede cambiarse al editar</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="club_id" class="form-label">Club *</label>
                            <select class="form-select" id="club_id" name="club_id" required <?= $action === 'edit' ? 'disabled' : '' ?>>
                                <option value="">Seleccionar club...</option>
                                <?php foreach ($clubs_list as $club): ?>
                                    <option value="<?= (int)$club['id'] ?>"
                                            <?= ($action === 'edit' && $invitation['club_id'] == $club['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($club['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($action === 'edit'): ?>
                                <input type="hidden" name="club_id" value="<?= (int)$invitation['club_id'] ?>">
                                <small class="text-muted">El club no puede cambiarse al editar</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="acceso1" class="form-label">Fecha Inicio de Acceso *</label>
                            <input type="date" class="form-control" id="acceso1" name="acceso1" 
                                   value="<?= htmlspecialchars($action === 'edit' ? $invitation['acceso1'] : '') ?>" required>
                            <small class="text-muted">Desde cu�ndo el club puede inscribir jugadores</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="acceso2" class="form-label">Fecha Fin de Acceso *</label>
                            <input type="date" class="form-control" id="acceso2" name="acceso2" 
                                   value="<?= htmlspecialchars($action === 'edit' ? $invitation['acceso2'] : '') ?>" required>
                            <small class="text-muted">Hasta cu�ndo el club puede inscribir jugadores</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="estado" class="form-label">Estado *</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="activa"<?= ($action === 'edit' && $invitation['estado'] === 'activa') ? ' selected' : ' selected' ?>>Activa</option>
                                <option value="expirada"<?= ($action === 'edit' && $invitation['estado'] === 'expirada') ? ' selected' : '' ?>>Expirada</option>
                                <option value="cancelada"<?= ($action === 'edit' && $invitation['estado'] === 'cancelada') ? ' selected' : '' ?>>Cancelada</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Nota importante:</strong> El club invitado podr� registrar jugadores hasta la <strong>fecha del torneo</strong>, 
                    aunque el per�odo de acceso (acceso2) sea posterior.
                </div>
                
                <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                    <?php 
                    $back_url = 'index.php?page=invitations';
                    if ($action === 'edit' && isset($_GET['filter_torneo'])) {
                        $back_url .= '&filter_torneo=' . (int)$_GET['filter_torneo'];
                    }
                    ?>
                    <a href="<?= $back_url ?>" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </a>
                    <button type="submit" class="btn btn-<?= $action === 'edit' ? 'warning' : 'success' ?>">
                        <i class="fas fa-save me-2"></i><?= $action === 'edit' ? 'Actualizar Invitaci�n' : 'Crear Invitaci�n' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
function copyToken(token) {
    navigator.clipboard.writeText(token).then(() => {
        alert('Token copiado al portapapeles');
    });
}

// Auto-calcular fechas basado en el torneo seleccionado
document.getElementById('torneo_id')?.addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const fechaTorneo = selected.dataset.fecha;
    
    if (fechaTorneo) {
        // Calcular acceso1 (7 d�as antes del torneo)
        const fecha = new Date(fechaTorneo);
        const acceso1 = new Date(fecha);
        acceso1.setDate(acceso1.getDate() - 7);
        
        // Calcular acceso2 (1 d�a antes del torneo)
        const acceso2 = new Date(fecha);
        acceso2.setDate(acceso2.getDate() - 1);
        
        document.getElementById('acceso1').value = acceso1.toISOString().split('T')[0];
        document.getElementById('acceso2').value = acceso2.toISOString().split('T')[0];
    }
});
</script>

