<?php
/**
 * Uso: php storage/reports/_audit_torneo10_cli.php [torneo_id]
 * Salida: resumen + tabla de inscritos (campos relevantes mesas/clasificación).
 */
require_once __DIR__ . '/../../config/db_config.php';

$tid = isset($argv[1]) ? (int) $argv[1] : 10;
$pdo = DB::pdo();

echo "=== Torneo ID: {$tid} ===\n\n";

$t = $pdo->prepare('SELECT id, nombre, modalidad, club_responsable FROM tournaments WHERE id = ?');
$t->execute([$tid]);
$torneo = $t->fetch(PDO::FETCH_ASSOC);
if (!$torneo) {
    fwrite(STDERR, "Torneo no encontrado.\n");
    exit(1);
}
echo "Nombre: " . ($torneo['nombre'] ?? '') . " | modalidad: " . ($torneo['modalidad'] ?? '') . " | club_responsable: " . ($torneo['club_responsable'] ?? '') . "\n\n";

echo "--- Conteo inscritos por estatus (crudo) ---\n";
$st = $pdo->prepare('SELECT estatus, COUNT(*) AS c FROM inscritos WHERE torneo_id = ? GROUP BY estatus ORDER BY estatus');
$st->execute([$tid]);
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  estatus=" . var_export($r['estatus'], true) . " → " . $r['c'] . "\n";
}

echo "\n--- Equipos (tabla equipos) ---\n";
$eq = $pdo->prepare('SELECT codigo_equipo, nombre_equipo, estatus, puntos, posicion FROM equipos WHERE id_torneo = ? ORDER BY codigo_equipo');
$eq->execute([$tid]);
$rowsEq = $eq->fetchAll(PDO::FETCH_ASSOC);
echo "Total equipos fila: " . count($rowsEq) . "\n";
foreach ($rowsEq as $r) {
    echo sprintf(
        "  %s | %s | estatus_eq=%s | pts=%s | pos=%s\n",
        $r['codigo_equipo'],
        mb_substr($r['nombre_equipo'] ?? '', 0, 40),
        $r['estatus'],
        $r['puntos'] ?? 'null',
        $r['posicion'] ?? 'null'
    );
}

$sql = "
SELECT
    i.id AS id_inscrito,
    i.id_usuario,
    i.estatus,
    i.codigo_equipo,
    i.numero,
    i.clasiequi,
    i.posicion AS pos_tabla,
    i.puntos,
    i.ganados,
    i.perdidos,
    i.efectividad,
    i.sancion,
    i.tarjeta,
    i.id_club,
    u.nombre,
    u.cedula,
    e.estatus AS equipo_estatus,
    CASE WHEN i.estatus != 4 AND i.estatus != 'retirado' THEN 1 ELSE 0 END AS pasa_filtro_mesa,
    CASE
        WHEN i.estatus IN (0, 1, 2, 3) OR i.estatus IN ('pendiente', 'confirmado', 'solvente', 'no_solvente') THEN 1
        ELSE 0
    END AS activo_helper
FROM inscritos i
LEFT JOIN usuarios u ON u.id = i.id_usuario
LEFT JOIN equipos e ON e.id_torneo = i.torneo_id AND e.codigo_equipo = i.codigo_equipo
WHERE i.torneo_id = ?
ORDER BY i.codigo_equipo, i.id
";

echo "\n--- Inscritos (detalle) ---\n";
$q = $pdo->prepare($sql);
$q->execute([$tid]);
$all = $q->fetchAll(PDO::FETCH_ASSOC);
echo "Total filas inscritos: " . count($all) . "\n\n";

$hdr = [
    'id_insc', 'id_user', 'estatus', 'cod_eq', 'num', 'clasiequi', 'pos', 'pts', 'g', 'p', 'ef',
    'eq_st', 'mesa_ok', 'act_h', 'nombre',
];
echo implode("\t", $hdr) . "\n";
foreach ($all as $r) {
    echo implode("\t", [
        $r['id_inscrito'],
        $r['id_usuario'],
        $r['estatus'],
        $r['codigo_equipo'] ?? '',
        $r['numero'] ?? '',
        $r['clasiequi'] ?? '',
        $r['pos_tabla'] ?? '',
        $r['puntos'] ?? '',
        $r['ganados'] ?? '',
        $r['perdidos'] ?? '',
        $r['efectividad'] ?? '',
        $r['equipo_estatus'] ?? 'NULL',
        $r['pasa_filtro_mesa'],
        $r['activo_helper'],
        str_replace(["\t", "\n"], ' ', mb_substr($r['nombre'] ?? '', 0, 35)),
    ]) . "\n";
}

echo "\n--- Resumen reglas ---\n";
$nMesa = 0;
$nNoMesa = 0;
$nAct = 0;
foreach ($all as $r) {
    if ((int) $r['pasa_filtro_mesa'] === 1) {
        $nMesa++;
    } else {
        $nNoMesa++;
    }
    if ((int) $r['activo_helper'] === 1) {
        $nAct++;
    }
}
echo "Pasan estatus mesa (≠4 / no retirado): {$nMesa}\n";
echo "No pasan (excluidos de mesas): {$nNoMesa}\n";
echo "Activos según InscritosHelper: {$nAct}\n";

$sinEq = array_filter($all, static function ($r) {
    $c = trim((string) ($r['codigo_equipo'] ?? ''));

    return $c === '';
});
echo "Sin codigo_equipo: " . count($sinEq) . "\n";

$eqInactivo = array_filter($all, static function ($r) {
    return isset($r['equipo_estatus']) && (string) $r['equipo_estatus'] !== '' && (int) $r['equipo_estatus'] !== 0;
});
echo "Con equipo no activo (equipos.estatus≠0): " . count($eqInactivo) . "\n";
