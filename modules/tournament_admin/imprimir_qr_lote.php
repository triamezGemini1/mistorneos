<?php
/**
 * Imprimir en lote: tarjetas personales por jugador confirmado.
 * Solo nombre, cédula e ID del jugador. Tarjeta 8cm × 8cm. 5 columnas × 6 filas por hoja.
 */

$pdo = DB::pdo();
$torneo_nombre = isset($torneo['nombre']) ? $torneo['nombre'] : 'Torneo';

$stmt = $pdo->prepare("
    SELECT i.id_usuario, u.nombre, u.cedula
    FROM inscritos i
    INNER JOIN usuarios u ON u.id = i.id_usuario
    WHERE i.torneo_id = ?
    AND (i.estatus = 1 OR i.estatus = 2 OR i.estatus = '1' OR i.estatus = 'confirmado')
    ORDER BY u.nombre ASC
");
$stmt->execute([$torneo_id]);
$jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

$url_panel = 'index.php?page=tournament_admin&action=dashboard&torneo_id=' . (int)$torneo_id;
?>
<style>
.contenedor-pagina-tarjetas {
    display: grid;
    grid-template-columns: repeat(5, 8cm);
    grid-template-rows: repeat(6, 8cm);
    width: 40cm;
    gap: 0;
    page-break-after: always;
}
.contenedor-pagina-tarjetas:last-child { page-break-after: auto; }
.tarjeta-id-lote {
    width: 8cm;
    height: 8cm;
    border: 4px solid #333;
    border-radius: 6px;
    padding: 0.4cm;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    font-family: Arial, Helvetica, sans-serif;
    page-break-inside: avoid;
    background: #fff;
}
.tarjeta-id-lote .titulo-torneo { font-size: 14pt; font-weight: bold; color: #1565c0; margin-bottom: 0.3cm; line-height: 1.2; }
.tarjeta-id-lote .nombre { font-size: 16pt; font-weight: bold; color: #212121; margin-bottom: 0.25cm; line-height: 1.25; }
.tarjeta-id-lote .cedula { font-size: 28pt; color: #424242; margin-bottom: 0.25cm; }
.tarjeta-id-lote .id-jugador { font-size: 36pt; font-weight: bold; color: #0d47a1; }
@media print {
    .no-print-lote { display: none !important; }
    .col-md-3 { display: none !important; }
    .col-md-9 { max-width: 100% !important; flex: 0 0 100% !important; }
    .card .card-body { padding: 0 !important; border: none !important; background: transparent !important; }
    .card { border: none !important; box-shadow: none !important; background: transparent !important; }
    .tarjeta-id-lote .titulo-torneo { display: none !important; }
    @page { size: 40cm 48cm; margin: 0.5cm; }
}
</style>
<div class="no-print-lote mb-3 d-flex align-items-center gap-2">
    <a href="<?= htmlspecialchars($url_panel) ?>" class="btn btn-primary">
        <i class="fas fa-arrow-left me-1"></i>Volver al panel
    </a>
    <button type="button" class="btn btn-outline-secondary" onclick="window.print();">
        <i class="fas fa-print me-1"></i>Imprimir
    </button>
</div>
<div class="card">
    <div class="card-body">
        <?php if (empty($jugadores)): ?>
            <p class="text-muted">No hay jugadores confirmados para este torneo.</p>
        <?php else: ?>
            <div id="area-impresion-tarjetas">
                <?php
                $por_pagina = 30;
                $paginas = array_chunk($jugadores, $por_pagina);
                foreach ($paginas as $grupo):
                ?>
                <div class="contenedor-pagina-tarjetas">
                    <?php foreach ($grupo as $j):
                        $nombre = htmlspecialchars($j['nombre'] ?? '—');
                        $cedula = htmlspecialchars($j['cedula'] ?? '');
                        $id_jugador = (int)($j['id_usuario'] ?? 0);
                    ?>
                    <div class="tarjeta-id-lote">
                        <div class="titulo-torneo"><?= htmlspecialchars($torneo_nombre) ?></div>
                        <div class="nombre"><?= $nombre ?></div>
                        <div class="cedula">C.I. <?= $cedula ?></div>
                        <div class="id-jugador">ID: <?= $id_jugador ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
