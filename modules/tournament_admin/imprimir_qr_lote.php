<?php
/**
 * Imprimir en lote: tarjetas QR personales por jugador confirmado.
 * Modelo compacto tipo tarjeta: torneo, nombre, ID jugador, QR a perfil (para entregar al confirmar participación).
 * $torneo_id, $torneo, $pdo provienen del módulo tournament_admin.
 */

$pdo = DB::pdo();
$base_url = rtrim(app_base_url(), '/');
$perfil_base = $base_url . '/public/perfil_jugador.php?torneo_id=' . (int)$torneo_id;

function qrUrl($data, $size = 120) {
    return 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
        'size' => $size . 'x' . $size,
        'data' => $data,
        'format' => 'png',
        'margin' => 2,
        'qzone' => 0
    ]);
}

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

$torneo_nombre = isset($torneo['nombre']) ? $torneo['nombre'] : 'Torneo';
?>
<style>
.tarjeta-qr-lote { page-break-inside: avoid; }
@media print {
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .no-print-lote { display: none !important; }
    .contenedor-tarjetas { padding: 0; }
}
</style>
<div class="card">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center no-print-lote">
        <h6 class="mb-0"><i class="fas fa-id-card me-2"></i>Tarjetas personales — <?= htmlspecialchars($torneo_nombre) ?></h6>
        <button type="button" class="btn btn-light btn-sm" onclick="window.print();">
            <i class="fas fa-print me-1"></i>Imprimir
        </button>
    </div>
    <div class="card-body contenedor-tarjetas">
        <?php if (empty($jugadores)): ?>
            <p class="text-muted">No hay jugadores confirmados para este torneo.</p>
        <?php else: ?>
            <div class="row g-3" id="tarjetas-qr-lote">
                <?php foreach ($jugadores as $j): 
                    $url_perfil = $perfil_base;
                    $qr_src = qrUrl($url_perfil, 100);
                    $nombre = htmlspecialchars($j['nombre'] ?? '—');
                    $cedula = htmlspecialchars($j['cedula'] ?? '');
                    $id_jugador = (int)$j['id_usuario'];
                ?>
                <div class="col-6 col-md-4 col-lg-3 tarjeta-qr-lote">
                    <div class="border rounded p-2 bg-white shadow-sm" style="max-width: 140px;">
                        <div class="text-center small fw-bold text-dark mb-1" style="font-size: 0.7rem; line-height: 1.1;"><?= $torneo_nombre ?></div>
                        <div class="text-center small text-dark mb-1" style="font-size: 0.75rem;"><?= $nombre ?></div>
                        <div class="text-center text-muted" style="font-size: 0.65rem;">ID: <?= $id_jugador ?><?= $cedula !== '' ? ' · ' . $cedula : '' ?></div>
                        <div class="text-center mt-1">
                            <img src="<?= htmlspecialchars($qr_src) ?>" alt="QR" width="80" height="80" class="img-fluid">
                        </div>
                        <div class="text-center text-muted" style="font-size: 0.6rem;">Escanear para perfil</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
