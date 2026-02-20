<?php
/**
 * Wrapper: Tabla de Asignacion de Atletas (Cuadricula)
 * Reutiliza gestion_torneos/cuadricula.php
 */

require_once __DIR__ . '/../../lib/GestionTorneosViewsData.php';

$ronda = isset($_GET['ronda']) ? (int)$_GET['ronda'] : 0;
if ($ronda <= 0) {
    try {
        $stmt = $pdo->prepare("SELECT MAX(CAST(partida AS UNSIGNED)) FROM partiresul WHERE id_torneo = ?");
        $stmt->execute([$torneo_id]);
        $ronda = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $ronda = 0;
    }
}

if ($ronda <= 0) {
    echo '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>No hay rondas generadas. Genere rondas primero.</div>';
    return;
}

$view_data = GestionTorneosViewsData::obtenerCuadricula($torneo_id, $ronda);
extract($view_data);

$base_url = 'index.php?page=tournament_admin';
$use_standalone = false;

require __DIR__ . '/../gestion_torneos/cuadricula.php';
