<?php
/**
 * Panel Principal de Inscripción de Equipos
 * Muestra equipos existentes y permite crear nuevos
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
    
    // Obtener equipos del club en este torneo
    $equipos = EquiposHelper::getEquiposTorneo($torneo_id, $club_id);
    
    // Estadísticas
    $total_equipos = count($equipos);
    $total_jugadores = 0;
    foreach ($equipos as $equipo) {
        $total_jugadores += (int)($equipo['total_jugadores'] ?? 0);
    }
    
    // Mensajes
    $mensaje_exito = $_GET['success'] ?? null;
    $mensaje_error = $_GET['error'] ?? null;
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscripción de Equipos - <?= htmlspecialchars($torneo['nombre']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            margin: 20px auto;
            max-width: 1000px;
        }
        
        .header-section {
            background: var(--primary-gradient);
            color: white;
            padding: 25px 30px;
            border-radius: 20px 20px 0 0;
        }
        
        .stats-card {
            border-radius: 15px;
            padding: 20px;
            color: white;
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-card h3 {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 10px 0;
        }
        
        .equipo-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .equipo-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .equipo-header {
            background: var(--primary-gradient);
            color: white;
            padding: 15px 20px;
        }
        
        .equipo-codigo {
            background: rgba(255,255,255,0.2);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .jugador-item {
            display: flex;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        
        .jugador-item:last-child {
            border-bottom: none;
        }
        
        .jugador-posicion {
            width: 30px;
            height: 30px;
            background: #e9ecef;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 12px;
        }
        
        .jugador-posicion.capitan {
            background: #ffc107;
            color: #000;
        }
        
        .btn-crear-equipo {
            background: var(--success-gradient);
            border: none;
            padding: 15px 40px;
            font-size: 1.1rem;
            border-radius: 50px;
            transition: all 0.3s ease;
        }
        
        .btn-crear-equipo:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 30px rgba(17, 153, 142, 0.4);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 5rem;
            color: #dee2e6;
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
                    <h2 class="mb-1"><i class="bi bi-people-fill me-2"></i>Inscripción de Equipos</h2>
                    <p class="mb-0 opacity-75">
                        <strong><?= htmlspecialchars($torneo['nombre']) ?></strong>
                    </p>
                    <p class="mb-0 opacity-75">
                        <i class="bi bi-building me-1"></i><?= htmlspecialchars($club['nombre']) ?>
                    </p>
                </div>
                <div>
                    <a href="../invitations/inscripciones/logout.php" class="btn btn-outline-light">
                        <i class="bi bi-box-arrow-right me-1"></i>Salir
                    </a>
                </div>
            </div>
        </div>
        
        <div class="p-4">
            <!-- Mensajes -->
            <?php if ($mensaje_exito): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($mensaje_exito) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($mensaje_error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($mensaje_error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stats-card" style="background: var(--primary-gradient);">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-people-fill fs-1 me-3 opacity-75"></i>
                            <div>
                                <h5 class="mb-0 opacity-75">Equipos</h5>
                                <h3 class="mb-0"><?= $total_equipos ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card" style="background: var(--success-gradient);">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-person-fill fs-1 me-3 opacity-75"></i>
                            <div>
                                <h5 class="mb-0 opacity-75">Jugadores</h5>
                                <h3 class="mb-0"><?= $total_jugadores ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card" style="background: var(--warning-gradient);">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-hash fs-1 me-3 opacity-75"></i>
                            <div>
                                <h5 class="mb-0 opacity-75">Próximo Código</h5>
                                <h3 class="mb-0"><?= EquiposHelper::getProximoCodigo($torneo_id, $club_id) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Botón Crear Equipo -->
            <div class="text-center mb-4">
                <a href="crear_equipo.php" class="btn btn-success btn-crear-equipo">
                    <i class="bi bi-plus-circle me-2"></i>Crear Nuevo Equipo
                </a>
            </div>
            
            <!-- Lista de Equipos -->
            <h4 class="mb-3"><i class="bi bi-list-ul me-2"></i>Equipos Inscritos</h4>
            
            <?php if (empty($equipos)): ?>
                <div class="empty-state">
                    <i class="bi bi-people"></i>
                    <h4 class="mt-3 text-muted">No hay equipos inscritos</h4>
                    <p class="text-muted">Crea tu primer equipo haciendo clic en el botón verde</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($equipos as $equipo): 
                        $jugadores = EquiposHelper::getJugadoresEquipo($equipo['id']);
                    ?>
                        <div class="col-md-6 mb-4">
                            <div class="card equipo-card">
                                <div class="equipo-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-0"><?= htmlspecialchars($equipo['nombre_equipo']) ?></h5>
                                    </div>
                                    <span class="equipo-codigo">
                                        <i class="bi bi-upc me-1"></i><?= $equipo['codigo_equipo'] ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-3">
                                        <span class="badge bg-primary">
                                            <i class="bi bi-person me-1"></i><?= count($jugadores) ?>/4 Jugadores
                                        </span>
                                        <?php if ($equipo['estatus'] == 0): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactivo</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($jugadores)): ?>
                                        <div class="jugadores-lista">
                                            <?php foreach ($jugadores as $jugador): ?>
                                                <div class="jugador-item">
                                                    <span class="jugador-posicion <?= $jugador['es_capitan'] ? 'capitan' : '' ?>">
                                                        <?= $jugador['es_capitan'] ? '★' : $jugador['posicion_equipo'] ?>
                                                    </span>
                                                    <div>
                                                        <div class="fw-semibold"><?= htmlspecialchars($jugador['nombre']) ?></div>
                                                        <small class="text-muted"><?= htmlspecialchars($jugador['cedula']) ?></small>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted text-center mb-0">
                                            <i class="bi bi-exclamation-circle me-1"></i>Sin jugadores asignados
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if (count($jugadores) < 4): ?>
                                        <div class="mt-3">
                                            <a href="editar_equipo.php?id=<?= $equipo['id'] ?>" 
                                               class="btn btn-outline-primary btn-sm w-100">
                                                <i class="bi bi-person-plus me-1"></i>Completar Equipo
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
</body>
</html>









