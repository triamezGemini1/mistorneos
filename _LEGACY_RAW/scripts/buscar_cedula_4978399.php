<?php
/**
 * Buscar cómo está registrada la cédula 4978399 en usuarios e inscritos.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

$pdo = DB::pdo();

echo "=== Usuarios con cedula LIKE '%4978399%' ===\n";
$stmt = $pdo->prepare("SELECT id, username, nombre, cedula, email FROM usuarios WHERE cedula LIKE ?");
$stmt->execute(['%4978399%']);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($rows)) {
    echo "Ningún usuario encontrado.\n";
} else {
    foreach ($rows as $r) {
        echo "  id={$r['id']} cedula={$r['cedula']} nombre=" . ($r['nombre'] ?? $r['username']) . "\n";
    }
}

echo "\n=== Inscritos con id_usuario de esos usuarios ===\n";
if (!empty($rows)) {
    $ids = array_column($rows, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT i.id, i.torneo_id, i.id_usuario, t.nombre as torneo_nombre FROM inscritos i LEFT JOIN tournaments t ON t.id = i.torneo_id WHERE i.id_usuario IN ($placeholders)");
    $stmt->execute($ids);
    $inscritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($inscritos as $i) {
        echo "  torneo_id={$i['torneo_id']} ({$i['torneo_nombre']}) id_usuario={$i['id_usuario']}\n";
    }
}

echo "\n=== Primeros 5 usuarios con cedula que contenga 4978 ===\n";
$stmt = $pdo->prepare("SELECT id, username, cedula, nombre FROM usuarios WHERE cedula LIKE ? LIMIT 5");
$stmt->execute(['%4978%']);
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  id={$r['id']} cedula={$r['cedula']} nombre=" . ($r['nombre'] ?? $r['username']) . "\n";
}
