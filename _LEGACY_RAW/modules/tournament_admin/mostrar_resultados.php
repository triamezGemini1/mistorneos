<?php
/**
 * Mostrar Resultados del Torneo
 */

require_once __DIR__ . '/../../lib/InscritosPartiresulHelper.php';

// Verificar que la tabla partiresul existe
if (!$tabla_partiresul_existe) {
    echo '<div class="alert alert-danger">';
    echo '<h6 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Tabla partiresul no encontrada</h6>';
    echo '<p class="mb-2">La tabla <code>partiresul</code> no existe. Para ver resultados, debe crear esta tabla primero.</p>';
    echo '<p class="mb-0">Ejecute: <code>php scripts/migrate_partiresul_table.php</code></p>';
    echo '</div>';
    return;
}

// Obtener clasificación
$clasificacion = InscritosPartiresulHelper::obtenerClasificacion($torneo_id);

// Obtener estadísticas generales
$estadisticas_partidas = [
    'total_rondas' => 0,
    'total_partidas' => 0,
    'partidas_registradas' => 0
];

try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT partida) as total_rondas,
            COUNT(*) as total_partidas,
            COUNT(CASE WHEN registrado = 1 THEN 1 END) as partidas_registradas
        FROM partiresul
        WHERE id_torneo = ?
    ");
    $stmt->execute([$torneo_id]);
    $estadisticas_partidas = $stmt->fetch(PDO::FETCH_ASSOC) ?: $estadisticas_partidas;
} catch (Exception $e) {
    // Usar valores por defecto
}
?>

<div class="card">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0">
            <i class="fas fa-chart-bar me-2"></i>Resultados del Torneo
        </h5>
    </div>
    <div class="card-body">
        <!-- Estadísticas Generales -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?= $estadisticas_partidas['total_rondas'] ?? 0 ?></h3>
                        <p class="mb-0">Rondas Jugadas</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?= $estadisticas_partidas['total_partidas'] ?? 0 ?></h3>
                        <p class="mb-0">Total Partidas</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?= $estadisticas_partidas['partidas_registradas'] ?? 0 ?></h3>
                        <p class="mb-0">Partidas Registradas</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Clasificación -->
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">
                    <i class="fas fa-trophy me-2"></i>Clasificación General
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($clasificacion)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No hay resultados disponibles aún.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th class="text-center" style="width: 60px;">#</th>
                                    <th class="text-center">ID Usuario</th>
                                    <th>Jugador</th>
                                    <th class="text-center">Club</th>
                                    <th class="text-center">Partidas</th>
                                    <th class="text-center">G</th>
                                    <th class="text-center">P</th>
                                    <th class="text-center">Efectividad</th>
                                    <th class="text-center">Puntos</th>
                                    <th class="text-center">Ranking</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $posicion = 1;
                                foreach ($clasificacion as $jugador): 
                                    $estadisticas = InscritosPartiresulHelper::obtenerEstadisticas($jugador['id_usuario'], $torneo_id);
                                ?>
                                    <tr class="<?= $posicion <= 3 ? 'table-warning' : '' ?>">
                                        <td class="text-center">
                                            <?php if ($posicion == 1): ?>
                                                <i class="fas fa-trophy text-warning"></i>
                                            <?php elseif ($posicion == 2): ?>
                                                <i class="fas fa-medal text-secondary"></i>
                                            <?php elseif ($posicion == 3): ?>
                                                <i class="fas fa-medal text-warning"></i>
                                            <?php else: ?>
                                                <strong><?= $posicion ?></strong>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"><code><?= htmlspecialchars($jugador['id_usuario'] ?? 'N/A') ?></code></td>
                                        <td>
                                            <strong><?= htmlspecialchars($jugador['usuario_nombre'] ?? 'N/A') ?></strong>
                                        </td>
                                        <td class="text-center">
                                            <?= htmlspecialchars($jugador['club_nombre'] ?? 'Sin club') ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-info"><?= $estadisticas['total_partidas'] ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-success"><?= $estadisticas['ganados'] ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-danger"><?= $estadisticas['perdidos'] ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="fw-bold <?= $jugador['efectividad'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                                <?= (int)$jugador['efectividad'] >= 0 ? '+' : '' ?><?= (int)$jugador['efectividad'] ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <strong><?= (int)$jugador['puntos'] ?></strong>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary fs-6"><?= (int)$jugador['ptosrnk'] ?></span>
                                        </td>
                                    </tr>
                                    <?php $posicion++; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Botones de Acción -->
        <div class="d-flex justify-content-end gap-2">
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print me-2"></i>Imprimir Clasificación
            </button>
            <button class="btn btn-success" onclick="exportarExcel()">
                <i class="fas fa-file-excel me-2"></i>Exportar Excel
            </button>
        </div>
    </div>
</div>

<script>
function exportarExcel() {
    // Implementar exportación a Excel
    alert('Funcionalidad de exportación a Excel en desarrollo');
}
</script>

<style>
@media print {
    .btn, .card-header {
        display: none !important;
    }
    .table {
        font-size: 0.9rem;
    }
}
</style>

