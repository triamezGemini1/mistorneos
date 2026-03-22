<?php
/**
 * Invitar Usuarios por WhatsApp desde Panel de Torneo
 * Permite seleccionar usuarios de la lista de afiliados e invitarlos por WhatsApp
 */

// Obtener usuarios del territorio del administrador
$usuarios_territorio = [];

if ($is_admin_general) {
    // Admin general: todos los usuarios
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.nombre, u.cedula, u.celular, u.email, c.nombre as club_nombre, c.id as club_id
        FROM usuarios u
        LEFT JOIN clubes c ON u.club_id = c.id
        WHERE u.status = 1 AND u.role = 'usuario'
        ORDER BY COALESCE(u.nombre, u.username) ASC
    ");
    $stmt->execute();
    $usuarios_territorio = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else if ($user_club_id) {
    if ($is_admin_club) {
        // Admin_club: usuarios de su club y clubes supervisados
        require_once __DIR__ . '/../../lib/ClubHelper.php';
        $clubes_supervisados = ClubHelper::getClubesSupervised($user_club_id);
        $clubes_ids = array_merge([$user_club_id], $clubes_supervisados);
        
        if (!empty($clubes_ids)) {
            $placeholders = str_repeat('?,', count($clubes_ids) - 1) . '?';
            $stmt = $pdo->prepare("
                SELECT u.id, u.username, u.nombre, u.cedula, u.celular, u.email, c.nombre as club_nombre, c.id as club_id
                FROM usuarios u
                LEFT JOIN clubes c ON u.club_id = c.id
                WHERE u.status = 1 AND u.role = 'usuario' AND u.club_id IN ($placeholders)
                ORDER BY COALESCE(u.nombre, u.username) ASC
            ");
            $stmt->execute($clubes_ids);
            $usuarios_territorio = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        // Admin_torneo: solo usuarios de su club
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.nombre, u.cedula, u.celular, u.email, c.nombre as club_nombre, c.id as club_id
            FROM usuarios u
            LEFT JOIN clubes c ON u.club_id = c.id
            WHERE u.status = 1 AND u.role = 'usuario' AND u.club_id = ?
            ORDER BY COALESCE(u.nombre, u.username) ASC
        ");
        $stmt->execute([$user_club_id]);
        $usuarios_territorio = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Obtener usuarios ya inscritos en el torneo
$stmt = $pdo->prepare("
    SELECT DISTINCT i.id_usuario
    FROM inscritos i
    WHERE i.torneo_id = ?
");
$stmt->execute([$torneo_id]);
$usuarios_inscritos_ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id_usuario');

// Separar usuarios disponibles e inscritos
$usuarios_disponibles = array_filter($usuarios_territorio, function($u) use ($usuarios_inscritos_ids) {
    return !in_array($u['id'], $usuarios_inscritos_ids);
});

// Agrupar por club
$usuarios_por_club = [];
foreach ($usuarios_disponibles as $usuario) {
    $club_id = $usuario['club_id'] ?? 0;
    if (!isset($usuarios_por_club[$club_id])) {
        $usuarios_por_club[$club_id] = [
            'club_nombre' => $usuario['club_nombre'] ?? 'Sin club',
            'usuarios' => []
        ];
    }
    $usuarios_por_club[$club_id]['usuarios'][] = $usuario;
}
?>

<div class="card">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0">
            <i class="fab fa-whatsapp me-2"></i>Invitar Usuarios por WhatsApp
        </h5>
    </div>
    <div class="card-body">
        <p class="text-muted mb-4">
            <i class="fas fa-info-circle me-2"></i>
            Selecciona usuarios de la lista de afiliados para invitarlos a participar en este torneo mediante WhatsApp.
        </p>
        
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0">
                            <i class="fas fa-users me-2"></i>Usuarios Disponibles para Invitar
                            <span class="badge bg-light text-dark ms-2" id="total_disponibles"><?= count($usuarios_disponibles) ?></span>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <input type="text" 
                                   class="form-control" 
                                   id="searchUsuarios" 
                                   placeholder="Buscar por nombre, cédula o club...">
                        </div>
                        
                        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                            <table class="table table-hover table-sm">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th width="40">
                                            <input type="checkbox" id="selectAll" title="Seleccionar todos">
                                        </th>
                                        <th>Nombre</th>
                                        <th>Cédula</th>
                                        <th>Club</th>
                                        <th>Teléfono</th>
                                    </tr>
                                </thead>
                                <tbody id="usuariosTable">
                                    <?php foreach ($usuarios_por_club as $club_id => $club_data): ?>
                                        <?php foreach ($club_data['usuarios'] as $usuario): 
                                            $nombre_completo = !empty($usuario['nombre']) ? $usuario['nombre'] : $usuario['username'];
                                            $tiene_telefono = !empty($usuario['celular']);
                                        ?>
                                            <tr data-nombre="<?= htmlspecialchars(strtolower($nombre_completo)) ?>" 
                                                data-cedula="<?= htmlspecialchars(strtolower($usuario['cedula'] ?? '')) ?>"
                                                data-club="<?= htmlspecialchars(strtolower($club_data['club_nombre'])) ?>"
                                                class="<?= !$tiene_telefono ? 'table-warning' : '' ?>">
                                                <td>
                                                    <?php if ($tiene_telefono): ?>
                                                        <input type="checkbox" 
                                                               class="usuario-checkbox" 
                                                               value="<?= $usuario['id'] ?>"
                                                               data-nombre="<?= htmlspecialchars($nombre_completo) ?>"
                                                               data-celular="<?= htmlspecialchars($usuario['celular']) ?>">
                                                    <?php else: ?>
                                                        <span class="text-muted" title="Sin teléfono">
                                                            <i class="fas fa-exclamation-triangle"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><strong><?= htmlspecialchars($nombre_completo) ?></strong></td>
                                                <td><?= !empty($usuario['cedula']) ? htmlspecialchars($usuario['cedula']) : '<span class="text-muted">N/A</span>' ?></td>
                                                <td><?= htmlspecialchars($club_data['club_nombre']) ?></td>
                                                <td>
                                                    <?php if ($tiene_telefono): ?>
                                                        <?= htmlspecialchars($usuario['celular']) ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Sin teléfono</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <div class="card border-success">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0">
                            <i class="fas fa-check-circle me-2"></i>Usuarios Seleccionados
                            <span class="badge bg-light text-dark ms-2" id="count_seleccionados">0</span>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="usuariosSeleccionados" class="mb-3">
                            <p class="text-muted text-center">No hay usuarios seleccionados</p>
                        </div>
                        <button type="button" 
                                class="btn btn-success btn-lg w-100" 
                                id="btnEnviarWhatsApp"
                                disabled>
                            <i class="fab fa-whatsapp me-2"></i>Enviar Invitaciones por WhatsApp
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.usuario-checkbox');
    const usuariosSeleccionados = document.getElementById('usuariosSeleccionados');
    const countSeleccionados = document.getElementById('count_seleccionados');
    const btnEnviarWhatsApp = document.getElementById('btnEnviarWhatsApp');
    const searchInput = document.getElementById('searchUsuarios');
    const usuariosTable = document.getElementById('usuariosTable');
    
    let usuariosSeleccionadosList = [];
    
    // Seleccionar todos
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => {
                if (!cb.disabled) {
                    cb.checked = this.checked;
                    if (this.checked) {
                        agregarUsuario(cb);
                    } else {
                        removerUsuario(cb.value);
                    }
                }
            });
            actualizarUI();
        });
    }
    
    // Checkboxes individuales
    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            if (this.checked) {
                agregarUsuario(this);
            } else {
                removerUsuario(this.value);
            }
            actualizarUI();
        });
    });
    
    // Búsqueda
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = usuariosTable.querySelectorAll('tr');
            
            rows.forEach(row => {
                const nombre = row.dataset.nombre || '';
                const cedula = row.dataset.cedula || '';
                const club = row.dataset.club || '';
                
                if (nombre.includes(searchTerm) || cedula.includes(searchTerm) || club.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
    
    function agregarUsuario(checkbox) {
        const id = checkbox.value;
        const nombre = checkbox.dataset.nombre;
        const celular = checkbox.dataset.celular;
        
        if (!usuariosSeleccionadosList.find(u => u.id === id)) {
            usuariosSeleccionadosList.push({
                id: id,
                nombre: nombre,
                celular: celular
            });
        }
    }
    
    function removerUsuario(id) {
        usuariosSeleccionadosList = usuariosSeleccionadosList.filter(u => u.id !== id);
    }
    
    function actualizarUI() {
        countSeleccionados.textContent = usuariosSeleccionadosList.length;
        
        if (usuariosSeleccionadosList.length > 0) {
            usuariosSeleccionados.innerHTML = usuariosSeleccionadosList.map(u => `
                <span class="badge bg-success me-2 mb-2">
                    ${u.nombre}
                    <button type="button" class="btn-close btn-close-white ms-2" onclick="removerSeleccionado(${u.id})" style="font-size: 0.7em;"></button>
                </span>
            `).join('');
            btnEnviarWhatsApp.disabled = false;
        } else {
            usuariosSeleccionados.innerHTML = '<p class="text-muted text-center">No hay usuarios seleccionados</p>';
            btnEnviarWhatsApp.disabled = true;
        }
    }
    
    window.removerSeleccionado = function(id) {
        const checkbox = document.querySelector(`.usuario-checkbox[value="${id}"]`);
        if (checkbox) {
            checkbox.checked = false;
            removerUsuario(id);
            actualizarUI();
        }
    };
    
    // Enviar invitaciones por WhatsApp
    if (btnEnviarWhatsApp) {
        btnEnviarWhatsApp.addEventListener('click', function() {
            if (usuariosSeleccionadosList.length === 0) {
                alert('Debes seleccionar al menos un usuario');
                return;
            }
            
            const ids = usuariosSeleccionadosList.map(u => u.id).join(',');
            const url = `../modules/tournament_admin/send_whatsapp_invitations.php?torneo_id=<?= $torneo_id ?>&usuarios=${ids}`;
            
            // Abrir en nueva ventana para enviar WhatsApp
            window.location.href = url;
            
            // Mostrar mensaje de confirmación
            alert(`Se abrirán ${usuariosSeleccionadosList.length} conversaciones de WhatsApp para enviar las invitaciones.`);
        });
    }
});
</script>





