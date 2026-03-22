<?php
/**
 * Vista Verificación de Actas QR
 * Formulario siempre visible; al elegir una mesa se carga su información (ronda, mesa, jugadores, foto).
 * Diseño moderno, práctico y funcional.
 */
$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php']);
$base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';
$action_param = $use_standalone ? '?' : '&';
$url_volver = $base_url . $action_param . 'action=panel&torneo_id=' . (int)$torneo_id;

$base_upload = function_exists('AppHelpers') ? AppHelpers::getBaseUrl() : (function_exists('app_base_url') ? app_base_url() : '');
$base_upload = rtrim($base_upload ?? '', '/');

$tiene_mesa_seleccionada = isset($ronda) && isset($mesa) && !empty($jugadores);
$can_edit = isset($can_edit) ? (bool)$can_edit : true;
$ruta_imagen = '';
if ($tiene_mesa_seleccionada && !empty($jugadores[0]['foto_acta'])) {
    $ruta_imagen = $base_upload . '/' . ltrim($jugadores[0]['foto_acta'], '/');
}

$parejaA = [];
$parejaB = [];
$puntosA = 0;
$puntosB = 0;
$readonly = ' readonly';
if ($tiene_mesa_seleccionada) {
    foreach ($jugadores as $j) {
        if ((int)($j['secuencia'] ?? 0) <= 2) {
            $parejaA[] = $j;
        } else {
            $parejaB[] = $j;
        }
    }
    $puntosA = (int)($parejaA[0]['resultado1'] ?? 0);
    $puntosB = (int)($parejaB[0]['resultado1'] ?? 0);
    $readonly = !$can_edit ? ' readonly' : '';
}
?>
<style>
/* Contenedor principal */
.verif-page { max-width: 1400px; margin: 0 auto; padding: 0 0.5rem; }
.verif-page h2 { font-size: 1.35rem; font-weight: 600; color: #1e293b; margin-bottom: 0.35rem; display: flex; align-items: center; gap: 0.5rem; }
.verif-page .subtitle { font-size: 0.9rem; color: #64748b; margin-bottom: 1.25rem; }
.verif-wrap { display: flex; gap: 1.25rem; min-height: 65vh; }
/* Sidebar: listado de mesas */
.verif-sidebar {
    width: 280px; flex-shrink: 0;
    background: #fff; border-radius: 12px; border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06); overflow: hidden;
}
.verif-sidebar .sidebar-head {
    padding: 1rem 1.25rem; background: linear-gradient(135deg, #475569 0%, #334155 100%);
    color: #fff; font-weight: 600; font-size: 0.95rem;
}
.verif-sidebar ul { list-style: none; margin: 0; padding: 0.5rem 0; }
.verif-sidebar li { margin: 0; }
.verif-sidebar a {
    display: block; padding: 0.7rem 1.25rem; color: #475569; text-decoration: none;
    font-size: 0.9rem; border-left: 3px solid transparent; transition: all 0.15s ease;
}
.verif-sidebar a:hover { background: #f1f5f9; color: #0f172a; }
.verif-sidebar a.active {
    background: #eff6ff; color: #1d4ed8; border-left-color: #3b82f6; font-weight: 500;
}
.verif-sidebar .empty-msg { padding: 1rem 1.25rem; color: #64748b; font-size: 0.875rem; }
/* Área principal */
.verif-main { flex: 1; min-width: 0; display: flex; flex-direction: column; min-height: 0; }
.verif-main .subtitle { font-size: 0.9rem; color: #64748b; margin-bottom: 1rem; }
/* Fila superior: Volver + barra de parejas o placeholder */
.verif-top {
    display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
    padding: 1rem 1.25rem; background: #fff; border-radius: 12px;
    border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    margin-bottom: 1rem;
}
.verif-top .btn-volver-panel {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.5rem 1rem; font-size: 0.875rem; border-radius: 8px;
    background: #f1f5f9; color: #475569; text-decoration: none; border: 1px solid #e2e8f0;
    transition: background 0.15s, color 0.15s;
}
.verif-top .btn-volver-panel:hover { background: #e2e8f0; color: #1e293b; }
.verif-placeholder {
    flex: 1; min-width: 200px; padding: 0.75rem 1rem; background: #f8fafc; border-radius: 8px;
    color: #64748b; font-size: 0.9rem; text-align: center;
}
/* Barra parejas + botones */
.verif-bar {
    display: grid;
    grid-template-columns: 1fr 5rem 1fr 5rem auto;
    grid-template-rows: auto;
    grid-template-areas: "parejaA puntosA parejaB puntosB acciones";
    gap: 0.5rem 1rem; align-items: center; flex: 1; min-width: 280px;
}
.verif-bar .celda-pareja { font-size: 0.875rem; color: #334155; }
.verif-bar .celda-pareja.pareja-a { grid-area: parejaA; background: #dbeafe; padding: 0.5rem 0.75rem; border-radius: 8px; }
.verif-bar .celda-pareja.pareja-b { grid-area: parejaB; background: #d1fae5; padding: 0.5rem 0.75rem; border-radius: 8px; }
.verif-bar .celda-puntos { width: 100%; max-width: 5rem; }
.verif-bar .celda-puntos.puntos-a { grid-area: puntosA; }
.verif-bar .celda-puntos.puntos-b { grid-area: puntosB; }
.verif-bar .celda-puntos input {
    width: 100%; padding: 0.5rem; text-align: center; font-size: 1rem; font-weight: 700;
    border: 1px solid #cbd5e1; border-radius: 8px; background: #fff;
}
.verif-bar .verif-acciones {
    grid-area: acciones; display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;
}
.verif-bar .verif-acciones .btn-aprobar {
    margin: 0; padding: 0.5rem 0.9rem; font-size: 0.875rem; border-radius: 8px; border: none;
    background: #10b981; color: #fff; font-weight: 600; cursor: pointer;
}
.verif-bar .verif-acciones .btn-aprobar:hover { background: #059669; }
.verif-bar .verif-acciones .btn-rechazar {
    margin: 0; padding: 0.5rem 0.9rem; font-size: 0.875rem; border-radius: 8px; border: none;
    background: #ef4444; color: #fff; font-weight: 600; cursor: pointer;
}
.verif-bar .verif-acciones .btn-rechazar:hover { background: #dc2626; }
.verif-bar .verif-acciones .btn-volver {
    margin: 0; padding: 0.5rem 0.9rem; font-size: 0.875rem; border-radius: 8px;
    text-decoration: none; display: inline-block; text-align: center; background: #64748b; color: #fff;
}
.verif-bar .verif-acciones .btn-volver:hover { background: #475569; color: #fff; }
@media (max-width: 900px) {
    .verif-bar { grid-template-columns: 1fr 4rem 1fr 4rem; grid-template-areas: "parejaA puntosA parejaB puntosB" "acciones acciones acciones acciones"; }
    .verif-bar .verif-acciones { grid-area: acciones; }
}
/* Contenedor imagen */
.verif-form-body { display: flex; flex-direction: column; flex: 1; min-height: 0; width: 100%; }
.verif-panel-foto {
    flex: 1; min-height: 320px; width: 100%; border-radius: 12px;
    background: #f8fafc; border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06); overflow: hidden;
    display: flex; flex-direction: column;
}
.verif-img-wrap { position: relative; flex: 1; min-height: 0; width: 100%; height: 100%; }
.verif-img-area {
    position: absolute; top: 0; left: 0; right: 0; bottom: 0;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden; cursor: grab; user-select: none;
}
.verif-img-area:active { cursor: grabbing; }
.verif-img-area .img-inner { position: absolute; top: 50%; left: 50%; transform-origin: 50% 50%; }
.verif-img-area img {
    display: block; max-width: 90%; max-height: 90%; object-fit: contain;
    width: auto; height: auto; pointer-events: none;
}
.verif-controls {
    position: absolute; bottom: 12px; right: 12px; z-index: 5;
    display: flex; gap: 6px; flex-wrap: wrap;
}
.verif-controls button {
    padding: 0.5rem 0.75rem; font-size: 0.8rem; border-radius: 8px;
    border: 1px solid #e2e8f0; background: #fff; cursor: pointer;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
.verif-controls button:hover { background: #f1f5f9; }
.verif-empty-state {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    min-height: 280px; padding: 2rem; text-align: center; color: #64748b;
    background: #f8fafc;
}
.verif-empty-state .icon { font-size: 2.5rem; margin-bottom: 0.75rem; opacity: 0.6; }
.verif-empty-state p { margin: 0; font-size: 0.95rem; }
.verif-alert {
    padding: 0.75rem 1rem; margin-bottom: 1rem; font-size: 0.875rem; border-radius: 8px;
    background: #fef3c7; color: #92400e; border: 1px solid #fcd34d;
}
.verif-two-cols {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
    align-items: start;
}
@media (max-width: 900px) {
    .verif-two-cols { grid-template-columns: 1fr; }
}
.verif-results-card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    padding: 1.25rem;
}
.verif-results-card h3 {
    font-size: 1rem;
    font-weight: 600;
    color: #334155;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.verif-photo-card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    overflow: hidden;
    min-height: 320px;
}
.verif-btn-icon { display: inline-flex; align-items: center; gap: 0.35rem; }
</style>

<div class="verif-page">
    <h2><i data-lucide="clipboard-check" class="verif-btn-icon"></i> Verificar actas (QR)</h2>
    <p class="subtitle">Elija una mesa en el listado; se cargarán los datos y la imagen del acta para aprobar o rechazar.</p>

    <div class="verif-wrap">
        <aside class="verif-sidebar">
            <div class="sidebar-head"><i data-lucide="list" style="width:1rem;height:1rem;vertical-align:middle;"></i> Mesas pendientes</div>
            <?php if (empty($actas_pendientes)): ?>
                <div class="empty-msg">No hay actas pendientes para este torneo.</div>
            <?php else: ?>
                <ul>
                    <?php foreach ($actas_pendientes as $a):
                        $acta_ronda = (int)$a['partida'];
                        $acta_mesa = (int)$a['mesa'];
                        $is_active = $tiene_mesa_seleccionada && (int)$ronda === $acta_ronda && (int)$mesa === $acta_mesa;
                        $href = $base_url . $action_param . 'action=verificar_resultados&torneo_id=' . (int)$torneo_id . '&ronda=' . $acta_ronda . '&mesa=' . $acta_mesa;
                    ?>
                        <li>
                            <a href="<?= htmlspecialchars($href) ?>" class="<?= $is_active ? 'active' : '' ?>">
                                Ronda <?= $acta_ronda ?> · Mesa <?= $acta_mesa ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </aside>

        <div class="verif-main">
            <p class="subtitle"><?= $tiene_mesa_seleccionada ? 'Ronda ' . (int)$ronda . ' · Mesa ' . (int)$mesa : 'Seleccione una mesa para cargar la información' ?></p>

            <?php if ($tiene_mesa_seleccionada && !$can_edit && !empty($torneo_finalizado)): ?>
                <div class="verif-alert">Torneo finalizado. Solo el administrador general puede editar o aprobar actas.</div>
            <?php endif; ?>

            <?php if ($tiene_mesa_seleccionada): ?>
                <form method="POST" action="" id="formVerificar" class="verif-form-body" onsubmit="distribuirPuntosVerif('todas'); return true;">
                    <input type="hidden" name="action" value="verificar_acta_aprobar">
                    <input type="hidden" name="redirect_action" value="verificar_resultados">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="torneo_id" value="<?= (int)$torneo_id ?>">
                    <input type="hidden" name="ronda" value="<?= (int)$ronda ?>">
                    <input type="hidden" name="mesa" value="<?= (int)$mesa ?>">
                    <?php foreach ($jugadores as $i => $j): ?>
                    <input type="hidden" name="jugadores[<?= (int)$j['id'] ?>][resultado1]" id="resultado1_<?= $i ?>" value="<?= (int)($j['resultado1'] ?? 0) ?>">
                    <input type="hidden" name="jugadores[<?= (int)$j['id'] ?>][resultado2]" id="resultado2_<?= $i ?>" value="<?= (int)($j['resultado2'] ?? 0) ?>">
                    <input type="hidden" name="jugadores[<?= (int)$j['id'] ?>][sancion]" value="0">
                    <?php endforeach; ?>

                    <div class="verif-top">
                        <a href="<?= htmlspecialchars($url_volver) ?>" class="btn-volver-panel"><i data-lucide="arrow-left" style="width:1rem;height:1rem;"></i> Volver al panel</a>
                    </div>

                    <div class="verif-two-cols">
                        <div class="verif-results-card">
                            <h3><i data-lucide="users" style="width:1.1rem;height:1.1rem;"></i> Resultados de la mesa</h3>
                            <div class="verif-bar" style="grid-template-columns: 1fr 5rem; grid-template-areas: 'parejaA puntosA' 'parejaB puntosB' 'acciones acciones'; gap: 0.75rem 1rem;">
                                <div class="celda-pareja pareja-a"><?= htmlspecialchars(($parejaA[0]['nombre_completo'] ?? $parejaA[0]['nombre'] ?? '') . ' / ' . ($parejaA[1]['nombre_completo'] ?? $parejaA[1]['nombre'] ?? '')) ?></div>
                                <div class="celda-puntos puntos-a">
                                    <input type="number" id="puntos_pareja_A" value="<?= $puntosA ?>" min="0" max="500" step="1"<?= $readonly ?>
                                           oninput="distribuirPuntosVerif('A');" onchange="distribuirPuntosVerif('A');">
                                </div>
                                <div class="celda-pareja pareja-b"><?= htmlspecialchars(($parejaB[0]['nombre_completo'] ?? $parejaB[0]['nombre'] ?? '') . ' / ' . ($parejaB[1]['nombre_completo'] ?? $parejaB[1]['nombre'] ?? '')) ?></div>
                                <div class="celda-puntos puntos-b">
                                    <input type="number" id="puntos_pareja_B" value="<?= $puntosB ?>" min="0" max="500" step="1"<?= $readonly ?>
                                           oninput="distribuirPuntosVerif('B');" onchange="distribuirPuntosVerif('B');">
                                </div>
                                <div class="verif-acciones" style="grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem;">
                                    <?php if ($can_edit): ?>
                                        <button type="submit" class="btn-aprobar verif-btn-icon"><i data-lucide="check" style="width:1rem;height:1rem;"></i> Aprobar</button>
                                        <button type="button" class="btn-rechazar verif-btn-icon" onclick="rechazar()"><i data-lucide="x" style="width:1rem;height:1rem;"></i> Rechazar</button>
                                    <?php endif; ?>
                                    <a href="<?= htmlspecialchars($url_volver) ?>" class="btn-volver verif-btn-icon"><i data-lucide="corner-up-left" style="width:1rem;height:1rem;"></i> Volver</a>
                                </div>
                            </div>
                        </div>
                        <div class="verif-photo-card">
                            <div class="verif-img-wrap" style="min-height: 320px;">
                                <?php if (!empty($ruta_imagen)): ?>
                                    <div class="verif-img-area" id="imgAreaActa">
                                        <div class="img-inner" id="imgInnerActa">
                                            <img id="imgActa" src="<?= htmlspecialchars($ruta_imagen) ?>" alt="Acta">
                                        </div>
                                    </div>
                                    <div class="verif-controls">
                                        <button type="button" onclick="zoomImg(1.2)">+ Zoom</button>
                                        <button type="button" onclick="zoomImg(0.8)">− Zoom</button>
                                        <button type="button" onclick="rotarImg(90)">↻ 90°</button>
                                        <button type="button" onclick="rotarImg(-90)">↺ 90°</button>
                                        <button type="button" onclick="resetImg()">Reset</button>
                                    </div>
                                <?php else: ?>
                                    <div class="verif-empty-state">
                                        <i data-lucide="image" style="width:3rem;height:3rem;opacity:0.6;"></i>
                                        <p>No hay imagen del acta disponible para esta mesa.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <div class="verif-top">
                    <a href="<?= htmlspecialchars($url_volver) ?>" class="btn-volver-panel"><i data-lucide="arrow-left" style="width:1rem;height:1rem;"></i> Volver al panel</a>
                    <div class="verif-placeholder">
                        <i data-lucide="hand" style="width:1.2rem;height:1.2rem;vertical-align:middle;"></i> Seleccione una mesa del listado para cargar los datos y la imagen del acta.
                    </div>
                </div>
                <div class="verif-photo-card">
                    <div class="verif-img-wrap">
                        <div class="verif-empty-state">
                            <i data-lucide="image" style="width:3rem;height:3rem;opacity:0.6;"></i>
                            <p>Elija una mesa en la columna izquierda para ver la foto del acta y verificar el resultado.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <form id="formRechazar" method="POST" action="" style="display:none;">
                <input type="hidden" name="action" value="verificar_acta_rechazar">
                <input type="hidden" name="redirect_action" value="verificar_resultados">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="torneo_id" value="<?= (int)$torneo_id ?>">
                <input type="hidden" name="ronda" value="<?= (int)($ronda ?? 0) ?>">
                <input type="hidden" name="mesa" value="<?= (int)($mesa ?? 0) ?>">
            </form>
        </div>
    </div>
</div>

<script>
(function(){
    var inner = document.getElementById('imgInnerActa');
    var area = document.getElementById('imgAreaActa');
    if (!inner || !area) return;
    var scale = 1, rot = 0, tx = 0, ty = 0;
    var minScale = 0.3, maxScale = 10;
    function aplicar() {
        scale = Math.max(minScale, Math.min(maxScale, scale));
        inner.style.transform = 'translate(-50%, -50%) translate(' + tx + 'px, ' + ty + 'px) scale(' + scale + ') rotate(' + rot + 'deg)';
    }
    window.zoomImg = function(f) { scale *= f; aplicar(); };
    window.rotarImg = function(g) { rot += g; aplicar(); };
    window.resetImg = function() { scale = 1; rot = 0; tx = 0; ty = 0; aplicar(); };
    aplicar();
    var dragging = false, startX, startY, startTx, startTy;
    function getXY(e) {
        if (e.touches && e.touches.length) return { x: e.touches[0].clientX, y: e.touches[0].clientY };
        return { x: e.clientX, y: e.clientY };
    }
    area.addEventListener('mousedown', function(e) { dragging = true; startX = e.clientX; startY = e.clientY; startTx = tx; startTy = ty; });
    area.addEventListener('touchstart', function(e) { e.preventDefault(); dragging = true; var p = getXY(e); startX = p.x; startY = p.y; startTx = tx; startTy = ty; }, { passive: false });
    document.addEventListener('mousemove', function(e) {
        if (!dragging) return;
        tx = startTx + (e.clientX - startX);
        ty = startTy + (e.clientY - startY);
        aplicar();
    });
    document.addEventListener('touchmove', function(e) {
        if (!dragging || !e.touches.length) return;
        e.preventDefault();
        var p = getXY(e);
        tx = startTx + (p.x - startX);
        ty = startTy + (p.y - startY);
        aplicar();
    }, { passive: false });
    document.addEventListener('mouseup', function() { dragging = false; });
    document.addEventListener('touchend', function() { dragging = false; });
})();
function distribuirPuntosVerif(pareja) {
    var inpA = document.getElementById('puntos_pareja_A');
    var inpB = document.getElementById('puntos_pareja_B');
    if (!inpA || !inpB) return;
    var puntosA = parseInt(inpA.value, 10) || 0;
    var puntosB = parseInt(inpB.value, 10) || 0;
    if (pareja === 'todas') {
        distribuirPuntosVerif('A');
        distribuirPuntosVerif('B');
        return;
    }
    var indices = pareja === 'A' ? [0, 1] : [2, 3];
    var puntosPareja = pareja === 'A' ? puntosA : puntosB;
    var puntosContraria = pareja === 'A' ? puntosB : puntosA;
    indices.forEach(function(idx) {
        var r1 = document.getElementById('resultado1_' + idx);
        var r2 = document.getElementById('resultado2_' + idx);
        if (r1) r1.value = puntosPareja;
        if (r2) r2.value = puntosContraria;
    });
    if (pareja === 'A') {
        [2, 3].forEach(function(idx) { var r2 = document.getElementById('resultado2_' + idx); if (r2) r2.value = puntosA; });
    } else {
        [0, 1].forEach(function(idx) { var r2 = document.getElementById('resultado2_' + idx); if (r2) r2.value = puntosB; });
    }
}
function rechazar() {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: '¿Rechazar esta acta?',
            text: 'Se solicitará al jugador volver a escanear y enviar el acta.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Sí, rechazar'
        }).then(function(r) { if (r.isConfirmed) document.getElementById('formRechazar').submit(); });
    } else if (confirm('¿Rechazar esta acta? Se solicitará al jugador volver a escanear y enviar el acta.')) {
        document.getElementById('formRechazar').submit();
    }
}
</script>
<script src="https://unpkg.com/lucide@0.263.1/dist/umd/lucide.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>document.addEventListener('DOMContentLoaded', function() { if (typeof lucide !== 'undefined') lucide.createIcons(); });</script>
