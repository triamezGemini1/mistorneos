<?php
/**
 * Vista Moderna: Panel de Control de Torneo
 * Panel común para todos los tipos de torneo (individual/parejas/equipos)
 * Se adapta dinámicamente según la modalidad del torneo
 * Diseño con Tailwind CSS - 3 columnas organizadas
 *
 * Datos: extract($view_data) en torneo_gestion.php → PanelTorneoViewData::build() + contexto (base_url, use_standalone, user_id, is_admin_general).
 * Fallback mínimo si la vista se incluye sin el flujo normal (p. ej. rutas legacy).
 */
if (!isset($base_url) || !isset($use_standalone)) {
$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
    if (!isset($use_standalone)) {
        $use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php'], true);
    }
    if (!isset($base_url)) {
$base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';
    }
}
if (!isset($user_id)) {
    $user_id = 0;
}
if (!isset($is_admin_general)) {
    $is_admin_general = false;
}
?>

<link rel="stylesheet" href="assets/css/design-system.css">
<link rel="stylesheet" href="assets/css/modern-panel.css">
<?php if ($use_standalone): ?>
<!-- Tailwind CSS solo en modo standalone para no romper el layout del dashboard -->
<link rel="stylesheet" href="assets/dist/output.css">
<script>
tailwind.config = {
    theme: {
        extend: {
            colors: {
                'panel-blue': '#3b82f6',
                'panel-purple': '#8b5cf6',
                'panel-green': '#10b981',
                'panel-amber': '#f59e0b',
                'panel-cyan': '#06b6d4',
                'panel-red': '#ef4444',
                'panel-indigo': '#6366f1',
                'panel-dark': '#111827',
            }
        }
    }
}
</script>
<?php endif; ?>
<?php /* Estilos del panel movidos a assets/css/modern-panel.css (Design System + Panel) */ ?>

<div class="tw-panel ds-root">
    <?php include __DIR__ . '/partials/panel/_header_stats.php'; ?>

    <!-- Cronómetro Finalizar Torneo (mismo diseño que cronómetro de ronda, encima de él) -->
    <?php if ($mostrar_aviso_20min && $countdown_fin_timestamp): ?>
    <div class="mb-4 text-center cronometro-finalizar-torneo" id="countdown-cierre-torneo-top">
        <p class="cron-finalizar-label"><i class="fas fa-lock mr-2"></i>El torneo se cerrará oficialmente en</p>
        <p class="countdown-tiempo-restante tabular-nums" data-fin="<?php echo (int)$countdown_fin_timestamp; ?>">--:--</p>
        <p class="cron-finalizar-hint">Tras este tiempo se habilitará el botón Finalizar torneo</p>
    </div>
    <?php elseif ($puedeCerrar): ?>
    <div class="mb-4 text-center cronometro-finalizar-torneo">
        <p class="cron-finalizar-label"><i class="fas fa-check-circle mr-2"></i>Puede finalizar el torneo</p>
        <form method="POST" action="<?php echo $use_standalone ? $base_url : 'index.php?page=torneo_gestion'; ?>" class="inline mt-2" onsubmit="event.preventDefault(); confirmarCierreTorneo(event);">
            <input type="hidden" name="action" value="cerrar_torneo">
            <input type="hidden" name="csrf_token" value="<?php echo CSRF::token(); ?>">
            <input type="hidden" name="torneo_id" value="<?php echo $torneo['id']; ?>">
            <button type="submit" class="d-inline-block font-bold py-2 px-5 rounded-lg text-lg border-0 shadow" style="background: rgba(255,255,255,0.25); color: #fff; cursor: pointer;">
                <i class="fas fa-lock mr-2"></i>Finalizar torneo
            </button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Botón Activar/Retornar Cronómetro (compacto) -->
    <div class="mb-2 text-center">
        <button type="button" id="btnCronometro"
           class="d-inline-block font-bold py-2 px-5 rounded-lg text-lg transition-all transform shadow border-0" style="cursor: pointer;">
            <i class="fas fa-clock mr-2"></i><span id="lblCronometro">ACTIVAR CRONÓMETRO DE RONDA</span>
        </button>
    </div>
    
    <?php
    $tiempo_ronda_min = isset($tiempo_ronda_minutos) ? (int) $tiempo_ronda_minutos : (int) ($torneo['tiempo'] ?? 35);
    if ($tiempo_ronda_min < 1) {
        $tiempo_ronda_min = 35;
    }
    ?>
    <!-- Overlay Cronómetro - pantalla completa en la misma página (tiempo definido en el torneo) -->
    <div id="cronometroOverlay" data-tiempo-minutos="<?php echo $tiempo_ronda_min; ?>">
        <div class="cron-box">
            <div class="cron-header">
                <h1><i class="fas fa-clock me-2"></i>Cronómetro - <?= htmlspecialchars($torneo['nombre'] ?? 'Ronda') ?></h1>
                <div style="display:flex;gap:0.5rem;align-items:center;">
                    <button type="button" class="btn-retornar" onclick="ocultarCronometroOverlay()">
                        <i class="fas fa-arrow-left me-1"></i> Retornar al Panel
                    </button>
                    <button type="button" class="btn-retornar" style="background:rgba(255,255,255,0.2)" onclick="toggleConfigCron()"><i class="fas fa-cog me-1"></i>Configurar</button>
                </div>
            </div>
            <div id="configPanelCron">
                <div class="config-grid">
                    <div><label>Minutos</label><input type="number" id="configMinutosCron" min="1" max="99" value="<?php echo $tiempo_ronda_min; ?>"></div>
                    <div><label>Segundos</label><input type="number" id="configSegundosCron" min="0" max="59" value="0"></div>
                </div>
                <button type="button" class="btn-retornar" style="width:100%;background:#22c55e" onclick="aplicarConfigCron()"><i class="fas fa-check me-1"></i>APLICAR</button>
            </div>
            <div class="cron-display">
                <div id="tiempoDisplayCron"><?php echo str_pad((string)$tiempo_ronda_min, 2, '0', STR_PAD_LEFT); ?>:00</div>
                <div id="estadoDisplayCron"><i class="fas fa-pause-circle me-1"></i>DETENIDO</div>
                <div class="cron-controles">
                    <button id="btnIniciarCron" onclick="iniciarCronometro()" title="Iniciar"><i class="fas fa-play"></i></button>
                    <button id="btnDetenerCron" onclick="detenerCronometro()" title="Detener" disabled><i class="fas fa-stop"></i></button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    (function() {
        var overlayEl = document.getElementById('cronometroOverlay');
        var minutosTorneo = overlayEl ? (parseInt(overlayEl.getAttribute('data-tiempo-minutos'), 10) || 35) : 35;
        if (minutosTorneo < 1) minutosTorneo = 35;
        var tiempoRestante = minutosTorneo * 60, tiempoOriginal = minutosTorneo * 60, cronometroInterval = null;
        var estaCorriendo = false, alarmaReproducida = false, alarmaRepetida = false;
        var overlay = overlayEl;
        var btnLbl = document.getElementById('lblCronometro');
        
        function formatear(s) { var m=Math.floor(s/60),se=s%60; return String(m).padStart(2,'0')+':'+String(se).padStart(2,'0'); }
        function actualizarDisplayCron() {
            var d=document.getElementById('tiempoDisplayCron'),e=document.getElementById('estadoDisplayCron');
            if(!d||!e)return;
            d.textContent=formatear(tiempoRestante);
            d.style.color=tiempoRestante<=30?'#ef4444':tiempoRestante<=60?'#fbbf24':'white';
            d.style.animation=tiempoRestante<=30?'cronPulse 1s infinite':'none';
            e.innerHTML=estaCorriendo?'<i class="fas fa-play-circle me-1"></i>EN EJECUCIÓN':'<i class="fas fa-pause-circle me-1"></i>DETENIDO';
            e.style.color=estaCorriendo?'#86efac':'rgba(255,255,255,0.9)';
            if(btnLbl) btnLbl.textContent=estaCorriendo?'RETORNAR AL CRONÓMETRO':'ACTIVAR CRONÓMETRO DE RONDA';
        }
        function reproducirAlarma() {
            try {
                var ctx=new(window.AudioContext||window.webkitAudioContext)();
                for(var i=0;i<5;i++) {
                    var o=ctx.createOscillator(),g=ctx.createGain();
                    o.connect(g);g.connect(ctx.destination);
                    o.frequency.setValueAtTime(400,ctx.currentTime+i*0.8);
                    o.frequency.exponentialRampToValueAtTime(800,ctx.currentTime+i*0.8+0.6);
                    o.type='sine';
                    g.gain.setValueAtTime(0,ctx.currentTime+i*0.8);
                    g.gain.linearRampToValueAtTime(0.5,ctx.currentTime+i*0.8+0.1);
                    g.gain.linearRampToValueAtTime(0,ctx.currentTime+i*0.8+0.6);
                    o.start(ctx.currentTime+i*0.8);o.stop(ctx.currentTime+i*0.8+0.6);
                }
            } catch(err){if(navigator.vibrate)navigator.vibrate([300,100,300,100,300]);}
        }
        function reproducirAlarma2() {
            try {
                var ctx=new(window.AudioContext||window.webkitAudioContext)();
                for(var i=0;i<3;i++) {
                    var o=ctx.createOscillator(),g=ctx.createGain();
                    o.connect(g);g.connect(ctx.destination);
                    o.frequency.setValueAtTime(60,ctx.currentTime+i*1.2);
                    o.frequency.exponentialRampToValueAtTime(120,ctx.currentTime+i*1.2+0.5);
                    o.type='sawtooth';
                    g.gain.setValueAtTime(0,ctx.currentTime+i*1.2);
                    g.gain.linearRampToValueAtTime(0.6,ctx.currentTime+i*1.2+0.2);
                    g.gain.linearRampToValueAtTime(0,ctx.currentTime+i*1.2+1);
                    o.start(ctx.currentTime+i*1.2);o.stop(ctx.currentTime+i*1.2+1);
                }
            } catch(err){if(navigator.vibrate)navigator.vibrate([500,200,500]);}
        }
        window.iniciarCronometro=function(){
            if(tiempoRestante<=0)tiempoRestante=tiempoOriginal;
            estaCorriendo=true;alarmaReproducida=false;alarmaRepetida=false;
            document.getElementById('btnIniciarCron').disabled=true;
            document.getElementById('btnDetenerCron').disabled=false;
            cronometroInterval=setInterval(function(){
                tiempoRestante--;actualizarDisplayCron();
                if(tiempoRestante<=0){
                    clearInterval(cronometroInterval);cronometroInterval=null;
                    estaCorriendo=false;
                    document.getElementById('btnIniciarCron').disabled=false;
                    document.getElementById('btnDetenerCron').disabled=true;
                    if(!alarmaReproducida){reproducirAlarma();alarmaReproducida=true;setTimeout(function(){if(!alarmaRepetida){reproducirAlarma2();alarmaRepetida=true;}},180000);}
                    actualizarDisplayCron();
                }
            },1000);
            actualizarDisplayCron();
        };
        window.detenerCronometro=function(){
            estaCorriendo=false;clearInterval(cronometroInterval);cronometroInterval=null;
            document.getElementById('btnIniciarCron').disabled=false;
            document.getElementById('btnDetenerCron').disabled=true;
            actualizarDisplayCron();
        };
        window.toggleConfigCron=function(){
            var p=document.getElementById('configPanelCron');
            p.style.display=p.style.display==='none'?'block':'none';
        };
        window.aplicarConfigCron=function(){
            var m=parseInt(document.getElementById('configMinutosCron').value)||minutosTorneo;
            var s=parseInt(document.getElementById('configSegundosCron').value)||0;
            tiempoRestante=m*60+s;tiempoOriginal=tiempoRestante;
            if(!estaCorriendo)actualizarDisplayCron();
            document.getElementById('configPanelCron').style.display='none';
        };
        window.ocultarCronometroOverlay=function(){
            overlay.style.display='none';
        };
        var btnCron=document.getElementById('btnCronometro');
        if(btnCron) btnCron.onclick=function(){
            overlay.style.display='flex';
        };
        actualizarDisplayCron();
        /* Arrastrar ventana del cronómetro por el encabezado (desvincular de la página) */
        (function initDragCron() {
            var header = overlayEl ? overlayEl.querySelector('.cron-header') : null;
            if (!header) return;
            var dragging = false, startX, startY, startLeft, startTop;
            header.addEventListener('mousedown', function(e) {
                if (e.target.closest('button')) return;
                dragging = true;
                var r = overlayEl.getBoundingClientRect();
                startLeft = r.left;
                startTop = r.top;
                startX = e.clientX;
                startY = e.clientY;
                overlayEl.style.right = 'auto';
                overlayEl.style.bottom = 'auto';
                overlayEl.style.left = startLeft + 'px';
                overlayEl.style.top = startTop + 'px';
                e.preventDefault();
            });
            document.addEventListener('mousemove', function(e) {
                if (!dragging) return;
                var dx = e.clientX - startX, dy = e.clientY - startY;
                overlayEl.style.left = (startLeft + dx) + 'px';
                overlayEl.style.top = (startTop + dy) + 'px';
                startLeft += dx; startTop += dy; startX = e.clientX; startY = e.clientY;
            });
            document.addEventListener('mouseup', function() { dragging = false; });
        })();
    })();
    </script>
    
    <!-- Alerta de Torneo Cerrado (compacto) -->
    <?php if ($isLocked): ?>
        <div class="bg-gray-100 border-l-4 border-gray-500 rounded-lg p-2 mb-2">
            <div class="flex items-center gap-2 text-gray-700">
                <i class="fas fa-lock text-xl"></i>
                <span class="font-semibold">Torneo cerrado: solo se permite consultar e imprimir. Las acciones de modificación están deshabilitadas.</span>
            </div>
        </div>
    <?php endif; ?>

    <?php include __DIR__ . '/partials/panel/_acciones_torneo.php'; ?>

</div>

<!-- Modal Importación Masiva (solo torneos individuales) -->
<div class="modal fade" id="modalImportacionMasiva" tabindex="-1" aria-labelledby="modalImportacionMasivaLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-indigo-600 text-white">
                <h5 class="modal-title" id="modalImportacionMasivaLabel"><i class="fas fa-file-csv me-2"></i>Importación masiva</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Cargue un archivo <strong>Excel (.xls / 97-2003 o .xlsx)</strong> o CSV. Campos obligatorios: <strong>nacionalidad, cédula, nombre, club, organización</strong>. Si falta cualquiera, la fila se rechaza. Asigne cada columna al campo (entidad/organización se asocian a Organización).</p>
                <p class="small mb-2"><strong>Semáforo (tras Validar):</strong> <span class="badge" style="background:#3b82f6">Azul</span> Ya inscrito (omitir) · <span class="badge" style="background:#eab308;color:#000">Amarillo</span> Usuario existe (solo inscribir) · <span class="badge" style="background:#22c55e">Verde</span> Todo nuevo (crear e inscribir) · <span class="badge bg-danger">Rojo</span> Error de datos</p>
                <div class="mb-3">
                    <label class="form-label">Archivo CSV</label>
                    <input type="file" class="form-control" id="importMasivaFile" accept=".xls,.xlsx,.csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv">
                </div>
                <div id="importMasivaMapping" class="mb-3 d-none">
                    <h6 class="mb-2">Mapeo de columnas</h6>
                    <div class="row g-2 flex-wrap" id="importMasivaMappingRow"></div>
                </div>
                <div id="importMasivaPreviewWrap" class="mb-3 d-none">
                    <h6 class="mb-2">Vista previa <span class="badge bg-secondary" id="importMasivaPreviewCount">0</span> filas</h6>
                    <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                        <table class="table table-sm table-bordered" id="importMasivaPreviewTable"></table>
                    </div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btnImportMasivaValidar"><i class="fas fa-check-double me-1"></i>Validar (semáforo)</button>
                        <button type="button" class="btn btn-success btn-sm ms-2" id="btnImportMasivaProcesar"><i class="fas fa-play me-1"></i>Procesar importación</button>
                    </div>
                </div>
                <div id="importMasivaLoading" class="d-none text-center py-3"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i><p class="mt-2 mb-0">Procesando...</p></div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const formGenerarRonda = document.getElementById('form-generar-ronda');
    if (formGenerarRonda) {
        formGenerarRonda.addEventListener('submit', async function(e) {
            const btnGenerar = document.getElementById('btn-generar-ronda');
            if (btnGenerar && !btnGenerar.disabled) {
                btnGenerar.disabled = true;
                btnGenerar.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Generando...';
            }
        });
    }

    // Cuenta regresiva: cierre oficial del torneo en 20 minutos (actualiza todos los .countdown-tiempo-restante)
    const countdownEls = document.querySelectorAll('.countdown-tiempo-restante');
    const countdownEl = countdownEls[0];
    if (countdownEl) {
        const finTimestamp = parseInt(countdownEl.getAttribute('data-fin'), 10);
        function actualizarCuentaRegresiva() {
            const ahora = Math.floor(Date.now() / 1000);
            let restante = finTimestamp - ahora;
            const m = Math.floor(restante / 60);
            const s = restante <= 0 ? 0 : (restante % 60);
            const texto = (restante <= 0 ? '00:00' : (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s);
            countdownEls.forEach(function(el) { el.textContent = texto; });
            if (restante <= 0) {
                var listoHtml = '<p class="text-sm font-medium text-white"><i class="fas fa-check-circle"></i> Listo para finalizar. Recargando…</p>';
                var topBlock = document.getElementById('countdown-cierre-torneo-top') || countdownEl.closest('.mb-4');
                if (topBlock) topBlock.innerHTML = listoHtml;
                var col = document.getElementById('countdown-cierre-torneo');
                if (col) col.innerHTML = listoHtml;
                window.clearInterval(intervalId);
                setTimeout(function() { window.location.reload(); }, 1500);
                return;
            }
        }
        actualizarCuentaRegresiva();
        const intervalId = window.setInterval(actualizarCuentaRegresiva, 1000);
    }
});

async function actualizarEstadisticasConfirmar(event) {
    const result = await Swal.fire({
        title: '¿Actualizar estadísticas?',
        text: '¿Actualizar estadísticas de todos los inscritos?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, actualizar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6b7280'
    });
    
    if (result.isConfirmed) {
        event.target.submit();
    }
}


async function eliminarRondaConfirmar(event, ronda, tieneResultadosMesas) {
    const form = event.target;
    const inputConfirmar = document.getElementById('confirmar_eliminar_con_resultados');
    if (inputConfirmar) inputConfirmar.value = '';

    // Fallback cuando SweetAlert2 no está cargado (p. ej. panel desde index.php con layout)
    if (typeof Swal === 'undefined') {
        if (tieneResultadosMesas) {
            var texto = prompt('La ronda ' + ronda + ' tiene resultados registrados. Para eliminar de todas formas escriba exactamente: ELIMINAR');
            if (texto === 'ELIMINAR' && inputConfirmar) {
                inputConfirmar.value = 'ELIMINAR';
                form.submit();
            }
        } else {
            if (confirm('¿Eliminar la ronda ' + ronda + '? Se eliminarán las asignaciones de mesas de esta ronda.')) {
                form.submit();
            }
        }
        return;
    }

    if (tieneResultadosMesas) {
        const { value: texto } = await Swal.fire({
            title: 'Confirmación estricta',
            html: '<p class="text-left">La ronda <strong>' + ronda + '</strong> tiene <strong>resultados de mesas registrados</strong>.</p>' +
                  '<p class="text-left text-gray-600">Eliminar borrará todos los resultados y asignaciones de esta ronda. Esta acción no se puede deshacer.</p>' +
                  '<p class="text-left mt-3 font-semibold">Para continuar, escriba exactamente: <code class="bg-gray-200 px-1">ELIMINAR</code></p>',
            icon: 'warning',
            input: 'text',
            inputPlaceholder: 'Escriba ELIMINAR',
            inputValidator: (value) => {
                if (value !== 'ELIMINAR') return 'Debe escribir exactamente: ELIMINAR';
            },
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar la ronda',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280'
        });
        if (texto === 'ELIMINAR' && inputConfirmar) {
            inputConfirmar.value = 'ELIMINAR';
            form.submit();
        }
        return;
    }

    const result = await Swal.fire({
        title: '¿Eliminar ronda?',
        html: '¿Está seguro de eliminar la ronda <strong>' + ronda + '</strong>?<br><small class="text-gray-500">Se eliminarán las asignaciones de mesas de esta ronda.</small>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280'
    });
    if (result.isConfirmed) {
        form.submit();
    }
}

async function confirmarCierreTorneo(event) {
    await Swal.fire({
        title: '<i class="fas fa-lock text-gray-700"></i> Finalizar torneo',
        html: `
            <div class="text-left text-sm">
                <div class="bg-red-50 border-l-4 border-red-500 p-3 mb-3">
                    <p class="text-red-700 font-semibold"><i class="fas fa-exclamation-triangle mr-1"></i> Acción irreversible</p>
                </div>
                <p class="mb-2">Esta acción <strong>finalizará definitivamente</strong> el torneo. A partir de ese momento <strong>no será posible modificar datos</strong>; solo consulta:</p>
                <ul class="list-disc pl-5 mb-3 text-gray-600">
                    <li>Inscripciones</li>
                    <li>Resultados</li>
                    <li>Rondas</li>
                    <li>Reasignaciones</li>
                </ul>
                <div class="bg-amber-50 border-l-4 border-amber-500 p-3">
                    <p class="text-amber-700"><i class="fas fa-info-circle mr-1"></i> Ya han pasado 20 minutos desde el último resultado; puede finalizar para evitar manipulaciones.</p>
                </div>
            </div>
        `,
        icon: null,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-lock mr-1"></i> Sí, finalizar torneo',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#111827',
        cancelButtonColor: '#6b7280',
        reverseButtons: true,
        focusCancel: true,
        customClass: {
            popup: 'rounded-xl'
        }
    }).then((res) => {
        if (res.isConfirmed) {
            event.target.submit();
        }
    });
}

// --- Importación masiva ---
(function() {
    const CAMPOS = ['nacionalidad','cedula','nombre','sexo','fecha_nac','telefono','email','club','organizacion'];
    const CAMPOS_LABEL = { organizacion: 'Organización' };
    const COLORS = { omitir: '#3b82f6', inscribir: '#eab308', crear_inscribir: '#22c55e', error: '#ef4444' };
    const CAMPO_ALIASES = {
        nombre: ['nombre', 'nombres y apellidos', 'nombres', 'nombres y apellido'],
        cedula: ['cedula', 'cédula', 'cedula de identidad'],
        organizacion: ['organizacion', 'organización', 'entidad', 'asociacion', 'asociación'],
        club: ['club', 'club_nombre', 'club nombre']
    };
    let importMasivaHeaders = [];
    let importMasivaRows = [];
    let importMasivaValidacion = [];

    function detectEncodingAndDecode(buffer) {
        var bytes = new Uint8Array(buffer);
        var utf8 = new TextDecoder('utf-8').decode(bytes);
        var mojibakePattern = /Ã[Âª©®¯°±²³´µ¶·¸¹º»¼½¾¿ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏ]/;
        if (mojibakePattern.test(utf8) || (utf8.indexOf('Ã') !== -1 && utf8.indexOf('©') !== -1)) {
            try {
                return new TextDecoder('windows-1252').decode(bytes);
            } catch (e) {
                try {
                    return new TextDecoder('iso-8859-1').decode(bytes);
                } catch (e2) {
                    return utf8;
                }
            }
        }
        return utf8;
    }

    function parseCSV(text) {
        const lines = [];
        let cur = '', inQuotes = false;
        for (let i = 0; i < text.length; i++) {
            const c = text[i];
            if (c === '"') { inQuotes = !inQuotes; continue; }
            if (!inQuotes && (c === '\n' || c === '\r')) {
                if (c === '\r' && text[i+1] === '\n') i++;
                if (cur.trim()) lines.push(cur);
                cur = '';
                continue;
            }
            cur += c;
        }
        if (cur.trim()) lines.push(cur);
        return lines.map(function(line) {
            const out = [];
            let cell = '';
            inQuotes = false;
            for (let j = 0; j < line.length; j++) {
                const c = line[j];
                if (c === '"') { inQuotes = !inQuotes; continue; }
                if (!inQuotes && (c === ',' || c === ';')) { out.push(cell.trim()); cell = ''; continue; }
                cell += c;
            }
            out.push(cell.trim());
            return out;
        });
    }

    function getTorneoId() {
        const m = window.location.href.match(/torneo_id=(\d+)/);
        return m ? m[1] : (document.querySelector('input[name="torneo_id"]') && document.querySelector('input[name="torneo_id"]').value) || '';
    }

    function getCsrfToken() {
        return document.querySelector('input[name="csrf_token"]') && document.querySelector('input[name="csrf_token"]').value || '';
    }

    document.getElementById('importMasivaFile').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        var ext = (file.name.split('.').pop() || '').toLowerCase();
        document.getElementById('importMasivaLoading').classList.remove('d-none');
        if (ext === 'xls' || ext === 'xlsx' || ext === 'csv') {
            var fd = new FormData();
            fd.append('archivo', file);
            fd.append('csrf_token', getCsrfToken());
            fetch('api/tournament_import_parse.php', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    document.getElementById('importMasivaLoading').classList.add('d-none');
                    if (!data.success) { alert(data.error || 'Error al leer el archivo'); return; }
                    importMasivaHeaders = data.headers || [];
                    importMasivaRows = data.rows || [];
                    if (importMasivaHeaders.length === 0 || importMasivaRows.length === 0) {
                        alert('El archivo debe tener cabecera y al menos una fila de datos.');
                        return;
                    }
                    applyParsedData();
                })
                .catch(function() {
                    document.getElementById('importMasivaLoading').classList.add('d-none');
                    alert('Error de conexión al procesar el archivo.');
                });
        } else {
            var reader = new FileReader();
            reader.onload = function(ev) {
                var buffer = ev.target.result;
                var text = detectEncodingAndDecode(buffer);
                text = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
                var parsed = parseCSV(text);
                document.getElementById('importMasivaLoading').classList.add('d-none');
                if (parsed.length < 2) { alert('El archivo debe tener al menos cabecera y una fila.'); return; }
                importMasivaHeaders = parsed[0];
                importMasivaRows = parsed.slice(1);
                applyParsedData();
            };
            reader.readAsArrayBuffer(file);
        }
    });

    function applyParsedData() {
        const row = document.getElementById('importMasivaMappingRow');
        row.innerHTML = '';
        CAMPOS.forEach(function(campo) {
            const div = document.createElement('div');
            div.className = 'col-6 col-md-4 col-lg-3';
            var label = (CAMPOS_LABEL[campo] || campo);
            var opts = importMasivaHeaders.map(function(h, i) {
                var head = (String(h || 'Col ' + (i+1))).trim().toLowerCase();
                var aliases = CAMPO_ALIASES[campo];
                var selected = aliases && aliases.indexOf(head) !== -1 ? ' selected' : '';
                if (!selected && campo === 'organizacion' && (head === 'entidad' || head === 'organizacion' || head === 'organización' || head === 'asociacion' || head === 'asociación')) selected = ' selected';
                return '<option value="' + i + '"' + selected + '>' + (h || 'Col ' + (i+1)) + '</option>';
            }).join('');
            div.innerHTML = '<label class="form-label small mb-0">' + label + '</label><select class="form-select form-select-sm map-select" data-campo="' + campo + '"><option value="">-- No usar --</option>' + opts + '</select>';
            row.appendChild(div);
        });
        document.getElementById('importMasivaMapping').classList.remove('d-none');
        document.getElementById('importMasivaPreviewWrap').classList.remove('d-none');
        document.getElementById('importMasivaPreviewCount').textContent = importMasivaRows.length;
        buildPreviewTable();
    }

    function buildPreviewTable() {
        const map = {};
        document.querySelectorAll('.map-select').forEach(function(s) {
            const v = s.value;
            if (v !== '') map[s.dataset.campo] = parseInt(v, 10);
        });
        const thead = ['#'].concat(CAMPOS.map(function(c) { return CAMPOS_LABEL[c] || c; }));
        const tbody = importMasivaRows.map(function(r, i) {
            const row = [(i+1)];
            CAMPOS.forEach(function(c) { row.push(map[c] !== undefined ? (r[map[c]] || '') : ''); });
            return row;
        });
        const table = document.getElementById('importMasivaPreviewTable');
        table.innerHTML = '<thead class="table-light"><tr>' + thead.map(function(h) { return '<th>' + h + '</th>'; }).join('') + '</tr></thead><tbody id="importMasivaTbody"></tbody>';
        const tbodyEl = document.getElementById('importMasivaTbody');
        tbody.forEach(function(row, i) {
            const tr = document.createElement('tr');
            tr.dataset.index = i;
            tr.innerHTML = row.map(function(cell) { return '<td>' + (cell !== undefined && cell !== null ? String(cell) : '') + '</td>'; }).join('');
            tbodyEl.appendChild(tr);
        });
        importMasivaValidacion = [];
    }

    document.querySelector('#importMasivaMappingRow') && document.querySelector('#importMasivaMappingRow').addEventListener('change', function() {
        if (importMasivaRows.length) buildPreviewTable();
    });

    function getFilasMapeadas() {
        const map = {};
        document.querySelectorAll('.map-select').forEach(function(s) {
            const v = s.value;
            if (v !== '') map[s.dataset.campo] = parseInt(v, 10);
        });
        return importMasivaRows.map(function(r) {
            const obj = {};
            CAMPOS.forEach(function(c) {
                if (map[c] !== undefined) {
                    var val = r[map[c]];
                    val = val != null ? String(val).trim() : '';
                    obj[c] = val;
                }
            });
            if (obj.nacionalidad === undefined || obj.nacionalidad === '') {
                obj.nacionalidad = 'V';
            }
            return obj;
        });
    }

    document.getElementById('btnImportMasivaValidar').addEventListener('click', function() {
        const filas = getFilasMapeadas();
        if (!filas.length) { alert('No hay filas para validar.'); return; }
        const fd = new FormData();
        fd.append('action', 'validar');
        fd.append('torneo_id', getTorneoId());
        fd.append('filas', JSON.stringify(filas));
        fd.append('csrf_token', getCsrfToken());
        document.getElementById('importMasivaLoading').classList.remove('d-none');
        fetch('api/tournament_import_masivo.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                document.getElementById('importMasivaLoading').classList.add('d-none');
                if (!data.success) { alert(data.error || 'Error al validar'); return; }
                importMasivaValidacion = data.validacion || [];
                const tbody = document.getElementById('importMasivaTbody');
                if (tbody) {
                    [].forEach.call(tbody.querySelectorAll('tr'), function(tr, i) {
                        const v = importMasivaValidacion[i];
                        tr.style.backgroundColor = v && COLORS[v.estado] ? COLORS[v.estado] : '';
                        tr.style.color = v && v.estado === 'error' ? '#fff' : (v && COLORS[v.estado] ? '#fff' : '');
                        tr.title = v ? v.mensaje : '';
                    });
                }
            })
            .catch(function() { document.getElementById('importMasivaLoading').classList.add('d-none'); alert('Error de conexión'); });
    });

    document.getElementById('btnImportMasivaProcesar').addEventListener('click', function() {
        const filas = getFilasMapeadas();
        if (!filas.length) { alert('No hay filas para procesar.'); return; }
        const fd = new FormData();
        fd.append('action', 'importar');
        fd.append('torneo_id', getTorneoId());
        fd.append('filas', JSON.stringify(filas));
        fd.append('csrf_token', getCsrfToken());
        document.getElementById('importMasivaLoading').classList.remove('d-none');
        fetch('api/tournament_import_masivo.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                document.getElementById('importMasivaLoading').classList.add('d-none');
                if (!data.success) { alert(data.error || 'Error'); return; }
                const tieneErrores = data.errores && data.errores.length > 0;
                const html = '<p>Procesados: <strong>' + (data.procesados || 0) + '</strong></p><p>Nuevos (creados e inscritos): <strong>' + (data.nuevos || 0) + '</strong></p><p>Omitidos (ya inscritos): <strong>' + (data.omitidos || 0) + '</strong></p>' +
                    (tieneErrores ? '<p class="text-danger">Errores: ' + data.errores.length + '</p>' : '');
                const opts = {
                    title: 'Importación finalizada',
                    html: html,
                    icon: tieneErrores ? 'warning' : 'success',
                    confirmButtonText: 'Aceptar'
                };
                if (tieneErrores && data.archivo_errores_base64) {
                    opts.showDenyButton = true;
                    opts.denyButtonText = 'Descargar Log de Errores';
                    opts.denyButtonColor = '#6b7280';
                }
                Swal.fire(opts).then(function(res) {
                    if (res.isDenied && data.archivo_errores_base64) {
                        var bin = atob(data.archivo_errores_base64);
                        var blob = new Blob([bin], { type: 'text/plain;charset=utf-8' });
                        const a = document.createElement('a');
                        a.href = URL.createObjectURL(blob);
                        a.download = 'log_errores_importacion_' + (new Date().toISOString().slice(0,10)) + '.txt';
                        a.click();
                        URL.revokeObjectURL(a.href);
                    }
                    if (data.success && (data.procesados > 0 || data.omitidos > 0)) window.location.reload();
                });
            })
            .catch(function() { document.getElementById('importMasivaLoading').classList.add('d-none'); alert('Error de conexión'); });
    });

    if (window.location.hash === '#importacion-masiva') {
        var btnImp = document.getElementById('btnAbrirImportacionMasiva');
        if (btnImp) {
            setTimeout(function() { btnImp.click(); }, 300);
        }
    }
})();
</script>
