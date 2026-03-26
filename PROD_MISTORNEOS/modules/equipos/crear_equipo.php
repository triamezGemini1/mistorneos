<?php
/**
 * Formulario para Crear Equipo de 4 Jugadores
 * Incluye validación en tiempo real de disponibilidad
 */

session_start();

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/EquiposHelper.php';

try {
    $pdo = DB::pdo();
    
    $torneo_id = $_SESSION['torneo_id'];
    $club_id = $_SESSION['club_id'];
    
    // Obtener información del torneo
    $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener información del club
    $stmt = $pdo->prepare("SELECT * FROM clubes WHERE id = ?");
    $stmt->execute([$club_id]);
    $club = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener próximo código
    $proximoCodigo = EquiposHelper::getProximoCodigo($torneo_id, $club_id);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Equipo - <?= htmlspecialchars($torneo['nombre']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .main-container {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            margin: 20px auto;
            max-width: 900px;
        }
        
        .header-section {
            background: var(--primary-gradient);
            color: white;
            padding: 25px 30px;
            border-radius: 20px 20px 0 0;
        }
        
        .jugador-card {
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .jugador-card:hover {
            border-color: #667eea;
        }
        
        .jugador-card.valid {
            border-color: #28a745;
            background: rgba(40, 167, 69, 0.05);
        }
        
        .jugador-card.invalid {
            border-color: #dc3545;
            background: rgba(220, 53, 69, 0.05);
        }
        
        .jugador-number {
            position: absolute;
            top: -15px;
            left: 20px;
            background: var(--primary-gradient);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
        }
        
        .jugador-number.capitan {
            background: linear-gradient(135deg, #f5af19 0%, #f12711 100%);
        }
        
        .capitan-badge {
            position: absolute;
            top: -10px;
            right: 20px;
            background: #ffc107;
            color: #000;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .codigo-preview {
            background: rgba(102, 126, 234, 0.1);
            border: 2px dashed #667eea;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
        }
        
        .codigo-preview h2 {
            font-family: 'Courier New', monospace;
            font-size: 2.5rem;
            color: #667eea;
            margin: 0;
            letter-spacing: 5px;
        }
        
        .btn-guardar {
            background: var(--success-gradient);
            border: none;
            padding: 15px 50px;
            font-size: 1.2rem;
            border-radius: 50px;
            transition: all 0.3s ease;
        }
        
        .btn-guardar:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 30px rgba(17, 153, 142, 0.4);
        }
        
        .btn-guardar:disabled {
            background: #6c757d;
            transform: none;
            box-shadow: none;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-indicator.loading {
            background: #ffc107;
            animation: pulse 1s infinite;
        }
        
        .status-indicator.valid {
            background: #28a745;
        }
        
        .status-indicator.invalid {
            background: #dc3545;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .feedback-message {
            font-size: 0.875rem;
            margin-top: 5px;
            padding: 5px 10px;
            border-radius: 5px;
        }
        
        .feedback-message.success {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
        }
        
        .feedback-message.error {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="main-container">
        <!-- Header -->
        <div class="header-section">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1"><i class="bi bi-plus-circle me-2"></i>Crear Nuevo Equipo</h2>
                    <p class="mb-0 opacity-75"><?= htmlspecialchars($torneo['nombre']) ?></p>
                </div>
                <a href="index.php" class="btn btn-outline-light">
                    <i class="bi bi-arrow-left me-1"></i>Volver
                </a>
            </div>
        </div>
        
        <div class="p-4">
            <!-- Mensaje de error global -->
            <div id="mensaje-global" class="alert d-none"></div>
            
            <form id="formEquipo" method="POST" action="guardar_equipo.php">
                <!-- Nombre del Equipo -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">
                            <i class="bi bi-people-fill me-1"></i>Nombre del Equipo *
                        </label>
                        <input type="text" name="nombre_equipo" id="nombre_equipo" 
                               class="form-control form-control-lg" 
                               placeholder="Ej: Los Invencibles" 
                               required maxlength="100"
                               style="text-transform: uppercase;">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">
                            <i class="bi bi-upc me-1"></i>Código Asignado
                        </label>
                        <div class="codigo-preview">
                            <h2 id="codigo-preview"><?= $proximoCodigo ?></h2>
                            <small class="text-muted">Se genera automáticamente</small>
                        </div>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <h4 class="mb-4">
                    <i class="bi bi-person-plus me-2"></i>Jugadores del Equipo (4 requeridos)
                </h4>
                
                <!-- Jugador 1 - Capitán -->
                <div class="jugador-card" id="jugador-card-1">
                    <span class="jugador-number capitan">1</span>
                    <span class="capitan-badge"><i class="bi bi-star-fill me-1"></i>Capitán</span>
                    
                    <div class="row mt-3">
                        <div class="col-md-2">
                            <label class="form-label">Nacionalidad</label>
                            <select name="nacionalidad_1" class="form-select nacionalidad-field">
                                <option value="V" selected>V</option>
                                <option value="E">E</option>
                                <option value="J">J</option>
                                <option value="P">P</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Cédula *</label>
                            <input type="text" name="cedula_1" id="cedula_1" 
                                   class="form-control cedula-jugador" 
                                   data-jugador="1"
                                   placeholder="12345678" required>
                            <div class="feedback-jugador" id="feedback-1"></div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Nombre Completo *</label>
                            <input type="text" name="nombre_1" id="nombre_1" 
                                   class="form-control nombre-jugador" 
                                   placeholder="Se cargará automáticamente" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Sexo *</label>
                            <select name="sexo_1" id="sexo_1" class="form-select sexo-jugador" required>
                                <option value="">...</option>
                                <option value="M">M</option>
                                <option value="F">F</option>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" name="es_capitan_1" value="1">
                </div>
                
                <!-- Jugador 2 -->
                <div class="jugador-card" id="jugador-card-2">
                    <span class="jugador-number">2</span>
                    
                    <div class="row mt-3">
                        <div class="col-md-2">
                            <label class="form-label">Nacionalidad</label>
                            <select name="nacionalidad_2" class="form-select nacionalidad-field">
                                <option value="V" selected>V</option>
                                <option value="E">E</option>
                                <option value="J">J</option>
                                <option value="P">P</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Cédula *</label>
                            <input type="text" name="cedula_2" id="cedula_2" 
                                   class="form-control cedula-jugador" 
                                   data-jugador="2"
                                   placeholder="12345678" required>
                            <div class="feedback-jugador" id="feedback-2"></div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Nombre Completo *</label>
                            <input type="text" name="nombre_2" id="nombre_2" 
                                   class="form-control nombre-jugador" 
                                   placeholder="Se cargará automáticamente" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Sexo *</label>
                            <select name="sexo_2" id="sexo_2" class="form-select sexo-jugador" required>
                                <option value="">...</option>
                                <option value="M">M</option>
                                <option value="F">F</option>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" name="es_capitan_2" value="0">
                </div>
                
                <!-- Jugador 3 -->
                <div class="jugador-card" id="jugador-card-3">
                    <span class="jugador-number">3</span>
                    
                    <div class="row mt-3">
                        <div class="col-md-2">
                            <label class="form-label">Nacionalidad</label>
                            <select name="nacionalidad_3" class="form-select nacionalidad-field">
                                <option value="V" selected>V</option>
                                <option value="E">E</option>
                                <option value="J">J</option>
                                <option value="P">P</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Cédula *</label>
                            <input type="text" name="cedula_3" id="cedula_3" 
                                   class="form-control cedula-jugador" 
                                   data-jugador="3"
                                   placeholder="12345678" required>
                            <div class="feedback-jugador" id="feedback-3"></div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Nombre Completo *</label>
                            <input type="text" name="nombre_3" id="nombre_3" 
                                   class="form-control nombre-jugador" 
                                   placeholder="Se cargará automáticamente" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Sexo *</label>
                            <select name="sexo_3" id="sexo_3" class="form-select sexo-jugador" required>
                                <option value="">...</option>
                                <option value="M">M</option>
                                <option value="F">F</option>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" name="es_capitan_3" value="0">
                </div>
                
                <!-- Jugador 4 -->
                <div class="jugador-card" id="jugador-card-4">
                    <span class="jugador-number">4</span>
                    
                    <div class="row mt-3">
                        <div class="col-md-2">
                            <label class="form-label">Nacionalidad</label>
                            <select name="nacionalidad_4" class="form-select nacionalidad-field">
                                <option value="V" selected>V</option>
                                <option value="E">E</option>
                                <option value="J">J</option>
                                <option value="P">P</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Cédula *</label>
                            <input type="text" name="cedula_4" id="cedula_4" 
                                   class="form-control cedula-jugador" 
                                   data-jugador="4"
                                   placeholder="12345678" required>
                            <div class="feedback-jugador" id="feedback-4"></div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Nombre Completo *</label>
                            <input type="text" name="nombre_4" id="nombre_4" 
                                   class="form-control nombre-jugador" 
                                   placeholder="Se cargará automáticamente" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Sexo *</label>
                            <select name="sexo_4" id="sexo_4" class="form-select sexo-jugador" required>
                                <option value="">...</option>
                                <option value="M">M</option>
                                <option value="F">F</option>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" name="es_capitan_4" value="0">
                </div>
                
                <!-- Resumen de validación -->
                <div class="card bg-light mb-4">
                    <div class="card-body">
                        <h5 class="mb-3"><i class="bi bi-check2-square me-2"></i>Estado de Validación</h5>
                        <div class="row">
                            <div class="col-md-3">
                                <div id="status-1" class="d-flex align-items-center">
                                    <span class="status-indicator"></span>
                                    <span>Jugador 1 (Capitán)</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div id="status-2" class="d-flex align-items-center">
                                    <span class="status-indicator"></span>
                                    <span>Jugador 2</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div id="status-3" class="d-flex align-items-center">
                                    <span class="status-indicator"></span>
                                    <span>Jugador 3</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div id="status-4" class="d-flex align-items-center">
                                    <span class="status-indicator"></span>
                                    <span>Jugador 4</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Botones -->
                <div class="text-center">
                    <button type="submit" id="btnGuardar" class="btn btn-success btn-guardar" disabled>
                        <i class="bi bi-check-lg me-2"></i>Crear Equipo
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary btn-lg ms-2">
                        <i class="bi bi-x-lg me-1"></i>Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
<script>
const TORNEO_ID = <?= $torneo_id ?>;
const CLUB_ID = <?= $club_id ?>;

// Estado de validación de cada jugador
const estadoJugadores = {
    1: { validado: false, disponible: false, datos: null },
    2: { validado: false, disponible: false, datos: null },
    3: { validado: false, disponible: false, datos: null },
    4: { validado: false, disponible: false, datos: null }
};

// Verificar si todos los jugadores están validados y disponibles
function verificarFormularioCompleto() {
    const nombreEquipo = document.getElementById('nombre_equipo').value.trim();
    let todosValidos = nombreEquipo.length >= 3;
    
    for (let i = 1; i <= 4; i++) {
        if (!estadoJugadores[i].validado || !estadoJugadores[i].disponible) {
            todosValidos = false;
            break;
        }
    }
    
    document.getElementById('btnGuardar').disabled = !todosValidos;
}

// Actualizar indicador de estado
function actualizarEstado(jugadorNum, estado) {
    const statusDiv = document.getElementById(`status-${jugadorNum}`);
    const indicator = statusDiv.querySelector('.status-indicator');
    const card = document.getElementById(`jugador-card-${jugadorNum}`);
    
    indicator.className = 'status-indicator';
    card.classList.remove('valid', 'invalid');
    
    switch(estado) {
        case 'loading':
            indicator.classList.add('loading');
            break;
        case 'valid':
            indicator.classList.add('valid');
            card.classList.add('valid');
            break;
        case 'invalid':
            indicator.classList.add('invalid');
            card.classList.add('invalid');
            break;
    }
}

// Mostrar feedback del jugador
function mostrarFeedback(jugadorNum, mensaje, tipo) {
    const feedback = document.getElementById(`feedback-${jugadorNum}`);
    feedback.className = `feedback-message ${tipo}`;
    feedback.innerHTML = mensaje;
}

// Limpiar feedback
function limpiarFeedback(jugadorNum) {
    const feedback = document.getElementById(`feedback-${jugadorNum}`);
    feedback.className = 'feedback-jugador';
    feedback.innerHTML = '';
}

// Buscar persona y validar disponibilidad para equipo
async function buscarYValidarJugador(jugadorNum) {
    const cedulaField = document.getElementById(`cedula_${jugadorNum}`);
    const nombreField = document.getElementById(`nombre_${jugadorNum}`);
    const sexoField = document.getElementById(`sexo_${jugadorNum}`);
    const nacionalidad = cedulaField.closest('.row').querySelector('.nacionalidad-field').value;
    
    let cedula = cedulaField.value.trim();
    
    // Limpiar si está vacío
    if (!cedula) {
        estadoJugadores[jugadorNum] = { validado: false, disponible: false, datos: null };
        limpiarFeedback(jugadorNum);
        actualizarEstado(jugadorNum, '');
        verificarFormularioCompleto();
        return;
    }
    
    // Limpiar cédula
    cedula = cedula.replace(/^[VEJP]/i, '');
    cedulaField.value = cedula;
    
    // Verificar que no esté duplicada en el mismo formulario
    for (let i = 1; i <= 4; i++) {
        if (i !== jugadorNum) {
            const otraCedula = document.getElementById(`cedula_${i}`).value.trim();
            if (otraCedula === cedula) {
                estadoJugadores[jugadorNum] = { validado: true, disponible: false, datos: null };
                mostrarFeedback(jugadorNum, '❌ Esta cédula ya está asignada a otro jugador del equipo', 'error');
                actualizarEstado(jugadorNum, 'invalid');
                verificarFormularioCompleto();
                return;
            }
        }
    }
    
    // Mostrar estado de carga
    actualizarEstado(jugadorNum, 'loading');
    mostrarFeedback(jugadorNum, '<i class="bi bi-hourglass-split me-1"></i>Verificando...', 'loading');
    
    try {
        // 1. Primero verificar disponibilidad en equipos
        const respEquipo = await fetch(`<?= rtrim(AppHelpers::getPublicPath(), '/') ?>/api/verificar_jugador_equipo.php?torneo_id=${TORNEO_ID}&cedula=${cedula}`);
        const dataEquipo = await respEquipo.json();
        
        if (!dataEquipo.disponible) {
            // Ya está en otro equipo
            estadoJugadores[jugadorNum] = { validado: true, disponible: false, datos: null };
            mostrarFeedback(jugadorNum, `❌ ${dataEquipo.mensaje}`, 'error');
            actualizarEstado(jugadorNum, 'invalid');
            nombreField.value = '';
            sexoField.value = '';
            verificarFormularioCompleto();
            return;
        }
        
        // 2. Si está disponible, buscar datos de la persona
        const respPersona = await fetch(`<?= rtrim(AppHelpers::getPublicPath(), '/') ?>api/search_user_persona.php?nacionalidad=${nacionalidad}&cedula=${cedula}`);
        const dataPersona = await respPersona.json();
        
        if (dataPersona.ya_inscrito) {
            // Ya está inscrito individualmente en el torneo - está bien para equipos
            // Pero usar sus datos
        }
        
        if (dataPersona.encontrado && dataPersona.persona) {
            // Encontrado en BD
            nombreField.value = dataPersona.persona.nombre || '';
            nombreField.readOnly = true;
            
            if (dataPersona.persona.sexo) {
                sexoField.value = dataPersona.persona.sexo.toUpperCase();
            }
            
            estadoJugadores[jugadorNum] = { 
                validado: true, 
                disponible: true, 
                datos: dataPersona.persona 
            };
            
            const fuente = dataPersona.fuente === 'local' ? 'BD Local' : 'BD Externa';
            mostrarFeedback(jugadorNum, `✅ ${dataPersona.persona.nombre} (${fuente})`, 'success');
            actualizarEstado(jugadorNum, 'valid');
            
        } else if (dataEquipo.jugador) {
            // Tenemos datos del verificador de equipos
            nombreField.value = dataEquipo.jugador.nombre || '';
            nombreField.readOnly = true;
            
            estadoJugadores[jugadorNum] = { 
                validado: true, 
                disponible: true, 
                datos: dataEquipo.jugador 
            };
            
            mostrarFeedback(jugadorNum, `✅ ${dataEquipo.jugador.nombre}`, 'success');
            actualizarEstado(jugadorNum, 'valid');
            
        } else {
            // No encontrado en ninguna BD - permitir ingreso manual
            nombreField.readOnly = false;
            nombreField.value = '';
            nombreField.placeholder = 'Ingrese nombre manualmente';
            nombreField.focus();
            
            estadoJugadores[jugadorNum] = { 
                validado: true, 
                disponible: true, 
                datos: null 
            };
            
            mostrarFeedback(jugadorNum, '⚠️ Cédula disponible. Ingrese el nombre manualmente.', 'success');
            actualizarEstado(jugadorNum, 'valid');
        }
        
    } catch (error) {
        console.error('Error al verificar jugador:', error);
        mostrarFeedback(jugadorNum, '❌ Error de conexión', 'error');
        actualizarEstado(jugadorNum, 'invalid');
        estadoJugadores[jugadorNum] = { validado: false, disponible: false, datos: null };
    }
    
    verificarFormularioCompleto();
}

// Evento: Al salir del campo cédula
document.querySelectorAll('.cedula-jugador').forEach(campo => {
    campo.addEventListener('blur', function() {
        const jugadorNum = parseInt(this.dataset.jugador);
        buscarYValidarJugador(jugadorNum);
    });
    
    campo.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const jugadorNum = parseInt(this.dataset.jugador);
            buscarYValidarJugador(jugadorNum);
        }
    });
});

// Evento: Al cambiar nombre del equipo
document.getElementById('nombre_equipo').addEventListener('input', verificarFormularioCompleto);

// Evento: Al cambiar nombre del jugador (si es editable)
document.querySelectorAll('.nombre-jugador').forEach(campo => {
    campo.addEventListener('input', function() {
        const num = this.id.replace('nombre_', '');
        if (estadoJugadores[num].validado && this.value.trim().length >= 3) {
            estadoJugadores[num].disponible = true;
        }
        verificarFormularioCompleto();
    });
});

// Evento: Envío del formulario
document.getElementById('formEquipo').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const btnGuardar = document.getElementById('btnGuardar');
    const mensajeGlobal = document.getElementById('mensaje-global');
    
    btnGuardar.disabled = true;
    btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';
    
    try {
        const formData = new FormData(this);
        
        const response = await fetch('guardar_equipo.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            mensajeGlobal.className = 'alert alert-success';
            mensajeGlobal.innerHTML = `<i class="bi bi-check-circle me-2"></i>${data.message}`;
            mensajeGlobal.classList.remove('d-none');
            
            // Redirigir después de 2 segundos
            setTimeout(() => {
                window.location.href = 'index.php?success=' + encodeURIComponent(data.message);
            }, 2000);
        } else {
            mensajeGlobal.className = 'alert alert-danger';
            mensajeGlobal.innerHTML = `<i class="bi bi-exclamation-circle me-2"></i>${data.message}`;
            mensajeGlobal.classList.remove('d-none');
            
            btnGuardar.disabled = false;
            btnGuardar.innerHTML = '<i class="bi bi-check-lg me-2"></i>Crear Equipo';
        }
        
    } catch (error) {
        console.error('Error:', error);
        mensajeGlobal.className = 'alert alert-danger';
        mensajeGlobal.innerHTML = '<i class="bi bi-exclamation-circle me-2"></i>Error de conexión';
        mensajeGlobal.classList.remove('d-none');
        
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = '<i class="bi bi-check-lg me-2"></i>Crear Equipo';
    }
});

// Convertir nombre de equipo a mayúsculas
document.getElementById('nombre_equipo').addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});
</script>

</body>
</html>









