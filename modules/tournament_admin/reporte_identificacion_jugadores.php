<?php
/**
 * Reporte de identificación de jugadores.
 * Vista de impresión optimizada para papel CARTA, 24 tarjetas por hoja (4 columnas × 6 filas).
 * Tarjeta 4cm × 4cm; contenido: Nombre, Cédula, Club, Organización.
 */

$pdo = DB::pdo();
$torneo_nombre = isset($torneo['nombre']) ? $torneo['nombre'] : 'Torneo';

$stmt = $pdo->prepare("
    SELECT i.id_usuario, u.nombre, u.cedula,
           c.nombre AS club_nombre,
           o.nombre AS organizacion_nombre
    FROM inscritos i
    INNER JOIN usuarios u ON u.id = i.id_usuario
    LEFT JOIN clubes c ON c.id = COALESCE(i.id_club, u.club_id)
    LEFT JOIN organizaciones o ON o.id = c.organizacion_id
    WHERE i.torneo_id = ?
    AND (i.estatus = 1 OR i.estatus = 2 OR i.estatus = '1' OR i.estatus = 'confirmado')
    ORDER BY u.nombre ASC
");
$stmt->execute([$torneo_id]);
$jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

$url_panel = 'index.php?page=tournament_admin&action=dashboard&torneo_id=' . (int)$torneo_id;
?>
<style>
.hoja-impresion {
    display: grid;
    grid-template-columns: repeat(4, 4cm);
    grid-template-rows: repeat(6, 4cm);
    justify-content: center;
    gap: 2mm;
    page-break-after: always;
    width: 100%;
}
.hoja-impresion:last-child { page-break-after: auto; }

.tarjeta-id {
    width: 4cm;
    height: 4cm;
    box-sizing: border-box;
    border: 0.1mm dashed #ccc;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    font-family: Arial, Helvetica, sans-serif;
    page-break-inside: avoid;
    background: #fff;
    padding: 1.5mm;
}
.tarjeta-id .nombre { font-size: 8pt; font-weight: bold; color: #212121; margin-bottom: 1mm; line-height: 1.15; }
.tarjeta-id .cedula { font-size: 7pt; color: #424242; margin-bottom: 1mm; }
.tarjeta-id .club { font-size: 6pt; color: #555; margin-bottom: 0.5mm; }
.tarjeta-id .organizacion { font-size: 6pt; color: #666; }

@media print {
    @page { size: letter; margin: 1cm; }
    header, footer, nav, aside, .buttons, .no-print-id,
    .col-md-3, .card > .card-body > p { display: none !important; }
    .col-md-9 { max-width: 100% !important; flex: 0 0 100% !important; }
    .card, .card-body { border: none !important; box-shadow: none !important; background: transparent !important; padding: 0 !important; }
    body { background: #fff; }
}
</style>
<div class="buttons no-print-id mb-3 d-flex align-items-center gap-2">
    <a href="<?= htmlspecialchars($url_panel) ?>" class="btn btn-primary">
        <i class="fas fa-arrow-left me-1"></i>Volver al panel
    </a>
    <button type="button" class="btn btn-outline-secondary" onclick="window.print();">
        <i class="fas fa-print me-1"></i>Imprimir Tarjetas
    </button>
</div>
<div class="card">
    <div class="card-body">
        <?php if (empty($jugadores)): ?>
            <p class="text-muted">No hay jugadores confirmados para este torneo.</p>
        <?php else: ?>
            <div id="area-impresion-tarjetas">
                <?php
                $por_pagina = 24;
                $paginas = array_chunk($jugadores, $por_pagina);
                foreach ($paginas as $grupo):
                ?>
                <div class="hoja-impresion">
                    <?php foreach ($grupo as $j):
                        $nombre = htmlspecialchars($j['nombre'] ?? '—');
                        $cedula = htmlspecialchars($j['cedula'] ?? '');
                        $club = htmlspecialchars($j['club_nombre'] ?? '—');
                        $organizacion = htmlspecialchars($j['organizacion_nombre'] ?? '—');
                    ?>
                    <div class="tarjeta-id">
                        <div class="nombre"><?= $nombre ?></div>
                        <div class="cedula">C.I. <?= $cedula ?></div>
                        <div class="club"><?= $club ?></div>
                        <div class="organizacion"><?= $organizacion ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
