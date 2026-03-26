<?php
/**
 * Script de diagnóstico para verificar la ronda 5 del torneo ID 4
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

$torneo_id = 4;
$ronda = 5;

$pdo = DB::pdo();

echo "=== DIAGNÓSTICO TORNEO ID $torneo_id - RONDA $ronda ===\n\n";

// Obtener todos los inscritos
$stmt = $pdo->prepare("
    SELECT i.*, u.nombre, u.cedula
    FROM inscritos i
    INNER JOIN usuarios u ON i.id_usuario = u.id
    WHERE i.torneo_id = ? AND i.estatus = 'confirmado'
    ORDER BY i.posicion ASC
");
$stmt->execute([$torneo_id]);
$inscritos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de inscritos activos: " . count($inscritos) . "\n\n";

// Obtener mesas de la ronda 5
$stmt = $pdo->prepare("
    SELECT pr.mesa, COUNT(*) as jugadores
    FROM partiresul pr
    WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa > 0
    GROUP BY pr.mesa
    ORDER BY pr.mesa
");
$stmt->execute([$torneo_id, $ronda]);
$mesas = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== MESAS DE LA RONDA $ronda ===\n";
$total_jugadores_asignados = 0;
foreach ($mesas as $mesa) {
    echo "Mesa {$mesa['mesa']}: {$mesa['jugadores']} jugador(es)\n";
    $total_jugadores_asignados += $mesa['jugadores'];
}
echo "\nTotal jugadores asignados: $total_jugadores_asignados\n";

// Verificar jugadores con BYE
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM partiresul
    WHERE id_torneo = ? AND partida = ? AND mesa = 0
");
$stmt->execute([$torneo_id, $ronda]);
$bye = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Jugadores con BYE: {$bye['total']}\n\n";

// Verificar jugadores no asignados
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM inscritos i
    WHERE i.torneo_id = ? AND i.estatus = 'confirmado'
    AND i.id_usuario NOT IN (
        SELECT DISTINCT pr.id_usuario
        FROM partiresul pr
        WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa >= 0
    )
");
$stmt->execute([$torneo_id, $torneo_id, $ronda]);
$no_asignados = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Jugadores no asignados: {$no_asignados['total']}\n";












