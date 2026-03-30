<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../lib/InscritosHelper.php';

Auth::requireRole(['admin_general', 'admin_torneo', 'admin_club']);

$torneoId = (int)($_GET['torneo_id'] ?? 0);
if ($torneoId <= 0 || !Auth::canAccessTournament($torneoId)) {
    http_response_code(403);
    exit('Acceso denegado');
}

$pdo = DB::pdo();
$stmtT = $pdo->prepare('SELECT id, nombre, modalidad, es_evento_masivo, club_responsable FROM tournaments WHERE id = ? LIMIT 1');
$stmtT->execute([$torneoId]);
$torneo = $stmtT->fetch(PDO::FETCH_ASSOC);
if (!$torneo) {
    http_response_code(404);
    exit('Torneo no encontrado');
}

$stmt = $pdo->prepare("
    SELECT
        i.id AS inscrito_id,
        i.torneo_id,
        i.id_usuario,
        COALESCE(i.numfvd, 0) AS inscrito_numfvd,
        i.id_club,
        i.estatus,
        i.fecha_inscripcion,
        i.cedula AS cedula_inscrita,
        COALESCE(u.id, u_alt.id) AS usuario_id_real,
        COALESCE(u.nombre, u_alt.nombre) AS usuario_nombre,
        COALESCE(u.username, u_alt.username) AS usuario_username,
        COALESCE(u.cedula, u_alt.cedula) AS usuario_cedula,
        COALESCE(u.numfvd, u_alt.numfvd, 0) AS usuario_numfvd,
        COALESCE(u.nacionalidad, u_alt.nacionalidad) AS usuario_nacionalidad,
        COALESCE(u.sexo, u_alt.sexo) AS usuario_sexo,
        COALESCE(u.email, u_alt.email) AS usuario_email,
        COALESCE(u.telefono, u_alt.telefono) AS usuario_telefono,
        c.nombre AS club_nombre
    FROM inscritos i
    LEFT JOIN usuarios u ON u.id = i.id_usuario
    LEFT JOIN usuarios u_alt ON u.id IS NULL
        AND u_alt.numfvd = i.id_usuario
        AND EXISTS (
            SELECT 1 FROM tournaments tx
            WHERE tx.id = i.torneo_id AND tx.club_responsable = 7
        )
    LEFT JOIN clubes c ON c.id = i.id_club
    WHERE i.torneo_id = ?
    ORDER BY COALESCE(c.nombre, 'Sin Club') ASC, COALESCE(u.nombre, u_alt.nombre, u.username, u_alt.username, '') ASC, i.id ASC
");
$stmt->execute([$torneoId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$rows = InscritosHelper::agregarEstatusTexto($rows);

$filename = 'inscritos_torneo_' . $torneoId . '_' . date('Y-m-d_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$esc = static fn ($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$modalidad = (int)($torneo['modalidad'] ?? 0) === 3 ? 'Equipos' : ((int)($torneo['modalidad'] ?? 0) === 2 ? 'Parejas' : 'Individual');
$tipo = (int)($torneo['es_evento_masivo'] ?? 0);
$tipoTxt = $tipo === 3 ? 'Local' : ($tipo === 2 ? 'Regional' : ($tipo === 1 ? 'Masivo' : 'Regular'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inscritos</title>
</head>
<body>
    <table border="1" cellpadding="5" cellspacing="0">
        <tr>
            <td colspan="14" style="font-weight:bold; text-align:center; background:#e2e8f0;">
                REPORTE GENERAL DE INSCRITOS - <?= $esc($torneo['nombre'] ?? '') ?>
            </td>
        </tr>
        <tr>
            <td colspan="14" style="text-align:center;">
                Torneo ID: <?= (int)$torneoId ?> |
                Modalidad: <?= $esc($modalidad) ?> |
                Tipo: <?= $esc($tipoTxt) ?> |
                Generado: <?= $esc(date('d/m/Y H:i')) ?>
            </td>
        </tr>
        <tr style="font-weight:bold; background:#f8fafc;">
            <td>inscrito_id</td>
            <td>id_usuario_guardado</td>
            <td>usuario_id_real</td>
            <td>numfvd</td>
            <td>cedula</td>
            <td>nombre</td>
            <td>username</td>
            <td>sexo</td>
            <td>nacionalidad</td>
            <td>email</td>
            <td>telefono</td>
            <td>club</td>
            <td>estatus</td>
            <td>fecha_inscripcion</td>
        </tr>
        <?php foreach ($rows as $r): ?>
            <?php
            $numfvd = (int)($r['usuario_numfvd'] ?? 0);
            if ($numfvd <= 0) {
                $numfvd = (int)($r['inscrito_numfvd'] ?? 0);
            }
            ?>
            <tr>
                <td><?= (int)($r['inscrito_id'] ?? 0) ?></td>
                <td><?= (int)($r['id_usuario'] ?? 0) ?></td>
                <td><?= (int)($r['usuario_id_real'] ?? 0) ?></td>
                <td><?= $numfvd ?></td>
                <td><?= $esc($r['usuario_cedula'] ?? $r['cedula_inscrita'] ?? '') ?></td>
                <td><?= $esc($r['usuario_nombre'] ?? '') ?></td>
                <td><?= $esc($r['usuario_username'] ?? '') ?></td>
                <td><?= $esc($r['usuario_sexo'] ?? '') ?></td>
                <td><?= $esc($r['usuario_nacionalidad'] ?? '') ?></td>
                <td><?= $esc($r['usuario_email'] ?? '') ?></td>
                <td><?= $esc($r['usuario_telefono'] ?? '') ?></td>
                <td><?= $esc($r['club_nombre'] ?? '') ?></td>
                <td><?= $esc($r['estatus_formateado'] ?? $r['estatus'] ?? '') ?></td>
                <td><?= $esc($r['fecha_inscripcion'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
