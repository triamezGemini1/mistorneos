<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db_config.php';

$pdo = DB::pdo();

$sqlInconsistencias = "
    SELECT
        pr.id_torneo,
        pr.partida,
        pr.mesa,
        i.codigo_equipo,
        COUNT(*) AS total_filas,
        MIN(COALESCE(pr.ff, 0)) AS min_ff,
        MAX(COALESCE(pr.ff, 0)) AS max_ff,
        MIN(COALESCE(pr.tarjeta, 0)) AS min_tarjeta,
        MAX(COALESCE(pr.tarjeta, 0)) AS max_tarjeta,
        MIN(COALESCE(pr.sancion, 0)) AS min_sancion,
        MAX(COALESCE(pr.sancion, 0)) AS max_sancion,
        MIN(COALESCE(pr.efectividad, 0)) AS min_efectividad,
        MAX(COALESCE(pr.efectividad, 0)) AS max_efectividad
    FROM partiresul pr
    INNER JOIN inscritos i
        ON i.torneo_id = pr.id_torneo
       AND i.id_usuario = pr.id_usuario
    INNER JOIN tournaments t
        ON t.id = pr.id_torneo
    WHERE t.modalidad = 2
      AND pr.registrado = 1
      AND pr.mesa > 0
      AND i.codigo_equipo IS NOT NULL
      AND i.codigo_equipo != ''
      AND i.codigo_equipo != '000-000'
    GROUP BY pr.id_torneo, pr.partida, pr.mesa, i.codigo_equipo
    HAVING total_filas >= 2
       AND (
            min_ff != max_ff
         OR min_tarjeta != max_tarjeta
         OR min_sancion != max_sancion
         OR min_efectividad != max_efectividad
       )
    ORDER BY pr.id_torneo DESC, pr.partida DESC, pr.mesa DESC
    LIMIT 50
";

$rows = $pdo->query($sqlInconsistencias)->fetchAll(PDO::FETCH_ASSOC);
echo 'inconsistencias_partiresul=' . count($rows) . PHP_EOL;
foreach ($rows as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

$sqlRojas = "
    SELECT
        pr.id_torneo,
        pr.partida,
        pr.mesa,
        i.codigo_equipo,
        MIN(COALESCE(pr.tarjeta, 0)) AS min_tarjeta,
        MAX(COALESCE(pr.tarjeta, 0)) AS max_tarjeta,
        MIN(COALESCE(pr.efectividad, 0)) AS min_efectividad,
        MAX(COALESCE(pr.efectividad, 0)) AS max_efectividad
    FROM partiresul pr
    INNER JOIN inscritos i
        ON i.torneo_id = pr.id_torneo
       AND i.id_usuario = pr.id_usuario
    INNER JOIN tournaments t
        ON t.id = pr.id_torneo
    WHERE t.modalidad = 2
      AND pr.registrado = 1
      AND pr.mesa > 0
      AND i.codigo_equipo IS NOT NULL
      AND i.codigo_equipo != ''
      AND i.codigo_equipo != '000-000'
    GROUP BY pr.id_torneo, pr.partida, pr.mesa, i.codigo_equipo
    HAVING max_tarjeta IN (3,4)
    ORDER BY pr.id_torneo DESC, pr.partida DESC, pr.mesa DESC
    LIMIT 20
";

$rojas = $pdo->query($sqlRojas)->fetchAll(PDO::FETCH_ASSOC);
echo 'muestras_tarjeta_grave=' . count($rojas) . PHP_EOL;
foreach ($rojas as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

$sqlInscritos = "
    SELECT
        i.torneo_id,
        i.codigo_equipo,
        COUNT(*) AS total_jugadores,
        MIN(COALESCE(i.ganados, 0)) AS min_ganados,
        MAX(COALESCE(i.ganados, 0)) AS max_ganados,
        MIN(COALESCE(i.perdidos, 0)) AS min_perdidos,
        MAX(COALESCE(i.perdidos, 0)) AS max_perdidos,
        MIN(COALESCE(i.efectividad, 0)) AS min_efectividad,
        MAX(COALESCE(i.efectividad, 0)) AS max_efectividad,
        MIN(COALESCE(i.puntos, 0)) AS min_puntos,
        MAX(COALESCE(i.puntos, 0)) AS max_puntos,
        MIN(COALESCE(i.sancion, 0)) AS min_sancion,
        MAX(COALESCE(i.sancion, 0)) AS max_sancion,
        MIN(COALESCE(i.tarjeta, 0)) AS min_tarjeta,
        MAX(COALESCE(i.tarjeta, 0)) AS max_tarjeta
    FROM inscritos i
    INNER JOIN tournaments t ON t.id = i.torneo_id
    WHERE t.modalidad = 2
      AND i.estatus != 4
      AND i.codigo_equipo IS NOT NULL
      AND i.codigo_equipo != ''
      AND i.codigo_equipo != '000-000'
    GROUP BY i.torneo_id, i.codigo_equipo
    HAVING total_jugadores >= 2
       AND (
            min_ganados != max_ganados
         OR min_perdidos != max_perdidos
         OR min_efectividad != max_efectividad
         OR min_puntos != max_puntos
         OR min_sancion != max_sancion
         OR min_tarjeta != max_tarjeta
       )
    ORDER BY i.torneo_id DESC, i.codigo_equipo ASC
    LIMIT 50
";

$insInconsistentes = $pdo->query($sqlInscritos)->fetchAll(PDO::FETCH_ASSOC);
echo 'inconsistencias_inscritos=' . count($insInconsistentes) . PHP_EOL;
foreach ($insInconsistentes as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

