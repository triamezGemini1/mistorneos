<?php
/**
 * Vista de verificación de acta QR - Doble pantalla
 * Izquierda: imagen foto_acta con zoom/rotación
 * Derecha: formulario con puntos y sanciones (editable)
 * Acciones: Aprobar | Rechazar
 */
$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php']);
$base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';
$action_param = $use_standalone ? '?' : '&';
$url_volver = $base_url . $action_param . 'action=panel&torneo_id=' . (int)$torneo_id;

$letras = [1 => 'A', 2 => 'C', 3 => 'B', 4 => 'D'];
$base_upload = function_exists('AppHelpers') ? AppHelpers::getBaseUrl() : (function_exists('app_base_url') ? app_base_url() : '');
$base_upload = rtrim($base_upload, '/');
$ruta_imagen = '';
if (!empty($jugadores[0]['foto_acta'])) {
    $ruta_imagen = $base_upload . '/' . ltrim($jugadores[0]['foto_acta'], '/');
}
?>
<style>
.verif-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; max-width: 1400px; margin: 0 auto; }
@media (max-width: 900px) { .verif-layout { grid-template-columns: 1fr; } }
.verif-panel { background: #fff; border-radius: 0.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); overflow: hidden; }
.verif-panel h3 { margin: 0; padding: 0.75rem 1rem; background: #374151; color: #fff; font-size: 1rem; }
.verif-img-wrap { padding: 1rem; display: flex; flex-direction: column; align-items: center; min-height: 280px; }
.verif-img-wrap img { max-width: 100%; max-height: 400px; transition: transform 0.3s ease; }
.verif-controls { margin-top: 0.75rem; display: flex; gap: 0.5rem; flex-wrap: wrap; justify-content: center; }
.verif-controls button { padding: 0.4rem 0.75rem; font-size: 0.85rem; border-radius: 0.375rem; border: 1px solid #d1d5db; background: #f9fafb; cursor: pointer; }
.verif-controls button:hover { background: #e5e7eb; }
.verif-form { padding: 1rem; }
.verif-jugador { display: grid; grid-template-columns: 1fr auto auto auto; gap: 0.5rem; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid #e5e7eb; }
.verif-jugador:last-child { border-bottom: none; }
.verif-jugador label { font-size: 0.8rem; color: #6b7280; }
.verif-jugador input[type="number"] { width: 60px; padding: 0.35rem; }
.verif-acciones { margin-top: 1.5rem; padding-top: 1rem; border-top: 2px solid #e5e7eb; display: flex; gap: 1rem; flex-wrap: wrap; }
.verif-acciones .btn-aprobar { background: #10b981; color: #fff; padding: 0.6rem 1.25rem; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer; }
.verif-acciones .btn-aprobar:hover { background: #059669; }
.verif-acciones .btn-rechazar { background: #ef4444; color: #fff; padding: 0.6rem 1.25rem; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer; }
.verif-acciones .btn-rechazar:hover { background: #dc2626; }
.verif-acciones .btn-volver { background: #6b7280; color: #fff; padding: 0.6rem 1.25rem; border: none; border-radius: 0.5rem; text-decoration: none; display: inline-block; }
.verif-acciones .btn-volver:hover { background: #4b5563; color: #fff; }
</style>

<div class="mb-4">
    <a href="<?= htmlspecialchars($url_volver) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Volver al panel</a>
</div>

<h4 class="mb-3">Verificar acta — Ronda <?= (int)$ronda ?> · Mesa <?= (int)$mesa ?></h4>

<div class="verif-layout">
    <!-- Lado izquierdo: imagen con controles -->
    <div class="verif-panel">
        <h3><i class="fas fa-image me-2"></i>Foto del acta</h3>
        <div class="verif-img-wrap">
            <?php
$foto_path = !empty($jugadores[0]['foto_acta']) ? (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $jugadores[0]['foto_acta'])) : '';
$foto_exists = $foto_path && file_exists($foto_path);
?>
            <?php if ($ruta_imagen && $foto_exists): ?>
                <img id="imgActa" src="<?= htmlspecialchars($ruta_imagen) ?>" alt="Acta" style="transform: scale(1) rotate(0deg);">
                <div class="verif-controls">
                    <button type="button" onclick="zoomImg(1.2)">+ Zoom</button>
                    <button type="button" onclick="zoomImg(0.8)">− Zoom</button>
                    <button type="button" onclick="rotarImg(90)">↻ 90°</button>
                    <button type="button" onclick="rotarImg(-90)">↺ 90°</button>
                    <button type="button" onclick="resetImg()">Reset</button>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">No hay imagen del acta disponible</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Lado derecho: formulario editable -->
    <div class="verif-panel">
        <h3><i class="fas fa-edit me-2"></i>Puntos y sanciones</h3>
        <form method="POST" action="" id="formVerificar" class="verif-form">
            <input type="hidden" name="action" value="verificar_acta_aprobar">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <input type="hidden" name="torneo_id" value="<?= (int)$torneo_id ?>">
            <input type="hidden" name="ronda" value="<?= (int)$ronda ?>">
            <input type="hidden" name="mesa" value="<?= (int)$mesa ?>">
            <?php foreach ($jugadores as $i => $j): ?>
                <div class="verif-jugador">
                    <div>
                        <strong><?= htmlspecialchars($j['nombre_completo'] ?? $j['nombre'] ?? 'N/A') ?></strong>
                        <span class="text-muted">(<?= $letras[(int)($j['secuencia'] ?? $i+1)] ?>)</span>
                    </div>
                    <div>
                        <label>R1</label>
                        <input type="number" name="jugadores[<?= $j['id'] ?>][resultado1]" value="<?= (int)($j['resultado1'] ?? 0) ?>" min="0" max="500">
                    </div>
                    <div>
                        <label>R2</label>
                        <input type="number" name="jugadores[<?= $j['id'] ?>][resultado2]" value="<?= (int)($j['resultado2'] ?? 0) ?>" min="0" max="500">
                    </div>
                    <div>
                        <label>Sanc.</label>
                        <input type="number" name="jugadores[<?= $j['id'] ?>][sancion]" value="<?= (int)($j['sancion'] ?? 0) ?>" min="0" max="80">
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="verif-acciones">
                <button type="submit" class="btn-aprobar"><i class="fas fa-check me-1"></i>Aprobar</button>
                <button type="button" class="btn-rechazar" onclick="rechazar()"><i class="fas fa-times me-1"></i>Rechazar</button>
                <a href="<?= htmlspecialchars($url_volver) ?>" class="btn-volver">Volver</a>
            </div>
        </form>
    </div>
</div>

<form id="formRechazar" method="POST" action="" style="display:none;">
    <input type="hidden" name="action" value="verificar_acta_rechazar">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <input type="hidden" name="torneo_id" value="<?= (int)$torneo_id ?>">
    <input type="hidden" name="ronda" value="<?= (int)$ronda ?>">
    <input type="hidden" name="mesa" value="<?= (int)$mesa ?>">
</form>

<script>
(function(){
    var scale = 1, rot = 0;
    window.zoomImg = function(f) { scale *= f; aplicar(); };
    window.rotarImg = function(g) { rot += g; aplicar(); };
    window.resetImg = function() { scale = 1; rot = 0; aplicar(); };
    function aplicar() {
        var img = document.getElementById('imgActa');
        if (img) img.style.transform = 'scale(' + scale + ') rotate(' + rot + 'deg)';
    }
})();
function rechazar() {
    if (confirm('¿Rechazar esta acta? Se solicitará al jugador volver a escanear y enviar el acta.')) {
        document.getElementById('formRechazar').submit();
    }
}
</script>
