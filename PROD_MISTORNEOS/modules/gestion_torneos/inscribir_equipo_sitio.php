<?php
/**
 * Vista: Inscribir Equipos en Sitio
 * Formulario simplificado que muestra solo jugadores NO inscritos
 */
$torneo = $view_data['torneo'] ?? [];
$jugadores_disponibles = $view_data['jugadores_disponibles'] ?? [];
$clubes_disponibles = $view_data['clubes_disponibles'] ?? [];
$equipos_registrados = $view_data['equipos_registrados'] ?? [];
$jugadores_por_equipo = $view_data['jugadores_por_equipo'] ?? 4;

// Determinar si el torneo ya inició (tiene rondas)
$torneo_iniciado = false;
if (!empty($torneo['id'])) {
    try {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("SELECT MAX(CAST(partida AS UNSIGNED)) AS ultima_ronda FROM partiresul WHERE id_torneo = ? AND mesa > 0");
        $stmt->execute([(int)$torneo['id']]);
        $ultima_ronda = (int)($stmt->fetchColumn() ?? 0);
        // Equipos: bloquear desde la primera ronda
        $torneo_iniciado = $ultima_ronda >= 1;
    } catch (Exception $e) {
        $torneo_iniciado = false;
    }
}

// Determinar si el torneo ya inició (tiene rondas)
$torneo_iniciado = false;
if (!empty($torneo['id'])) {
    try {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("SELECT MAX(CAST(partida AS UNSIGNED)) AS ultima_ronda FROM partiresul WHERE id_torneo = ? AND mesa > 0");
        $stmt->execute([(int)$torneo['id']]);
        $ultima_ronda = (int)($stmt->fetchColumn() ?? 0);
        $torneo_iniciado = $ultima_ronda > 0;
    } catch (Exception $e) {
        $torneo_iniciado = false;
    }
}

$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php']);
$base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';

// Ruta base para API (dinámica según entorno)
$api_base_path = (function_exists('AppHelpers') ? AppHelpers::getPublicPath() : '/mistorneos/public/') . 'api/';
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
    body {
        background-color: #f8f9fa;
    }
    .jugador-item {
        padding: 8px 12px;
        border-bottom: 1px solid #e9ecef;
        transition: background-color 0.2s;
        cursor: pointer;
    }
    .jugador-item:hover {
        background-color: #e9ecef;
    }
    .jugador-item.selected {
        background-color: #cfe2ff;
        border-left: 3px solid #0d6efd;
    }
    .search-box {
        position: sticky;
        top: 0;
        background: white;
        padding: 15px;
        border-bottom: 2px solid #e9ecef;
        z-index: 10;
    }
    .separador-jugador {
        border-top: 2px dashed #0d6efd;
        margin: 8px 0;
        opacity: 0.5;
    }
    .equipo-registrado-item {
        border: 1px solid #dee2e6;
        border-radius: 6px;
        background: white;
        transition: all 0.2s;
    }
    .equipo-registrado-item:hover {
        background-color: #f8f9fa;
        border-color: #0d6efd;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .equipo-registrado-item.selected {
        background-color: #e7f3ff;
        border-color: #0d6efd;
        border-width: 2px;
    }
    .equipo-registrado-item > div:first-child:hover {
        color: #0d6efd;
    }
</style>

<div class="container-fluid py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>">Gestión de Torneos</a></li>
            <li class="breadcrumb-item"><a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=panel&torneo_id=<?php echo $torneo['id']; ?>"><?php echo htmlspecialchars($torneo['nombre']); ?></a></li>
            <li class="breadcrumb-item active">Inscribir en Sitio</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h2 class="h4 mb-1">
                        <i class="fas fa-user-plus text-warning me-2"></i>Inscribir Equipo en Sitio
                    </h2>
                    <p class="text-muted mb-0">
                        <i class="fas fa-trophy me-1"></i><?php echo htmlspecialchars($torneo['nombre']); ?>
                        <span class="badge bg-info ms-2"><?php echo $jugadores_por_equipo; ?> jugadores por equipo</span>
                    </p>
                </div>
                <div class="mt-2 mt-md-0">
                    <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=panel&torneo_id=<?php echo $torneo['id']; ?>" 
                       class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Retornar al Panel
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenedor Principal: Dos Columnas (45% / 55%) -->
    <div class="row g-4">
        <!-- COLUMNA IZQUIERDA: Jugadores Disponibles (45%) -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-user-friends me-2"></i>Jugadores Disponibles (No Inscritos)
                    </h5>
                </div>
                
                <!-- Buscador -->
                <div class="search-box">
                    <input type="text" 
                           id="searchJugadores" 
                           class="form-control" 
                           placeholder="Buscar por ID, cédula o nombre..."
                           disabled>
                    <small class="text-muted">Seleccione el Club y Nombre del Equipo para habilitar</small>
                </div>
                
                <!-- Lista de Jugadores -->
                <div class="card-body p-0" style="max-height: calc(100vh - 400px); overflow-y: auto;">
                    <?php if (empty($jugadores_disponibles)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-user-slash fa-3x mb-3 opacity-50"></i>
                            <p class="mb-0">No hay jugadores disponibles</p>
                            <small>Todos los jugadores ya están inscritos</small>
                        </div>
                    <?php else: ?>
                        <div class="small fw-bold text-muted px-3 py-2 border-bottom bg-light">
                            ID Usuario | Cédula | Nombre
                        </div>
                        <div id="listaJugadores">
                            <?php foreach ($jugadores_disponibles as $jugador): ?>
                                <div class="jugador-item <?= $torneo_iniciado ? 'disabled' : '' ?>" 
                                     data-nombre="<?php echo strtolower(htmlspecialchars($jugador['nombre'] ?? '')); ?>"
                                     data-cedula="<?php echo htmlspecialchars($jugador['cedula'] ?? ''); ?>"
                                     data-id-usuario="<?php echo $jugador['id_usuario'] ?? ''; ?>"
                                     data-id="<?php echo $jugador['id'] ?? ''; ?>"
                                     data-jugador='<?php echo htmlspecialchars(json_encode($jugador, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>'
                                     <?php if (!$torneo_iniciado): ?>
                                     onclick="seleccionarJugador(this)"
                                     <?php endif; ?>
                                     style="cursor: <?= $torneo_iniciado ? 'not-allowed' : 'pointer' ?>;">
                                    <div class="small">
                                        <span class="text-muted fw-bold"><?php echo htmlspecialchars($jugador['id_usuario'] ?? '-'); ?></span>
                                        <span class="mx-1">|</span>
                                        <span class="text-muted"><?php echo htmlspecialchars($jugador['cedula'] ?? 'Sin cédula'); ?></span>
                                        <span class="mx-1">|</span>
                                        <span class="text-dark"><?php echo htmlspecialchars($jugador['nombre'] ?? 'Sin nombre'); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- COLUMNA DERECHA: Formulario de Registro (55%) -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-edit me-2"></i>Formulario de Registro de Equipo
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($torneo_iniciado): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            El torneo ya inició (hay rondas generadas). No se permiten nuevas inscripciones de equipos. Solo información de control.
                        </div>
                    <?php endif; ?>
                    <form id="formEquipo">
                        <?php require_once __DIR__ . '/../../config/csrf.php'; ?>
                        <input type="hidden" name="csrf_token" value="<?php echo CSRF::token(); ?>">
                        <input type="hidden" id="equipo_id" name="equipo_id" value="">
                        <input type="hidden" id="torneo_id" name="torneo_id" value="<?php echo $torneo['id']; ?>">
                        
                        <!-- Primera línea: Código del Equipo (readonly), Club, Nombre del Equipo -->
                        <div class="row mb-3 g-2">
                            <div class="col-md-2">
                                <input type="text" 
                                       id="codigo_equipo" 
                                       class="form-control" 
                                       readonly 
                                       placeholder="Código (generado automático)"
                                       style="background-color: #e9ecef;">
                            </div>
                            <div class="col-md-5">
                                <select id="club_id" name="club_id" class="form-select" required>
                                    <option value="">Club *</option>
                                    <?php if (!empty($clubes_disponibles)): ?>
                                        <?php foreach ($clubes_disponibles as $club): ?>
                                            <option value="<?php echo $club['id']; ?>"><?php echo htmlspecialchars($club['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No hay clubes disponibles</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <input type="text" 
                                       id="nombre_equipo" 
                                       name="nombre_equipo" 
                                       class="form-control" 
                                       required
                                       placeholder="Nombre del Equipo *">
                            </div>
                        </div>
                        
                        <hr>
                        
                        <!-- Jugadores del Equipo -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <label class="form-label fw-bold mb-0">Jugadores del Equipo (<?php echo $jugadores_por_equipo; ?> requeridos)</label>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-success" id="btnGuardarEquipo" <?= $torneo_iniciado ? 'disabled' : 'disabled' ?>>
                                        <i class="fas fa-save me-2"></i>Guardar Equipo
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="limpiarFormulario()" <?= $torneo_iniciado ? 'disabled' : '' ?>>
                                        <i class="fas fa-redo me-2"></i>Nuevo Equipo
                                    </button>
                                </div>
                            </div>
                            <div id="jugadores-container">
                                <?php for ($i = 1; $i <= $jugadores_por_equipo; $i++): ?>
                                    <div class="row g-2 align-items-center mb-2" data-posicion="<?php echo $i; ?>" data-jugador-asignado="">
                                        <div class="col-md-1 text-center">
                                            <?php if ($i == 1): ?>
                                                <span class="badge bg-warning text-dark">★</span>
                                            <?php else: ?>
                                                <strong><?php echo $i; ?></strong>
                                            <?php endif; ?>
                                            <input type="hidden" 
                                                   id="es_capitan_<?php echo $i; ?>" 
                                                   name="jugadores[<?php echo $i; ?>][es_capitan]" 
                                                   value="<?php echo $i == 1 ? '1' : '0'; ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <input type="text" 
                                                   class="form-control form-control-sm jugador-id-usuario" 
                                                   id="jugador_id_usuario_<?php echo $i; ?>" 
                                                   placeholder="ID"
                                                   readonly
                                                   style="background-color: #e9ecef; font-weight: bold;">
                                            <input type="hidden" 
                                                   id="jugador_id_usuario_h_<?php echo $i; ?>" 
                                                   name="jugadores[<?php echo $i; ?>][id_usuario]">
                                        </div>
                                        <div class="col-md-3">
                                            <input type="text" 
                                                   class="form-control form-control-sm jugador-cedula" 
                                                   id="jugador_cedula_<?php echo $i; ?>" 
                                                   name="jugadores[<?php echo $i; ?>][cedula]" 
                                                   placeholder="Cédula"
                                                   data-posicion="<?php echo $i; ?>"
                                                   onblur="buscarJugadorPorCedula(this)"
                                                   oninput="validarFormulario()"
                                                   readonly
                                                   style="background-color: #f1f1f1;">
                                            <input type="hidden" 
                                                   class="jugador-id-inscrito" 
                                                   id="jugador_id_inscrito_<?php echo $i; ?>" 
                                                   name="jugadores[<?php echo $i; ?>][id_inscrito]">
                                        </div>
                                        <div class="col-md-5">
                                            <input type="text" 
                                                   class="form-control form-control-sm jugador-nombre" 
                                                   id="jugador_nombre_<?php echo $i; ?>" 
                                                   name="jugadores[<?php echo $i; ?>][nombre]" 
                                                   placeholder="Nombre del jugador"
                                                   readonly
                                                   style="background-color: #e9ecef;"
                                                   oninput="validarFormulario()">
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-danger p-1" 
                                                    onclick="limpiarJugadorYDevolver(<?php echo $i; ?>)"
                                                    title="Quitar jugador"
                                                    id="btn_limpiar_<?php echo $i; ?>"
                                                    style="display: none;"
                                                    disabled>
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <?php if ($i < $jugadores_por_equipo): ?>
                                        <div class="separador-jugador mb-2"></div>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Lista de Equipos Registrados -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>Equipos Registrados (<?php echo count($equipos_registrados); ?>)
                    </h5>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php if (empty($equipos_registrados)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-users-slash fa-2x mb-2 opacity-50"></i>
                            <p class="mb-0">No hay equipos registrados</p>
                            <small>Los equipos aparecerán aquí después de guardarlos</small>
                        </div>
                    <?php else: ?>
                        <div id="listaEquiposRegistrados">
                            <?php foreach ($equipos_registrados as $equipo): ?>
                                <div class="equipo-registrado-item d-flex align-items-center justify-content-between p-2 mb-2" 
                                     data-equipo-id="<?php echo $equipo['id']; ?>">
                                    <div class="d-flex align-items-center flex-grow-1 gap-3" 
                                         style="cursor: pointer;"
                                         onclick="cargarEquipo(<?php echo $equipo['id']; ?>)">
                                        <div class="badge bg-secondary"><?php echo htmlspecialchars($equipo['codigo_equipo']); ?></div>
                                        <div class="fw-bold text-primary"><?php echo htmlspecialchars($equipo['nombre_equipo']); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($equipo['nombre_club'] ?? 'Sin Club'); ?></div>
                                    </div>
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-danger ms-2"
                                            onclick="event.stopPropagation(); eliminarEquipo(<?php echo $equipo['id']; ?>, '<?php echo htmlspecialchars($equipo['nombre_equipo'], ENT_QUOTES); ?>')"
                                            title="Eliminar equipo">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>
<script>
const JUGADORES_POR_EQUIPO = <?php echo $jugadores_por_equipo; ?>;
const TORNEO_ID = <?php echo $torneo['id']; ?>;

// Validar formulario al cargar
document.addEventListener('DOMContentLoaded', function() {
    validarFormulario();
    actualizarBloqueoSeleccionJugadores();
    
    document.getElementById('nombre_equipo').addEventListener('input', () => {
        validarFormulario();
        actualizarBloqueoSeleccionJugadores();
    });
    document.getElementById('club_id').addEventListener('change', () => {
        validarFormulario();
        actualizarBloqueoSeleccionJugadores();
    });
});

// Búsqueda en tiempo real
document.getElementById('searchJugadores')?.addEventListener('input', function(e) {
    if (!puedeSeleccionarJugadores()) {
        e.target.value = '';
        return;
    }
    const searchTerm = e.target.value.toLowerCase();
    const items = document.querySelectorAll('.jugador-item');
    
    items.forEach(item => {
        const nombre = item.getAttribute('data-nombre') || '';
        const cedula = item.getAttribute('data-cedula') || '';
        const idUsuario = (item.getAttribute('data-id-usuario') || '').toString();
        
        if (nombre.includes(searchTerm) || cedula.includes(searchTerm) || idUsuario.includes(searchTerm)) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
});

// Seleccionar jugador desde la lista
function seleccionarJugador(element) {
    if (!puedeSeleccionarJugadores()) {
        Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: 'Primero seleccione el Club y el Nombre del Equipo.',
            confirmButtonColor: '#3b82f6'
        });
        return;
    }
    const jugadorData = JSON.parse(element.getAttribute('data-jugador'));
    
    // Verificar que no esté jugando (ya tiene codigo_equipo)
    if (jugadorData.codigo_equipo) {
        Swal.fire({
            icon: 'warning',
            title: 'Jugador no disponible',
            text: 'Este jugador ya está asignado a un equipo (código: ' + jugadorData.codigo_equipo + ')',
            confirmButtonColor: '#3b82f6'
        });
        return;
    }
    
    // Buscar primera posición vacía
    for (let i = 1; i <= JUGADORES_POR_EQUIPO; i++) {
        const cedula = document.getElementById(`jugador_cedula_${i}`).value.trim();
        if (!cedula) {
            asignarJugadorAPosicion(i, jugadorData);
            element.remove();
            actualizarContadorDisponibles();
            return;
        }
    }
    
    Swal.fire({
        icon: 'info',
        title: 'Posiciones completas',
        text: 'Todas las posiciones están ocupadas. Use el botón X para quitar un jugador.',
        confirmButtonColor: '#3b82f6'
    });
}

// Asignar jugador a una posición
function asignarJugadorAPosicion(posicion, jugador) {
    const idInscritoEl = document.getElementById(`jugador_id_inscrito_${posicion}`);
    if (idInscritoEl) idInscritoEl.value = jugador.id_inscrito || jugador.id || '';
    
    const idUsuarioEl = document.getElementById(`jugador_id_usuario_${posicion}`);
    const idUsuarioHEl = document.getElementById(`jugador_id_usuario_h_${posicion}`);
    const idUsuario = jugador.id_usuario || '';
    if (idUsuarioEl) idUsuarioEl.value = idUsuario;
    if (idUsuarioHEl) idUsuarioHEl.value = idUsuario;
    
    document.getElementById(`jugador_cedula_${posicion}`).value = jugador.cedula || '';
    document.getElementById(`jugador_nombre_${posicion}`).value = jugador.nombre || '';
    
    const fila = document.querySelector(`[data-posicion="${posicion}"]`);
    if (fila) {
        fila.setAttribute('data-jugador-asignado', JSON.stringify(jugador));
        const btnLimpiar = document.getElementById(`btn_limpiar_${posicion}`);
        if (btnLimpiar) btnLimpiar.style.display = 'inline-block';
    }
    
    validarFormulario();
    actualizarBloqueoSeleccionJugadores();
}

// Limpiar jugador y devolverlo al listado
async function limpiarJugadorYDevolver(posicion) {
    const fila = document.querySelector(`[data-posicion="${posicion}"]`);
    const jugadorDataStr = fila ? fila.getAttribute('data-jugador-asignado') : null;
    
    // Obtener nombre del jugador para mostrar en la confirmación
    const nombreJugador = document.getElementById(`jugador_nombre_${posicion}`)?.value || '';
    const cedulaJugador = document.getElementById(`jugador_cedula_${posicion}`)?.value || '';
    const jugadorTexto = nombreJugador ? `"${nombreJugador}"` : (cedulaJugador ? `con cédula ${cedulaJugador}` : 'este jugador');
    
    // Confirmar antes de retirar
    const result = await Swal.fire({
        icon: 'question',
        title: '¿Retirar jugador?',
        html: `¿Está seguro de retirar ${jugadorTexto} del equipo?<br><br>El jugador quedará disponible para asignarlo a otra posición.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, retirar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d',
        reverseButtons: true
    });
    
    // Si el usuario cancela, no hacer nada
    if (!result.isConfirmed) {
        return;
    }
    
    // Ejecutar la acción de retirar
    limpiarJugador(posicion);
    
    if (jugadorDataStr) {
        try {
            const jugador = JSON.parse(jugadorDataStr);
            devolverJugadorAListado(jugador);
            fila.setAttribute('data-jugador-asignado', '');
        } catch (e) {
            console.error('Error al parsear jugador:', e);
        }
    }
    
    const btnLimpiar = document.getElementById(`btn_limpiar_${posicion}`);
    if (btnLimpiar) btnLimpiar.style.display = 'none';
    
    // Mostrar mensaje de confirmación de éxito
    Swal.fire({
        icon: 'success',
        title: 'Jugador retirado',
        text: 'El jugador ha sido retirado del equipo y está disponible para asignación.',
        confirmButtonColor: '#10b981',
        timer: 2000,
        timerProgressBar: true
    });
}

// Devolver jugador al listado
function devolverJugadorAListado(jugador) {
    const listaJugadores = document.getElementById('listaJugadores');
    if (!listaJugadores) return;
    
    const ready = puedeSeleccionarJugadores();
    const jugadorHtml = `
        <div class="jugador-item" 
             data-nombre="${(jugador.nombre || '').toLowerCase()}"
             data-cedula="${jugador.cedula || ''}"
             data-id-usuario="${jugador.id_usuario || ''}"
             data-id="${jugador.id || ''}"
             data-jugador='${JSON.stringify(jugador).replace(/'/g, "&#39;")}'
             onclick="seleccionarJugador(this)"
             style="cursor: ${ready ? 'pointer' : 'not-allowed'}; pointer-events: ${ready ? 'auto' : 'none'}; opacity: ${ready ? '1' : '0.6'};">
            <div class="small">
                <span class="text-muted fw-bold">${jugador.id_usuario || '-'}</span>
                <span class="mx-1">|</span>
                <span class="text-muted">${jugador.cedula || 'Sin cédula'}</span>
                <span class="mx-1">|</span>
                <span class="text-dark">${jugador.nombre || 'Sin nombre'}</span>
            </div>
        </div>
    `;
    
    listaJugadores.insertAdjacentHTML('beforeend', jugadorHtml);
    actualizarContadorDisponibles();
    // Asegurar que el bloqueo se actualice después de agregar
    actualizarBloqueoSeleccionJugadores();
}

// Actualizar contador
function actualizarContadorDisponibles() {
    const numItems = document.querySelectorAll('#listaJugadores .jugador-item').length;
}

// Buscar jugador por cédula
async function buscarJugadorPorCedula(input) {
    const cedula = input.value.trim();
    const posicion = input.getAttribute('data-posicion');
    
    if (!puedeSeleccionarJugadores()) {
        Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: 'Primero seleccione el Club y el Nombre del Equipo.',
            confirmButtonColor: '#3b82f6'
        });
        input.value = '';
        return;
    }
    
    if (!cedula) {
        limpiarJugador(posicion);
        return;
    }
    
    try {
        const response = await fetch(`<?php echo $api_base_path; ?>buscar_jugador_inscripcion.php?cedula=${encodeURIComponent(cedula)}&torneo_id=${TORNEO_ID}`);
        const data = await response.json();
        
        if (data.success && data.jugador) {
            // Verificar que no esté jugando
            if (data.jugador.codigo_equipo) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Jugador no disponible',
                    text: 'Este jugador ya está asignado a un equipo (código: ' + data.jugador.codigo_equipo + ')',
                    confirmButtonColor: '#3b82f6'
                });
                limpiarJugador(posicion);
                return;
            }
            asignarJugadorAPosicion(posicion, data.jugador);
            // Quitar de la lista si está
            const items = document.querySelectorAll('.jugador-item');
            items.forEach(item => {
                const itemCedula = item.getAttribute('data-cedula');
                if (itemCedula === cedula) {
                    item.remove();
                }
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Jugador no encontrado',
                text: data.message || 'Jugador no encontrado o ya está inscrito en un equipo',
                confirmButtonColor: '#3b82f6'
            });
            limpiarJugador(posicion);
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error al buscar jugador por cédula',
            confirmButtonColor: '#3b82f6'
        });
        limpiarJugador(posicion);
    }
}

// Limpiar jugador
function limpiarJugador(posicion) {
    const idInscritoEl = document.getElementById(`jugador_id_inscrito_${posicion}`);
    const idUsuarioEl = document.getElementById(`jugador_id_usuario_${posicion}`);
    const idUsuarioHEl = document.getElementById(`jugador_id_usuario_h_${posicion}`);
    const cedulaEl = document.getElementById(`jugador_cedula_${posicion}`);
    const nombreEl = document.getElementById(`jugador_nombre_${posicion}`);
    const btnLimpiar = document.getElementById(`btn_limpiar_${posicion}`);
    
    if (idInscritoEl) idInscritoEl.value = '';
    if (idUsuarioEl) idUsuarioEl.value = '';
    if (idUsuarioHEl) idUsuarioHEl.value = '';
    if (cedulaEl) cedulaEl.value = '';
    if (nombreEl) nombreEl.value = '';
    if (btnLimpiar) btnLimpiar.style.display = 'none';
    
    const fila = document.querySelector(`[data-posicion="${posicion}"]`);
    if (fila) fila.setAttribute('data-jugador-asignado', '');
    
    validarFormulario();
    actualizarBloqueoSeleccionJugadores();
}

// Validar formulario
function validarFormulario() {
    let jugadoresCompletos = 0;
    
    for (let i = 1; i <= JUGADORES_POR_EQUIPO; i++) {
        const cedula = document.getElementById(`jugador_cedula_${i}`).value.trim();
        const nombre = document.getElementById(`jugador_nombre_${i}`).value.trim();
        
        if (cedula && nombre) {
            jugadoresCompletos++;
        }
    }
    
    const nombreEquipo = document.getElementById('nombre_equipo').value.trim();
    const clubId = document.getElementById('club_id').value;
    
    const btnGuardar = document.getElementById('btnGuardarEquipo');
    
    if (jugadoresCompletos === JUGADORES_POR_EQUIPO && nombreEquipo && clubId) {
        btnGuardar.disabled = false;
        btnGuardar.classList.remove('btn-secondary');
        btnGuardar.classList.add('btn-success');
    } else {
        btnGuardar.disabled = true;
        btnGuardar.classList.remove('btn-success');
        btnGuardar.classList.add('btn-secondary');
    }
}

// Bloqueo/Desbloqueo
function puedeSeleccionarJugadores() {
    const nombreEquipo = document.getElementById('nombre_equipo').value.trim();
    const clubId = document.getElementById('club_id').value;
    return !!(nombreEquipo && clubId);
}

function actualizarBloqueoSeleccionJugadores() {
    const ready = puedeSeleccionarJugadores();
    
    const searchInput = document.getElementById('searchJugadores');
    if (searchInput) {
        searchInput.disabled = !ready;
        if (!ready) searchInput.value = '';
    }
    
    // Actualizar contenedor y cada item individual
    const lista = document.getElementById('listaJugadores');
    if (lista) {
        lista.style.pointerEvents = ready ? 'auto' : 'none';
        lista.style.opacity = ready ? '1' : '0.6';
    }
    
    // Actualizar cada item de jugador individual
    const items = document.querySelectorAll('.jugador-item');
    items.forEach(item => {
        if (ready) {
            item.style.pointerEvents = 'auto';
            item.style.opacity = '1';
            item.style.cursor = 'pointer';
        } else {
            item.style.pointerEvents = 'none';
            item.style.opacity = '0.6';
            item.style.cursor = 'not-allowed';
        }
    });
    
    for (let i = 1; i <= JUGADORES_POR_EQUIPO; i++) {
        const cedulaEl = document.getElementById(`jugador_cedula_${i}`);
        const limpiarBtn = document.getElementById(`btn_limpiar_${i}`);
        
        if (cedulaEl) {
            cedulaEl.readOnly = !ready;
            cedulaEl.style.backgroundColor = ready ? '' : '#f1f1f1';
        }
        if (limpiarBtn) {
            limpiarBtn.disabled = !ready;
        }
    }
}

// Limpiar formulario
function limpiarFormulario() {
    // Devolver todos los jugadores asignados a la lista
    for (let i = 1; i <= JUGADORES_POR_EQUIPO; i++) {
        const fila = document.querySelector(`[data-posicion="${i}"]`);
        const jugadorDataStr = fila ? fila.getAttribute('data-jugador-asignado') : null;
        if (jugadorDataStr) {
            try {
                const jugador = JSON.parse(jugadorDataStr);
                devolverJugadorAListado(jugador);
            } catch (e) {}
        }
        limpiarJugador(i);
    }
    
    document.getElementById('formEquipo').reset();
    document.getElementById('equipo_id').value = '';
    document.getElementById('codigo_equipo').value = '';
    
    // Limpiar selección visual de equipo
    document.querySelectorAll('.equipo-registrado-item').forEach(item => {
        item.classList.remove('selected');
    });
    
    validarFormulario();
    actualizarBloqueoSeleccionJugadores();
}

// Guardar equipo
document.getElementById('formEquipo').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    console.log('=== INICIO GUARDAR EQUIPO (JavaScript) ===');
    
    if (!puedeSeleccionarJugadores()) {
        console.log('ERROR: Validación falló - falta Club o Nombre del Equipo');
        Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: 'Primero seleccione el Club y el Nombre del Equipo.',
            confirmButtonColor: '#3b82f6'
        });
        return;
    }
    
    const form = this;
    const formData = new FormData();
    
    const equipo_id = document.getElementById('equipo_id').value || '';
    const torneo_id = document.getElementById('torneo_id').value || '';
    const nombre_equipo = document.getElementById('nombre_equipo').value || '';
    const club_id = document.getElementById('club_id').value || '';
    
    console.log('Datos del equipo:', { equipo_id, torneo_id, nombre_equipo, club_id });
    
    formData.append('csrf_token', form.querySelector('input[name="csrf_token"]')?.value || '');
    formData.append('equipo_id', equipo_id);
    formData.append('torneo_id', torneo_id);
    formData.append('nombre_equipo', nombre_equipo);
    formData.append('club_id', club_id);
    
    let posicionJugador = 1;
    const jugadoresEnviados = [];
    for (let i = 1; i <= JUGADORES_POR_EQUIPO; i++) {
        const cedula = document.getElementById(`jugador_cedula_${i}`).value.trim();
        const nombre = document.getElementById(`jugador_nombre_${i}`).value.trim();
        
        if (cedula && nombre) {
            const id_inscritoEl = document.getElementById(`jugador_id_inscrito_${i}`);
            const id_inscrito = id_inscritoEl ? id_inscritoEl.value : '';
            const id_usuario_hel = document.getElementById(`jugador_id_usuario_h_${i}`);
            const id_usuario = id_usuario_hel ? id_usuario_hel.value : '';
            const es_capitan = document.getElementById(`es_capitan_${i}`)?.value == '1' ? 1 : 0;
            
            const jugadorData = { cedula, nombre, id_inscrito, id_usuario, es_capitan, posicion: i };
            jugadoresEnviados.push(jugadorData);
            console.log(`Jugador ${posicionJugador} (posición ${i}):`, jugadorData);
            
            formData.append(`jugadores[${posicionJugador}][cedula]`, cedula);
            formData.append(`jugadores[${posicionJugador}][nombre]`, nombre);
            formData.append(`jugadores[${posicionJugador}][id_inscrito]`, id_inscrito || '');
            formData.append(`jugadores[${posicionJugador}][id_usuario]`, id_usuario || '');
            formData.append(`jugadores[${posicionJugador}][es_capitan]`, es_capitan);
            posicionJugador++;
        }
    }
    
    console.log('Total de jugadores a enviar:', jugadoresEnviados.length);
    console.log('Enviando datos al servidor...');
    
    try {
        const response = await fetch('<?php echo $api_base_path; ?>guardar_equipo.php', {
            method: 'POST',
            body: formData
        });
        
        console.log('Respuesta recibida, status:', response.status);
        console.log('Content-Type:', response.headers.get('content-type'));
        
        // Obtener el texto de la respuesta primero
        const responseText = await response.text();
        console.log('Respuesta completa (primeros 500 caracteres):', responseText.substring(0, 500));
        
        // Intentar parsear como JSON
        let data;
        try {
            data = JSON.parse(responseText);
            console.log('Datos de respuesta (JSON parseado):', data);
        } catch (parseError) {
            console.error('=== ERROR: La respuesta no es JSON válido ===');
            console.error('Error de parseo:', parseError);
            console.error('Respuesta completa:', responseText);
            
            // Si la respuesta contiene HTML (página de error), intentar extraer el mensaje
            if (responseText.includes('<!DOCTYPE') || responseText.includes('<html')) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error del servidor',
                    html: 'El servidor devolvió una página de error HTML. Revisa la consola para más detalles.<br><br>Verifica los logs de PHP en el servidor.',
                    confirmButtonColor: '#3b82f6'
                });
                console.error('HTML recibido en lugar de JSON - probablemente un error de PHP');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de respuesta',
                    html: 'Respuesta del servidor no válida. Revisa la consola para más detalles.<br><br>' + responseText.substring(0, 200),
                    confirmButtonColor: '#3b82f6'
                });
            }
            return;
        }
        
        if (data.success) {
            console.log('=== ÉXITO: Equipo guardado correctamente ===');
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: data.message || 'Equipo guardado exitosamente',
                confirmButtonColor: '#10b981',
                timer: 2000,
                timerProgressBar: true
            }).then(() => {
                location.reload();
            });
        } else {
            console.error('=== ERROR: ' + (data.message || 'Error al guardar el equipo') + ' ===');
            console.error('Detalles del error:', data);
            Swal.fire({
                icon: 'error',
                title: 'Error al guardar',
                text: data.message || 'Error al guardar el equipo',
                confirmButtonColor: '#3b82f6'
            });
        }
    } catch (error) {
        console.error('=== ERROR en fetch: ===', error);
        console.error('Stack trace:', error.stack);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            html: 'Error al guardar el equipo: ' + error.message + '<br><br>Revisa la consola para más detalles.',
            confirmButtonColor: '#3b82f6'
        });
    }
});

// Cargar equipo en el formulario al seleccionarlo de la lista
async function cargarEquipo(equipoId) {
    try {
        const response = await fetch(`<?php echo $api_base_path; ?>obtener_equipo.php?id=${equipoId}`);
        const data = await response.json();
        
        if (data.success && data.equipo) {
            const equipo = data.equipo;
            
            // Marcar equipo seleccionado visualmente
            document.querySelectorAll('.equipo-registrado-item').forEach(item => {
                item.classList.remove('selected');
            });
            const equipoElement = document.querySelector(`[data-equipo-id="${equipoId}"]`);
            if (equipoElement) {
                equipoElement.classList.add('selected');
            }
            
            // Cargar datos del equipo en el formulario
            document.getElementById('equipo_id').value = equipo.id || '';
            document.getElementById('codigo_equipo').value = equipo.codigo_equipo || '';
            document.getElementById('nombre_equipo').value = equipo.nombre_equipo || '';
            document.getElementById('club_id').value = equipo.id_club || '';
            
            // Limpiar jugadores actuales y devolverlos a la lista si están asignados
            for (let i = 1; i <= JUGADORES_POR_EQUIPO; i++) {
                const fila = document.querySelector(`[data-posicion="${i}"]`);
                const jugadorDataStr = fila ? fila.getAttribute('data-jugador-asignado') : null;
                if (jugadorDataStr) {
                    try {
                        const jugador = JSON.parse(jugadorDataStr);
                        devolverJugadorAListado(jugador);
                    } catch (e) {}
                }
                limpiarJugador(i);
            }
            
            // Cargar jugadores del equipo en el formulario
            if (equipo.jugadores && equipo.jugadores.length > 0) {
                equipo.jugadores.forEach((jugador, index) => {
                    const posicion = index + 1;
                    if (posicion <= JUGADORES_POR_EQUIPO) {
                        const jugadorData = {
                            id: jugador.id_inscrito || jugador.id || '',
                            id_inscrito: jugador.id_inscrito || '',
                            id_usuario: jugador.id_usuario || jugador.usuario_id || '',
                            cedula: jugador.cedula || '',
                            nombre: jugador.nombre || '',
                            club_nombre: equipo.club_nombre || 'Sin Club'
                        };
                        asignarJugadorAPosicion(posicion, jugadorData);
                        
                        // Quitar jugador de la lista de disponibles si está (por id_usuario)
                        const items = document.querySelectorAll('.jugador-item');
                        items.forEach(item => {
                            const itemIdUsuario = item.getAttribute('data-id-usuario');
                            if (itemIdUsuario && itemIdUsuario == jugadorData.id_usuario) {
                                item.remove();
                            }
                        });
                    }
                });
            }
            
            // Habilitar formulario y actualizar bloqueo (el club y nombre ya están llenos)
            actualizarBloqueoSeleccionJugadores();
            
            // Scroll al formulario
            document.getElementById('formEquipo').scrollIntoView({ behavior: 'smooth', block: 'start' });
            
            validarFormulario();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error al cargar los datos del equipo',
                confirmButtonColor: '#3b82f6'
            });
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error al cargar el equipo',
            confirmButtonColor: '#3b82f6'
        });
    }
}

// Eliminar equipo
async function eliminarEquipo(equipoId, nombreEquipo) {
    const result = await Swal.fire({
        icon: 'warning',
        title: '¿Eliminar equipo?',
        html: `¿Está seguro de eliminar el equipo <strong>"${nombreEquipo}"</strong>?<br><br>Los jugadores del equipo quedarán liberados y disponibles para otros equipos.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d',
        reverseButtons: true
    });
    
    if (!result.isConfirmed) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('equipo_id', equipoId);
        formData.append('csrf_token', '<?php echo CSRF::token(); ?>');
        
        const response = await fetch('<?php echo $api_base_path; ?>eliminar_equipo.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Eliminado!',
                text: data.message || 'Equipo eliminado exitosamente',
                confirmButtonColor: '#10b981',
                timer: 2000,
                timerProgressBar: true
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'Error al eliminar el equipo',
                confirmButtonColor: '#3b82f6'
            });
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error al eliminar el equipo: ' + error.message,
            confirmButtonColor: '#3b82f6'
        });
    }
}
</script>

