<?php
/**
 * Reporte de identificación: cuadrícula de tarjetas (estilo asignación de mesas).
 * Papel CARTA. Tarjeta 3,5cm × 3,5cm. 5 columnas × 6 filas (30 por hoja). Sin encabezado al imprimir.
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

$script = $_SERVER['SCRIPT_NAME'] ?? 'index.php';
$base_url = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . dirname($script);
$url_panel = rtrim($base_url, '/') . '/' . basename($script) . '?page=tournament_admin&action=dashboard&torneo_id=' . (int)$torneo_id;
?>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

.cuadricula-tarjetas-container {
    background: #fff;
    margin: 0 auto;
    width: 95%;
    max-width: 95%;
}

.cuadricula-tarjetas-grid {
    display: grid;
    grid-template-columns: repeat(5, 3.6cm);
    grid-template-rows: repeat(6, 4cm);
    gap: 0;
    border-collapse: collapse;
    width: 100%;
    margin: 0 auto;
    page-break-after: always;
}
.cuadricula-tarjetas-grid:last-child { page-break-after: auto; }

.tarjeta-id {
    width: 3.6cm;
    height: 4cm;
    box-sizing: border-box;
    border: 0.5mm solid #000;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    font-family: Calibri, 'Lato', Arial, sans-serif;
    page-break-inside: avoid;
    background: #fff;
    padding: 1.5mm;
}
.tarjeta-id .nombre { font-size: 10.5pt; font-weight: bold; color: #212121; margin-bottom: 1mm; line-height: 1.1; }
.tarjeta-id .cedula { font-size: 14.7pt; font-weight: bold; color: #424242; margin-bottom: 1mm; }
.tarjeta-id .id-jugador { font-size: 19.6pt; font-weight: bold; color: #0d47a1; margin-bottom: 0.5mm; }

@media print {
    @page { size: letter; margin: 1cm; }
    header, footer, nav, aside, .buttons, .no-print-id,
    .col-md-3, .card > .card-body > p, .cuadricula-tarjetas-container > .no-print-id { display: none !important; }
    .col-md-9 { max-width: 100% !important; flex: 0 0 100% !important; }
    .card, .card-body { border: none !important; box-shadow: none !important; background: transparent !important; padding: 0 !important; }
    body { background: #fff; margin: 0; padding: 0; }
    .cuadricula-tarjetas-container { padding: 0; }
    .cuadricula-tarjetas-grid { page-break-inside: avoid; }
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
            <div class="cuadricula-tarjetas-container">
                <div id="area-impresion-tarjetas">
                    <?php
                    $por_pagina = 30;
                    $paginas = array_chunk($jugadores, $por_pagina);
                    foreach ($paginas as $grupo):
                    ?>
                    <div class="cuadricula-tarjetas-grid">
                        <?php foreach ($grupo as $j):
                            $nombre = htmlspecialchars($j['nombre'] ?? '—');
                            $cedula = htmlspecialchars($j['cedula'] ?? '');
                            $id_jugador = (int)($j['id_usuario'] ?? 0);
                        ?>
                        <div class="tarjeta-id">
                            <div class="nombre"><?= $nombre ?></div>
                            <div class="cedula"><?= $cedula ?></div>
                            <div class="id-jugador"><?= $id_jugador ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
