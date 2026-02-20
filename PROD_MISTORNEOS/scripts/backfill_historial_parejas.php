<?php
/**
 * Rellena la tabla historial_parejas con datos de partiresul.
 * Regla: id_menor-id_mayor en jugador_1_id, jugador_2_id y llave.
 * Ejecutar add_llave_historial_parejas.sql antes si la tabla no tiene columna llave.
 * Uso: php scripts/backfill_historial_parejas.php
 */
require_once __DIR__ . '/../config/db.php';

$pdo = DB::pdo();

$sql = "SELECT id_torneo, partida, mesa, id_usuario, secuencia 
        FROM partiresul 
        WHERE mesa > 0 
        ORDER BY id_torneo, partida, mesa, secuencia";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$insert = $pdo->prepare(
    "INSERT IGNORE INTO historial_parejas (torneo_id, ronda_id, jugador_1_id, jugador_2_id, llave) VALUES (?, ?, ?, ?, ?)"
);

$mesaActual = null;
$jugadores = [];
$torneoId = null;
$partida = null;
$insertados = 0;

foreach ($rows as $r) {
    $key = $r['id_torneo'] . '-' . $r['partida'] . '-' . $r['mesa'];
    if ($mesaActual !== $key) {
        if (count($jugadores) >= 4 && $torneoId !== null) {
            $j1 = min($jugadores[0], $jugadores[1]);
            $j2 = max($jugadores[0], $jugadores[1]);
            $insert->execute([$torneoId, $partida, $j1, $j2, $j1 . '-' . $j2]);
            $insertados++;
            $j1 = min($jugadores[2], $jugadores[3]);
            $j2 = max($jugadores[2], $jugadores[3]);
            $insert->execute([$torneoId, $partida, $j1, $j2, $j1 . '-' . $j2]);
            $insertados++;
        } elseif (count($jugadores) >= 2 && $torneoId !== null) {
            $j1 = min($jugadores[0], $jugadores[1]);
            $j2 = max($jugadores[0], $jugadores[1]);
            $insert->execute([$torneoId, $partida, $j1, $j2, $j1 . '-' . $j2]);
            $insertados++;
        }
        $mesaActual = $key;
        $torneoId = (int)$r['id_torneo'];
        $partida = (int)$r['partida'];
        $jugadores = [];
    }
    $jugadores[] = (int)$r['id_usuario'];
}

if (count($jugadores) >= 4 && $torneoId !== null) {
    $j1 = min($jugadores[0], $jugadores[1]);
    $j2 = max($jugadores[0], $jugadores[1]);
    $insert->execute([$torneoId, $partida, $j1, $j2, $j1 . '-' . $j2]);
    $insertados++;
    $j1 = min($jugadores[2], $jugadores[3]);
    $j2 = max($jugadores[2], $jugadores[3]);
    $insert->execute([$torneoId, $partida, $j1, $j2, $j1 . '-' . $j2]);
    $insertados++;
} elseif (count($jugadores) >= 2 && $torneoId !== null) {
    $j1 = min($jugadores[0], $jugadores[1]);
    $j2 = max($jugadores[0], $jugadores[1]);
    $insert->execute([$torneoId, $partida, $j1, $j2, $j1 . '-' . $j2]);
    $insertados++;
}

echo "Backfill completado. Insertados: $insertados parejas.\n";
