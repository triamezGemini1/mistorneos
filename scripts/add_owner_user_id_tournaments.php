<?php
/**
 * Script para agregar owner_user_id a la tabla tournaments
 * owner_user_id = ID del usuario admin que registra el torneo (no puede ser 0 ni diferente al admin)
 * 
 * Ejecutar: php scripts/add_owner_user_id_tournaments.php
 */

require_once __DIR__ . '/../config/db.php';

$pdo = DB::pdo();

try {
    // owner_user_id
    $stmt = $pdo->query("SHOW COLUMNS FROM tournaments LIKE 'owner_user_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE tournaments ADD COLUMN owner_user_id INT NULL COMMENT 'ID del usuario admin que registra el torneo' AFTER club_responsable");
        echo "✓ Columna 'owner_user_id' agregada exitosamente.\n";
    } else {
        echo "Columna 'owner_user_id' ya existe.\n";
    }
    
    // entidad (misma validación que owner_user_id: no puede ser 0, debe ser del admin)
    $stmt = $pdo->query("SHOW COLUMNS FROM tournaments LIKE 'entidad'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE tournaments ADD COLUMN entidad INT NULL DEFAULT 0 COMMENT 'Código de entidad del admin que registra' AFTER club_responsable");
        echo "✓ Columna 'entidad' agregada exitosamente.\n";
    } else {
        echo "Columna 'entidad' ya existe.\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
