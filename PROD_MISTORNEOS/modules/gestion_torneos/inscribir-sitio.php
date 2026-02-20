<?php
/**
 * Vista: Inscribir Jugador en Sitio
 */
$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php']);
$base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';

// Extraer datos de la vista
extract($view_data ?? []);

// Verificar que tenemos los datos necesarios
if (!isset($torneo) || !isset($usuarios_disponibles) || !isset($usuarios_inscritos)) {
    echo '<div class="alert alert-danger">Error: No se pudieron cargar los datos necesarios.</div>';
    return;
}

require_once __DIR__ . '/../../lib/InscritosHelper.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-2">
                <i class="fas fa-user-plus text-success"></i> Inscribir Jugador en Sitio
                <small class="text-muted">- <?php echo htmlspecialchars($torneo['nombre']); ?></small>
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>">Gestión de Torneos</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=panel&torneo_id=<?php echo $torneo['id']; ?>"><?php echo htmlspecialchars($torneo['nombre']); ?></a></li>
                    <li class="breadcrumb-item active">Inscribir en Sitio</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-12">
            <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=panel&torneo_id=<?php echo $torneo['id']; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i> Retornar al Panel
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">
                <i class="fas fa-user-plus me-2"></i>Inscribir Jugador en Sitio
            </h5>
        </div>
        <div class="card-body">
            <!-- Pestañas para elegir método de inscripción -->
            <ul class="nav nav-tabs mb-4" id="inscripcionTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="territorio-tab" data-bs-toggle="tab" data-bs-target="#territorio" type="button" role="tab">
                        <i class="fas fa-users me-2"></i>Atletas de Mi Territorio
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
                                                    <th>Club</th>
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
                                                        <td><?= !empty($usuario['club_nombre']) ? htmlspecialchars($usuario['club_nombre']) : '<span class="text-muted">N/A</span>' ?></td>
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
                                                    <th>Club</th>
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
                                                        <td><?= !empty($inscrito['club_nombre']) ? htmlspecialchars($inscrito['club_nombre']) : '<span class="text-muted">N/A</span>' ?></td>
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
                                            <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=panel&torneo_id=<?= $torneo['id'] ?>" 
                                               class="btn btn-secondary">
                                                <i class="fas fa-times me-2"></i>Cancelar
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Club</label>
                                        <select id="select_club_cedula" class="form-select">
                                            <option value="">-- Usar club del usuario encontrado --</option>
                                            <?php foreach ($clubes_disponibles ?? [] as $club): ?>
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
        </div>
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
const TORNEOS_ID = <?= $torneo['id'] ?>;
const CSRF_TOKEN = '<?= htmlspecialchars(CSRF::token(), ENT_QUOTES) ?>';
const ESTATUS_DEFAULT = '1'; // Estatus por defecto: confirmado
const API_URL = '<?php 
if (!function_exists("app_base_url")) {
    require_once __DIR__ . "/../../config/bootstrap.php";
}
echo app_base_url() . "/public/tournament_admin_toggle_inscripcion.php"; 
?>';

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
    
    async function desinscribirJugador(idUsuario, nombre, cedula, clubId, rowElement) {
        // Validar que tenemos los datos necesarios
        if (!idUsuario || !TORNEOS_ID) {
            showMessage('Error: Faltan datos necesarios para desinscribir', 'danger');
            return;
        }
        
        const result = await Swal.fire({
            title: '¿Desinscribir jugador?',
            html: '¿Está seguro de que desea desinscribir a <strong>' + nombre + '</strong>?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, desinscribir',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6c757d'
        });
        
        if (!result.isConfirmed) {
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
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        `;
        
        const cardBody = document.querySelector('.card-body');
        if (cardBody) {
            cardBody.insertBefore(alertDiv, cardBody.firstChild);
            setTimeout(() => alertDiv.remove(), 3000);
        }
    }
    
    // Búsqueda por cédula/ID
    const btnBuscarCedula = document.getElementById('btn_buscar_cedula');
    const btnInscribirCedula = document.getElementById('btn_inscribir_cedula');
    const inputCedula = document.getElementById('input_cedula');
    const resultadoBusqueda = document.getElementById('resultado_busqueda');
    const infoUsuario = document.getElementById('info_usuario_encontrado');
    let usuarioEncontrado = null;
    
    function buscarPorCedula() {
        const valor = inputCedula.value.trim();
        
        if (!valor) {
            Swal.fire({
                icon: 'warning',
                title: 'Campo vacío',
                text: 'Por favor ingrese una cédula o ID de usuario',
                confirmButtonColor: '#667eea'
            });
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
            
            fetch(API_BASE.replace('api/', '') + 'api/search_user_persona.php?cedula=' + encodeURIComponent(valor))
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
        
        fetch(API_BASE.replace('api/', '') + 'api/search_user_persona.php?cedula=' + id)
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
    
    if (inputCedula) {
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
                Swal.fire({
                    icon: 'warning',
                    title: 'Usuario no seleccionado',
                    text: 'Debe buscar un usuario primero',
                    confirmButtonColor: '#667eea'
                });
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
            <td><span class="text-muted">N/A</span></td>
        `;
        tbodyInscritos.appendChild(newRow);
    }
});
</script>

