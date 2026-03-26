<?php
/**
 * Prueba de autonomía del core desktop.
 * Usa la ruta centralizada (DESKTOP_DB_PATH). Asegura el esquema vía DB_Local y luego
 * valida db_bridge + MesaAsignacionService y lectura de inscritos y partiresul.
 */
header('Content-Type: text/plain; charset=utf-8');

try {
    require_once __DIR__ . '/db_local.php';
    DB_Local::pdo();
    require_once __DIR__ . '/core/db_bridge.php';
    require_once __DIR__ . '/core/MesaAsignacionService.php';

    $pdo = DB::pdo();
    $stmt = $pdo->query("SELECT COUNT(*) FROM inscritos");
    if ($stmt === false) {
        echo "ERROR: No se pudo consultar la tabla inscritos\n";
        exit(1);
    }
    $n = (int) $stmt->fetchColumn();

    $stmt2 = $pdo->query("SELECT COUNT(*) FROM partiresul");
    $partiresulCount = $stmt2 ? (int) $stmt2->fetchColumn() : 0;

    new MesaAsignacionService();

    echo "OK\n";
    echo "Inscritos (total): " . $n . "\n";
    echo "Partiresul (total): " . $partiresulCount . "\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
