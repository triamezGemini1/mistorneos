<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db_config.php';

$pdo = DB::pdo();

$stmtTorneo = $pdo->query("
    SELECT t.id, t.nombre, t.puntos, t.modalidad, t.estatus
    FROM tournaments t
    WHERE t.modalidad = 2 AND t.estatus = 1
    ORDER BY t.id DESC
    LIMIT 1
");
$torneo = $stmtTorneo->fetch(PDO::FETCH_ASSOC);

if (!$torneo) {
    $stmtTorneo = $pdo->query("
        SELECT t.id, t.nombre, t.puntos, t.modalidad, t.estatus
        FROM tournaments t
        WHERE t.modalidad = 2
          AND EXISTS (
              SELECT 1 FROM partiresul pr
              WHERE pr.id_torneo = t.id AND pr.mesa > 0
          )
        ORDER BY t.id DESC
        LIMIT 1
    ");
    $torneo = $stmtTorneo->fetch(PDO::FETCH_ASSOC);
}

if (!$torneo) {
    echo "No hay torneos de parejas con rondas registradas.\n";
    exit(0);
}

$torneoId = (int)$torneo['id'];
echo "torneo_activo=" . json_encode($torneo, JSON_UNESCAPED_UNICODE) . PHP_EOL;

$stmtRonda = $pdo->prepare("
    SELECT MAX(partida) AS ultima_ronda
    FROM partiresul
    WHERE id_torneo = ? AND mesa > 0
");
$stmtRonda->execute([$torneoId]);
$ultimaRonda = (int)($stmtRonda->fetchColumn() ?? 0);

if ($ultimaRonda <= 0) {
    echo "El torneo activo no tiene rondas con mesas.\n";
    exit(0);
}

echo "ultima_ronda={$ultimaRonda}\n";

$mesas = [1, 6];
$stmtMesa = $pdo->prepare("
    SELECT
        pr.id_usuario,
        pr.secuencia,
        pr.resultado1,
        pr.resultado2,
        pr.efectividad,
        pr.ff,
        pr.tarjeta,
        pr.sancion,
        pr.registrado,
        i.codigo_equipo,
        u.nombre AS nombre_jugador
    FROM partiresul pr
    LEFT JOIN inscritos i ON i.torneo_id = pr.id_torneo AND i.id_usuario = pr.id_usuario
    LEFT JOIN usuarios u ON u.id = pr.id_usuario
    WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa = ?
    ORDER BY pr.secuencia ASC
");

foreach ($mesas as $mesa) {
    $stmtMesa->execute([$torneoId, $ultimaRonda, $mesa]);
    $rows = $stmtMesa->fetchAll(PDO::FETCH_ASSOC);

    echo "mesa={$mesa} filas=" . count($rows) . PHP_EOL;
    if (empty($rows)) {
        continue;
    }

    foreach ($rows as $r) {
        echo json_encode($r, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }

    $porCodigo = [];
    foreach ($rows as $r) {
        $codigo = trim((string)($r['codigo_equipo'] ?? ''));
        if ($codigo === '') {
            $codigo = '(sin_codigo)';
        }
        if (!isset($porCodigo[$codigo])) {
            $porCodigo[$codigo] = [];
        }
        $porCodigo[$codigo][] = $r;
    }

    foreach ($porCodigo as $codigo => $grupo) {
        $ffs = array_map(fn($x) => (int)$x['ff'], $grupo);
        $tars = array_map(fn($x) => (int)$x['tarjeta'], $grupo);
        $sans = array_map(fn($x) => (int)$x['sancion'], $grupo);
        $efes = array_map(fn($x) => (int)$x['efectividad'], $grupo);

        $ok = (min($ffs) === max($ffs))
            && (min($tars) === max($tars))
            && (min($sans) === max($sans))
            && (min($efes) === max($efes));

        echo "pareja={$codigo} consistencia_partiresul=" . ($ok ? 'OK' : 'ERROR')
            . " ff=[" . min($ffs) . "," . max($ffs) . "]"
            . " tarjeta=[" . min($tars) . "," . max($tars) . "]"
            . " sancion=[" . min($sans) . "," . max($sans) . "]"
            . " efectividad=[" . min($efes) . "," . max($efes) . "]"
            . PHP_EOL;
    }
}

