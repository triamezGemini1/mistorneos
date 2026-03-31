<?php
/**
 * Vista standalone: Cronómetro de Ronda
 * Página aparte - pantalla dedicada al cronómetro
 */
$torneo = $torneo ?? ['id' => 0, 'nombre' => 'Torneo'];
$torneo_id = $torneo_id ?? (int)($_GET['torneo_id'] ?? 0);
$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$base_panel = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php']) ? $script_actual : 'index.php?page=torneo_gestion';
$url_panel = class_exists('AppHelpers') 
    ? AppHelpers::url($base_panel, ['action' => 'panel', 'torneo_id' => $torneo_id])
    : ($base_panel . (strpos($base_panel, '?') !== false ? '&' : '?') . "action=panel&torneo_id=" . $torneo_id);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cronómetro - <?= htmlspecialchars($torneo['nombre'] ?? 'Ronda') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #1e1b4b 0%, #4c1d95 50%, #831843 100%);
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .cronometro-box {
            width: 90%;
            max-width: 805px;
            background: rgba(0,0,0,0.3);
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: 0 25px 50px rgba(0,0,0,0.4);
        }
        .cronometro-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        .cronometro-header h1 { font-size: 1.5rem; font-weight: 700; }
        .btn-retornar {
            background: #3b82f6;
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        .btn-retornar:hover { background: #2563eb; color: white; }
        #configPanel {
            background: rgba(255,255,255,0.1);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            display: none;
        }
        .config-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .config-grid label { display: block; font-weight: 700; margin-bottom: 0.5rem; font-size: 1.25rem; }
        .config-grid input {
            width: 100%;
            padding: 0.75rem;
            border-radius: 0.5rem;
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
        }
        .config-grid input:focus { outline: none; border-color: white; }
        #configPanel .btn-aplicar {
            width: 100%;
            background: #22c55e;
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 0.5rem;
            font-size: 1.25rem;
            font-weight: 700;
            cursor: pointer;
            margin-top: 0.5rem;
        }
        #configPanel .btn-aplicar:hover { background: #16a34a; }
        .btn-config { background: rgba(255,255,255,0.2); color: white; border: none; padding: 0.5rem 1rem; border-radius: 0.5rem; cursor: pointer; font-weight: 600; }
        .btn-config:hover { background: rgba(255,255,255,0.3); }
        .display-area {
            text-align: center;
            padding: 2rem 0;
        }
        #tiempoDisplay {
            font-family: 'Arial Black', sans-serif;
            font-size: clamp(4.6rem, 17.25vw, 11.5rem);
            font-weight: 900;
            line-height: 1.1;
            text-shadow: 0 4px 20px rgba(0,0,0,0.5);
        }
        #estadoDisplay { font-size: 1.725rem; opacity: 0.9; margin-top: 0.5rem; }
        .controles {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }
        .controles button {
            padding: 1.15rem 1.725rem;
            border-radius: 0.75rem;
            border: none;
            font-size: 1.725rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .controles #btnIniciar { background: #22c55e; color: white; }
        .controles #btnIniciar:hover:not(:disabled) { background: #16a34a; transform: scale(1.05); }
        .controles #btnDetener { background: #ef4444; color: white; }
        .controles #btnDetener:hover:not(:disabled) { background: #dc2626; transform: scale(1.05); }
        .controles button:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        @keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:0.7;transform:scale(1.05)} }
    </style>
</head>
<body>
    <div class="cronometro-box">
        <div class="cronometro-header">
            <h1><i class="fas fa-clock me-2"></i>Cronómetro - <?= htmlspecialchars($torneo['nombre'] ?? 'Ronda') ?></h1>
            <div style="display:flex;gap:0.5rem;align-items:center;">
                <a href="<?= htmlspecialchars($url_panel) ?>" class="btn-retornar" title="Vuelve al panel - el cronómetro sigue corriendo"
                   onclick="window.location.href=this.href;return false;">
                    <i class="fas fa-arrow-left"></i> Retornar al Panel
                </a>
                <button type="button" onclick="toggleConfig()" class="btn-config"><i class="fas fa-cog me-1"></i>Configurar</button>
            </div>
        </div>
        
        <div id="configPanel" style="display:none">
            <div class="config-grid">
                <div>
                    <label>Minutos</label>
                    <input type="number" id="configMinutos" min="1" max="99" value="35">
                </div>
                <div>
                    <label>Segundos</label>
                    <input type="number" id="configSegundos" min="0" max="59" value="0">
                </div>
            </div>
            <button type="button" onclick="aplicarConfiguracion()" class="btn-aplicar"><i class="fas fa-check me-1"></i>APLICAR</button>
        </div>
        
        <div class="display-area">
            <div id="tiempoDisplay">35:00</div>
            <div id="estadoDisplay"><i class="fas fa-pause-circle me-1"></i>DETENIDO</div>
            <div class="controles">
                <button id="btnIniciar" onclick="iniciarCronometro()" title="Iniciar"><i class="fas fa-play"></i></button>
                <button id="btnDetener" onclick="detenerCronometro()" title="Detener" disabled><i class="fas fa-stop"></i></button>
            </div>
        </div>
    </div>
    
    <script>
    const TORNEO_ID = <?= (int)$torneo_id ?>;
    const LS_KEY = 'cronometro_activo_' + TORNEO_ID;
    let tiempoRestante = 35 * 60;
    let tiempoOriginal = 35 * 60;
    let cronometroInterval = null;
    let estaCorriendo = false;
    let alarmaReproducida = false;
    let alarmaRepetida = false;
    
    function formatearTiempo(s) {
        const m = Math.floor(s / 60), segs = s % 60;
        return String(m).padStart(2,'0') + ':' + String(segs).padStart(2,'0');
    }
    
    function actualizarDisplay() {
        const d = document.getElementById('tiempoDisplay');
        const e = document.getElementById('estadoDisplay');
        d.textContent = formatearTiempo(tiempoRestante);
        d.style.color = tiempoRestante <= 30 ? '#ef4444' : tiempoRestante <= 60 ? '#fbbf24' : 'white';
        d.style.animation = tiempoRestante <= 30 ? 'pulse 1s infinite' : 'none';
        e.innerHTML = estaCorriendo ? '<i class="fas fa-play-circle me-1"></i>EN EJECUCIÓN' : '<i class="fas fa-pause-circle me-1"></i>DETENIDO';
        e.style.color = estaCorriendo ? '#86efac' : 'rgba(255,255,255,0.9)';
    }
    
    function reproducirAlarmaTsunami() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            for (let i = 0; i < 5; i++) {
                const o = ctx.createOscillator();
                const g = ctx.createGain();
                o.connect(g); g.connect(ctx.destination);
                o.frequency.setValueAtTime(400, ctx.currentTime + i * 0.8);
                o.frequency.exponentialRampToValueAtTime(800, ctx.currentTime + i * 0.8 + 0.6);
                o.type = 'sine';
                g.gain.setValueAtTime(0, ctx.currentTime + i * 0.8);
                g.gain.linearRampToValueAtTime(0.5, ctx.currentTime + i * 0.8 + 0.1);
                g.gain.linearRampToValueAtTime(0, ctx.currentTime + i * 0.8 + 0.6);
                o.start(ctx.currentTime + i * 0.8);
                o.stop(ctx.currentTime + i * 0.8 + 0.6);
            }
        } catch (err) { if (navigator.vibrate) navigator.vibrate([300,100,300,100,300]); }
    }
    
    function reproducirAlarmaTerremoto() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            for (let i = 0; i < 3; i++) {
                const o = ctx.createOscillator();
                const g = ctx.createGain();
                o.connect(g); g.connect(ctx.destination);
                o.frequency.setValueAtTime(60, ctx.currentTime + i * 1.2);
                o.frequency.exponentialRampToValueAtTime(120, ctx.currentTime + i * 1.2 + 0.5);
                o.type = 'sawtooth';
                g.gain.setValueAtTime(0, ctx.currentTime + i * 1.2);
                g.gain.linearRampToValueAtTime(0.6, ctx.currentTime + i * 1.2 + 0.2);
                g.gain.linearRampToValueAtTime(0, ctx.currentTime + i * 1.2 + 1);
                o.start(ctx.currentTime + i * 1.2);
                o.stop(ctx.currentTime + i * 1.2 + 1);
            }
        } catch (err) { if (navigator.vibrate) navigator.vibrate([500,200,500]); }
    }
    
    function iniciarCronometro() {
        if (tiempoRestante <= 0) tiempoRestante = tiempoOriginal;
        estaCorriendo = true;
        alarmaReproducida = false;
        alarmaRepetida = false;
        localStorage.setItem(LS_KEY, '1');
        document.getElementById('btnIniciar').disabled = true;
        document.getElementById('btnDetener').disabled = false;
        cronometroInterval = setInterval(() => {
            tiempoRestante--;
            actualizarDisplay();
            if (tiempoRestante <= 0) {
                localStorage.removeItem(LS_KEY);
                detenerCronometro();
                if (!alarmaReproducida) {
                    reproducirAlarmaTsunami();
                    alarmaReproducida = true;
                    setTimeout(() => { if (!alarmaRepetida) { reproducirAlarmaTerremoto(); alarmaRepetida = true; } }, 180000);
                }
            }
        }, 1000);
        actualizarDisplay();
    }
    
    function detenerCronometro() {
        estaCorriendo = false;
        clearInterval(cronometroInterval);
        cronometroInterval = null;
        localStorage.removeItem(LS_KEY);
        document.getElementById('btnIniciar').disabled = false;
        document.getElementById('btnDetener').disabled = true;
        actualizarDisplay();
    }
    
    function toggleConfig() {
        const p = document.getElementById('configPanel');
        p.style.display = p.style.display === 'none' ? 'block' : 'none';
    }
    
    function aplicarConfiguracion() {
        const m = parseInt(document.getElementById('configMinutos').value) || 35;
        const s = parseInt(document.getElementById('configSegundos').value) || 0;
        tiempoRestante = m * 60 + s;
        tiempoOriginal = tiempoRestante;
        if (!estaCorriendo) actualizarDisplay();
        document.getElementById('configPanel').style.display = 'none';
    }
    
    actualizarDisplay();
    window.addEventListener('beforeunload', function() { localStorage.removeItem(LS_KEY); });
    </script>
</body>
</html>
