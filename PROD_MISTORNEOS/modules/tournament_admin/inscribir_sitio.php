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
            
            <!-- Tab: Búsqueda por Cédula -->
            <div class="tab-pane fade" id="cedula" role="tabpanel">
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">
                                        Cédula / ID de Usuario <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="text" 
                                               id="input_cedula" 
                                               class="form-control" 
                                               placeholder="Ej: 12345678 o ID de usuario">
                                    <button type="button" 
                                            class="btn btn-info" 
                                            id="btn_buscar_cedula">
                                        <i class="fas fa-search me-2"></i>Buscar
                                    </button>
                                    <button type="button" 
                                            class="btn btn-success" 
                                            id="btn_inscribir_cedula" 
                                            disabled>
                                        <i class="fas fa-save me-2"></i>Inscribir
                                    </button>
                                    <a href="admin_torneo.php?action=panel&torneo_id=<?= $torneo_id ?>" 
                                       class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Cancelar
                                    </a>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Club</label>
                                    <select id="select_club_cedula" class="form-select">
                                        <option value="">-- Usar club del usuario encontrado --</option>
                                        <?php foreach ($clubes_disponibles as $club): ?>
                                            <option value="<?= $club['id'] ?>">
                                                <?= htmlspecialchars($club['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Estatus Inicial</label>
                                    <select id="select_estatus_cedula" class="form-select">
                                        <?php 
                                        $estatus_options = InscritosHelper::getEstatusFormOptions();
                                        foreach ($estatus_options as $opt): 
                                        ?>
                                            <option value="<?= $opt['value'] ?>" <?= $opt['value'] == 1 ? 'selected' : '' ?>>
                                                <?= $opt['label'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Resultado de la búsqueda -->
                                <div id="resultado_busqueda" style="display: none;">
                                    <div class="card border-info">
                                        <div class="card-body">
                                            <h6 class="card-title">Resultado de la Búsqueda</h6>
                                            <div id="info_usuario_encontrado"></div>
                                        </div>
                                    </div>
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
const SEARCH_API_URL = '<?= app_base_url() ?>/api/search_user_persona.php';

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
    
    // Busqueda por cedula/ID - instantanea (AJAX al escribir)
    const btnBuscarCedula = document.getElementById('btn_buscar_cedula');
    const btnInscribirCedula = document.getElementById('btn_inscribir_cedula');
    const inputCedula = document.getElementById('input_cedula');
    const resultadoBusqueda = document.getElementById('resultado_busqueda');
    const infoUsuario = document.getElementById('info_usuario_encontrado');
    let usuarioEncontrado = null;
    let searchDebounceTimer = null;
    
    function buscarPorCedula() {
        const valor = inputCedula.value.trim();
        
        if (!valor) {
            alert('Por favor ingrese una cédula o ID de usuario');
            return;
        }
        
        // Verificar si es un número (ID de usuario)
        const esId = /^\d+$/.test(valor);
        
        if (esId) {
            // Buscar por ID directamente
            buscarPorId(parseInt(valor));
        } else {
            // Buscar por cédula
            infoUsuario.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Buscando...</div>';
            resultadoBusqueda.style.display = 'block';
            btnInscribirCedula.disabled = true;
            
            fetch(SEARCH_API_URL + '?cedula=' + encodeURIComponent(valor))
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data && data.data.existe_usuario && data.data.usuario_existente) {
                        usuarioEncontrado = data.data.usuario_existente;
                        mostrarUsuarioEncontrado(usuarioEncontrado, valor);
                    } else {
                        infoUsuario.innerHTML = `
                            <div class="alert alert-danger">
                                <strong>No encontrado:</strong> No se encontró un usuario con la cédula ${valor}.
                            </div>
                        `;
                        btnInscribirCedula.disabled = true;
                        usuarioEncontrado = null;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    infoUsuario.innerHTML = `
                        <div class="alert alert-danger">
                            <strong>Error:</strong> No se pudo realizar la búsqueda.
                        </div>
                    `;
                    btnInscribirCedula.disabled = true;
                    usuarioEncontrado = null;
                });
        }
    }
    
    function buscarPorId(id) {
        infoUsuario.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Buscando...</div>';
        resultadoBusqueda.style.display = 'block';
        btnInscribirCedula.disabled = true;
        
        fetch(SEARCH_API_URL + '?cedula=' + encodeURIComponent(String(id)))
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.existe_usuario && data.data.usuario_existente) {
                    usuarioEncontrado = data.data.usuario_existente;
                    mostrarUsuarioEncontrado(usuarioEncontrado, id.toString());
                } else {
                    // Intentar buscar directamente en la BD
                    usuarioEncontrado = { id: id };
                    mostrarUsuarioEncontrado(usuarioEncontrado, id.toString());
                }
            })
            .catch(() => {
                usuarioEncontrado = { id: id };
                mostrarUsuarioEncontrado(usuarioEncontrado, id.toString());
            });
    }
    
    function mostrarUsuarioEncontrado(usuario, identificador) {
        infoUsuario.innerHTML = `
            <div class="alert alert-success">
                <strong><i class="fas fa-check-circle me-2"></i>Usuario encontrado:</strong><br>
                <strong>ID:</strong> ${usuario.id}<br>
                <strong>Nombre:</strong> ${usuario.nombre || usuario.username || 'N/A'}<br>
                <strong>Username:</strong> ${usuario.username || 'N/A'}<br>
                <strong>Identificador:</strong> ${identificador}
            </div>
        `;
        btnInscribirCedula.disabled = false;
    }
    
    if (btnBuscarCedula) {
        btnBuscarCedula.addEventListener('click', buscarPorCedula);
    }
    
    // Busqueda instantanea al escribir (debounce 400ms, minimo 3 caracteres o ID numerico)
    if (inputCedula) {
        inputCedula.addEventListener('input', function() {
            const valor = this.value.trim();
            if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
            if (!valor) {
                resultadoBusqueda.style.display = 'none';
                usuarioEncontrado = null;
                btnInscribirCedula.disabled = true;
                return;
            }
            const esId = /^\d+$/.test(valor);
            const minLen = esId ? 1 : 3;
            if (valor.length < minLen) return;
            searchDebounceTimer = setTimeout(function() {
                buscarPorCedula();
            }, 400);
        });
        inputCedula.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                buscarPorCedula();
            }
        });
    }
    
    // Inscribir desde búsqueda
    if (btnInscribirCedula) {
        btnInscribirCedula.addEventListener('click', function() {
            if (!usuarioEncontrado || !usuarioEncontrado.id) {
                alert('Debe buscar un usuario primero');
                return;
            }
            
            const clubId = document.getElementById('select_club_cedula').value || '';
            const estatus = document.getElementById('select_estatus_cedula').value || '1';
            
            const formData = new FormData();
            formData.append('action', 'inscribir');
            formData.append('torneo_id', TORNEOS_ID);
            formData.append('id_usuario', usuarioEncontrado.id);
            formData.append('id_club', clubId);
            formData.append('estatus', estatus);
            formData.append('csrf_token', CSRF_TOKEN);
            
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
                if (data.success) {
                    // Agregar a listado de inscritos
                    const nombre = usuarioEncontrado.nombre || usuarioEncontrado.username || 'Usuario';
                    const cedula = inputCedula.value.trim();
                    agregarFilaInscrito(usuarioEncontrado.id, nombre, cedula, clubId);
                    
                    // Limpiar búsqueda
                    inputCedula.value = '';
                    resultadoBusqueda.style.display = 'none';
                    btnInscribirCedula.disabled = true;
                    usuarioEncontrado = null;
                    
                    showMessage('Jugador inscrito exitosamente', 'success');
                    updateCounters();
                } else {
                    showMessage(data.error || 'Error al inscribir jugador', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Error al inscribir jugador: ' + error.message, 'danger');
            });
        });
    }
    
    function agregarFilaInscrito(id, nombre, cedula, clubId) {
        const newRow = document.createElement('tr');
        newRow.style.cursor = 'pointer';
        newRow.className = 'table-row-hover';
        newRow.dataset.id = id;
        newRow.dataset.nombre = nombre;
        newRow.dataset.cedula = cedula;
        newRow.dataset.clubId = clubId;
        newRow.style.animation = 'fadeIn 0.3s';
        newRow.innerHTML = `
            <td><strong>${nombre}</strong></td>
            <td><code>${id}</code></td>
        `;
        tbodyInscritos.appendChild(newRow);
    }
});
</script>
