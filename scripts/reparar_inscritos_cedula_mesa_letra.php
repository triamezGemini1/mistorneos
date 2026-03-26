<?php
/**
 * Reparación de inscritos:
 * 1) Completa cedula/nacionalidad en inscritos desde usuarios.
 * 2) (Opcional) Sincroniza mesa/letra de inscritos desde partiresul por ronda.
 *
 * Uso:
 *   php scripts/reparar_inscritos_cedula_mesa_letra.php --torneo=4
 *   php scripts/reparar_inscritos_cedula_mesa_letra.php --torneo=4 --ronda=2
 *   php scripts/reparar_inscritos_cedula_mesa_letra.php --torneo=4 --ronda=2 --solo-activos=1
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/InscritosHelper.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(400);
    echo "Este script es solo para CLI.\n";
    exit(1);
}

$opts = getopt('', ['torneo:', 'ronda::', 'solo-activos::']);
$torneoId = isset($opts['torneo']) ? (int)$opts['torneo'] : 0;
$ronda = isset($opts['ronda']) ? (int)$opts['ronda'] : 0;
$soloActivos = !isset($opts['solo-activos']) || (int)$opts['solo-activos'] === 1;

if ($torneoId <= 0) {
    echo "Falta --torneo=ID\n";
    exit(1);
}

$pdo = DB::pdo();

function tieneColumna(PDO $pdo, string $tabla, string $columna): bool
{
    $sql = "SHOW COLUMNS FROM `{$tabla}` LIKE ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$columna]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

echo "=== REPARAR INSCRITOS (torneo={$torneoId}) ===\n";

$tieneCedula = tieneColumna($pdo, 'inscritos', 'cedula');
$tieneNacionalidad = tieneColumna($pdo, 'inscritos', 'nacionalidad');
$tieneMesa = tieneColumna($pdo, 'inscritos', 'mesa');
$tieneLetra = tieneColumna($pdo, 'inscritos', 'letra');

echo "Columnas detectadas en inscritos: ";
echo "cedula=" . ($tieneCedula ? 'SI' : 'NO') . ", ";
echo "nacionalidad=" . ($tieneNacionalidad ? 'SI' : 'NO') . ", ";
echo "mesa=" . ($tieneMesa ? 'SI' : 'NO') . ", ";
echo "letra=" . ($tieneLetra ? 'SI' : 'NO') . "\n\n";

if (!$tieneCedula || !$tieneNacionalidad) {
    echo "ERROR: faltan columnas cedula/nacionalidad en inscritos.\n";
    echo "Ejecuta primero la migración correspondiente.\n";
    exit(1);
}

$whereActivos = $soloActivos ? (" AND " . InscritosHelper::SQL_WHERE_ACTIVO) : '';

// 1) Reparar cédula y nacionalidad
$sqlCedula = "
    UPDATE inscritos i
    INNER JOIN usuarios u ON u.id = i.id_usuario
    SET
        i.nacionalidad = CASE
            WHEN UPPER(TRIM(COALESCE(u.nacionalidad, ''))) IN ('V','E','J','P')
                THEN UPPER(TRIM(u.nacionalidad))
            ELSE 'V'
        END,
        i.cedula = REGEXP_REPLACE(COALESCE(u.cedula, ''), '[^0-9]', '')
    WHERE i.torneo_id = ?
    {$whereActivos}
";
$stmtCedula = $pdo->prepare($sqlCedula);
$stmtCedula->execute([$torneoId]);
$rowsCedula = $stmtCedula->rowCount();
echo "1) Cedula/nacionalidad sincronizadas en inscritos: {$rowsCedula} filas.\n";

// Diagnóstico cedulas vacías
$sqlVacias = "
    SELECT COUNT(*) AS total
    FROM inscritos i
    WHERE i.torneo_id = ?
      AND (i.cedula IS NULL OR TRIM(i.cedula) = '')
      {$whereActivos}
";
$stmtVacias = $pdo->prepare($sqlVacias);
$stmtVacias->execute([$torneoId]);
$vacias = (int)($stmtVacias->fetchColumn() ?: 0);
echo "   Cedulas vacias restantes: {$vacias}\n";

// 2) Opcional: sincronizar mesa/letra desde partiresul
if ($ronda > 0) {
    if (!$tieneMesa || !$tieneLetra) {
        echo "\n2) Se pidio --ronda={$ronda}, pero faltan columnas mesa/letra en inscritos.\n";
        exit(1);
    }

    echo "\n2) Sincronizando mesa/letra desde partiresul (ronda={$ronda})...\n";

    $pdo->beginTransaction();
    try {
        // Limpiar staging previo solo para activos en este torneo
        $sqlReset = "
            UPDATE inscritos i
            SET i.mesa = 0, i.letra = NULL
            WHERE i.torneo_id = ?
              AND i.codigo_equipo IS NOT NULL
              AND i.codigo_equipo != ''
              {$whereActivos}
        ";
        $stmtReset = $pdo->prepare($sqlReset);
        $stmtReset->execute([$torneoId]);
        $rowsReset = $stmtReset->rowCount();

        // Cargar mesa/letra según secuencia de partiresul
        $sqlMesaLetra = "
            UPDATE inscritos i
            INNER JOIN partiresul pr
                ON pr.id_torneo = i.torneo_id
               AND pr.id_usuario = i.id_usuario
               AND pr.partida = ?
            SET
                i.mesa = pr.mesa,
                i.letra = CASE pr.secuencia
                    WHEN 1 THEN 'A'
                    WHEN 2 THEN 'C'
                    WHEN 3 THEN 'B'
                    WHEN 4 THEN 'D'
                    ELSE NULL
                END
            WHERE i.torneo_id = ?
              AND pr.mesa > 0
              {$whereActivos}
        ";
        $stmtMesaLetra = $pdo->prepare($sqlMesaLetra);
        $stmtMesaLetra->execute([$ronda, $torneoId]);
        $rowsMesaLetra = $stmtMesaLetra->rowCount();

        $pdo->commit();
        echo "   Reset mesa/letra: {$rowsReset} filas.\n";
        echo "   Mesa/letra actualizadas desde partiresul: {$rowsMesaLetra} filas.\n";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "   ERROR sincronizando mesa/letra: {$e->getMessage()}\n";
        exit(1);
    }

    // Diagnóstico por pareja: cuantos jugadores tienen mesa asignada
    $sqlDiagPareja = "
        SELECT
            i.codigo_equipo,
            COUNT(*) AS jugadores,
            SUM(CASE WHEN i.mesa > 0 THEN 1 ELSE 0 END) AS con_mesa,
            MIN(i.mesa) AS mesa_min,
            MAX(i.mesa) AS mesa_max
        FROM inscritos i
        WHERE i.torneo_id = ?
          AND i.codigo_equipo IS NOT NULL
          AND i.codigo_equipo != ''
          {$whereActivos}
        GROUP BY i.codigo_equipo
        ORDER BY i.codigo_equipo ASC
    ";
    $stmtDiagPareja = $pdo->prepare($sqlDiagPareja);
    $stmtDiagPareja->execute([$torneoId]);
    $diag = $stmtDiagPareja->fetchAll(PDO::FETCH_ASSOC);

    $inconsistentes = 0;
    foreach ($diag as $row) {
        $jug = (int)$row['jugadores'];
        $conMesa = (int)$row['con_mesa'];
        $mesaMin = (int)$row['mesa_min'];
        $mesaMax = (int)$row['mesa_max'];
        if ($jug !== 2 || $conMesa !== 2 || $mesaMin !== $mesaMax) {
            $inconsistentes++;
            echo "   [WARN] {$row['codigo_equipo']} jugadores={$jug}, con_mesa={$conMesa}, mesa_min={$mesaMin}, mesa_max={$mesaMax}\n";
        }
    }
    echo "   Parejas inconsistentes (deben ser 2 jugadores y misma mesa): {$inconsistentes}\n";
}

echo "\n=== FIN ===\n";
exit(0);

