<?php
/**
 * Revisar Inscripciones del Torneo
 */

require_once __DIR__ . '/../../lib/InscritosHelper.php';

// Verificar que la tabla inscritos existe
if (!$tabla_inscritos_existe) {
    echo '<div class="alert alert-danger">';
    echo '<h6 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Tabla inscritos no encontrada</h6>';
    echo '<p class="mb-2">La tabla <code>inscritos</code> no existe. Para revisar inscripciones, debe crear esta tabla primero.</p>';
    echo '<p class="mb-0">Ejecute: <code>php scripts/migrate_inscritos_table_final.php</code></p>';
    echo '</div>';
    return;
}

// Obtener información del usuario actual y su club
$current_user = Auth::user();
$user_club_id = $current_user['club_id'] ?? null;
$is_admin_general = Auth::isAdminGeneral();
$is_admin_club = Auth::isAdminClub();

// Construir filtro de territorio según el rol del administrador
$where_clause = "i.torneo_id = ?";
$params = [$torneo_id];

// Si no es admin_general, filtrar por territorio del administrador
if (!$is_admin_general && $user_club_id) {
    if ($is_admin_club) {
        // Admin_club: ver inscripciones de su club y clubes supervisados
        require_once __DIR__ . '/../../lib/ClubHelper.php';
        $clubes_supervisados = ClubHelper::getClubesSupervised($user_club_id);
        $clubes_ids = array_merge([$user_club_id], $clubes_supervisados);
        
        if (!empty($clubes_ids)) {
            $placeholders = str_repeat('?,', count($clubes_ids) - 1) . '?';
            $where_clause .= " AND (i.id_club IN ($placeholders) OR u.club_id IN ($placeholders))";
            $params = array_merge($params, $clubes_ids, $clubes_ids);
        } else {
            $where_clause .= " AND (i.id_club = ? OR u.club_id = ?)";
            $params[] = $user_club_id;
            $params[] = $user_club_id;
        }
    } else {
        // Admin_torneo: solo ver inscripciones de su club
        $where_clause .= " AND (i.id_club = ? OR u.club_id = ?)";
        $params[] = $user_club_id;
        $params[] = $user_club_id;
    }
}

// Obtener inscripciones con información del club y administrador
$stmt = $pdo->prepare("
    SELECT 
        i.*,
        u.username as usuario_nombre,
        u.nombre as usuario_nombre_completo,
        u.cedula as usuario_cedula,
        u.club_id as usuario_club_id,
        c.id as club_id,
        c.nombre as club_nombre,
        c.delegado as club_delegado,
        admin.username as admin_username,
        admin.nombre as admin_nombre
    FROM inscritos i
    LEFT JOIN usuarios u ON i.id_usuario = u.id
    LEFT JOIN clubes c ON i.id_club = c.id
    LEFT JOIN usuarios admin ON c.id = admin.club_id AND admin.role IN ('admin_club', 'admin_torneo') AND admin.status = 0
    WHERE $where_clause
    ORDER BY c.nombre ASC, COALESCE(u.nombre, u.username) ASC
");
$stmt->execute($params);
$inscripciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agregar texto de estatus
$inscripciones = InscritosHelper::agregarEstatusTexto($inscripciones);

// Agrupar inscripciones por club
$inscripciones_por_club = [];
foreach ($inscripciones as $insc) {
    $club_id = $insc['club_id'] ?? 0;
    $club_nombre = $insc['club_nombre'] ?? 'Sin Club';
    
    if (!isset($inscripciones_por_club[$club_id])) {
        $inscripciones_por_club[$club_id] = [
            'club_id' => $club_id,
            'club_nombre' => $club_nombre,
            'club_delegado' => $insc['club_delegado'] ?? '',
            'admin_nombre' => $insc['admin_nombre'] ?? $insc['admin_username'] ?? '',
            'inscripciones' => []
        ];
    }
    
    $inscripciones_por_club[$club_id]['inscripciones'][] = $insc;
}
?>

<div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-list-check me-2"></i>Revisar Inscripciones
        </h5>
        <span class="badge bg-light text-dark">
            Total: <?= count($inscripciones) ?>
        </span>
    </div>
    <div class="card-body">
        <?php if (empty($inscripciones_por_club)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                No hay inscripciones registradas para este torneo.
            </div>
        <?php else: ?>
            <?php foreach ($inscripciones_por_club as $club_data): ?>
                <div class="card mb-4 border-primary">
                    <div class="card-header bg-primary text-white">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5 class="mb-0">
                                    <i class="fas fa-building me-2"></i>
                                    <?= htmlspecialchars($club_data['club_nombre']) ?>
                                </h5>
                            </div>
                            <div class="col-md-4 text-end">
                                <span class="badge bg-light text-dark">
                                    <?= count($club_data['inscripciones']) ?> inscrito(s)
                                </span>
                            </div>
                        </div>
                        <?php if (!empty($club_data['admin_nombre']) || !empty($club_data['club_delegado'])): ?>
                            <div class="mt-2">
                                <small>
                                    <i class="fas fa-user-shield me-1"></i>
                                    <strong>Responsable:</strong> 
                                    <?php 
                                    $responsable = !empty($club_data['admin_nombre']) 
                                        ? $club_data['admin_nombre'] 
                                        : ($club_data['club_delegado'] ?? 'No asignado');
                                    echo htmlspecialchars($responsable);
                                    ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre Completo</th>
                                        <th>Estatus</th>
                                        <th>Fecha Inscripción</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($club_data['inscripciones'] as $insc): 
                                        $nombre_completo = !empty($insc['usuario_nombre_completo']) 
                                            ? $insc['usuario_nombre_completo'] 
                                            : $insc['usuario_nombre'];
                                        $id_usuario = $insc['id_usuario'] ?? 0;
                                    ?>
                                        <tr id="row_inscripcion_<?= $insc['id'] ?>">
                                            <td>
                                                <code class="text-primary"><?= $id_usuario ?></code>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($nombre_completo ?? 'N/A') ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge <?= $insc['estatus_clase'] ?> fw-bold" id="badge_estatus_<?= $insc['id'] ?>">
                                                    <?= $insc['estatus_formateado'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= date('d/m/Y H:i', strtotime($insc['fecha_inscripcion'])) ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-info" 
                                                            onclick="verDetalle(<?= $insc['id'] ?>)"
                                                            title="Ver Detalle">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-outline-warning" 
                                                            onclick="editarEstatus(<?= $insc['id'] ?>, <?= $insc['estatus'] ?>)"
                                                            title="Editar Estatus">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($insc['estatus'] != 4 && $insc['estatus'] !== 'retirado'): ?>
                                                        <button class="btn btn-outline-danger" 
                                                                onclick="retirarJugador(<?= $insc['id'] ?>, <?= $torneo_id ?>)"
                                                                title="Retirar Jugador">
                                                            <i class="fas fa-user-times"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
const TORNEOS_ID = <?= $torneo_id ?>;
const CSRF_TOKEN = '<?= htmlspecialchars(CSRF::token(), ENT_QUOTES) ?>';

function verDetalle(id) {
    // Implementar modal de detalle
    alert('Ver detalle de inscripción #' + id);
}

function editarEstatus(id, estatus_actual) {
    // Implementar modal de edición de estatus
    const nuevoEstatus = prompt('Seleccione nuevo estatus:\n0 = Pendiente\n1 = Confirmado\n2 = Solvente\n3 = No Solvente\n4 = Retirado', estatus_actual);
    
    if (nuevoEstatus !== null && nuevoEstatus !== estatus_actual.toString()) {
        cambiarEstatus(id, parseInt(nuevoEstatus));
    }
}

function retirarJugador(inscripcion_id, torneo_id) {
    if (!confirm('¿Está seguro de retirar a este jugador del torneo?')) {
        return;
    }
    
    cambiarEstatus(inscripcion_id, 4); // 4 = retirado
}

function cambiarEstatus(inscripcion_id, nuevo_estatus) {
    const formData = new FormData();
    formData.append('inscripcion_id', inscripcion_id);
    formData.append('torneo_id', TORNEOS_ID);
    formData.append('estatus', nuevo_estatus);
    formData.append('csrf_token', CSRF_TOKEN);
    
    const badge = document.getElementById('badge_estatus_' + inscripcion_id);
    if (badge) {
        badge.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    }
    
    fetch('../api/tournament_admin_cambiar_estatus.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Error en la respuesta del servidor: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Actualizar badge
            if (badge) {
                badge.className = 'badge ' + data.estatus_clase + ' fw-bold';
                badge.textContent = data.estatus_texto;
            }
            
            // Si se retiró, ocultar botón de retiro
            if (nuevo_estatus === 4) {
                const row = document.getElementById('row_inscripcion_' + inscripcion_id);
                if (row) {
                    const retiroBtn = row.querySelector('button[onclick*="retirarJugador"]');
                    if (retiroBtn) {
                        retiroBtn.remove();
                    }
                }
            }
            
            // Mostrar mensaje de éxito
            showMessage('Estatus actualizado exitosamente', 'success');
        } else {
            showMessage(data.error || 'Error al actualizar estatus', 'danger');
            if (badge) {
                // Recargar página para restaurar estado
                location.reload();
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Error al actualizar estatus: ' + error.message, 'danger');
        if (badge) {
            location.reload();
        }
    });
}

function showMessage(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const cardBody = document.querySelector('.card-body');
    if (cardBody) {
        cardBody.insertBefore(alertDiv, cardBody.firstChild);
        setTimeout(() => alertDiv.remove(), 3000);
    }
}
</script>

