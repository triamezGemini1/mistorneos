<?php
/**
 * Script de diagnóstico para verificar la asignación de la segunda ronda del torneo ID 4
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

$torneo_id = 4;
$ronda = 2;

$pdo = DB::pdo();

echo "=== DIAGNÓSTICO TORNEO ID $torneo_id - RONDA $ronda ===\n\n";

// Obtener todos los inscritos
$stmt = $pdo->prepare("
    SELECT i.*, u.nombre, u.cedula, c.nombre as club_nombre
    FROM inscritos i
    INNER JOIN usuarios u ON i.id_usuario = u.id
    LEFT JOIN clubes c ON i.id_club = c.id
    WHERE i.torneo_id = ? AND i.estatus = 'confirmado'
    ORDER BY i.posicion ASC
");
$stmt->execute([$torneo_id]);
$inscritos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de inscritos activos: " . count($inscritos) . "\n\n";

// Obtener mesas de la ronda 2
$stmt = $pdo->prepare("
    SELECT pr.mesa, pr.secuencia, pr.id_usuario, u.nombre, u.cedula
    FROM partiresul pr
    INNER JOIN usuarios u ON pr.id_usuario = u.id
    WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa > 0
    ORDER BY pr.mesa, pr.secuencia
");
$stmt->execute([$torneo_id, $ronda]);
$mesas_ronda2 = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por mesa
$mesas_agrupadas = [];
foreach ($mesas_ronda2 as $registro) {
    $mesa = $registro['mesa'];
    if (!isset($mesas_agrupadas[$mesa])) {
        $mesas_agrupadas[$mesa] = [];
    }
    $mesas_agrupadas[$mesa][] = $registro;
}

echo "=== MESAS DE LA RONDA 2 ===\n";
foreach ($mesas_agrupadas as $mesa_num => $jugadores) {
    echo "\nMesa $mesa_num: " . count($jugadores) . " jugador(es)\n";
    foreach ($jugadores as $j) {
        echo "  - Secuencia {$j['secuencia']}: {$j['nombre']} (ID: {$j['id_usuario']}, Cédula: {$j['cedula']})\n";
    }
}

// Verificar jugadores no asignados
$asignados_ids = array_column($mesas_ronda2, 'id_usuario');
$no_asignados = array_filter($inscritos, function($i) use ($asignados_ids) {
    return !in_array($i['id_usuario'], $asignados_ids);
});

echo "\n=== JUGADORES NO ASIGNADOS ===\n";
if (empty($no_asignados)) {
    echo "Todos los jugadores están asignados.\n";
} else {
    echo "Total: " . count($no_asignados) . "\n";
    foreach ($no_asignados as $j) {
        echo "  - {$j['nombre']} (ID: {$j['id_usuario']}, Cédula: {$j['cedula']}, Posición: {$j['posicion']})\n";
    }
}

// Obtener parejas de la ronda 1
$stmt = $pdo->prepare("
    SELECT pr.mesa, pr.secuencia, pr.id_usuario, u.nombre
    FROM partiresul pr
    INNER JOIN usuarios u ON pr.id_usuario = u.id
    WHERE pr.id_torneo = ? AND pr.partida = 1 AND pr.mesa > 0
    ORDER BY pr.mesa, pr.secuencia
");
$stmt->execute([$torneo_id]);
$mesas_ronda1 = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Crear matriz de compañeros
$parejas_ronda1 = [];
$mesa_actual = null;
$jugadores_mesa = [];
foreach ($mesas_ronda1 as $r) {
    if ($mesa_actual !== $r['mesa']) {
        if (count($jugadores_mesa) >= 4) {
            $parejas_ronda1[] = [$jugadores_mesa[0]['id_usuario'], $jugadores_mesa[1]['id_usuario']];
            $parejas_ronda1[] = [$jugadores_mesa[2]['id_usuario'], $jugadores_mesa[3]['id_usuario']];
        } elseif (count($jugadores_mesa) >= 2) {
            $parejas_ronda1[] = [$jugadores_mesa[0]['id_usuario'], $jugadores_mesa[1]['id_usuario']];
        }
        $mesa_actual = $r['mesa'];
        $jugadores_mesa = [];
    }
    $jugadores_mesa[] = $r;
}
// Última mesa
if (count($jugadores_mesa) >= 4) {
    $parejas_ronda1[] = [$jugadores_mesa[0]['id_usuario'], $jugadores_mesa[1]['id_usuario']];
    $parejas_ronda1[] = [$jugadores_mesa[2]['id_usuario'], $jugadores_mesa[3]['id_usuario']];
} elseif (count($jugadores_mesa) >= 2) {
    $parejas_ronda1[] = [$jugadores_mesa[0]['id_usuario'], $jugadores_mesa[1]['id_usuario']];
}

echo "\n=== PAREJAS DE LA RONDA 1 ===\n";
foreach ($parejas_ronda1 as $idx => $pareja) {
    echo "Pareja " . ($idx + 1) . ": Jugador {$pareja[0]} y Jugador {$pareja[1]}\n";
}

// Verificar conflictos en mesas de ronda 2
echo "\n=== VERIFICACIÓN DE CONFLICTOS EN RONDA 2 ===\n";
foreach ($mesas_agrupadas as $mesa_num => $jugadores) {
    if (count($jugadores) < 4) {
        echo "⚠️ Mesa $mesa_num tiene solo " . count($jugadores) . " jugadores (debería tener 4)\n";
    }
    
    $ids_mesa = array_column($jugadores, 'id_usuario');
    
    // Verificar si hay compañeros repetidos
    foreach ($parejas_ronda1 as $pareja) {
        if (in_array($pareja[0], $ids_mesa) && in_array($pareja[1], $ids_mesa)) {
            echo "⚠️ Mesa $mesa_num: Los jugadores {$pareja[0]} y {$pareja[1]} fueron compañeros en ronda 1\n";
        }
    }
}

echo "\n=== FIN DEL DIAGNÓSTICO ===\n";












