<?php
/**
 * Añade índices faltantes para optimizar consultas.
 * Incluye Mejora 2 (inscritos) y Mejora 4 (partiresul) del procedimiento de rondas.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

$pdo = DB::pdo();

echo "=== Añadiendo índices faltantes ===\n\n";

// Verificar e índice usuarios.club_id
$stmt = $pdo->query("SHOW INDEX FROM usuarios WHERE Key_name = 'idx_usuarios_club_id'");
if ($stmt->rowCount() == 0) {
    try {
        $pdo->exec("CREATE INDEX idx_usuarios_club_id ON usuarios(club_id)");
        echo "✅ Creado: idx_usuarios_club_id\n";
    } catch (Exception $e) {
        echo "❌ ERROR idx_usuarios_club_id: " . $e->getMessage() . "\n";
    }
} else {
    echo "⏭️ Ya existe: idx_usuarios_club_id\n";
}

// Verificar e índice notifications_queue
$stmt = $pdo->query("SHOW INDEX FROM notifications_queue WHERE Key_name = 'idx_notifications_queue_usuario'");
if ($stmt->rowCount() == 0) {
    try {
        $pdo->exec("CREATE INDEX idx_notifications_queue_usuario ON notifications_queue(usuario_id, canal, estado)");
        echo "✅ Creado: idx_notifications_queue_usuario\n";
    } catch (Exception $e) {
        echo "❌ ERROR idx_notifications_queue_usuario: " . $e->getMessage() . "\n";
    }
} else {
    echo "⏭️ Ya existe: idx_notifications_queue_usuario\n";
}

// --- Mejora 2: Índices en inscritos (listados, conteos y clasificación) ---
echo "\n--- Inscritos (Mejora 2) ---\n";

$stmt = $pdo->query("SHOW INDEX FROM inscritos WHERE Key_name = 'idx_inscritos_torneo_estatus'");
if ($stmt->rowCount() == 0) {
    try {
        $pdo->exec("ALTER TABLE inscritos ADD KEY idx_inscritos_torneo_estatus (torneo_id, estatus)");
        echo "✅ Creado: idx_inscritos_torneo_estatus (torneo_id, estatus)\n";
    } catch (Exception $e) {
        echo "❌ ERROR idx_inscritos_torneo_estatus: " . $e->getMessage() . "\n";
    }
} else {
    echo "⏭️ Ya existe: idx_inscritos_torneo_estatus\n";
}

$stmt = $pdo->query("SHOW INDEX FROM inscritos WHERE Key_name = 'idx_inscritos_clasificacion'");
if ($stmt->rowCount() == 0) {
    try {
        $pdo->exec("ALTER TABLE inscritos ADD KEY idx_inscritos_clasificacion (torneo_id, posicion, ganados, efectividad, puntos)");
        echo "✅ Creado: idx_inscritos_clasificacion (torneo_id, posicion, ganados, efectividad, puntos)\n";
    } catch (Exception $e) {
        echo "❌ ERROR idx_inscritos_clasificacion: " . $e->getMessage() . "\n";
    }
} else {
    echo "⏭️ Ya existe: idx_inscritos_clasificacion\n";
}

// --- Mejora 4: Índices en partiresul (agregación y duplicados) ---
echo "\n--- Partiresul (Mejora 4) ---\n";

$tableExists = $pdo->query("SHOW TABLES LIKE 'partiresul'")->rowCount() > 0;
if (!$tableExists) {
    echo "⏭️ Tabla partiresul no existe; se omiten índices.\n";
} else {
    $stmt = $pdo->query("SHOW INDEX FROM partiresul WHERE Key_name = 'idx_partiresul_torneo_registrado'");
    if ($stmt->rowCount() == 0) {
        try {
            $pdo->exec("ALTER TABLE partiresul ADD KEY idx_partiresul_torneo_registrado (id_torneo, registrado)");
            echo "✅ Creado: idx_partiresul_torneo_registrado (id_torneo, registrado)\n";
        } catch (Exception $e) {
            echo "❌ ERROR idx_partiresul_torneo_registrado: " . $e->getMessage() . "\n";
        }
    } else {
        echo "⏭️ Ya existe: idx_partiresul_torneo_registrado\n";
    }

    $stmt = $pdo->query("SHOW INDEX FROM partiresul WHERE Key_name = 'idx_partiresul_torneo_usuario_partida'");
    if ($stmt->rowCount() == 0) {
        try {
            $pdo->exec("ALTER TABLE partiresul ADD KEY idx_partiresul_torneo_usuario_partida (id_torneo, id_usuario, partida)");
            echo "✅ Creado: idx_partiresul_torneo_usuario_partida (id_torneo, id_usuario, partida)\n";
        } catch (Exception $e) {
            echo "❌ ERROR idx_partiresul_torneo_usuario_partida: " . $e->getMessage() . "\n";
        }
    } else {
        echo "⏭️ Ya existe: idx_partiresul_torneo_usuario_partida\n";
    }
}

echo "\n=== Verificación final ===\n";
$stmt = $pdo->query("SHOW INDEX FROM usuarios WHERE Column_name = 'club_id'");
echo "usuarios.club_id: " . ($stmt->rowCount() > 0 ? "✅ Tiene índice" : "❌ Sin índice") . "\n";
