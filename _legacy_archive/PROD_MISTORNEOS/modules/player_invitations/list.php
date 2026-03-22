<?php
/**
 * Invitaciones a Jugadores por WhatsApp
 * Solo para admin_club - Invitar jugadores de sus clubes a torneos
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../lib/ClubHelper.php';

Auth::requireRole(['admin_club']);

$pdo = DB::pdo();
$user = Auth::user();
$club_principal_id = $user['club_id'];

// Obtener todos los clubes gestionados
$clubes_gestionados = ClubHelper::getClubesSupervised($club_principal_id);

// Obtener torneos del admin_club
$placeholders = implode(',', array_fill(0, count($clubes_gestionados), '?'));
$stmt = $pdo->prepare("
    SELECT t.*, c.nombre as club_nombre
    FROM tournaments t
    LEFT JOIN clubes c ON t.club_responsable = c.id
    WHERE t.club_responsable IN ($placeholders) AND t.estatus = 1 AND t.fechator >= CURDATE()
    ORDER BY t.fechator ASC
");
$stmt->execute($clubes_gestionados);
$torneos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener jugadores de los clubes gestionados
$stmt = $pdo->prepare("
    SELECT u.id, u.nombre, u.cedula, u.celular, u.email, u.club_id, c.nombre as club_nombre
    FROM usuarios u
    LEFT JOIN clubes c ON u.club_id = c.id
    WHERE u.role = 'usuario' AND u.status = 1 AND u.club_id IN ($placeholders)
    ORDER BY c.nombre, u.nombre ASC
");
$stmt->execute($clubes_gestionados);
$jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar jugadores por club
$jugadores_por_club = [];
foreach ($jugadores as $jugador) {
    $club_id = $jugador['club_id'];
    if (!isset($jugadores_por_club[$club_id])) {
        $jugadores_por_club[$club_id] = [
            'club_nombre' => $jugador['club_nombre'],
            'jugadores' => []
        ];
    }
    $jugadores_por_club[$club_id]['jugadores'][] = $jugador;
}

$torneo_selected = isset($_GET['torneo_id']) ? (int)$_GET['torneo_id'] : null;
$torneo_data = null;

if ($torneo_selected) {
    $stmt = $pdo->prepare("
        SELECT t.*, c.nombre as club_nombre, c.delegado, c.telefono, c.email as club_email
        FROM tournaments t
        LEFT JOIN clubes c ON t.club_responsable = c.id
        WHERE t.id = ? AND t.club_responsable IN ($placeholders)
    ");
    $stmt->execute(array_merge([$torneo_selected], $clubes_gestionados));
    $torneo_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

$modalidades = [1 => 'Individual', 2 => 'Parejas', 3 => 'Equipos'];
$clases = [1 => 'Abierto', 2 => 'Por Categorías'];
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>
            <i class="fab fa-whatsapp me-2"></i>Invitaciones a Jugadores
        </h2>
    </div>
    
    <?php if (!$torneo_selected || !$torneo_data): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>
        Debes acceder a esta página desde un torneo. Selecciona un torneo en el menú lateral y luego haz clic en "Invitaciones a Jugadores".
    </div>
    <?php elseif ($torneo_data): ?>
    <!-- Información del Torneo Seleccionado -->
    <div class="card mb-4 border-success">
        <div class="card-header bg-success text-white">
            <i class="fas fa-info-circle me-2"></i>Información del Torneo
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Torneo:</strong> <?= htmlspecialchars($torneo_data['nombre']) ?></p>
                    <p><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($torneo_data['fechator'])) ?></p>
                    <p><strong>Lugar:</strong> <?= htmlspecialchars($torneo_data['lugar'] ?? 'Por definir') ?></p>
                    <p><strong>Modalidad:</strong> <?= $modalidades[$torneo_data['modalidad']] ?? 'N/A' ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Club Organizador:</strong> <?= htmlspecialchars($torneo_data['club_nombre']) ?></p>
                    <p><strong>Clase:</strong> <?= $clases[$torneo_data['clase']] ?? 'N/A' ?></p>
                    <?php if ($torneo_data['costo'] > 0): ?>
                        <p><strong>Costo:</strong> $<?= number_format($torneo_data['costo'], 2) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Lista de Jugadores por Club -->
    <?php if (empty($jugadores)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            No hay jugadores registrados en tus clubes.
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <span>
                    <i class="fas fa-users me-2"></i>Jugadores de tus Clubes
                </span>
                <div>
                    <button class="btn btn-sm btn-success" onclick="seleccionarTodos()">
                        <i class="fas fa-check-double me-1"></i>Seleccionar Todos
                    </button>
                    <button class="btn btn-sm btn-warning" onclick="deseleccionarTodos()">
                        <i class="fas fa-times me-1"></i>Deseleccionar
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form id="invitacionForm">
                    <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
                    <input type="hidden" name="torneo_id" value="<?= $torneo_selected ?>">
                    
                    <?php foreach ($jugadores_por_club as $club_id => $data): ?>
                        <div class="mb-4">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-building me-2"></i><?= htmlspecialchars($data['club_nombre']) ?>
                                <span class="badge bg-secondary"><?= count($data['jugadores']) ?> jugadores</span>
                            </h5>
                            
                            <div class="row g-3">
                                <?php foreach ($data['jugadores'] as $jugador): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="card border h-100">
                                            <div class="card-body p-3">
                                                <div class="form-check">
                                                    <input class="form-check-input jugador-check" 
                                                           type="checkbox" 
                                                           name="jugadores[]" 
                                                           value="<?= $jugador['id'] ?>"
                                                           id="jugador_<?= $jugador['id'] ?>"
                                                           data-nombre="<?= htmlspecialchars($jugador['nombre']) ?>"
                                                           data-celular="<?= htmlspecialchars($jugador['celular'] ?? '') ?>">
                                                    <label class="form-check-label w-100" for="jugador_<?= $jugador['id'] ?>">
                                                        <strong><?= htmlspecialchars($jugador['nombre']) ?></strong><br>
                                                        <small class="text-muted">
                                                            <i class="fas fa-id-card me-1"></i><?= htmlspecialchars($jugador['cedula']) ?><br>
                                                            <?php if ($jugador['celular']): ?>
                                                                <i class="fas fa-phone me-1"></i><?= htmlspecialchars($jugador['celular']) ?>
                                                            <?php else: ?>
                                                                <span class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Sin teléfono</span>
                                                            <?php endif; ?>
                                                        </small>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="text-center mt-4">
                        <button type="button" class="btn btn-success btn-lg" onclick="enviarInvitaciones()">
                            <i class="fab fa-whatsapp me-2"></i>Enviar Invitaciones por WhatsApp
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function seleccionarTodos() {
    document.querySelectorAll('.jugador-check').forEach(cb => {
        cb.checked = true;
    });
}

function deseleccionarTodos() {
    document.querySelectorAll('.jugador-check').forEach(cb => {
        cb.checked = false;
    });
}

function enviarInvitaciones() {
    const form = document.getElementById('invitacionForm');
    const formData = new FormData(form);
    const jugadoresSeleccionados = formData.getAll('jugadores[]');
    
    if (jugadoresSeleccionados.length === 0) {
        alert('Por favor selecciona al menos un jugador');
        return;
    }
    
    // Verificar que todos tengan teléfono
    const sinTelefono = [];
    jugadoresSeleccionados.forEach(id => {
        const checkbox = document.getElementById('jugador_' + id);
        const celular = checkbox.getAttribute('data-celular');
        if (!celular || celular.trim() === '') {
            sinTelefono.push(checkbox.getAttribute('data-nombre'));
        }
    });
    
    if (sinTelefono.length > 0) {
        if (!confirm('Algunos jugadores no tienen teléfono registrado:\n' + sinTelefono.join(', ') + '\n\n¿Deseas continuar solo con los que tienen teléfono?')) {
            return;
        }
    }
    
    // Redirigir a página de envío
    const torneoId = formData.get('torneo_id');
    const jugadoresParam = jugadoresSeleccionados.join(',');
    window.location.href = `?page=player_invitations&action=send&torneo_id=${torneoId}&jugadores=${jugadoresParam}`;
}
</script>


