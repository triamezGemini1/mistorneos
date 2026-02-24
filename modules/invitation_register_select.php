<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

// Admin general, admin torneo y admin organizaciùn (club) pueden acceder
Auth::requireRole(['admin_general', 'admin_torneo', 'admin_club']);

// Obtener invitaciones activas disponibles (filtradas por rol)
$invitations = [];
try {
    $filter = Auth::getTournamentFilterForRole('t');
    $where = "i.estado IN (0, 1, 'activa', 'vinculado') AND NOW() BETWEEN i.acceso1 AND i.acceso2";
    $params = [];
    if (!empty($filter['where'])) {
        $where .= " AND " . $filter['where'];
        $params = $filter['params'];
    }
    $stmt = DB::pdo()->prepare("
        SELECT i.*, t.nombre as tournament_name, t.fechator, t.club_responsable,
               c.nombre as club_name, c.email as club_email,
               oc.nombre as organizer_name
        FROM " . TABLE_INVITATIONS . " i
        LEFT JOIN tournaments t ON i.torneo_id = t.id 
        LEFT JOIN clubes c ON i.club_id = c.id 
        LEFT JOIN clubes oc ON t.club_responsable = oc.id
        WHERE {$where}
        ORDER BY i.id DESC
    ");
    $stmt->execute($params);
    $invitations = $stmt->fetchAll();
} catch (Exception $e) {
    $error_message = "Error al obtener invitaciones: " . $e->getMessage();
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
            <p class="text-muted mb-0">Seleccione una invitaciùn para acceder al formulario de registro</p>
        </div>
        <div>
            <a href="index.php?page=invitations" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Volver a Invitaciones
            </a>
        </div>
    </div>

    <?php if (empty($invitations)): ?>
        <!-- Sin invitaciones disponibles -->
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-inbox text-muted fs-1 mb-3"></i>
                <h5 class="text-muted">No hay invitaciones activas disponibles</h5>
                <p class="text-muted">No se encontraron invitaciones con perùodo de acceso activo.</p>
                <a href="index.php?page=invitations" class="btn btn-primary">
                    <i class="fas fa-envelope me-2"></i>Gestionar Invitaciones
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Lista de invitaciones disponibles -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Invitaciones Activas (<?= count($invitations) ?>)
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <?php foreach ($invitations as $invitation): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-trophy me-2"></i>
                                        <?= htmlspecialchars($invitation['tournament_name']) ?>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <h6 class="text-primary">Club Invitado:</h6>
                                        <p class="mb-1">
                                            <i class="fas fa-building me-2"></i>
                                            <?= htmlspecialchars($invitation['club_name']) ?>
                                        </p>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <h6 class="text-success">Club Organizador:</h6>
                                        <p class="mb-1">
                                            <i class="fas fa-home me-2"></i>
                                            <?= htmlspecialchars($invitation['organizer_name']) ?>
                                        </p>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <h6 class="text-info">Informaciùn de Acceso:</h6>
                                        <p class="mb-1">
                                            <i class="fas fa-user me-2"></i>
                                            <strong>Usuario:</strong> <?= htmlspecialchars($invitation['usuario']) ?>
                                        </p>
                                        <p class="mb-1">
                                            <i class="fas fa-key me-2"></i>
                                            <strong>Contraseùa:</strong> usuario
                                        </p>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <h6 class="text-warning">Perùodo de Acceso:</h6>
                                        <p class="mb-1">
                                            <i class="fas fa-calendar-start me-2"></i>
                                            <small>Desde: <?= date('d/m/Y H:i', strtotime($invitation['acceso1'])) ?></small>
                                        </p>
                                        <p class="mb-1">
                                            <i class="fas fa-calendar-end me-2"></i>
                                            <small>Hasta: <?= date('d/m/Y H:i', strtotime($invitation['acceso2'])) ?></small>
                                        </p>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <h6 class="text-secondary">Fecha del Torneo:</h6>
                                        <p class="mb-0">
                                            <i class="fas fa-calendar-alt me-2"></i>
                                            <?= date('d/m/Y', strtotime($invitation['fechator'])) ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="card-footer bg-light">
                                    <a href="index.php?page=invitation_register&token=<?= htmlspecialchars($invitation['token']) ?>&torneo=<?= $invitation['torneo_id'] ?>&club=<?= $invitation['club_id'] ?>" 
                                       class="btn btn-primary w-100">
                                        <i class="fas fa-sign-in-alt me-2"></i>Acceder al Formulario
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Informaciùn adicional -->
        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informaciùn del Sistema
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary">ùCùmo funciona?</h6>
                        <ol class="small">
                            <li>Seleccione una invitaciùn de la lista</li>
                            <li>Acceda al formulario con las credenciales del club</li>
                            <li>Complete el formulario de registro de jugadores</li>
                            <li>Los jugadores quedarùn inscritos automùticamente</li>
                        </ol>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-success">Credenciales de Acceso</h6>
                        <ul class="small">
                            <li><strong>Usuario:</strong> Campo "usuario" de la invitaciùn</li>
                            <li><strong>Contraseùa:</strong> "usuario" (para todos los clubes)</li>
                            <li><strong>Perùodo:</strong> Solo durante las fechas de acceso</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>


