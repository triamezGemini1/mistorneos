<?php
/**
 * Vista Moderna: Panel de Control de Torneo
 * Panel común para todos los tipos de torneo (individual/parejas/equipos)
 * Se adapta dinámicamente según la modalidad del torneo
 * Diseño con Tailwind CSS - 3 columnas organizadas
 */
require_once __DIR__ . '/../../config/db.php';

$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php']);
$base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';

// Asegurar que $torneo esté disponible (viene de extract($view_data) en torneo_gestion.php)
// Si no está disponible después del extract, intentar obtenerlo
if (!isset($torneo) || empty($torneo)) {
    // Intentar obtenerlo del torneo_id que debería estar disponible
    $torneo_id_local = isset($torneo_id) ? (int)$torneo_id : (int)($_GET['torneo_id'] ?? 0);
    if ($torneo_id_local > 0) {
        try {
            $pdo = DB::pdo();
            $stmt = $pdo->prepare("SELECT t.*, o.nombre as organizacion_nombre FROM tournaments t LEFT JOIN organizaciones o ON t.club_responsable = o.id WHERE t.id = ?");
            $stmt->execute([$torneo_id_local]);
            $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo torneo en panel-moderno.php: " . $e->getMessage());
            $torneo = null;
        }
    }
}

// Asegurar valores por defecto si aún no está disponible
if (!isset($torneo) || empty($torneo) || !is_array($torneo)) {
    $torneo = ['id' => $torneo_id_local ?? 0, 'nombre' => 'Torneo', 'modalidad' => 0];
}

$page_title = 'Panel de Control - ' . htmlspecialchars($torneo['nombre'] ?? 'Torneo');

// Detectar modalidad del torneo (3 = Equipos, 4 = Parejas fijas)
$es_modalidad_equipos = isset($torneo['modalidad']) && (int)$torneo['modalidad'] === 3;
$es_modalidad_parejas_fijas = isset($torneo['modalidad']) && (int)$torneo['modalidad'] === 4;

// Lógica de bloqueo de inscripciones: equipos y parejas fijas bloquean desde ronda >=1; otros desde ronda >=2
$torneo_bloqueado_inscripciones = false;
if ($ultima_ronda > 0) {
    $torneo_bloqueado_inscripciones = ($es_modalidad_equipos || $es_modalidad_parejas_fijas) ? ($ultima_ronda >= 1) : ($ultima_ronda >= 2);
}

// Variables de estado (asegurar que estén disponibles desde view_data después del extract)
// Nota: Estas variables vienen de obtenerDatosPanel() a través de extract($view_data)
// Usar variables con diferentes nombres para evitar conflictos con extract()
$ultima_ronda_val = isset($ultima_ronda) && $ultima_ronda !== null ? (int)$ultima_ronda : (isset($ultimaRonda) && $ultimaRonda !== null ? (int)$ultimaRonda : 0);
$proxima_ronda_val = isset($proxima_ronda) && $proxima_ronda !== null ? (int)$proxima_ronda : (isset($proximaRonda) && $proximaRonda !== null ? (int)$proximaRonda : ($ultima_ronda_val + 1));
$ultima_ronda = $ultima_ronda_val;
$proxima_ronda = $proxima_ronda_val;
$proximaRonda = $proxima_ronda_val;
$ultima_ronda_tiene_resultados = isset($ultima_ronda_tiene_resultados) ? (bool)$ultima_ronda_tiene_resultados : false;
$totalRondas = isset($torneo['rondas']) ? (int)$torneo['rondas'] : 0;
$puede_generar_ronda = isset($puede_generar_ronda) ? (bool)$puede_generar_ronda : (isset($puedeGenerarRonda) ? (bool)$puedeGenerarRonda : true);
$puedeGenerar = $puede_generar_ronda;
$mesas_incompletas = isset($mesas_incompletas) && $mesas_incompletas !== null ? (int)$mesas_incompletas : (isset($mesasIncompletas) && $mesasIncompletas !== null ? (int)$mesasIncompletas : 0);
$mesasInc = $mesas_incompletas;
$isLocked = isset($torneo['locked']) ? ((int)$torneo['locked'] === 1) : false;
// Finalizar torneo: habilitado cuando torneo completado (todas rondas, 0 mesas pendientes); no se exige esperar 20 min
$correcciones_cierre_at = isset($correcciones_cierre_at) ? $correcciones_cierre_at : null;
$torneo_completado = $totalRondas > 0 && $ultima_ronda >= $totalRondas && $mesasInc == 0;
$puedeCerrar = !$isLocked && $ultima_ronda > 0 && $mesasInc == 0 && $torneo_completado;
// Countdown "correcciones se cierran" desde correcciones_cierre_at (fijado al guardar última mesa; no se resetea)
$countdown_fin_timestamp = null;
$mostrar_aviso_20min = false;
if (!empty($correcciones_cierre_at) && $correcciones_cierre_at !== '0000-00-00 00:00:00') {
    $countdown_fin_timestamp = strtotime($correcciones_cierre_at);
    $mostrar_aviso_20min = !$isLocked && $torneo_completado && (time() < $countdown_fin_timestamp);
}

// Actas pendientes de verificación (QR)
$actas_pendientes_count = isset($actas_pendientes_count) && $actas_pendientes_count !== null ? (int)$actas_pendientes_count : 0;
// Auditoría: mesas Verificadas (QR con foto) vs Digitadas (por admin)
$mesas_verificadas_count = isset($mesas_verificadas_count) && $mesas_verificadas_count !== null ? (int)$mesas_verificadas_count : 0;
$mesas_digitadas_count = isset($mesas_digitadas_count) && $mesas_digitadas_count !== null ? (int)$mesas_digitadas_count : 0;

// Estadísticas adicionales
$total_inscritos = isset($total_inscritos) && $total_inscritos !== null ? (int)$total_inscritos : (isset($totalInscritos) && $totalInscritos !== null ? (int)$totalInscritos : 0);
// Participantes que cuentan para rondas/mesas/BYE = solo confirmados (estatus 1)
$inscritos_para_rondas = isset($inscritos_confirmados) && $inscritos_confirmados !== null ? (int)$inscritos_confirmados : $total_inscritos;
$total_equipos = isset($total_equipos) && $total_equipos !== null ? (int)$total_equipos : (isset($estadisticas['total_equipos']) ? (int)$estadisticas['total_equipos'] : 0);
$estadisticas = isset($estadisticas) && is_array($estadisticas) ? $estadisticas : [];

// Obtener primera mesa para registrar resultados
$primera_mesa = null;
if ($ultima_ronda > 0 && isset($torneo['id'])) {
    try {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("SELECT MIN(CAST(mesa AS UNSIGNED)) as primera_mesa FROM partiresul WHERE id_torneo = ? AND partida = ? AND mesa > 0");
        $stmt->execute([$torneo['id'], $ultima_ronda]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $primera_mesa = $result['primera_mesa'] ?? null;
    } catch (Exception $e) {
        error_log("Error obteniendo primera mesa en panel-moderno.php: " . $e->getMessage());
    }
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
    <!-- Breadcrumb (compacto) -->
    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="flex items-center space-x-2 text-sm text-gray-500">
            <li><a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=index" class="hover:text-blue-600">Gestión de Torneos</a></li>
            <li><i class="fas fa-chevron-right text-xs mx-2"></i></li>
            <li class="text-gray-700 font-medium"><?php echo htmlspecialchars($torneo['nombre'] ?? 'Panel'); ?></li>
        </ol>
    </nav>

    <!-- Header del Torneo (compacto) -->
    <div class="panel-header bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl shadow-lg p-4 mb-3 text-white">
        <div class="panel-header-inner flex justify-between items-center flex-wrap gap-4">
            <div class="flex-grow-1 panel-header-grow">
                <h2 class="titulo-torneo">
                    <?php echo htmlspecialchars($torneo['nombre'] ?? 'Torneo'); ?>
                </h2>
                <div class="meta flex flex-wrap gap-4">
                    <span><i class="fas fa-calendar-alt mr-1"></i> <?php echo date('d/m/Y', strtotime($torneo['fechator'] ?? 'now')); ?></span>
                    <span><i class="fas fa-chess mr-1"></i> 
                        <?php 
                        $modalidad_num = (int)($torneo['modalidad'] ?? 0);
                        if ($modalidad_num === 3) {
                            echo 'Equipos';
                        } else if ($modalidad_num === 4) {
                            echo 'Parejas fijas';
                        } else if ($modalidad_num === 2) {
                            echo 'Parejas';
                        } else {
                            echo 'Individual';
                        }
                        ?>
                    </span>
                    <span><i class="fas fa-layer-group mr-1"></i> <?php echo ($torneo['rondas'] ?? 0); ?> rondas</span>
                </div>
            </div>
            <div class="text-right flex-shrink-0">
                <div class="torneo-id text-4xl font-extrabold opacity-80">#<?php echo $torneo['id']; ?></div>
                <div class="text-sm opacity-70">ID del Torneo</div>
            </div>
        </div>
    </div>

    <!-- Mensajes de éxito/error -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4 flex items-center justify-between">
            <span><i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></span>
            <button type="button" onclick="this.parentElement.remove()" class="text-green-700 hover:text-green-900">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert-error bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-center justify-between">
            <span><i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
            <button type="button" onclick="this.parentElement.remove()" class="text-red-700 hover:text-red-900">&times;</button>
        </div>
    <?php endif; ?>

    <?php if ($actas_pendientes_count > 0): ?>
        <div class="bg-amber-50 border-l-4 border-amber-500 px-4 py-3 rounded-lg mb-4 flex items-center justify-between">
            <span>
                <i class="fas fa-clipboard-check text-amber-600 mr-2"></i>
                <strong>Tienes <?= $actas_pendientes_count ?> acta(s) pendientes de verificación</strong>
                <span class="text-amber-700 ml-1">(enviadas por QR)</span>
            </span>
            <a href="<?= $base_url . ($use_standalone ? '?' : '&') ?>action=verificar_resultados&torneo_id=<?= (int)($torneo['id'] ?? 0) ?>" class="bg-amber-500 hover:bg-amber-600 text-white font-semibold px-4 py-2 rounded-lg transition-colors">
                <i class="fas fa-eye me-1"></i>Verificar
            </a>
        </div>
    <?php endif; ?>

    <?php if (($mesas_verificadas_count + $mesas_digitadas_count) > 0): ?>
        <div class="bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 mb-4">
            <h5 class="text-slate-700 font-semibold mb-2"><i class="fas fa-chart-bar mr-2"></i>Auditoría de Resultados</h5>
            <div class="flex flex-wrap gap-4">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-emerald-100 text-emerald-800">
                    <i class="fas fa-camera mr-1"></i>Verificadas (QR): <?= $mesas_verificadas_count ?>
                </span>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                    <i class="fas fa-keyboard mr-1"></i>Digitadas (admin): <?= $mesas_digitadas_count ?>
                </span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Estado de Ronda Actual -->
    <?php if ($ultima_ronda > 0): ?>
        <div class="ronda-info bg-blue-50 border-l-4 border-blue-500 rounded-lg p-4 mb-6">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-4">
                    <span class="text-gray-600 font-semibold">
                        Ronda Actual: <span class="text-blue-600 text-xl font-bold"><?php echo $ultima_ronda; ?></span>
                    </span>
                    <?php if (isset($estadisticas['mesas_ronda'])): ?>
                        <span class="text-blue-600 font-semibold"><?php echo $estadisticas['mesas_ronda']; ?> mesas</span>
                    <?php endif; ?>
                    <?php if ($es_modalidad_equipos): ?>
                        <?php if (isset($total_equipos) && $total_equipos > 0): ?>
                            <span class="text-indigo-600 font-semibold ml-4">
                                <i class="fas fa-users mr-1"></i> <?php echo $total_equipos; ?> equipos inscritos
                            </span>
                        <?php endif; ?>
                        <?php if (isset($estadisticas['total_jugadores_inscritos']) && $estadisticas['total_jugadores_inscritos'] > 0): ?>
                            <span class="text-green-600 font-semibold ml-4">
                                <i class="fas fa-user-friends mr-1"></i> <?php echo $estadisticas['total_jugadores_inscritos']; ?> jugadores
                            </span>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if (isset($inscritos_para_rondas) && $inscritos_para_rondas > 0): ?>
                            <span class="text-green-600 font-semibold ml-4">
                                <i class="fas fa-user-friends mr-1"></i> <?php echo $inscritos_para_rondas; ?> inscritos<?php if ($total_inscritos !== $inscritos_para_rondas): ?> (<?php echo $total_inscritos; ?> en lista)<?php endif; ?>
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php if (!$puedeGenerar && $ultima_ronda > 0 && $mesasInc > 0): ?>
                    <div class="flex items-center gap-2 text-amber-600">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span class="font-semibold"><?php echo $mesasInc; ?> mesa(s) pendiente(s)</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

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
    
    <?php $tiempo_ronda_min = (int)($torneo['tiempo'] ?? 35); if ($tiempo_ronda_min < 1) $tiempo_ronda_min = 35; ?>
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

    <!-- Panel de Control - 3 Columnas (compacto) -->
    <div class="tw-columns flex gap-3">
            <!-- COLUMNA IZQUIERDA: Gestión de Mesas -->
            <div class="tw-column w-1/3">
                <div class="bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden h-full">
                    <div class="bg-gradient-to-r from-emerald-500 to-teal-500 px-4 py-2">
                        <h3 class="text-white text-lg flex items-center mb-0">
                            <i class="fas fa-table mr-2"></i> Gestión de Mesas
                        </h3>
                    </div>
                    <div class="p-5 space-y-4">
                        <!-- Invitar Clubes (listado directorio + envío por WhatsApp/Telegram) -->
                        <a href="index.php?page=invitacion_clubes&torneo_id=<?= (int)($torneo['id'] ?? 0) ?>" class="tw-btn bg-cyan-500 hover:bg-cyan-600 text-white w-full text-center">
                            <i class="fas fa-paper-plane mr-2"></i> Invitar Clubes
                        </a>
                        <!-- Inscripciones: un solo bloque (Gestionar + Inscribir en sitio) -->
                        <?php if ($isLocked || $torneo_bloqueado_inscripciones): ?>
                            <button type="button" disabled class="tw-btn bg-gray-400 text-white">
                                <i class="fas fa-lock"></i> Inscripciones (Cerrado)
                            </button>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-1">
                                <?php if ($es_modalidad_equipos): ?>
                                    <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=gestionar_inscripciones_equipos&torneo_id=<?php echo $torneo['id']; ?>" class="tw-btn bg-blue-500 hover:bg-blue-600 text-white"><i class="fas fa-clipboard-list"></i> Gestionar Inscripciones</a>
                                    <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=inscribir_equipo_sitio&torneo_id=<?php echo $torneo['id']; ?>" class="tw-btn bg-amber-500 hover:bg-amber-600 text-white"><i class="fas fa-user-plus"></i> Inscribir en Sitio</a>
                                <?php elseif ($es_modalidad_parejas_fijas): ?>
                                    <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=gestionar_inscripciones_parejas_fijas&torneo_id=<?php echo $torneo['id']; ?>" class="tw-btn bg-blue-500 hover:bg-blue-600 text-white"><i class="fas fa-clipboard-list"></i> Gestionar Inscripciones</a>
                                    <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=inscribir_pareja_sitio&torneo_id=<?php echo $torneo['id']; ?>" class="tw-btn bg-amber-500 hover:bg-amber-600 text-white"><i class="fas fa-user-plus"></i> Inscribir Pareja en Sitio</a>
                                <?php else: ?>
                                    <a href="index.php?page=registrants&torneo_id=<?php echo $torneo['id']; ?><?php echo $use_standalone ? '&return_to=panel_torneo' : ''; ?>" class="tw-btn bg-blue-500 hover:bg-blue-600 text-white"><i class="fas fa-clipboard-list"></i> Gestionar Inscripciones</a>
                                    <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=inscribir_sitio&torneo_id=<?php echo $torneo['id']; ?>" class="tw-btn bg-amber-500 hover:bg-amber-600 text-white"><i class="fas fa-user-check"></i> Inscripción en Sitio</a>
                                <?php endif; ?>
                                <a href="index.php?page=tournament_admin&torneo_id=<?php echo (int)$torneo['id']; ?>&action=activar_participantes" class="tw-btn bg-green-500 hover:bg-green-600 text-white"><i class="fas fa-user-check"></i> Activar participantes</a>
                            </div>
                        <?php endif; ?>
                        <?php if ($isLocked || $torneo_bloqueado_inscripciones): ?>
                        <div class="d-flex flex-column gap-1 mt-2">
                            <a href="index.php?page=tournament_admin&torneo_id=<?php echo (int)$torneo['id']; ?>&action=activar_participantes" class="tw-btn bg-green-500 hover:bg-green-600 text-white"><i class="fas fa-user-check"></i> Activar participantes</a>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Mostrar Asignaciones (solo si hay rondas generadas) -->
                        <?php if ($ultima_ronda > 0): ?>
                            <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=mesas&torneo_id=<?php echo $torneo['id']; ?>&ronda=<?php echo $ultima_ronda; ?>" 
                               class="tw-btn bg-emerald-500 hover:bg-emerald-600 text-white">
                                <i class="fas fa-eye"></i> Mostrar Asignaciones
                            </a>
                            
                            <!-- Asignar mesas al operador -->
                            <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=asignar_mesas_operador&torneo_id=<?php echo $torneo['id']; ?>&ronda=<?php echo $ultima_ronda; ?>" 
                               class="tw-btn bg-teal-500 hover:bg-teal-600 text-white">
                                <i class="fas fa-user-cog"></i> Asignar mesas al operador
                            </a>
                            
                            <!-- Agregar Mesa: solo habilitado en ronda 1 -->
                            <?php if ($isLocked): ?>
                                <button type="button" disabled class="tw-btn bg-gray-400 text-white">
                                    <i class="fas fa-lock"></i> Agregar Mesa (Cerrado)
                                </button>
                            <?php elseif ($ultima_ronda >= 2): ?>
                                <button type="button" disabled class="tw-btn bg-gray-400 text-white" title="Solo disponible en la ronda 1">
                                    <i class="fas fa-plus-circle"></i> Agregar Mesa (solo ronda 1)
                                </button>
                            <?php else: ?>
                                <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=agregar_mesa&torneo_id=<?php echo $torneo['id']; ?>&ronda=<?php echo $ultima_ronda; ?>" 
                                   class="tw-btn bg-cyan-500 hover:bg-cyan-600 text-white">
                                    <i class="fas fa-plus-circle"></i> Agregar Mesa
                                </a>
                            <?php endif; ?>
                            
                            <!-- Eliminar Ronda: siempre habilitado si el torneo no está cerrado. Con resultados en mesas exige confirmación estricta. -->
                            <form method="POST" action="<?php echo $use_standalone ? $base_url : 'index.php?page=torneo_gestion'; ?>" 
                                  onsubmit="event.preventDefault(); eliminarRondaConfirmar(event, <?php echo $ultima_ronda; ?>, <?php echo $ultima_ronda_tiene_resultados ? 'true' : 'false'; ?>);">
                                <input type="hidden" name="action" value="eliminar_ultima_ronda">
                                <input type="hidden" name="csrf_token" value="<?php echo CSRF::token(); ?>">
                                <input type="hidden" name="torneo_id" value="<?php echo $torneo['id']; ?>">
                                <input type="hidden" name="confirmar_eliminar_con_resultados" id="confirmar_eliminar_con_resultados" value="">
                                <button type="submit" <?php echo $isLocked ? 'disabled' : ''; ?>
                                        class="tw-btn <?php echo $isLocked ? 'bg-gray-400' : 'bg-red-500 hover:bg-red-600'; ?> text-white"
                                        title="<?php echo $isLocked ? 'Torneo cerrado.' : ($ultima_ronda_tiene_resultados ? 'Eliminar ronda (la ronda tiene resultados en mesas; se pedirá confirmación estricta).' : 'Eliminar la última ronda.'); ?>">
                                    <i class="fas fa-trash-alt"></i> Eliminar Ronda
                                </button>
                            </form>
                        <?php else: ?>
                            <!-- Sin rondas generadas -->
                            <div class="bg-gray-50 rounded-lg p-3 text-center text-gray-500 text-sm">
                                <i class="fas fa-info-circle mr-2"></i>
                                Genera la primera ronda para ver estas opciones
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- COLUMNA CENTRAL: Operaciones Principales -->
            <div class="tw-column w-1/3">
                <div class="bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden h-full">
                    <div class="bg-gradient-to-r from-blue-500 to-indigo-500 px-4 py-2">
<h3 class="text-white text-lg flex items-center mb-0">
                        <i class="fas fa-cogs mr-2"></i> Operaciones
                    </h3>
                    </div>
                    <div class="p-5 space-y-4">
                        <!-- Actualizar Resultados -->
                        <form method="POST" action="<?php echo $use_standalone ? $base_url : 'index.php?page=torneo_gestion'; ?>" 
                              onsubmit="event.preventDefault(); actualizarEstadisticasConfirmar(event);">
                            <input type="hidden" name="action" value="actualizar_estadisticas">
                            <input type="hidden" name="csrf_token" value="<?php echo CSRF::token(); ?>">
                            <input type="hidden" name="torneo_id" value="<?php echo $torneo['id']; ?>">
                            <button type="submit" <?php echo $isLocked ? 'disabled' : ''; ?>
                                    class="tw-btn <?php echo $isLocked ? 'bg-gray-400' : 'bg-cyan-500 hover:bg-cyan-600'; ?> text-white">
                                <i class="fas fa-sync-alt"></i> Actualizar Estadísticas
                            </button>
                        </form>

                        <!-- Verificar Mesas (QR): activo cuando hay actas pendientes -->
                        <?php if ($actas_pendientes_count > 0): ?>
                            <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=verificar_resultados&torneo_id=<?php echo (int)$torneo['id']; ?>" 
                               class="tw-btn text-white" style="background-color: #ef4444;">
                                <i class="fas fa-check-double"></i> Verificar Mesas
                                <span class="ml-2 px-2 py-0.5 rounded-full text-sm" style="background-color: rgba(255,255,255,0.3);"><?php echo $actas_pendientes_count; ?></span>
                            </a>
                        <?php else: ?>
                            <button type="button" disabled class="tw-btn bg-gray-400 text-white">
                                <i class="fas fa-check-double"></i> Verificar Mesas
                                <span class="ml-2 text-xs opacity-75">(envíos QR pendientes)</span>
                            </button>
                        <?php endif; ?>
                        
                        <!-- Generar Ronda -->
                        <?php if ($proximaRonda <= $totalRondas): ?>
                            <form method="POST" action="<?php echo $use_standalone ? ($base_url . '?torneo_id=' . (int)($torneo['id'] ?? 0)) : 'index.php?page=torneo_gestion'; ?>" id="form-generar-ronda">
                                <input type="hidden" name="action" value="generar_ronda">
                                <input type="hidden" name="csrf_token" value="<?php echo CSRF::token(); ?>">
                                <input type="hidden" name="torneo_id" value="<?php echo (int)($torneo['id'] ?? 0); ?>">
                                <button type="submit" id="btn-generar-ronda"
                                        <?php echo (!$puedeGenerar || $isLocked) ? 'disabled' : ''; ?>
                                        class="tw-btn <?php echo ($puedeGenerar && !$isLocked) ? 'bg-blue-500 hover:bg-blue-600' : 'bg-gray-400'; ?> text-white">
                                    <i class="fas fa-<?php echo ($puedeGenerar && !$isLocked) ? 'play' : 'lock'; ?>"></i>
                                    Generar Ronda <?php echo $proximaRonda; ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="bg-green-100 text-green-700 rounded-lg p-3 text-center font-semibold">
                                <i class="fas fa-check-circle mr-2"></i> Todas las rondas generadas
                            </div>
                        <?php endif; ?>
                        
                        <!-- Registrar Resultados (solo si hay rondas y mesas) -->
                        <?php if ($ultima_ronda > 0 && $primera_mesa): ?>
                            <?php if ($isLocked): ?>
                                <button type="button" disabled class="tw-btn bg-gray-400 text-white">
                                    <i class="fas fa-lock"></i> Ingresar Resultados (Cerrado)
                                </button>
                            <?php else: ?>
                                <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=registrar_resultados&torneo_id=<?php echo $torneo['id']; ?>&ronda=<?php echo $ultima_ronda; ?>&mesa=<?php echo $primera_mesa; ?>" 
                                   class="tw-btn bg-amber-500 hover:bg-amber-600 text-white">
                                    <i class="fas fa-keyboard"></i> Ingresar Resultados
                                </a>
                            <?php endif; ?>
                        <?php elseif ($ultima_ronda > 0): ?>
                            <button type="button" disabled class="tw-btn bg-gray-400 text-white">
                                <i class="fas fa-info-circle"></i> Sin mesas registradas
                            </button>
                        <?php endif; ?>
                        
                        <!-- Cuadrícula (solo si hay rondas) -->
                        <?php if ($ultima_ronda > 0): ?>
                            <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=cuadricula&torneo_id=<?php echo $torneo['id']; ?>&ronda=<?php echo $ultima_ronda; ?>" 
                               class="tw-btn bg-purple-500 hover:bg-purple-600 text-white">
                                <i class="fas fa-th"></i> Cuadrícula
                            </a>
                            
                            <!-- Imprimir Hojas (solo si hay rondas) -->
                            <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=hojas_anotacion&torneo_id=<?php echo $torneo['id']; ?>&ronda=<?php echo $ultima_ronda; ?>" 
                               class="tw-btn bg-indigo-500 hover:bg-indigo-600 text-white">
                                <i class="fas fa-print"></i> Imprimir Hojas
                            </a>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
            
            <!-- COLUMNA DERECHA: Resultados y Cierre -->
            <div class="tw-column w-1/3">
                <div class="bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden h-full">
                    <div class="bg-gradient-to-r from-amber-500 to-orange-500 px-4 py-2">
<h3 class="text-white text-lg flex items-center mb-0">
                        <i class="fas fa-trophy mr-2"></i> Resultados
                    </h3>
                    </div>
                    <div class="p-5 space-y-4">
                        <!-- Resultados (Adaptado según modalidad) -->
                        <?php if ($es_modalidad_equipos): ?>
                            <!-- Modalidad Equipos: Pool de reportes específicos para equipos -->
                            <!-- Resultados por Equipos - Resumido (orden de clasificación) -->
                            <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=resultados_equipos_resumido&torneo_id=<?php echo $torneo['id']; ?>" 
                               class="tw-btn bg-purple-500 hover:bg-purple-600 text-white">
                                <i class="fas fa-list-ol"></i> Resultados Equipos (Resumido)
                            </a>
                            
                            <!-- Resultados por Equipos - Detallado (con rompe control por equipo, orden de clasificación) -->
                            <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=resultados_equipos_detallado&torneo_id=<?php echo $torneo['id']; ?>" 
                               class="tw-btn bg-indigo-500 hover:bg-indigo-600 text-white">
                                <i class="fas fa-list-ul"></i> Resultados Equipos (Detallado)
                            </a>
                            
                            <!-- Resultados / Posiciones (clasificación individual general, mismo reporte que individuales) -->
                            <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=posiciones&torneo_id=<?php echo $torneo['id']; ?>" 
                               class="tw-btn bg-rose-500 hover:bg-rose-600 text-white">
                                <i class="fas fa-users-cog"></i> Resultados / Posiciones
                            </a>
                        <?php else: ?>
                            <!-- Modalidad Individual/Parejas: Pool de reportes para individual/parejas -->
                            <!-- Mostrar Resultados / Posiciones -->
                            <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=posiciones&torneo_id=<?php echo $torneo['id']; ?>" 
                               class="tw-btn bg-purple-500 hover:bg-purple-600 text-white">
                                <i class="fas fa-list-ol"></i> Resultados
                            </a>
                            
                            <!-- Resultados por Club -->
                            <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=resultados_por_club&torneo_id=<?php echo $torneo['id']; ?>" 
                               class="tw-btn bg-emerald-500 hover:bg-emerald-600 text-white">
                                <i class="fas fa-building"></i> Resultados Clubes
                            </a>
                        <?php endif; ?>
                        
                        <!-- Podios (Común para ambos tipos - detecta modalidad automáticamente) -->
                        <?php 
                        $podios_action = $es_modalidad_equipos ? 'podios_equipos' : 'podios';
                        $sep = $use_standalone ? '?' : '&';
                        $url_podios = $base_url . $sep . 'action=' . $podios_action . '&torneo_id=' . (int)$torneo['id'];
                        ?>
                        <a href="<?php echo htmlspecialchars($url_podios); ?>" 
                           class="tw-btn bg-amber-500 hover:bg-amber-600 text-white"
                           title="Ver podios del torneo">
                            <i class="fas fa-medal"></i> Podios
                        </a>
                        
                        <!-- Separador -->
                        <hr class="border-gray-200 my-2">
                        
                        <!-- Finalizar Torneo (solo cuando rondas completadas + 20 min desde último resultado) -->
                        <?php if ($mostrar_aviso_20min && $countdown_fin_timestamp): ?>
                        <div id="countdown-cierre-torneo" class="mb-3 p-3 rounded-lg border-2" style="background-color: #fce7f3; border-color: #c026d3;">
                            <p class="text-sm font-medium mb-1" style="color: #86198f;">
                                <i class="fas fa-clock"></i> El torneo se cerrará oficialmente en:
                            </p>
                            <p class="countdown-tiempo-restante text-2xl font-bold tabular-nums" style="color: #86198f;" data-fin="<?php echo (int)$countdown_fin_timestamp; ?>">
                                --:--
                            </p>
                            <p class="text-xs mt-1" style="color: #701a75;">Tras este tiempo se habilitará el botón <strong>Finalizar torneo</strong>.</p>
                        </div>
                        <?php endif; ?>
                        <form method="POST" action="<?php echo $use_standalone ? $base_url : 'index.php?page=torneo_gestion'; ?>" 
                              onsubmit="event.preventDefault(); confirmarCierreTorneo(event);">
                            <input type="hidden" name="action" value="cerrar_torneo">
                            <input type="hidden" name="csrf_token" value="<?php echo CSRF::token(); ?>">
                            <input type="hidden" name="torneo_id" value="<?php echo $torneo['id']; ?>">
                            <button type="submit" <?php echo $puedeCerrar ? '' : 'disabled'; ?>
                                    class="tw-btn <?php echo $isLocked ? 'bg-gray-500' : 'bg-gray-800 hover:bg-gray-900'; ?> text-white">
                                <i class="fas fa-lock"></i>
                                <?php echo $isLocked ? 'Torneo Finalizado' : 'Finalizar torneo'; ?>
                            </button>
                        </form>
                    </div>
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
</script>
