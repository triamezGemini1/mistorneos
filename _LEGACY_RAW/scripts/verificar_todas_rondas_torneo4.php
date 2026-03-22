<?php
/**
 * Script para verificar todas las rondas del torneo ID 4
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

$torneo_id = 4;

$pdo = DB::pdo();

echo "=== VERIFICACIÓN COMPLETA - TORNEO ID $torneo_id ===\n\n";

// Obtener total de inscritos
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM inscritos
    WHERE torneo_id = ? AND estatus = 'confirmado'
");
$stmt->execute([$torneo_id]);
$total_inscritos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

echo "Total de inscritos activos: $total_inscritos\n";
echo "Mesas esperadas: " . ceil($total_inscritos / 4) . "\n\n";

// Obtener todas las rondas generadas
$stmt = $pdo->prepare("
    SELECT DISTINCT partida as ronda
    FROM partiresul
    WHERE id_torneo = ?
    ORDER BY partida
");
$stmt->execute([$torneo_id]);
$rondas = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "=== VERIFICACIÓN POR RONDA ===\n\n";

foreach ($rondas as $ronda) {
    echo "RONDA $ronda:\n";
    echo str_repeat("-", 50) . "\n";
    
    // Contar mesas y jugadores
    $stmt = $pdo->prepare("
        SELECT pr.mesa, COUNT(*) as jugadores
        FROM partiresul pr
        WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa > 0
        GROUP BY pr.mesa
        ORDER BY pr.mesa
    ");
    $stmt->execute([$torneo_id, $ronda]);
    $mesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_asignados = 0;
    $mesas_incompletas = 0;
    $mesas_completas = 0;
    
    foreach ($mesas as $mesa) {
        $total_asignados += $mesa['jugadores'];
        if ($mesa['jugadores'] < 4) {
            $mesas_incompletas++;
        } else {
            $mesas_completas++;
        }
    }
    
    // Contar BYE
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM partiresul
        WHERE id_torneo = ? AND partida = ? AND mesa = 0
    ");
    $stmt->execute([$torneo_id, $ronda]);
    $bye_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $bye = (int)($bye_result['total'] ?? 0);
    
    echo "  Mesas completas: $mesas_completas\n";
    echo "  Mesas incompletas: $mesas_incompletas\n";
    echo "  Total jugadores asignados: $total_asignados\n";
    echo "  Jugadores con BYE: $bye\n";
    
    if ($total_asignados != $total_inscritos) {
        echo "  ⚠️ ADVERTENCIA: Jugadores asignados ($total_asignados) != Total inscritos ($total_inscritos)\n";
    }
    
    if ($bye > 0 && $total_inscritos % 4 == 0) {
        echo "  ⚠️ ADVERTENCIA: Hay BYE pero el total es múltiplo de 4 (no debería haber BYE)\n";
    }
    
    if ($mesas_incompletas > 0 && $total_inscritos % 4 == 0) {
        echo "  ⚠️ ADVERTENCIA: Hay mesas incompletas pero el total es múltiplo de 4\n";
    }
    
    echo "\n";
}

echo "=== FIN DE LA VERIFICACIÓN ===\n";

