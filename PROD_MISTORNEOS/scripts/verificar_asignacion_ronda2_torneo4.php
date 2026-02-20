<?php
/**
 * Script para verificar la asignación detallada de la segunda ronda del torneo ID 4
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

$torneo_id = 4;
$ronda = 2;

$pdo = DB::pdo();

echo "=== ASIGNACIÓN DETALLADA - TORNEO ID $torneo_id - RONDA $ronda ===\n\n";

// Obtener todas las mesas con sus jugadores
$stmt = $pdo->prepare("
    SELECT pr.mesa, pr.secuencia, pr.id_usuario, u.nombre, u.cedula, c.nombre as club_nombre
    FROM partiresul pr
    INNER JOIN usuarios u ON pr.id_usuario = u.id
    LEFT JOIN clubes c ON u.club_id = c.id
    WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa > 0
    ORDER BY pr.mesa, pr.secuencia
");
$stmt->execute([$torneo_id, $ronda]);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por mesa
$mesas = [];
foreach ($registros as $r) {
    $mesa = $r['mesa'];
    if (!isset($mesas[$mesa])) {
        $mesas[$mesa] = [];
    }
    $mesas[$mesa][] = $r;
}

// Mostrar cada mesa
foreach ($mesas as $mesa_num => $jugadores) {
    echo "MESA $mesa_num (" . count($jugadores) . " jugadores):\n";
    echo str_repeat("-", 80) . "\n";
    
    // Pareja A (secuencias 1-2)
    echo "  Pareja A:\n";
    for ($i = 0; $i < min(2, count($jugadores)); $i++) {
        $j = $jugadores[$i];
        echo "    - Secuencia {$j['secuencia']}: {$j['nombre']} (ID: {$j['id_usuario']}, Cédula: {$j['cedula']}, Club: {$j['club_nombre']})\n";
    }
    
    // Pareja B (secuencias 3-4)
    if (count($jugadores) >= 3) {
        echo "  Pareja B:\n";
        for ($i = 2; $i < min(4, count($jugadores)); $i++) {
            $j = $jugadores[$i];
            echo "    - Secuencia {$j['secuencia']}: {$j['nombre']} (ID: {$j['id_usuario']}, Cédula: {$j['cedula']}, Club: {$j['club_nombre']})\n";
        }
    }
    
    echo "\n";
}

// Resumen
echo "=== RESUMEN ===\n";
echo "Total de mesas: " . count($mesas) . "\n";
echo "Total de jugadores asignados: " . count($registros) . "\n";

$mesas_completas = 0;
$mesas_incompletas = 0;
foreach ($mesas as $mesa_num => $jugadores) {
    if (count($jugadores) == 4) {
        $mesas_completas++;
    } else {
        $mesas_incompletas++;
    }
}

echo "Mesas completas (4 jugadores): $mesas_completas\n";
if ($mesas_incompletas > 0) {
    echo "Mesas incompletas: $mesas_incompletas\n";
}

echo "\n=== FIN DEL REPORTE ===\n";












