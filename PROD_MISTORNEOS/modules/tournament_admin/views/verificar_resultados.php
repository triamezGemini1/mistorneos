<?php
/**
 * Vista de Verificación de Resultados QR
 * Sidebar: lista de mesas pendientes (origen QR)
 * Visor comparativo: izquierda foto_acta (zoom/rotación), derecha formulario editable (solo admin)
 * Acceso restringido: solo administrador de la organización puede editar/aprobar.
 */
$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php']);
$base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';
$action_param = $use_standalone ? '?' : '&';
$url_volver = $base_url . $action_param . 'action=panel&torneo_id=' . (int)$torneo_id;

$letras = [1 => 'A', 2 => 'C', 3 => 'B', 4 => 'D'];
$base_upload = function_exists('AppHelpers') ? AppHelpers::getBaseUrl() : (function_exists('app_base_url') ? app_base_url() : '');
$base_upload = rtrim($base_upload ?? '', '/');

$tiene_mesa_seleccionada = isset($ronda) && isset($mesa) && !empty($jugadores);
$can_edit = isset($can_edit) ? (bool)$can_edit : true;
$ruta_imagen = '';
if ($tiene_mesa_seleccionada && !empty($jugadores[0]['foto_acta'])) {
    $ruta_imagen = $base_upload . '/' . ltrim($jugadores[0]['foto_acta'], '/');
}
?>
<style>
.verif-resultados-wrap { display: flex; gap: 1rem; min-height: 500px; }
.verif-sidebar {
    width: 240px; flex-shrink: 0; background: #f8fafc; border-radius: 0.5rem;
    border: 1px solid #e2e8f0; overflow-y: auto; max-height: 70vh;
}
.verif-sidebar h5 { margin: 0; padding: 0.75rem 1rem; background: #334155; color: #fff; font-size: 0.9rem; border-radius: 0.5rem 0.5rem 0 0; }
.verif-sidebar ul { list-style: none; margin: 0; padding: 0.5rem 0; }
.verif-sidebar li { margin: 0; }
.verif-sidebar a {
    display: block; padding: 0.5rem 1rem; color: #334155; text-decoration: none;
    border-bottom: 1px solid #e2e8f0; transition: background 0.15s;
}
.verif-sidebar a:hover { background: #e2e8f0; }
.verif-sidebar a.active { background: #3b82f6; color: #fff; }
.verif-main { flex: 1; min-width: 0; }
/* Layout: sin mesa = dos columnas; con mesa = barra (parejas+botones) + foto */
.verif-layout { display: grid; grid-template-columns: 0.7fr 1.25fr; gap: 1.25rem; align-items: stretch; min-height: calc(100vh - 140px); }
@media (max-width: 900px) { .verif-layout { grid-template-columns: 1fr; } }
.verif-panel { background: #fff; border-radius: 0.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); overflow: hidden; }
.verif-panel h3 { margin: 0; padding: 0.75rem 1rem; background: #374151; color: #fff; font-size: 1rem; }
.verif-panel.verif-panel-form h3 { font-size: 0.9rem; padding: 0.5rem 0.75rem; }
.verif-panel.verif-panel-form .verif-form { padding: 0.7rem; font-size: 0.9em; }
/* Barra: 2 filas (una por pareja), a la derecha 3 columnas (botones) con altura de 2 filas; debajo foto al 90% ancho */
.verif-bar {
    display: grid;
    grid-template-columns: 1fr auto auto auto auto;
    grid-template-rows: auto auto;
    grid-template-areas:
        "parejaA puntosA aprobar rechazar volver"
        "parejaB puntosB aprobar rechazar volver";
    gap: 0.4rem 0.75rem;
    align-items: center;
    padding: 0.5rem 0.75rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    width: 100%;
}
.verif-bar .celda-pareja.pareja-a { grid-area: parejaA; }
.verif-bar .celda-puntos.puntos-a { grid-area: puntosA; }
.verif-bar .celda-pareja.pareja-b { grid-area: parejaB; }
.verif-bar .celda-puntos.puntos-b { grid-area: puntosB; }
.verif-bar .verif-acciones { grid-area: 1 / 3 / 3 / 6; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.5rem; align-items: center; margin: 0; padding: 0; border: none; }
.verif-bar .verif-acciones .btn-aprobar { grid-column: 1; margin: 0; }
.verif-bar .verif-acciones .btn-rechazar { grid-column: 2; margin: 0; }
.verif-bar .verif-acciones .btn-volver { grid-column: 3; margin: 0; }
.verif-bar .celda-pareja { font-size: 0.85rem; color: #334155; }
.verif-bar .celda-pareja.pareja-a { background: #dbeafe; padding: 0.4rem 0.5rem; border-radius: 0.25rem; }
.verif-bar .celda-pareja.pareja-b { background: #d1fae5; padding: 0.4rem 0.5rem; border-radius: 0.25rem; }
.verif-bar .celda-puntos { width: 4.5rem; }
.verif-bar .celda-puntos input {
    width: 100%; padding: 0.4rem; text-align: center; font-size: 1rem; font-weight: 700;
    border: 1px solid #d1d5db; border-radius: 0.375rem;
}
/* Contenedor de foto: 90% del ancho de pantalla */
.verif-panel-foto { background: transparent; box-shadow: none; border: none; overflow: visible; display: flex; flex-direction: column; min-height: 0; width: 90%; max-width: 90vw; margin: 0 auto; }
.verif-panel-foto h3 { display: none; }
.verif-img-wrap {
    padding: 0; position: relative;
    flex: 1; min-height: 0; width: 100%; height: 60vh; max-height: calc(100vh - 160px); box-sizing: border-box;
}
.verif-img-area {
    position: absolute; top: 0; left: 0; right: 0; bottom: 0;
    overflow: hidden;
    cursor: grab; user-select: none; -webkit-user-select: none;
    display: flex; align-items: center; justify-content: center;
}
.verif-img-area:active { cursor: grabbing; }
.verif-img-area .img-inner { position: absolute; top: 50%; left: 50%; transform-origin: 50% 50%; }
.verif-img-area img {
    display: block; max-width: 90%; max-height: 90%; width: auto; height: auto;
    transition: none; pointer-events: none;
}
.verif-controls {
    position: absolute; bottom: 0.75rem; right: 0.75rem; z-index: 5;
    display: flex; flex-direction: row; gap: 0.4rem; flex-wrap: wrap;
}
.verif-controls button {
    padding: 0.5rem 0.75rem; font-size: 0.85rem; border-radius: 0.375rem;
    border: 1px solid #d1d5db; background: #fff; cursor: pointer; white-space: nowrap;
}
.verif-controls button:hover { background: #e5e7eb; }
.verif-form { padding: 1rem; }
.tabla-verif-acta { width: 100%; border-collapse: collapse; border-radius: 0.5rem; overflow: hidden; font-size: 0.9em; }
.tabla-verif-acta thead th {
    padding: 0.45rem 0.5rem; text-align: center; font-size: 0.75rem; font-weight: 600;
    background: #1e3a5f; color: #fff;
}
.tabla-verif-acta tbody td { padding: 0.45rem 0.5rem; border-bottom: 1px solid #e5e7eb; vertical-align: middle; }
.tabla-verif-acta tbody tr:last-child td { border-bottom: none; }
.tabla-verif-acta .col-pareja { width: 3rem; text-align: center; font-weight: 700; font-size: 0.85rem; }
.tabla-verif-acta .col-nombre { font-size: 0.85rem; color: #1f2937; }
.tabla-verif-acta .col-puntos { width: 4.5rem; text-align: center; }
.tabla-verif-acta .col-puntos input {
    width: 100%; padding: 0.35rem 0.3rem; text-align: center; font-size: 1rem; font-weight: 700;
    border: 1px solid #d1d5db; border-radius: 0.375rem;
}
.tabla-verif-acta tr.pareja-a td { background: #dbeafe; }
.tabla-verif-acta tr.pareja-b td { background: #d1fae5; }
.verif-acciones { margin-top: 1.5rem; padding-top: 1rem; border-top: 2px solid #e5e7eb; display: flex; gap: 1rem; flex-wrap: wrap; }
.verif-acciones .btn-aprobar { background: #10b981; color: #fff; padding: 0.6rem 1.25rem; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer; }
.verif-acciones .btn-aprobar:hover { background: #059669; }
.verif-acciones .btn-rechazar { background: #ef4444; color: #fff; padding: 0.6rem 1.25rem; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer; }
.verif-acciones .btn-rechazar:hover { background: #dc2626; }
.verif-acciones .btn-volver { background: #6b7280; color: #fff; padding: 0.6rem 1.25rem; border: none; border-radius: 0.5rem; text-decoration: none; display: inline-block; }
.verif-acciones .btn-volver:hover { background: #4b5563; color: #fff; }
.verif-acciones button, .verif-acciones .btn-volver { padding: 0.5rem 1rem; font-size: 0.9rem; }
.verif-empty { padding: 2rem; text-align: center; color: #64748b; background: #f8fafc; border-radius: 0.5rem; min-height: 200px; display: flex; flex-direction: column; align-items: center; justify-content: center; }
</style>

<!-- Vista verificar_resultados v2: barra Pareja A/B + botones + foto ancho completo -->
<div class="mb-4">
    <a href="<?= htmlspecialchars($url_volver) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Volver al panel</a>
</div>

<h4 class="mb-3"><i class="fas fa-clipboard-check me-2"></i>Verificar resultados (QR)</h4>

<div class="verif-resultados-wrap">
    <!-- Sidebar: Navegador de mesas pendientes -->
    <aside class="verif-sidebar">
        <h5><i class="fas fa-list me-1"></i>Mesas pendientes</h5>
        <?php if (empty($actas_pendientes)): ?>
            <p class="text-muted small px-3 py-2 mb-0">No hay actas pendientes.</p>
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

    <!-- Main: con mesa = form envuelve layout (izq botones, der fila+foto); sin mesa = layout con placeholders -->
    <div class="verif-main">
        <h5 class="mb-3"><?= $tiene_mesa_seleccionada ? 'Ronda ' . (int)$ronda . ' · Mesa ' . (int)$mesa : 'Verificación de actas QR' ?></h5>
        <?php if (!$tiene_mesa_seleccionada): ?>
            <div class="verif-layout">
                <div class="verif-panel verif-panel-form">
                    <h3><i class="fas fa-edit me-2"></i>Puntos</h3>
                    <div class="verif-empty">
                        <i class="fas fa-hand-pointer fa-2x mb-2 text-muted"></i>
                        <p class="mb-0">Seleccione una mesa del listado para ver el formulario y verificar el acta.</p>
                    </div>
                </div>
                <div class="verif-panel verif-panel-foto">
                    <div class="verif-img-wrap">
                        <div class="verif-img-area" style="align-items: center; justify-content: center;">
                            <div class="verif-empty">
                                <i class="fas fa-image fa-2x mb-2 text-muted"></i>
                                <p class="mb-0">Seleccione una mesa del listado para ver la foto del acta.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php if (!$can_edit && isset($torneo_finalizado) && $torneo_finalizado): ?>
                <p class="text-muted px-3 py-2"><small>Torneo finalizado. Solo el administrador general puede editar o aprobar actas.</small></p>
            <?php endif; ?>
            <?php
            $parejaA = [];
            $parejaB = [];
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
            ?>
            <form method="POST" action="" id="formVerificar" class="verif-form" onsubmit="distribuirPuntosVerif('todas'); return true;">
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
                <div class="verif-bar">
                    <div class="celda-pareja pareja-a"><?= htmlspecialchars(($parejaA[0]['nombre_completo'] ?? $parejaA[0]['nombre'] ?? '') . ' / ' . ($parejaA[1]['nombre_completo'] ?? $parejaA[1]['nombre'] ?? '')) ?></div>
                    <div class="celda-puntos puntos-a">
                        <input type="number" id="puntos_pareja_A" value="<?= $puntosA ?>" min="0" max="500" step="1"<?= $readonly ?>
                               oninput="distribuirPuntosVerif('A');" onchange="distribuirPuntosVerif('A');">
                    </div>
                    <div class="verif-acciones">
                        <?php if ($can_edit): ?>
                            <button type="submit" class="btn-aprobar"><i class="fas fa-check me-1"></i>Aprobar Resultado</button>
                            <button type="button" class="btn-rechazar" onclick="rechazar()"><i class="fas fa-times me-1"></i>Rechazar</button>
                        <?php endif; ?>
                        <a href="<?= htmlspecialchars($url_volver) ?>" class="btn-volver">Volver</a>
                    </div>
                    <div class="celda-pareja pareja-b"><?= htmlspecialchars(($parejaB[0]['nombre_completo'] ?? $parejaB[0]['nombre'] ?? '') . ' / ' . ($parejaB[1]['nombre_completo'] ?? $parejaB[1]['nombre'] ?? '')) ?></div>
                    <div class="celda-puntos puntos-b">
                        <input type="number" id="puntos_pareja_B" value="<?= $puntosB ?>" min="0" max="500" step="1"<?= $readonly ?>
                               oninput="distribuirPuntosVerif('B');" onchange="distribuirPuntosVerif('B');">
                    </div>
                </div>
                <div class="verif-panel-foto">
                    <div class="verif-img-wrap">
                            <?php
                            $foto_path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $jugadores[0]['foto_acta'] ?? '');
                            $foto_exists = !empty($jugadores[0]['foto_acta']) && file_exists($foto_path);
                            ?>
                            <?php if ($foto_exists && $ruta_imagen): ?>
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
                                <div class="verif-img-area" style="align-items: center; justify-content: center;"><p class="text-muted mb-0">No hay imagen del acta disponible</p></div>
                            <?php endif; ?>
                    </div>
                </div>
            </form>
        <?php endif; ?>

        <form id="formRechazar" method="POST" action="" style="display:none;">
            <input type="hidden" name="action" value="verificar_acta_rechazar">
            <input type="hidden" name="redirect_action" value="verificar_resultados">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <input type="hidden" name="torneo_id" value="<?= (int)$torneo_id ?>">
            <input type="hidden" name="ronda" value="<?= (int)$ronda ?>">
            <input type="hidden" name="mesa" value="<?= (int)$mesa ?>">
        </form>
    </div>
</div>

<script>
(function(){
    var inner = document.getElementById('imgInnerActa');
    var area = document.getElementById('imgAreaActa');
    if (!inner && !area) return;
    var scale = 1, rot = 0, tx = 0, ty = 0;
    var minScale = 0.3, maxScale = 10;
    function aplicar() {
        if (!inner) return;
        scale = Math.max(minScale, Math.min(maxScale, scale));
        inner.style.transform = 'translate(-50%, -50%) translate(' + tx + 'px, ' + ty + 'px) scale(' + scale + ') rotate(' + rot + 'deg)';
    }
    window.zoomImg = function(f) { scale *= f; aplicar(); };
    window.rotarImg = function(g) { rot += g; aplicar(); };
    window.resetImg = function() { scale = 1; rot = 0; tx = 0; ty = 0; aplicar(); };
    aplicar();
    if (area && inner) {
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
    }
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
    var indices, puntosPareja, puntosContraria;
    if (pareja === 'A') {
        indices = [0, 1];
        puntosPareja = puntosA;
        puntosContraria = puntosB;
    } else {
        indices = [2, 3];
        puntosPareja = puntosB;
        puntosContraria = puntosA;
    }
    indices.forEach(function(idx) {
        var r1 = document.getElementById('resultado1_' + idx);
        var r2 = document.getElementById('resultado2_' + idx);
        if (r1) r1.value = puntosPareja;
        if (r2) r2.value = puntosContraria;
    });
    if (pareja === 'A') {
        [2, 3].forEach(function(idx) {
            var r2 = document.getElementById('resultado2_' + idx);
            if (r2) r2.value = puntosA;
        });
    } else {
        [0, 1].forEach(function(idx) {
            var r2 = document.getElementById('resultado2_' + idx);
            if (r2) r2.value = puntosB;
        });
    }
}
function rechazar() {
    if (confirm('¿Rechazar esta acta? Se solicitará al jugador volver a escanear y enviar el acta.')) {
        document.getElementById('formRechazar').submit();
    }
}
</script>
