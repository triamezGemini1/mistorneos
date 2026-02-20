<?php
require_once __DIR__ . '/db_local.php';
$pdo = DB_Local::pdo();
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
echo "Tablas en la base de datos local (SQLite):\n";
echo str_repeat("-", 40) . "\n";
foreach ($tables as $t) {
    $count = $pdo->query("SELECT COUNT(*) FROM " . $t)->fetchColumn();
    echo sprintf("  %-25s (%d filas)\n", $t, $count);
}
