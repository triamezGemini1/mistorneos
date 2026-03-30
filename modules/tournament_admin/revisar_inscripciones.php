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

// En esta vista de administración por torneo se deben ver TODOS los inscritos del torneo.
$where_clause = "i.torneo_id = ?";
$params = [$torneo_id];

// Obtener metadatos de torneo para reporte general
$stmtMeta = $pdo->prepare("SELECT id, nombre, modalidad, es_evento_masivo, club_responsable FROM tournaments WHERE id = ? LIMIT 1");
$stmtMeta->execute([$torneo_id]);
$torneo_meta = $stmtMeta->fetch(PDO::FETCH_ASSOC) ?: [];
$modalidad_num = (int)($torneo_meta['modalidad'] ?? 0);
$modalidad_label = $modalidad_num === 3 ? 'Equipos'
    : ($modalidad_num === 2 ? 'Parejas' : ($modalidad_num === 4 ? 'Parejas fijas' : 'Individual'));
$tipo_evento = (int)($torneo_meta['es_evento_masivo'] ?? 0);
$tipo_evento_label = $tipo_evento === 3 ? 'Local'
    : ($tipo_evento === 2 ? 'Regional' : ($tipo_evento === 1 ? 'Masivo' : 'Regular'));
$usa_numfvd = (int)($torneo_meta['club_responsable'] ?? 0) === 7;

// Obtener inscripciones con información del club y administrador (sin perder registros).
// Importante: no usar OR (id vs numfvd) en un solo JOIN — puede duplicar filas y mezclar datos de usuarios.
$stmt = $pdo->prepare("
    SELECT 
        i.*,
        t.nombre AS torneo_nombre,
        t.modalidad AS torneo_modalidad,
        t.es_evento_masivo AS torneo_tipo_evento,
        COALESCE(u.username, u_alt.username) as usuario_nombre,
        COALESCE(u.nombre, u_alt.nombre) as usuario_nombre_completo,
        COALESCE(u.cedula, u_alt.cedula) as usuario_cedula,
        COALESCE(u.numfvd, u_alt.numfvd, i.numfvd, 0) AS usuario_numfvd,
        COALESCE(u.email, u_alt.email) as usuario_email,
        COALESCE(u.telefono, u_alt.telefono) as usuario_telefono,
        COALESCE(u.sexo, u_alt.sexo) as usuario_sexo,
        COALESCE(u.nacionalidad, u_alt.nacionalidad) as usuario_nacionalidad,
        COALESCE(u.club_id, u_alt.club_id) as usuario_club_id,
        c.id as club_id,
        c.nombre as club_nombre,
        c.delegado as club_delegado,
        admin_data.admin_username,
        admin_data.admin_nombre
    FROM inscritos i
    LEFT JOIN tournaments t ON t.id = i.torneo_id
    LEFT JOIN usuarios u ON u.id = i.id_usuario
    LEFT JOIN usuarios u_alt ON u.id IS NULL
        AND u_alt.numfvd = i.id_usuario
        AND EXISTS (
            SELECT 1 FROM tournaments tx
            WHERE tx.id = i.torneo_id AND tx.club_responsable = 7
        )
    LEFT JOIN clubes c ON i.id_club = c.id
    LEFT JOIN (
        SELECT club_id,
               MAX(username) AS admin_username,
               MAX(nombre) AS admin_nombre
        FROM usuarios
        WHERE role IN ('admin_club', 'admin_torneo') AND status = 0
        GROUP BY club_id
    ) admin_data ON c.id = admin_data.club_id
    WHERE $where_clause
    ORDER BY c.nombre ASC, COALESCE(u.nombre, u_alt.nombre, u.username, u_alt.username) ASC
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
        <div class="d-flex align-items-center" style="gap:.5rem;">
            <a class="btn btn-sm btn-light" target="_blank" rel="noopener"
               href="<?= htmlspecialchars(AppHelpers::torneoGestionUrl('inscripciones_export_xls', (int)$torneo_id)) ?>">
                <i class="fas fa-file-excel"></i> Exportar XLS
            </a>
            <a class="btn btn-sm btn-warning" target="_blank" rel="noopener"
               href="<?= htmlspecialchars(AppHelpers::torneoGestionUrl('inscripciones_export_pdf', (int)$torneo_id)) ?>">
                <i class="fas fa-file-pdf"></i> Exportar PDF
            </a>
            <a class="btn btn-sm btn-success" target="_blank" rel="noopener"
               href="<?= htmlspecialchars(AppHelpers::torneoGestionUrl('inscripciones_reporte_detallado_pdf', (int)$torneo_id)) ?>"
               title="Logo organizador, por asociación y equipo">
                <i class="fas fa-file-contract"></i> Detallado PDF
            </a>
            <a class="btn btn-sm btn-outline-light" target="_blank" rel="noopener"
               href="<?= htmlspecialchars(AppHelpers::torneoGestionUrl('inscripciones_reporte_detallado_xls', (int)$torneo_id)) ?>">
                <i class="fas fa-table"></i> Detallado Excel
            </a>
            <span class="badge bg-light text-dark">
                Total: <?= count($inscripciones) ?>
            </span>
        </div>
    </div>
    <div class="card-body">
        <div class="alert alert-light border mb-3">
            <strong>Reporte general del torneo:</strong>
            <span class="ml-2"><strong>Modalidad:</strong> <?= htmlspecialchars($modalidad_label) ?></span>
            <span class="ml-3"><strong>Tipo:</strong> <?= htmlspecialchars($tipo_evento_label) ?></span>
            <?php if ($usa_numfvd): ?>
                <span class="ml-3 badge bg-info text-dark">Identificación principal mostrada: NUMFVD</span>
            <?php else: ?>
                <span class="ml-3 badge bg-secondary">Identificación principal mostrada: ID Usuario</span>
            <?php endif; ?>
            <div class="mt-2">
                <?php
                $total_confirmados = 0;
                $total_pendientes = 0;
                $total_retirados = 0;
                $total_m = 0;
                $total_f = 0;
                foreach ($inscripciones as $ri) {
                    $est = (string)($ri['estatus_texto'] ?? '');
                    if ($est === 'confirmado') $total_confirmados++;
                    elseif ($est === 'retirado') $total_retirados++;
                    else $total_pendientes++;
                    $sx = strtoupper((string)($ri['usuario_sexo'] ?? ''));
                    if ($sx === 'M') $total_m++;
                    if ($sx === 'F') $total_f++;
                }
                ?>
                <small>
                    Total: <strong><?= count($inscripciones) ?></strong> |
                    Confirmados: <strong><?= $total_confirmados ?></strong> |
                    Pendientes: <strong><?= $total_pendientes ?></strong> |
                    Retirados: <strong><?= $total_retirados ?></strong> |
                    Masculino: <strong><?= $total_m ?></strong> |
                    Femenino: <strong><?= $total_f ?></strong>
                </small>
            </div>
        </div>

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
                                        <th>ID Usuario</th>
                                        <th>NUMFVD</th>
                                        <th>Cédula</th>
                                        <th>Nombre Completo</th>
                                        <th>Sexo</th>
                                        <th>Email</th>
                                        <th>Teléfono</th>
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
                                                <code class="<?= $usa_numfvd ? 'text-success' : 'text-muted' ?>"><?= (int)($insc['usuario_numfvd'] ?? 0) ?></code>
                                            </td>
                                            <td>
                                                <span><?= htmlspecialchars((string)($insc['usuario_cedula'] ?? $insc['cedula'] ?? '')) ?></span>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($nombre_completo ?? 'N/A') ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars((string)($insc['usuario_sexo'] ?? '')) ?></td>
                                            <td><?= htmlspecialchars((string)($insc['usuario_email'] ?? '')) ?></td>
                                            <td><?= htmlspecialchars((string)($insc['usuario_telefono'] ?? '')) ?></td>
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

