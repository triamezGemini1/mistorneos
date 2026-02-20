<?php
/**
 * Editar Equipo - Agregar/Modificar Jugadores
 */

session_start();

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/EquiposHelper.php';

$equipo_id = (int)($_GET['id'] ?? 0);

if ($equipo_id <= 0) {
    header("Location: index.php?error=" . urlencode("ID de equipo no válido"));
    exit;
}

try {
    $pdo = DB::pdo();
    
    $torneo_id = $_SESSION['torneo_id'];
    $club_id = $_SESSION['club_id'];
    
    // Obtener equipo
    $equipo = EquiposHelper::getEquipoCompleto($equipo_id);
    
    if (!$equipo) {
        header("Location: index.php?error=" . urlencode("Equipo no encontrado"));
        exit;
    }
    
    // Verificar que el equipo pertenece al club y torneo de la sesión
    if ($equipo['id_torneo'] != $torneo_id || $equipo['id_club'] != $club_id) {
        header("Location: index.php?error=" . urlencode("No tiene permisos para editar este equipo"));
        exit;
    }
    
    $jugadores = $equipo['jugadores'];
    $total_jugadores = count($jugadores);
    
    // Crear array indexado por posición
    $jugadoresPorPosicion = [];
    foreach ($jugadores as $j) {
        $jugadoresPorPosicion[$j['posicion_equipo']] = $j;
    }
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Equipo - <?= htmlspecialchars($equipo['nombre_equipo']) ?></title>
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
            position: relative;
        }
        
        .jugador-card.ocupado {
            border-color: #28a745;
            background: rgba(40, 167, 69, 0.05);
        }
        
        .jugador-card.vacio {
            border-color: #ffc107;
            background: rgba(255, 193, 7, 0.05);
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
        
        .jugador-existente {
            display: flex;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-top: 10px;
        }
        
        .jugador-existente .info {
            flex: 1;
        }
        
        .btn-remover {
            background: #dc3545;
            border: none;
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
                    <h2 class="mb-1">
                        <i class="bi bi-pencil-square me-2"></i>
                        <?= htmlspecialchars($equipo['nombre_equipo']) ?>
                    </h2>
                    <p class="mb-0 opacity-75">
                        <span class="badge bg-light text-dark me-2"><?= $equipo['codigo_equipo'] ?></span>
                        <?= $total_jugadores ?>/4 Jugadores
                    </p>
                </div>
                <a href="index.php" class="btn btn-outline-light">
                    <i class="bi bi-arrow-left me-1"></i>Volver
                </a>
            </div>
        </div>
        
        <div class="p-4">
            <!-- Mensaje global -->
            <div id="mensaje-global" class="alert d-none"></div>
            
            <h4 class="mb-4">
                <i class="bi bi-people me-2"></i>Jugadores del Equipo
            </h4>
            
            <?php for ($pos = 1; $pos <= 4; $pos++): 
                $jugador = $jugadoresPorPosicion[$pos] ?? null;
                $esCapitan = $pos === 1;
            ?>
                <div class="jugador-card <?= $jugador ? 'ocupado' : 'vacio' ?>" id="jugador-card-<?= $pos ?>">
                    <span class="jugador-number <?= $esCapitan ? 'capitan' : '' ?>"><?= $pos ?></span>
                    
                    <?php if ($esCapitan): ?>
                        <span class="badge bg-warning text-dark position-absolute" style="top: -10px; right: 20px;">
                            <i class="bi bi-star-fill me-1"></i>Capitán
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($jugador): ?>
                        <!-- Jugador existente -->
                        <div class="jugador-existente mt-3">
                            <div class="info">
                                <h5 class="mb-1"><?= htmlspecialchars($jugador['nombre']) ?></h5>
                                <span class="text-muted">
                                    <i class="bi bi-person-vcard me-1"></i><?= htmlspecialchars($jugador['cedula']) ?>
                                </span>
                            </div>
                            <button type="button" class="btn btn-remover btn-sm text-white" 
                                    onclick="removerJugador(<?= $jugador['id'] ?>, <?= $pos ?>)"
                                    title="Remover jugador">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    <?php else: ?>
                        <!-- Slot vacío - Formulario para agregar -->
                        <form class="form-agregar-jugador mt-3" data-posicion="<?= $pos ?>">
                            <div class="row">
                                <div class="col-md-2">
                                    <label class="form-label">Nac.</label>
                                    <select name="nacionalidad" class="form-select nacionalidad-field">
                                        <option value="V" selected>V</option>
                                        <option value="E">E</option>
                                        <option value="J">J</option>
                                        <option value="P">P</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Cédula</label>
                                    <input type="text" name="cedula" class="form-control cedula-field" 
                                           placeholder="12345678" required>
                                    <div class="feedback-jugador"></div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Nombre</label>
                                    <input type="text" name="nombre" class="form-control nombre-field" 
                                           placeholder="Nombre completo" required>
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label">Sexo</label>
                                    <select name="sexo" class="form-select sexo-field" required>
                                        <option value="">-</option>
                                        <option value="M">M</option>
                                        <option value="F">F</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="bi bi-plus"></i> Agregar
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
            
            <div class="text-center mt-4">
                <a href="index.php" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-lg me-2"></i>Finalizar
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
<script>
const TORNEO_ID = <?= $torneo_id ?>;
const EQUIPO_ID = <?= $equipo_id ?>;

// Buscar persona al salir del campo cédula
document.querySelectorAll('.cedula-field').forEach(campo => {
    campo.addEventListener('blur', async function() {
        const form = this.closest('form');
        const nacionalidad = form.querySelector('.nacionalidad-field').value;
        const nombreField = form.querySelector('.nombre-field');
        const sexoField = form.querySelector('.sexo-field');
        const feedbackDiv = form.querySelector('.feedback-jugador');
        
        let cedula = this.value.trim().replace(/^[VEJP]/i, '');
        this.value = cedula;
        
        if (!cedula) {
            feedbackDiv.innerHTML = '';
            return;
        }
        
        feedbackDiv.innerHTML = '<span class="text-muted"><i class="bi bi-hourglass-split"></i> Verificando...</span>';
        
        try {
            // Verificar disponibilidad
            const respEquipo = await fetch(`<?= rtrim(AppHelpers::getPublicPath(), '/') ?>api/verificar_jugador_equipo.php?torneo_id=${TORNEO_ID}&cedula=${cedula}&equipo_id=${EQUIPO_ID}`);
            const dataEquipo = await respEquipo.json();
            
            if (!dataEquipo.disponible) {
                feedbackDiv.innerHTML = `<span class="text-danger"><i class="bi bi-x-circle"></i> ${dataEquipo.mensaje}</span>`;
                nombreField.value = '';
                return;
            }
            
            // Buscar datos
            const respPersona = await fetch(`<?= rtrim(AppHelpers::getPublicPath(), '/') ?>api/search_user_persona.php?nacionalidad=${nacionalidad}&cedula=${cedula}`);
            const dataPersona = await respPersona.json();
            
            if (dataPersona.encontrado && dataPersona.persona) {
                nombreField.value = dataPersona.persona.nombre || '';
                if (dataPersona.persona.sexo) {
                    sexoField.value = dataPersona.persona.sexo.toUpperCase();
                }
                feedbackDiv.innerHTML = `<span class="text-success"><i class="bi bi-check-circle"></i> ${dataPersona.persona.nombre}</span>`;
            } else if (dataEquipo.jugador) {
                nombreField.value = dataEquipo.jugador.nombre || '';
                feedbackDiv.innerHTML = `<span class="text-success"><i class="bi bi-check-circle"></i> Disponible</span>`;
            } else {
                feedbackDiv.innerHTML = '<span class="text-warning"><i class="bi bi-exclamation-circle"></i> Ingrese nombre manualmente</span>';
                nombreField.focus();
            }
            
        } catch (error) {
            feedbackDiv.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle"></i> Error de conexión</span>';
        }
    });
});

// Agregar jugador
document.querySelectorAll('.form-agregar-jugador').forEach(form => {
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const posicion = parseInt(this.dataset.posicion);
        const cedula = this.querySelector('.cedula-field').value.trim();
        const nombre = this.querySelector('.nombre-field').value.trim();
        const sexo = this.querySelector('.sexo-field').value;
        const btn = this.querySelector('button[type="submit"]');
        
        if (!cedula || !nombre || !sexo) {
            alert('Complete todos los campos');
            return;
        }
        
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        
        try {
            const formData = new FormData();
            formData.append('equipo_id', EQUIPO_ID);
            formData.append('cedula', cedula);
            formData.append('nombre', nombre);
            formData.append('sexo', sexo);
            formData.append('posicion', posicion);
            formData.append('es_capitan', posicion === 1 ? '1' : '0');
            
            const response = await fetch('agregar_jugador.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                location.reload();
            } else {
                alert(data.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-plus"></i> Agregar';
            }
            
        } catch (error) {
            alert('Error de conexión');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-plus"></i> Agregar';
        }
    });
});

// Remover jugador
async function removerJugador(jugadorId, posicion) {
    if (!confirm('¿Está seguro de remover este jugador del equipo?')) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('jugador_id', jugadorId);
        formData.append('equipo_id', EQUIPO_ID);
        
        const response = await fetch('remover_jugador.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            location.reload();
        } else {
            alert(data.message);
        }
        
    } catch (error) {
        alert('Error de conexión');
    }
}
</script>

</body>
</html>









