<?php
/**
 * Inscribir Jugador en Sitio (durante el torneo)
 * - Limita el ámbito territorial al administrador del torneo
 * - Permite inscribir atletas de otros ámbitos usando cédula o identificador único
 */

// Verificar que la tabla inscritos existe
if (!$tabla_inscritos_existe) {
    echo '<div class="alert alert-danger">';
    echo '<h6 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Tabla inscritos no encontrada</h6>';
    echo '<p class="mb-2">La tabla <code>inscritos</code> no existe. Para inscribir jugadores, debe crear esta tabla primero.</p>';
    echo '<p class="mb-0">Ejecute: <code>php scripts/migrate_inscritos_table_final.php</code></p>';
    echo '</div>';
    return;
}

// Obtener información del usuario actual y su club
$current_user = Auth::user();
$user_club_id = $current_user['club_id'] ?? null;
$is_admin_general = Auth::isAdminGeneral();
$is_admin_club = Auth::isAdminClub();

// Determinar si el torneo debe bloquear inscripción según modalidad:
// - Equipos (modalidad 3): bloquea si hay al menos 1 ronda
// - Individual/Parejas: bloquea si ronda > 1
$torneo_iniciado = false;
try {
    $stmt = $pdo->prepare("SELECT MAX(CAST(partida AS UNSIGNED)) AS ultima_ronda FROM partiresul WHERE id_torneo = ? AND mesa > 0");
    $stmt->execute([$torneo_id]);
    $ultima_ronda = (int)($stmt->fetchColumn() ?? 0);
    $es_equipos = isset($torneo['modalidad']) && (int)$torneo['modalidad'] === 3;
    if ($es_equipos) {
        $torneo_iniciado = $ultima_ronda >= 1;
    } else {
        $torneo_iniciado = $ultima_ronda >= 2;
    }
} catch (Exception $e) {
    $torneo_iniciado = false;
}

// Obtener usuarios de la entidad del administrador
$usuarios_territorio = [];
$entidad_admin = isset($current_user['entidad']) ? (int)$current_user['entidad'] : 0;
$roles_permitidos = ['usuario', 'admin_club'];

if ($is_admin_general) {
    // Admin general: todos los usuarios (solo afiliados y admin_club)
    $stmt = $pdo->query("
        SELECT u.id, u.username, u.nombre, u.cedula, c.nombre as club_nombre, c.id as club_id
        FROM usuarios u
        LEFT JOIN clubes c ON u.club_id = c.id
        WHERE u.role IN ('usuario','admin_club')
          AND (u.status = 'approved' OR u.status = 1)
        ORDER BY COALESCE(u.nombre, u.username) ASC
    ");
    $usuarios_territorio = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($entidad_admin > 0) {
    // Admin_club / Admin_torneo: todos los usuarios de su entidad (sin importar club)
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.nombre, u.cedula, c.nombre as club_nombre, c.id as club_id
        FROM usuarios u
        LEFT JOIN clubes c ON u.club_id = c.id
        WHERE u.role IN ('usuario','admin_club')
          AND (u.status = 'approved' OR u.status = 1)
          AND u.entidad = ?
        ORDER BY COALESCE(u.nombre, u.username) ASC
    ");
    $stmt->execute([$entidad_admin]);
    $usuarios_territorio = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener usuarios ya inscritos
$stmt = $pdo->prepare("
    SELECT i.id_usuario, i.estatus, i.id_club,
           u.id, u.username, u.nombre, u.cedula, c.nombre as club_nombre
    FROM inscritos i
    LEFT JOIN usuarios u ON i.id_usuario = u.id
    LEFT JOIN clubes c ON i.id_club = c.id
    WHERE i.torneo_id = ?
    ORDER BY COALESCE(u.nombre, u.username) ASC
");
$stmt->execute([$torneo_id]);
$usuarios_inscritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$usuarios_inscritos_ids = array_column($usuarios_inscritos, 'id_usuario');

// Separar usuarios disponibles e inscritos
$usuarios_disponibles = array_filter($usuarios_territorio, function($u) use ($usuarios_inscritos_ids) {
    return !in_array($u['id'], $usuarios_inscritos_ids);
});

// Obtener lista de clubes (solo del territorio del administrador)
$clubes_disponibles = [];
if ($is_admin_general) {
    $stmt = $pdo->query("SELECT id, nombre FROM clubes WHERE estatus = 1 ORDER BY nombre ASC");
    $clubes_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else if ($user_club_id) {
    if ($is_admin_club) {
        require_once __DIR__ . '/../../lib/ClubHelper.php';
        $clubes_disponibles = ClubHelper::getClubesSupervisedWithData($user_club_id);
    } else {
        $stmt = $pdo->prepare("SELECT id, nombre FROM clubes WHERE id = ? AND estatus = 1");
        $stmt->execute([$user_club_id]);
        $club = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($club) {
            $clubes_disponibles = [$club];
        }
    }
}
?>

<div class="card">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0">
            <i class="fas fa-user-plus me-2"></i>Inscribir Jugador en Sitio
        </h5>
    </div>
    <div class="card-body">
        <?php if ($torneo_iniciado): ?>
            <div class="alert alert-warning mb-3">
                <i class="fas fa-exclamation-triangle me-2"></i>
                El torneo ya inició (hay rondas generadas). No se permiten nuevas inscripciones. Solo se muestra información de inscritos para control administrativo.
            </div>
            
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-list-check me-2"></i>Inscritos del Torneo
                        <span class="badge bg-light text-dark ms-2"><?= count($usuarios_inscritos) ?></span>
                    </h6>
                </div>
                <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nombre</th>
                                    <th>ID</th>
                                    <th>Club</th>
                                    <th>Estatus</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios_inscritos as $usuario): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($usuario['nombre'] ?? $usuario['username']) ?></strong></td>
                                    <td><code><?= (int)$usuario['id'] ?></code></td>
                                    <td><?= htmlspecialchars($usuario['club_nombre'] ?? 'Sin club') ?></td>
                                    <td><?= InscritosHelper::renderEstatusBadge($usuario['estatus']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($usuarios_inscritos)): ?>
                                <tr><td colspan="4" class="text-center text-muted">No hay inscritos</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
        <!-- Pestañas para elegir método de inscripción -->
        <ul class="nav nav-tabs mb-4" id="inscripcionTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="territorio-tab" data-bs-toggle="tab" data-bs-target="#territorio" type="button" role="tab">
                    <i class="fas fa-users me-2"></i>Atletas de Mi Entidad
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="cedula-tab" data-bs-toggle="tab" data-bs-target="#cedula" type="button" role="tab">
                    <i class="fas fa-id-card me-2"></i>Buscar por Cédula/ID
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="inscripcionTabsContent">
            <!-- Tab: Atletas del Territorio -->
            <div class="tab-pane fade show active" id="territorio" role="tabpanel">
                <!-- Listados: Disponibles e Inscritos -->
                <div class="row">
                    <!-- Listado de Disponibles -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-list me-2"></i>Atletas Disponibles
                                    <span class="badge bg-light text-dark ms-2" id="count_disponibles"><?= count($usuarios_disponibles) ?></span>
                                </h6>
                            </div>
                            <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Nombre</th>
                                                <th>ID Usuario</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbody_disponibles">
                                            <?php foreach ($usuarios_disponibles as $usuario): 
                                                $nombre_completo = !empty($usuario['nombre']) ? $usuario['nombre'] : $usuario['username'];
                                            ?>
                                                <tr style="cursor: pointer;" 
                                                    class="table-row-hover"
                                                    data-id="<?= $usuario['id'] ?>"
                                                    data-nombre="<?= htmlspecialchars($nombre_completo) ?>"
                                                    data-cedula="<?= htmlspecialchars($usuario['cedula'] ?? '') ?>"
                                                    data-club-id="<?= $usuario['club_id'] ?? '' ?>">
                                                    <td><strong><?= htmlspecialchars($nombre_completo) ?></strong></td>
                                                    <td><code><?= $usuario['id'] ?></code></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Listado de Inscritos -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-check-circle me-2"></i>Atletas Inscritos
                                    <span class="badge bg-light text-dark ms-2" id="count_inscritos"><?= count($usuarios_inscritos) ?></span>
                                </h6>
                            </div>
                            <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Nombre</th>
                                                <th>ID Usuario</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbody_inscritos">
                                            <?php foreach ($usuarios_inscritos as $inscrito): 
                                                $nombre_completo = !empty($inscrito['nombre']) ? $inscrito['nombre'] : $inscrito['username'];
                                            ?>
                                                <tr style="cursor: pointer;" 
                                                    class="table-row-hover"
                                                    data-id="<?= $inscrito['id_usuario'] ?>"
                                                    data-nombre="<?= htmlspecialchars($nombre_completo) ?>"
                                                    data-cedula="<?= htmlspecialchars($inscrito['cedula'] ?? '') ?>"
                                                    data-club-id="<?= $inscrito['id_club'] ?? '' ?>">
                                                    <td><strong><?= htmlspecialchars($nombre_completo) ?></strong></td>
                                                    <td><code><?= $inscrito['id_usuario'] ?></code></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tab: Búsqueda por Cédula (flujo: nacionalidad → cédula → on blur busca inscritos → usuarios → externa → formulario nuevo) -->
            <div class="tab-pane fade" id="cedula" role="tabpanel">
                <div class="row">
                    <div class="col-md-10">
                        <div class="card">
                            <div class="card-body">
                                <!-- Mensaje siempre visible en el formulario -->
                                <div id="mensaje_formulario_cedula" class="mb-3" role="alert" aria-live="polite"></div>

                                <div class="row mb-3">
                                    <div class="col-md-2">
                                        <label class="form-label fw-bold">Nacionalidad <span class="text-danger">*</span></label>
                                        <select id="select_nacionalidad_cedula" class="form-select">
                                            <option value="V" selected>V</option>
                                            <option value="E">E</option>
                                            <option value="J">J</option>
                                            <option value="P">P</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Nº Cédula <span class="text-danger">*</span></label>
                                        <input type="text" id="input_cedula" class="form-control" placeholder="Solo números, ej: 12345678" maxlength="15" inputmode="numeric" autocomplete="off">
                                        <small class="text-muted">Al salir del campo se busca automáticamente</small>
                                    </div>
                                </div>

                                <div class="mb-3 d-none" id="wrap_acciones_cedula">
                                    <label class="form-label fw-bold">Club</label>
                                    <select id="select_club_cedula" class="form-select">
                                        <option value="">-- Usar club del usuario --</option>
                                        <?php foreach ($clubes_disponibles as $club): ?>
                                            <option value="<?= $club['id'] ?>"><?= htmlspecialchars($club['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3 d-none" id="wrap_estatus_cedula">
                                    <label class="form-label fw-bold">Estatus</label>
                                    <select id="select_estatus_cedula" class="form-select">
                                        <?php foreach (InscritosHelper::getEstatusFormOptions() as $opt): ?>
                                            <option value="<?= $opt['value'] ?>" <?= $opt['value'] == 1 ? 'selected' : '' ?>><?= $opt['label'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3 d-none" id="wrap_btn_inscribir_cedula">
                                    <button type="button" class="btn btn-success me-2" id="btn_inscribir_cedula">
                                        <i class="fas fa-save me-2"></i>Inscribir
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" id="btn_otra_busqueda_cedula">
                                        <i class="fas fa-redo me-2"></i>Otra búsqueda
                                    </button>
                                </div>

                                <!-- Resultado: datos encontrados (usuario o persona externa) -->
                                <div id="resultado_busqueda" class="d-none">
                                    <div class="card border-info">
                                        <div class="card-body">
                                            <h6 class="card-title">Datos encontrados</h6>
                                            <div id="info_usuario_encontrado"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Formulario nuevo usuario (cuando no está en usuarios ni en BD externa, o datos externos para completar) -->
                                <div id="form_nuevo_usuario_inscribir" class="d-none card border-warning mt-3">
                                    <div class="card-header bg-warning text-dark">Registrar e inscribir</div>
                                    <div class="card-body">
                                        <p class="text-muted small">Complete los datos para crear el usuario e inscribirlo en el torneo.</p>
                                        <div class="row g-2">
                                            <div class="col-md-2"><label class="form-label">Nacionalidad</label><select id="form_nac" class="form-select"><option value="V">V</option><option value="E">E</option><option value="J">J</option><option value="P">P</option></select></div>
                                            <div class="col-md-2"><label class="form-label">Cédula</label><input type="text" id="form_cedula" class="form-control" placeholder="Solo números"></div>
                                            <div class="col-md-4"><label class="form-label">Nombre completo</label><input type="text" id="form_nombre" class="form-control" required></div>
                                            <div class="col-md-2"><label class="form-label">Fecha nac.</label><input type="date" id="form_fechnac" class="form-control"></div>
                                            <div class="col-md-2"><label class="form-label">Sexo</label><select id="form_sexo" class="form-select"><option value="M">M</option><option value="F">F</option><option value="O">O</option></select></div>
                                            <div class="col-md-4"><label class="form-label">Teléfono</label><input type="text" id="form_telefono" class="form-control" placeholder="Opcional"></div>
                                            <div class="col-md-4"><label class="form-label">Email</label><input type="email" id="form_email" class="form-control" placeholder="Opcional"></div>
                                            <div class="col-md-4"><label class="form-label">Club</label><select id="form_club" class="form-select"><option value="">-- Seleccione --</option><?php foreach ($clubes_disponibles as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option><?php endforeach; ?></select></div>
                                        </div>
                                        <div class="mt-3">
                                            <button type="button" class="btn btn-warning" id="btn_registrar_inscribir">
                                                <i class="fas fa-user-plus me-2"></i>Registrar e inscribir
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary ms-2" id="btn_cancelar_form_nuevo">Cancelar</button>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <a href="index.php?page=tournament_admin&torneo_id=<?= (int)$torneo_id ?>&action=panel" class="btn btn-secondary"><i class="fas fa-times me-2"></i>Volver al panel</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.table-row-hover {
    cursor: pointer;
    transition: background-color 0.2s;
}
.table-row-hover:hover {
    background-color: #e3f2fd !important;
}
.table-row-hover:active {
    background-color: #bbdefb !important;
}
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
</style>

<script>
const TORNEOS_ID = <?= $torneo_id ?>;
const CSRF_TOKEN = '<?= htmlspecialchars(CSRF::token(), ENT_QUOTES) ?>';
const ESTATUS_DEFAULT = '1';
const API_URL = '<?= app_base_url() ?>/public/tournament_admin_toggle_inscripcion.php';
const BUSCAR_INScribir_API = '<?= app_base_url() ?>/public/api/buscar_inscribir_sitio.php';

document.addEventListener('DOMContentLoaded', function() {
    // Funcionalidad de mover jugadores entre listados
    const tbodyDisponibles = document.getElementById('tbody_disponibles');
    const tbodyInscritos = document.getElementById('tbody_inscritos');
    
    // Click en disponible -> inscribir
    if (tbodyDisponibles) {
        tbodyDisponibles.addEventListener('click', function(e) {
            const row = e.target.closest('tr');
            if (row && row.dataset.id) {
                const idUsuario = parseInt(row.dataset.id);
                const nombre = row.dataset.nombre;
                const cedula = row.dataset.cedula || '';
                const clubId = row.dataset.clubId || '';
                
                // Inscribir con estatus por defecto
                inscribirJugador(idUsuario, nombre, cedula, clubId, ESTATUS_DEFAULT, row);
            }
        });
    }
    
    // Click en inscrito -> desinscribir
    if (tbodyInscritos) {
        tbodyInscritos.addEventListener('click', function(e) {
            const row = e.target.closest('tr');
            if (row && row.dataset.id) {
                const idUsuario = parseInt(row.dataset.id);
                const nombre = row.dataset.nombre;
                const cedula = row.dataset.cedula || '';
                const clubId = row.dataset.clubId || '';
                
                desinscribirJugador(idUsuario, nombre, cedula, clubId, row);
            }
        });
    }
    
    function inscribirJugador(idUsuario, nombre, cedula, clubId, estatus, rowElement) {
        // Validar que tenemos los datos necesarios
        if (!idUsuario || !TORNEOS_ID) {
            showMessage('Error: Faltan datos necesarios para inscribir', 'danger');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'inscribir');
        formData.append('torneo_id', TORNEOS_ID);
        formData.append('id_usuario', idUsuario);
        if (clubId) {
            formData.append('id_club', clubId);
        }
        formData.append('estatus', estatus);
        formData.append('csrf_token', CSRF_TOKEN);
        
        // Mostrar indicador de carga
        rowElement.style.opacity = '0.5';
        rowElement.style.pointerEvents = 'none';
        
        fetch(API_URL, {
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
            rowElement.style.opacity = '1';
            rowElement.style.pointerEvents = 'auto';
            
            if (data.success) {
                // Mover fila de disponibles a inscritos
                const newRow = rowElement.cloneNode(true);
                newRow.style.animation = 'fadeIn 0.3s';
                tbodyInscritos.appendChild(newRow);
                rowElement.remove();
                
                // Actualizar contadores
                updateCounters();
                
                // Mostrar mensaje de éxito
                showMessage('Jugador inscrito exitosamente', 'success');
            } else {
                showMessage(data.error || 'Error al inscribir jugador', 'danger');
            }
        })
        .catch(error => {
            rowElement.style.opacity = '1';
            rowElement.style.pointerEvents = 'auto';
            console.error('Error:', error);
            showMessage('Error al inscribir jugador: ' + error.message, 'danger');
        });
    }
    
    function desinscribirJugador(idUsuario, nombre, cedula, clubId, rowElement) {
        // Validar que tenemos los datos necesarios
        if (!idUsuario || !TORNEOS_ID) {
            showMessage('Error: Faltan datos necesarios para desinscribir', 'danger');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'desinscribir');
        formData.append('torneo_id', TORNEOS_ID);
        formData.append('id_usuario', idUsuario);
        formData.append('csrf_token', CSRF_TOKEN);
        
        // Mostrar indicador de carga
        rowElement.style.opacity = '0.5';
        rowElement.style.pointerEvents = 'none';
        
        fetch(API_URL, {
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
            rowElement.style.opacity = '1';
            rowElement.style.pointerEvents = 'auto';
            
            if (data.success) {
                // Mover fila de inscritos a disponibles
                const newRow = rowElement.cloneNode(true);
                newRow.style.animation = 'fadeIn 0.3s';
                tbodyDisponibles.appendChild(newRow);
                rowElement.remove();
                
                // Actualizar contadores
                updateCounters();
                
                // Mostrar mensaje de éxito
                showMessage('Jugador desinscrito exitosamente', 'success');
            } else {
                showMessage(data.error || 'Error al desinscribir jugador', 'danger');
            }
        })
        .catch(error => {
            rowElement.style.opacity = '1';
            rowElement.style.pointerEvents = 'auto';
            console.error('Error:', error);
            showMessage('Error al desinscribir jugador: ' + error.message, 'danger');
        });
    }
    
    function updateCounters() {
        const countDisponibles = document.getElementById('count_disponibles');
        const countInscritos = document.getElementById('count_inscritos');
        
        if (countDisponibles) {
            countDisponibles.textContent = tbodyDisponibles.children.length;
        }
        if (countInscritos) {
            countInscritos.textContent = tbodyInscritos.children.length;
        }
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
    
    // --- Búsqueda por nacionalidad + cédula (on blur, expedita) ---
    const selectNacionalidad = document.getElementById('select_nacionalidad_cedula');
    const inputCedula = document.getElementById('input_cedula');
    const mensajeForm = document.getElementById('mensaje_formulario_cedula');
    const resultadoBusqueda = document.getElementById('resultado_busqueda');
    const infoUsuario = document.getElementById('info_usuario_encontrado');
    const wrapAcciones = document.getElementById('wrap_acciones_cedula');
    const wrapEstatus = document.getElementById('wrap_estatus_cedula');
    const wrapBtnInscribir = document.getElementById('wrap_btn_inscribir_cedula');
    const btnInscribirCedula = document.getElementById('btn_inscribir_cedula');
    const formNuevo = document.getElementById('form_nuevo_usuario_inscribir');
    const btnRegistrarInscribir = document.getElementById('btn_registrar_inscribir');
    let usuarioEncontrado = null;

    function mostrarMensajeForm(html, tipo) {
        if (!mensajeForm) return;
        mensajeForm.innerHTML = html;
        mensajeForm.className = 'mb-3 alert alert-' + (tipo || 'info');
        mensajeForm.classList.remove('d-none');
    }

    function limpiarMensajeForm() {
        if (mensajeForm) {
            mensajeForm.innerHTML = '';
            mensajeForm.classList.add('d-none');
        }
    }

    function limpiarBusquedaCedula() {
        inputCedula.value = '';
        if (selectNacionalidad) {
            selectNacionalidad.value = 'V';
        }
        resultadoBusqueda.classList.add('d-none');
        wrapAcciones.classList.add('d-none');
        wrapEstatus.classList.add('d-none');
        wrapBtnInscribir.classList.add('d-none');
        formNuevo.classList.add('d-none');
        limpiarMensajeForm();
        usuarioEncontrado = null;
        if (selectNacionalidad) {
            selectNacionalidad.focus();
        }
    }

    function rellenarFormularioDatos(nac, num, p) {
        p = p || {};
        document.getElementById('form_nac').value = p.nacionalidad || nac;
        document.getElementById('form_cedula').value = p.cedula || num;
        document.getElementById('form_nombre').value = p.nombre || '';
        document.getElementById('form_fechnac').value = p.fechnac || '';
        document.getElementById('form_sexo').value = (p.sexo || 'M').toUpperCase();
        document.getElementById('form_telefono').value = p.telefono || p.celular || '';
        document.getElementById('form_email').value = p.email || '';
    }

    function buscarOnBlur() {
        const nac = (selectNacionalidad && selectNacionalidad.value) ? selectNacionalidad.value : 'V';
        const num = (inputCedula.value || '').replace(/\D/g, '');
        if (num.length < 4) {
            return;
        }
        resultadoBusqueda.classList.add('d-none');
        wrapAcciones.classList.add('d-none');
        wrapBtnInscribir.classList.add('d-none');
        formNuevo.classList.add('d-none');
        limpiarMensajeForm();
        usuarioEncontrado = null;

        var url = BUSCAR_INScribir_API + '?torneo_id=' + TORNEOS_ID + '&nacionalidad=' + encodeURIComponent(nac) + '&cedula=' + encodeURIComponent(num);

        if (typeof Swal !== 'undefined') {
            Swal.fire({ title: 'Buscando...', allowOutsideClick: false, didOpen: function() { Swal.showLoading(); } });
        } else {
            mostrarMensajeForm('<i class="fas fa-spinner fa-spin me-2"></i>Buscando (inscritos → usuarios → base externa)...', 'info');
        }

        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (typeof Swal !== 'undefined') {
                    Swal.close();
                } else {
                    limpiarMensajeForm();
                }
                if (!data.success) {
                    var msg = data.mensaje || 'No se pudo realizar la búsqueda.';
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Error', text: msg });
                    } else {
                        mostrarMensajeForm('<strong>Error:</strong> ' + msg, 'danger');
                    }
                    return;
                }
                // NIVEL 1: Ya inscrito (CRÍTICO) — SweetAlert bloqueante, limpieza solo al cerrar
                if (data.resultado === 'ya_inscrito') {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'info',
                            title: 'Atención',
                            text: 'El jugador ya se encuentra inscrito en este evento.'
                        }).then(function(result) {
                            inputCedula.value = '';
                            if (selectNacionalidad) {
                                selectNacionalidad.value = 'V';
                                selectNacionalidad.focus();
                            }
                            limpiarMensajeForm();
                        });
                    } else {
                        inputCedula.value = '';
                        if (selectNacionalidad) { selectNacionalidad.value = 'V'; selectNacionalidad.focus(); }
                        mostrarMensajeForm('Jugador ya inscrito en este torneo.', 'warning');
                    }
                    return;
                }
                // NIVEL 2 y 3: Usuario local o base externa — SweetAlert éxito, luego autocompletar y foco al siguiente vacío
                if (data.resultado === 'usuario' || data.resultado === 'persona_externa') {
                    var esExterno = data.resultado === 'persona_externa';
                    var p = esExterno ? (data.persona || {}) : data.usuario;
                    rellenarFormularioDatos(nac, num, p);
                    if (data.resultado === 'usuario') {
                        usuarioEncontrado = data.usuario;
                        if (btnRegistrarInscribir) { btnRegistrarInscribir.classList.add('d-none'); }
                        resultadoBusqueda.classList.remove('d-none');
                        infoUsuario.innerHTML = '<div class="alert alert-success mb-0"><strong><i class="fas fa-check-circle me-2"></i>Usuario encontrado</strong><br>ID: ' + usuarioEncontrado.id + ' &middot; ' + (usuarioEncontrado.nombre || usuarioEncontrado.username || '') + '</div>';
                        wrapAcciones.classList.remove('d-none');
                        wrapEstatus.classList.remove('d-none');
                        wrapBtnInscribir.classList.remove('d-none');
                    } else {
                        if (btnRegistrarInscribir) { btnRegistrarInscribir.classList.remove('d-none'); }
                    }
                    formNuevo.classList.remove('d-none');
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Jugador Localizado',
                            text: 'Datos cargados correctamente.',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(function() {
                            var tel = document.getElementById('form_telefono');
                            var eml = document.getElementById('form_email');
                            if (tel && !tel.value.trim() && eml) {
                                tel.focus();
                            } else if (eml && !eml.value.trim()) {
                                eml.focus();
                            } else if (tel) {
                                tel.focus();
                            }
                        });
                    } else {
                        var tel = document.getElementById('form_telefono');
                        var eml = document.getElementById('form_email');
                        if (tel) { setTimeout(function() { tel.focus(); }, 100); }
                        if (eml) { setTimeout(function() { eml.focus(); }, 500); }
                    }
                    return;
                }
                // NIVEL 4: No encontrado — SweetAlert question, luego formulario manual (mantener Cédula/Nac)
                if (data.resultado === 'no_encontrado') {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'question',
                            title: 'Sin registros',
                            text: 'El jugador no existe. Por favor, complete los datos manualmente.'
                        }).then(function() {
                            rellenarFormularioDatos(nac, num, {});
                            formNuevo.classList.remove('d-none');
                            if (btnRegistrarInscribir) { btnRegistrarInscribir.classList.remove('d-none'); }
                            var nom = document.getElementById('form_nombre');
                            if (nom) { nom.focus(); }
                        });
                    } else {
                        rellenarFormularioDatos(nac, num, {});
                        formNuevo.classList.remove('d-none');
                        if (btnRegistrarInscribir) { btnRegistrarInscribir.classList.remove('d-none'); }
                        var nom = document.getElementById('form_nombre');
                        if (nom) { setTimeout(function() { nom.focus(); }, 100); }
                    }
                    return;
                }
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'info', title: 'Sin resultados', text: data.mensaje || 'Sin resultados.' });
                } else {
                    mostrarMensajeForm((data.mensaje || 'Sin resultados.'), 'secondary');
                }
            })
            .catch(function(err) {
                console.error(err);
                if (typeof Swal !== 'undefined') {
                    Swal.close();
                    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar. Revise la consola.' });
                } else {
                    mostrarMensajeForm('<strong>Error:</strong> No se pudo conectar. Revise la consola.', 'danger');
                }
            });
    }

    if (inputCedula) {
        inputCedula.addEventListener('blur', function() {
            if (formNuevo.classList.contains('d-none')) {
                buscarOnBlur();
            }
        });
        inputCedula.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                buscarOnBlur();
            }
        });
    }

    if (document.getElementById('btn_otra_busqueda_cedula')) {
        document.getElementById('btn_otra_busqueda_cedula').addEventListener('click', limpiarBusquedaCedula);
    }

    if (btnInscribirCedula) {
        btnInscribirCedula.addEventListener('click', function() {
            if (!usuarioEncontrado || !usuarioEncontrado.id) {
                return;
            }
            var clubId = document.getElementById('select_club_cedula').value || '';
            var estatus = document.getElementById('select_estatus_cedula').value || '1';
            var fd = new FormData();
            fd.append('action', 'inscribir');
            fd.append('torneo_id', TORNEOS_ID);
            fd.append('id_usuario', usuarioEncontrado.id);
            fd.append('id_club', clubId);
            fd.append('estatus', estatus);
            fd.append('csrf_token', CSRF_TOKEN);
            btnInscribirCedula.disabled = true;
            fetch(API_URL, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    btnInscribirCedula.disabled = false;
                    if (data.success) {
                        agregarFilaInscrito(usuarioEncontrado.id, usuarioEncontrado.nombre || usuarioEncontrado.username || 'Usuario', inputCedula.value.trim(), clubId);
                        limpiarBusquedaCedula();
                        showMessage('Jugador inscrito exitosamente', 'success');
                        updateCounters();
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'No se pudo inscribir.' });
                        } else {
                            mostrarMensajeForm('<strong>Error:</strong> ' + (data.error || ''), 'danger');
                        }
                    }
                })
                .catch(function(err) {
                    btnInscribirCedula.disabled = false;
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                    } else {
                        mostrarMensajeForm('<strong>Error:</strong> ' + err.message, 'danger');
                    }
                });
        });
    }

    if (document.getElementById('btn_registrar_inscribir')) {
        document.getElementById('btn_registrar_inscribir').addEventListener('click', function() {
            var nac = document.getElementById('form_nac').value;
            var ced = (document.getElementById('form_cedula').value || '').replace(/\D/g, '');
            var nom = (document.getElementById('form_nombre').value || '').trim();
            if (ced.length < 4 || nom.length < 2) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'warning', title: 'Datos obligatorios', text: 'Cédula (mín. 4 dígitos) y nombre son obligatorios.' });
                } else {
                    mostrarMensajeForm('Cédula (mín. 4 dígitos) y nombre son obligatorios.', 'danger');
                }
                return;
            }
            var fd = new FormData();
            fd.append('action', 'registrar_inscribir');
            fd.append('torneo_id', TORNEOS_ID);
            fd.append('csrf_token', CSRF_TOKEN);
            fd.append('nacionalidad', nac);
            fd.append('cedula', ced);
            fd.append('nombre', nom);
            fd.append('fechnac', document.getElementById('form_fechnac').value || '');
            fd.append('sexo', document.getElementById('form_sexo').value || 'M');
            fd.append('telefono', document.getElementById('form_telefono').value || '');
            fd.append('email', document.getElementById('form_email').value || '');
            fd.append('id_club', document.getElementById('form_club').value || '');
            var btn = document.getElementById('btn_registrar_inscribir');
            btn.disabled = true;
            fetch(API_URL, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    btn.disabled = false;
                    if (data.success) {
                        agregarFilaInscrito(data.id_usuario, nom, nac + ced, document.getElementById('form_club').value || '');
                        limpiarBusquedaCedula();
                        showMessage(data.message || 'Usuario registrado e inscrito.', 'success');
                        updateCounters();
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'No se pudo registrar.' });
                        } else {
                            mostrarMensajeForm('<strong>Error:</strong> ' + (data.error || ''), 'danger');
                        }
                    }
                })
                .catch(function(err) {
                    btn.disabled = false;
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                    } else {
                        mostrarMensajeForm('<strong>Error:</strong> ' + err.message, 'danger');
                    }
                });
        });
    }

    if (document.getElementById('btn_cancelar_form_nuevo')) {
        document.getElementById('btn_cancelar_form_nuevo').addEventListener('click', function() {
            formNuevo.classList.add('d-none');
            mensajeForm.innerHTML = '';
            mensajeForm.classList.add('d-none');
        });
    }

    function agregarFilaInscrito(id, nombre, cedula, clubId) {
        var newRow = document.createElement('tr');
        newRow.style.cursor = 'pointer';
        newRow.className = 'table-row-hover';
        newRow.dataset.id = id;
        newRow.dataset.nombre = nombre;
        newRow.dataset.cedula = cedula;
        newRow.dataset.clubId = clubId || '';
        newRow.style.animation = 'fadeIn 0.3s';
        newRow.innerHTML = '<td><strong>' + (nombre || '') + '</strong></td><td><code>' + id + '</code></td>';
        tbodyInscritos.appendChild(newRow);
    }
});
</script>
