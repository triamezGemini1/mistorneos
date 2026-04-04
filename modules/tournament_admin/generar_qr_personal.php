<?php
/**
 * El QR personal por ID de jugador (enlace corto) vive en generar_qr.php — sección «QR personal por ID de jugador».
 */
$tid = (int) ($torneo_id ?? 0);
$url = 'index.php?page=tournament_admin&torneo_id=' . $tid . '&action=generar_qr#qr-personal-jugador';
?>
<div class="card border-success">
    <div class="card-body">
        <h5 class="card-title"><i class="fas fa-qrcode text-success me-2"></i>QR personal del jugador</h5>
        <p class="text-muted mb-3">
            Genere un código con el <strong>ID de jugador</strong> (número corto, sin cédula). El escaneo abre la vista móvil con mesa, resumen, clasificación y botón de actualizar.
        </p>
        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-success">
            <i class="fas fa-arrow-right me-1"></i> Ir a generar QR personal
        </a>
    </div>
</div>
