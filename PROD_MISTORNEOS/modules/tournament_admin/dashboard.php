<?php
/**
 * Dashboard de Administración de Torneo
 */

// Usar la variable global de verificación de tablas
// $tabla_partiresul_existe ya está definida en tournament_admin.php

// Obtener estadísticas adicionales
$estadisticas_partidas = [
    'total_rondas' => 0,
    'partidas_registradas' => 0,
    'partidas_pendientes' => 0
];

if ($tabla_partiresul_existe) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT p.partida) as total_rondas,
                COUNT(DISTINCT CASE WHEN p.registrado = 1 THEN p.id END) as partidas_registradas,
                COUNT(DISTINCT CASE WHEN p.registrado = 0 THEN p.id END) as partidas_pendientes
            FROM partiresul p
            WHERE p.id_torneo = ?
        ");
        $stmt->execute([$torneo_id]);
        $estadisticas_partidas = $stmt->fetch(PDO::FETCH_ASSOC) ?: $estadisticas_partidas;
    } catch (Exception $e) {
        // Si hay error, usar valores por defecto
    }
}

// Obtener última ronda
$ultima_ronda = 0;
if ($tabla_partiresul_existe) {
    try {
        $stmt = $pdo->prepare("
            SELECT MAX(partida) as ultima_ronda
            FROM partiresul
            WHERE id_torneo = ?
        ");
        $stmt->execute([$torneo_id]);
        $ultima_ronda = (int)$stmt->fetchColumn() ?: 0;
    } catch (Exception $e) {
        $ultima_ronda = 0;
    }
}
?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">
            <i class="fas fa-tachometer-alt me-2"></i>Panel de Control
        </h5>
    </div>
    <div class="card-body">
        <?php if (!$tabla_partiresul_existe): ?>
            <div class="alert alert-warning">
                <h6 class="alert-heading">
                    <i class="fas fa-exclamation-triangle me-2"></i>Tabla partiresul no encontrada
                </h6>
                <p class="mb-2">
                    La tabla <code>partiresul</code> no existe en la base de datos. 
                    Para habilitar todas las funcionalidades del torneo, ejecute la migración:
                </p>
                <p class="mb-0">
                    <code>php scripts/migrate_partiresul_table.php</code>
                </p>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-muted mb-3">Resumen del Torneo</h6>
                <ul class="list-group">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-users me-2 text-primary"></i>Total de Inscritos</span>
                        <span class="badge bg-primary rounded-pill"><?= number_format($estadisticas['total_inscritos'] ?? 0) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-check-circle me-2 text-success"></i>Confirmados</span>
                        <span class="badge bg-success rounded-pill"><?= number_format($estadisticas['confirmados'] ?? 0) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-dollar-sign me-2 text-info"></i>Solventes</span>
                        <span class="badge bg-info rounded-pill"><?= number_format($estadisticas['solventes'] ?? 0) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-user-times me-2 text-danger"></i>Retirados</span>
                        <span class="badge bg-danger rounded-pill"><?= number_format($estadisticas['retirados'] ?? 0) ?></span>
                    </li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted mb-3">Estado de Partidas</h6>
                <ul class="list-group">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-list-ol me-2 text-primary"></i>Total de Rondas</span>
                        <span class="badge bg-primary rounded-pill"><?= number_format($estadisticas_partidas['total_rondas'] ?? 0) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-check-double me-2 text-success"></i>Partidas Registradas</span>
                        <span class="badge bg-success rounded-pill"><?= number_format($estadisticas_partidas['partidas_registradas'] ?? 0) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-clock me-2 text-warning"></i>Partidas Pendientes</span>
                        <span class="badge bg-warning rounded-pill"><?= number_format($estadisticas_partidas['partidas_pendientes'] ?? 0) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-flag-checkered me-2 text-info"></i>Última Ronda</span>
                        <span class="badge bg-info rounded-pill"><?= $ultima_ronda > 0 ? "Ronda #{$ultima_ronda}" : "Sin rondas" ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <hr class="my-4">

        <div class="alert alert-info">
            <h6 class="alert-heading">
                <i class="fas fa-info-circle me-2"></i>Bienvenido al Panel de Administración
            </h6>
            <p class="mb-0">
                Utilice el menú lateral para acceder a las diferentes funcionalidades del torneo.
                Desde aquí puede gestionar inscripciones, generar rondas, registrar resultados y más.
            </p>
        </div>

        <div class="row mt-4">
            <div class="col-md-12">
                <h6 class="text-muted mb-3">Accesos Rápidos</h6>
                <div class="d-grid gap-2 d-md-flex">
                    <a href="index.php?page=tournament_admin&torneo_id=<?= $torneo_id ?>&action=revisar_inscripciones" 
                       class="btn btn-outline-primary">
                        <i class="fas fa-list-check me-2"></i>Ver Inscripciones
                    </a>
                    <a href="index.php?page=tournament_admin&torneo_id=<?= $torneo_id ?>&action=inscribir_sitio" 
                       class="btn btn-outline-success">
                        <i class="fas fa-user-plus me-2"></i>Inscribir Jugador
                    </a>
                    <a href="index.php?page=tournament_admin&torneo_id=<?= $torneo_id ?>&action=ingreso_resultados" 
                       class="btn btn-outline-warning">
                        <i class="fas fa-edit me-2"></i>Registrar Resultados
                    </a>
                    <a href="index.php?page=tournament_admin&torneo_id=<?= $torneo_id ?>&action=mostrar_resultados" 
                       class="btn btn-outline-info">
                        <i class="fas fa-chart-bar me-2"></i>Ver Resultados
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

