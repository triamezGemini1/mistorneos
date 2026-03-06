<?php
/**
 * Reporte de identificación de jugadores.
 * Solo nombre, cédula e ID del torneo. Tarjetas 4cm × 4cm con borde y tipografía legible.
 */

$pdo = DB::pdo();
$torneo_nombre = isset($torneo['nombre']) ? $torneo['nombre'] : 'Torneo';

$stmt = $pdo->prepare("
    SELECT i.id_usuario, i.torneo_id, u.nombre, u.cedula
    FROM inscritos i
    INNER JOIN usuarios u ON u.id = i.id_usuario
    WHERE i.torneo_id = ?
    AND (i.estatus = 1 OR i.estatus = 2 OR i.estatus = '1' OR i.estatus = 'confirmado')
    ORDER BY u.nombre ASC
");
$stmt->execute([$torneo_id]);
$jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<style>
.tarjeta-id-lote {
    width: 4cm;
    height: 4cm;
    border: 3px solid #333;
    border-radius: 4px;
    padding: 0.25cm;
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
.tarjeta-id-lote .titulo-torneo { font-size: 9pt; font-weight: bold; color: #1565c0; margin-bottom: 0.2cm; line-height: 1.15; }
.tarjeta-id-lote .nombre { font-size: 11pt; font-weight: bold; color: #212121; margin-bottom: 0.15cm; line-height: 1.2; }
.tarjeta-id-lote .cedula { font-size: 10pt; color: #424242; margin-bottom: 0.15cm; }
.tarjeta-id-lote .id-torneo { font-size: 10pt; font-weight: bold; color: #0d47a1; }
@media print {
    body * { visibility: hidden; }
    #area-impresion-tarjetas, #area-impresion-tarjetas * { visibility: visible; }
    #area-impresion-tarjetas { position: absolute; left: 0; top: 0; width: 100%; padding: 0.5cm; }
    .no-print-id { display: none !important; }
}
</style>
<div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center no-print-id">
        <h6 class="mb-0"><i class="fas fa-address-card me-2"></i>Identificación de jugadores</h6>
        <button type="button" class="btn btn-light btn-sm" onclick="window.print();">
            <i class="fas fa-print me-1"></i>Imprimir
        </button>
    </div>
    <div class="card-body">
        <?php if (empty($jugadores)): ?>
            <p class="text-muted">No hay jugadores confirmados para este torneo.</p>
        <?php else: ?>
            <p class="small text-muted no-print-id mb-3">Tarjetas 4cm × 4cm: nombre, cédula e ID del torneo.</p>
            <div id="area-impresion-tarjetas" class="row g-2">
                <?php foreach ($jugadores as $j):
                    $nombre = htmlspecialchars($j['nombre'] ?? '—');
                    $cedula = htmlspecialchars($j['cedula'] ?? '');
                    $id_torneo = (int)($j['torneo_id'] ?? $torneo_id);
                ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="tarjeta-id-lote">
                        <div class="titulo-torneo"><?= htmlspecialchars($torneo_nombre) ?></div>
                        <div class="nombre"><?= $nombre ?></div>
                        <div class="cedula">C.I. <?= $cedula ?></div>
                        <div class="id-torneo">ID Torneo: <?= $id_torneo ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
