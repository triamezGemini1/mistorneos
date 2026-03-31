<?php
declare(strict_types=1);

/**
 * Inserta/actualiza registros en clubes (asociaciones 1..39) desde la tabla entidad.
 * Misma lógica que sql/insert_clubes_simil_desde_entidades.sql (sin VALUES() deprecado).
 *
 * Uso:
 *   php scripts/actualizar_clubes_desde_entidades.php
 *   php scripts/actualizar_clubes_desde_entidades.php --dry-run
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);

$sqlInsert = <<<'SQL'
INSERT INTO clubes (
    id,
    nombre,
    admin_club_id,
    entidad,
    indica,
    estatus,
    permite_inscripcion_linea
)
SELECT
    src.id,
    src.nombre,
    src.admin_club_id,
    src.entidad,
    src.indica,
    src.estatus,
    src.permite_inscripcion_linea
FROM (
    SELECT
        e.id AS id,
        CONCAT('ASOCIACION ', UPPER(TRIM(e.nombre))) AS nombre,
        1 AS admin_club_id,
        e.id AS entidad,
        0 AS indica,
        1 AS estatus,
        0 AS permite_inscripcion_linea
    FROM entidad e
    WHERE e.id BETWEEN 1 AND 39
      AND COALESCE(TRIM(e.nombre), '') <> ''
) AS src
ON DUPLICATE KEY UPDATE
    nombre = src.nombre,
    entidad = src.entidad,
    estatus = src.estatus,
    permite_inscripcion_linea = src.permite_inscripcion_linea
SQL;

try {
    $pdo = DB::pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM entidad e WHERE e.id BETWEEN 1 AND 39 AND COALESCE(TRIM(e.nombre), '') <> ''"
    );
    $filasEntidad = (int)$stmt->fetchColumn();

    if ($dryRun) {
        echo "=== DRY-RUN ===\n";
        echo "Entidades elegibles (1-39 con nombre): {$filasEntidad}\n";
        echo "No se ejecutó INSERT.\n";
        exit(0);
    }

    $pdo->beginTransaction();
    $affected = $pdo->exec($sqlInsert);
    $pdo->commit();

    echo "=== ACTUALIZAR CLUBES DESDE ENTIDAD ===\n";
    echo "Filas en entidad elegibles: {$filasEntidad}\n";
    echo "Consulta ejecutada OK (affected rows reportado por el driver: " . (string)$affected . ").\n";
    echo "Listo.\n";
    exit(0);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
