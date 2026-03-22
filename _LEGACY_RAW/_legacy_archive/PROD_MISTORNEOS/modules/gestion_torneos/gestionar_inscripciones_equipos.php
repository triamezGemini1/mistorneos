<?php
/**
 * Vista: Gestionar Inscripciones de Equipos
 * Muestra listado HTML de todos los equipos inscritos ordenados por club
 * Permite expandir/colapsar jugadores y retirar equipos
 */
$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php']);
$base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';

// Obtener CSRF token
require_once __DIR__ . '/../../config/csrf.php';
$csrf_token = class_exists('CSRF') ? CSRF::token() : '';
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
    body {
        background-color: #f8f9fa;
    }
    .club-section {
        margin-bottom: 30px;
    }
    .club-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px 20px;
        border-radius: 8px 8px 0 0;
        font-weight: bold;
        font-size: 1.1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .equipo-row {
        background: white;
        border: 1px solid #dee2e6;
        border-top: none;
        padding: 12px 20px;
        transition: background-color 0.2s;
    }
    .equipo-row:hover {
        background-color: #f8f9fa;
    }
    .equipo-row:last-child {
        border-radius: 0 0 8px 8px;
    }
    .equipo-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
    }
    .equipo-main {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    .equipo-numero {
        width: 35px;
        height: 35px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        flex-shrink: 0;
    }
    .equipo-details {
        flex: 1;
    }
    .equipo-codigo {
        font-family: monospace;
        font-size: 0.9rem;
        color: #6c757d;
        margin-right: 10px;
    }
    .equipo-nombre {
        font-weight: 600;
        color: #212529;
        font-size: 1rem;
    }
    .equipo-actions {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    .jugadores-toggle {
        cursor: pointer;
        color: #667eea;
        font-size: 0.9rem;
        user-select: none;
    }
    .jugadores-toggle:hover {
        color: #764ba2;
        text-decoration: underline;
    }
    .jugadores-toggle i {
        transition: transform 0.3s;
    }
    .jugadores-toggle.expanded i {
        transform: rotate(90deg);
    }
    .jugadores-list {
        display: none;
        padding: 15px 20px 15px 70px;
        background-color: #f8f9fa;
        border-top: 1px solid #e9ecef;
    }
    .jugadores-list.show {
        display: block;
    }
    .jugador-item {
        padding: 8px 0;
        border-bottom: 1px dotted #dee2e6;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .jugador-item:last-child {
        border-bottom: none;
    }
    .jugador-id {
        font-family: monospace;
        color: #6c757d;
        font-size: 0.85rem;
        min-width: 60px;
    }
    .jugador-cedula {
        font-family: monospace;
        color: #495057;
        font-size: 0.9rem;
        min-width: 100px;
    }
    .jugador-nombre {
        flex: 1;
        color: #212529;
    }
    .badge-equipo-completo {
        background-color: #28a745;
        color: white;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.85rem;
        font-weight: 500;
    }
    .badge-equipo-incompleto {
        background-color: #ffc107;
        color: #212529;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.85rem;
        font-weight: 500;
    }
</style>

<div class="container-fluid py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=index">Gestión de Torneos</a></li>
            <li class="breadcrumb-item"><a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=panel&torneo_id=<?php echo $torneo['id']; ?>"><?php echo htmlspecialchars($torneo['nombre']); ?></a></li>
            <li class="breadcrumb-item active">Gestionar Inscripciones</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h2 class="h4 mb-1">
                        <i class="fas fa-clipboard-list text-primary me-2"></i>Gestionar Inscripciones de Equipos
                    </h2>
                    <p class="text-muted mb-0">
                        <i class="fas fa-trophy me-1"></i><?php echo htmlspecialchars($torneo['nombre']); ?>
                    </p>
                </div>
                <div class="d-flex gap-2 mt-2 mt-md-0">
                    <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=inscribir_equipo_sitio&torneo_id=<?php echo $torneo['id']; ?>" 
                       class="btn btn-primary btn-sm">
                        <i class="fas fa-plus me-1"></i>Inscribir Equipo
                    </a>
                    <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=panel&torneo_id=<?php echo $torneo['id']; ?>" 
                       class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Retornar al Panel
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($equipos_por_club)): ?>
        <!-- Sin equipos -->
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-users-slash fa-3x text-muted mb-3 opacity-50"></i>
                <p class="text-muted mb-0">No hay equipos inscritos</p>
                <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=inscribir_equipo_sitio&torneo_id=<?php echo $torneo['id']; ?>" 
                   class="btn btn-primary mt-3">
                    <i class="fas fa-plus me-1"></i>Inscribir Primer Equipo
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Listado de Equipos por Club -->
        <?php 
        $contador_global = 0;
        foreach ($equipos_por_club as $club_info): 
            $club_nombre = htmlspecialchars($club_info['nombre']);
            $total_equipos_club = count($club_info['equipos']);
        ?>
            <div class="club-section">
                <!-- Encabezado del Club -->
                <div class="club-header">
                    <div>
                        <i class="fas fa-building me-2"></i>
                        <?php echo $club_nombre; ?>
                        <span class="badge bg-light text-dark ms-2"><?php echo $total_equipos_club; ?> equipo(s)</span>
                    </div>
                </div>

                <!-- Listado de Equipos del Club -->
                <?php foreach ($club_info['equipos'] as $index => $equipo): 
                    $contador_global++;
                    $equipo_id = (int)$equipo['id'];
                    $codigo_equipo = htmlspecialchars($equipo['codigo_equipo'] ?? '');
                    $nombre_equipo = htmlspecialchars($equipo['nombre_equipo'] ?? '');
                    $jugadores = $equipo['jugadores'] ?? [];
                    $total_jugadores = count($jugadores);
                    $jugadores_requeridos = $jugadores_por_equipo;
                    $es_completo = $total_jugadores >= $jugadores_requeridos;
                    $equipo_id_js = htmlspecialchars(json_encode($equipo_id));
                ?>
                    <div class="equipo-row" id="equipo-row-<?php echo $equipo_id; ?>">
                        <div class="equipo-info">
                            <div class="equipo-main">
                                <div class="equipo-numero"><?php echo $contador_global; ?></div>
                                <div class="equipo-details">
                                    <div>
                                        <span class="equipo-codigo"><?php echo $codigo_equipo; ?></span>
                                        <span class="equipo-nombre"><?php echo $nombre_equipo; ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="equipo-actions">
                                <?php if ($es_completo): ?>
                                    <span class="badge-equipo-completo">
                                        <i class="fas fa-check-circle me-1"></i>Completo (<?php echo $total_jugadores; ?>/<?php echo $jugadores_requeridos; ?>)
                                    </span>
                                <?php else: ?>
                                    <span class="badge-equipo-incompleto">
                                        <i class="fas fa-exclamation-triangle me-1"></i>Incompleto (<?php echo $total_jugadores; ?>/<?php echo $jugadores_requeridos; ?>)
                                    </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($jugadores)): ?>
                                    <span class="jugadores-toggle" onclick="toggleJugadores(<?php echo $equipo_id; ?>)">
                                        <i class="fas fa-chevron-right me-1"></i>
                                        <span>Ver Jugadores</span>
                                    </span>
                                <?php endif; ?>
                                
                                <button class="btn btn-sm btn-danger" 
                                        onclick="retirarEquipo(<?php echo $equipo_id; ?>, '<?php echo htmlspecialchars($nombre_equipo, ENT_QUOTES); ?>')"
                                        title="Retirar Equipo">
                                    <i class="fas fa-times"></i> Retirar
                                </button>
                            </div>
                        </div>
                        
                        <!-- Lista de Jugadores (oculta por defecto) -->
                        <?php if (!empty($jugadores)): ?>
                            <div class="jugadores-list" id="jugadores-<?php echo $equipo_id; ?>">
                                <div class="mb-2 fw-semibold text-muted" style="font-size: 0.9rem;">
                                    <i class="fas fa-users me-1"></i>Jugadores del Equipo:
                                </div>
                                <?php foreach ($jugadores as $jugador): ?>
                                    <div class="jugador-item">
                                        <span class="jugador-id">ID: <?php echo htmlspecialchars($jugador['id_usuario'] ?? '-'); ?></span>
                                        <span class="jugador-cedula"><?php echo htmlspecialchars($jugador['cedula'] ?? '-'); ?></span>
                                        <span class="jugador-nombre"><?php echo htmlspecialchars($jugador['nombre'] ?? 'Sin nombre'); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>
<script>
function toggleJugadores(equipoId) {
    const lista = document.getElementById('jugadores-' + equipoId);
    const toggle = event.target.closest('.jugadores-toggle');
    
    if (lista && toggle) {
        const isExpanded = lista.classList.contains('show');
        
        if (isExpanded) {
            lista.classList.remove('show');
            toggle.classList.remove('expanded');
            toggle.querySelector('span').textContent = 'Ver Jugadores';
        } else {
            lista.classList.add('show');
            toggle.classList.add('expanded');
            toggle.querySelector('span').textContent = 'Ocultar Jugadores';
        }
    }
}

async function retirarEquipo(equipoId, nombreEquipo) {
    const result = await Swal.fire({
        title: '¿Retirar equipo?',
        html: `
            <div class="text-left">
                <p>¿Está seguro de retirar el equipo <strong>${nombreEquipo}</strong> del torneo?</p>
                <div class="alert alert-warning mt-3 mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Advertencia:</strong> Esta acción:
                    <ul class="mt-2 mb-0">
                        <li>Eliminará el equipo del torneo</li>
                        <li>Liberará a los jugadores del equipo (quedarán disponibles para otros equipos)</li>
                        <li>No se pueden deshacer los cambios una vez confirmado</li>
                    </ul>
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-check me-1"></i> Sí, retirar equipo',
        cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        reverseButtons: true
    });
    
    if (result.isConfirmed) {
        try {
            // Mostrar loading
            Swal.fire({
                title: 'Retirando equipo...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Enviar petición para eliminar equipo
            const formData = new FormData();
            formData.append('equipo_id', equipoId);
            formData.append('csrf_token', '<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>');
            
            const response = await fetch('<?= rtrim(AppHelpers::getPublicPath(), '/') ?>api/eliminar_equipo.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Equipo retirado',
                    text: data.message || 'El equipo ha sido retirado exitosamente del torneo',
                    confirmButtonText: 'Aceptar'
                }).then(() => {
                    // Remover la fila del equipo de la vista
                    const row = document.getElementById('equipo-row-' + equipoId);
                    if (row) {
                        row.style.transition = 'opacity 0.3s';
                        row.style.opacity = '0';
                        setTimeout(() => {
                            row.remove();
                            // Actualizar números de equipos si es necesario
                            actualizarNumerosEquipos();
                        }, 300);
                    } else {
                        // Si no se encuentra la fila, recargar la página
                        window.location.reload();
                    }
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al retirar equipo',
                    text: data.message || 'No se pudo retirar el equipo',
                    confirmButtonText: 'Aceptar'
                });
            }
        } catch (error) {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Ocurrió un error al intentar retirar el equipo. Por favor, intente nuevamente.',
                confirmButtonText: 'Aceptar'
            });
        }
    }
}

function actualizarNumerosEquipos() {
    // Actualizar números secuenciales de equipos después de eliminar uno
    const clubSections = document.querySelectorAll('.club-section');
    let contador = 0;
    
    clubSections.forEach(clubSection => {
        const equipoRows = clubSection.querySelectorAll('.equipo-row');
        equipoRows.forEach(row => {
            contador++;
            const numeroElement = row.querySelector('.equipo-numero');
            if (numeroElement) {
                numeroElement.textContent = contador;
            }
        });
    });
}
</script>
