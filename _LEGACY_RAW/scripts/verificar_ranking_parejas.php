<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db_config.php';

$pdo = DB::pdo();

$torneoId = isset($argv[1]) ? (int)$argv[1] : 0;
if ($torneoId > 0) {
    $stmt = $pdo->prepare("
        SELECT id, nombre, modalidad, estatus
        FROM tournaments
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$torneoId]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->query("
        SELECT id, nombre, modalidad, estatus
        FROM tournaments
        WHERE modalidad IN (2,4)
        ORDER BY id DESC
        LIMIT 1
    ");
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($torneo) {
        $torneoId = (int)$torneo['id'];
    }
}

if (!$torneo) {
    echo "No hay torneos de parejas en la base.\n";
    exit(0);
}

echo "torneo=" . json_encode($torneo, JSON_UNESCAPED_UNICODE) . PHP_EOL;
$modalidad = (int)($torneo['modalidad'] ?? 0);
if (!in_array($modalidad, [2, 4], true)) {
    echo "aviso=El torneo indicado no es modalidad parejas (2/4). No aplica verificación por pareja.\n";
    exit(0);
}

$sql = "
    SELECT
        i.codigo_equipo,
        COUNT(*) AS jugadores,
        MIN(COALESCE(i.posicion,0)) AS min_pos,
        MAX(COALESCE(i.posicion,0)) AS max_pos,
        MIN(COALESCE(i.ptosrnk,0)) AS min_ptosrnk,
        MAX(COALESCE(i.ptosrnk,0)) AS max_ptosrnk
    FROM inscritos i
    WHERE i.torneo_id = ?
      AND i.estatus != 4
      AND i.codigo_equipo IS NOT NULL
      AND i.codigo_equipo != ''
      AND i.codigo_equipo != '000-000'
    GROUP BY i.codigo_equipo
    ORDER BY min_pos ASC, i.codigo_equipo ASC
    LIMIT 10
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$torneoId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "muestra_parejas=" . count($rows) . PHP_EOL;
foreach ($rows as $r) {
    $okPos = ((int)$r['min_pos'] === (int)$r['max_pos']);
    $okRnk = ((int)$r['min_ptosrnk'] === (int)$r['max_ptosrnk']);
    echo json_encode([
        'codigo_equipo' => $r['codigo_equipo'],
        'jugadores' => (int)$r['jugadores'],
        'posicion' => [(int)$r['min_pos'], (int)$r['max_pos']],
        'ptosrnk' => [(int)$r['min_ptosrnk'], (int)$r['max_ptosrnk']],
        'consistente' => ($okPos && $okRnk) ? 'OK' : 'ERROR',
    ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

$sqlErr = "
    SELECT COUNT(*) AS inconsistentes
    FROM (
        SELECT
            i.codigo_equipo,
            MIN(COALESCE(i.posicion,0)) AS min_pos,
            MAX(COALESCE(i.posicion,0)) AS max_pos,
            MIN(COALESCE(i.ptosrnk,0)) AS min_ptosrnk,
            MAX(COALESCE(i.ptosrnk,0)) AS max_ptosrnk
        FROM inscritos i
        WHERE i.torneo_id = ?
          AND i.estatus != 4
          AND i.codigo_equipo IS NOT NULL
          AND i.codigo_equipo != ''
          AND i.codigo_equipo != '000-000'
        GROUP BY i.codigo_equipo
        HAVING min_pos != max_pos OR min_ptosrnk != max_ptosrnk
    ) t
";
$stmtErr = $pdo->prepare($sqlErr);
$stmtErr->execute([$torneoId]);
$inconsistentes = (int)$stmtErr->fetchColumn();
echo "inconsistentes_global={$inconsistentes}" . PHP_EOL;

