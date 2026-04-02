<?php
/**
 * Cronómetro de sala: ventana aparte, sin layout de la app ni retorno.
 * Acceso solo con URL firmada (generada desde el panel). Lee bannerclock como el cronómetro interno.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/db_config.php';

$t = (int) ($_GET['t'] ?? 0);
$e = (int) ($_GET['e'] ?? 0);
$s = (string) ($_GET['s'] ?? '');

require_once __DIR__ . '/../lib/CronometroPublicoToken.php';
if (!CronometroPublicoToken::validate($t, $e, $s)) {
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Acceso no disponible</title></head><body style="font-family:sans-serif;padding:2rem;">Enlace no válido o caducado.</body></html>';
    exit;
}

$torneo_id = $t;
$stmt = DB::pdo()->prepare(
    'SELECT id, nombre, owner_user_id, club_responsable FROM tournaments WHERE id = ? LIMIT 1'
);
$stmt->execute([$torneo_id]);
$torneo = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$torneo) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>No encontrado</title></head><body style="font-family:sans-serif;padding:2rem;">Torneo no encontrado.</body></html>';
    exit;
}

$banners_cronometro = [];
try {
    $nivel_organizador = (int) ($torneo['owner_user_id'] ?? 0);
    if ($nivel_organizador <= 0 && !empty($torneo['club_responsable'])) {
        $stmtOrg = DB::pdo()->prepare('SELECT admin_user_id FROM organizaciones WHERE id = ? LIMIT 1');
        $stmtOrg->execute([(int) $torneo['club_responsable']]);
        $nivel_organizador = (int) $stmtOrg->fetchColumn();
    }

    $niveles = [0];
    if ($nivel_organizador > 0) {
        $niveles[] = $nivel_organizador;
    }
    $niveles = array_values(array_unique($niveles));
    $ph_niveles = implode(',', array_fill(0, count($niveles), '?'));

    $sqlBanners = "
        SELECT contenido
        FROM bannerclock
        WHERE estatus = 1
          AND selector = 0
          AND nivel IN ($ph_niveles)
        ORDER BY
          CASE WHEN nivel = 0 THEN 0 ELSE 1 END ASC,
          id DESC
    ";
    $stmtBanners = DB::pdo()->prepare($sqlBanners);
    $stmtBanners->execute($niveles);
    $rows = $stmtBanners->fetchAll(PDO::FETCH_COLUMN);
    foreach ($rows as $texto) {
        $texto = trim((string) $texto);
        if ($texto !== '') {
            $banners_cronometro[] = $texto;
        }
    }
} catch (Throwable $ex) {
    // continuar con fallback
}

if ($banners_cronometro === []) {
    $banners_cronometro[] = 'Mesa técnica activa — Mantenga el orden de juego';
}

$titulo_esc = htmlspecialchars((string) ($torneo['nombre'] ?? 'Ronda'), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?= $titulo_esc ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; overflow: hidden; }
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: radial-gradient(ellipse at 30% 20%, #1a2332 0%, #0b0f14 55%, #050708 100%);
            color: #e2e8f0;
        }
        .btn-cerrar-ventana {
            position: fixed;
            top: 10px;
            right: 12px;
            z-index: 5000;
            background: rgba(30, 41, 59, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.35);
            color: #e2e8f0;
            padding: 0.4rem 0.75rem;
            border-radius: 0.35rem;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-cerrar-ventana:hover { background: rgba(51, 65, 85, 0.95); }
        #cronFloatingRoot {
            position: fixed;
            z-index: 100;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: min(92vw, 820px);
            max-height: calc(100vh - 100px);
            overflow: visible;
        }
        .cron-drag-handle {
            cursor: move;
            user-select: none;
            padding: 0.35rem 0.5rem 0.85rem;
            margin: 0 0 1rem;
            border-bottom: 1px solid rgba(148, 163, 184, 0.22);
            -webkit-user-select: none;
        }
        .cron-drag-handle h1 {
            font-size: clamp(1.1rem, 3.5vw, 1.45rem);
            font-weight: 800;
            text-align: center;
            line-height: 1.25;
            color: #f8fafc;
        }
        .cron-banner {
            width: 100%;
            margin-bottom: 1rem;
            background: rgba(45, 212, 191, 0.1);
            border: 1px solid rgba(45, 212, 191, 0.45);
            border-radius: 12px;
            padding: 0.5rem 0.7rem;
            font-weight: 700;
            font-size: clamp(1rem, 3.2vw, 1.85rem);
            line-height: 1.2;
            letter-spacing: 0.02em;
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.35);
        }
        .cron-banner-wrap {
            display: grid;
            grid-template-columns: 42px 1fr 42px;
            align-items: center;
            gap: 0.35rem;
        }
        .cron-banner-text {
            min-height: 1.6rem;
            padding: 0.2rem 0.4rem;
            overflow: hidden;
            position: relative;
            white-space: nowrap;
        }
        .cron-banner-track {
            display: inline-flex;
            align-items: center;
            gap: 2.5rem;
            width: max-content;
            will-change: transform;
            animation: marqueeBanner 28s linear infinite;
        }
        .cron-banner-item { display: inline-block; white-space: nowrap; }
        .cron-banner-btn {
            border: none;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.35);
            color: #fff;
            height: 34px;
            cursor: pointer;
            font-size: 1.1rem;
            line-height: 1;
            transition: background 0.15s ease;
        }
        .cron-banner-btn:hover { background: rgba(0, 0, 0, 0.55); }
        .cron-banner-dots {
            margin-top: 0.4rem;
            display: flex;
            justify-content: center;
            gap: 0.4rem;
        }
        .cron-banner-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.35);
            cursor: pointer;
        }
        .cron-banner-dot.is-active { background: #5eead4; }
        @keyframes marqueeBanner {
            0% { transform: translateX(0%); }
            100% { transform: translateX(-50%); }
        }
        .cronometro-box {
            width: 100%;
            background: rgba(15, 23, 42, 0.55);
            border-radius: 1.5rem;
            padding: 1.25rem 1.5rem 1.75rem;
            box-shadow: 0 25px 50px rgba(0,0,0,0.45);
            border: 1px solid rgba(71, 85, 105, 0.35);
        }
        .display-area { text-align: center; padding: 1rem 0 0.5rem; }
        #tiempoDisplay {
            font-family: ui-monospace, 'Cascadia Code', monospace;
            font-size: clamp(3.2rem, 14vw, 10rem);
            font-weight: 900;
            line-height: 1.1;
            text-shadow: 0 4px 24px rgba(0,0,0,0.55);
            letter-spacing: 0.04em;
        }
        #estadoDisplay {
            font-size: clamp(1rem, 2.8vw, 1.45rem);
            opacity: 0.95;
            margin-top: 0.5rem;
            font-weight: 700;
        }
        .controles {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }
        .controles button {
            padding: 0.85rem 1.35rem;
            border-radius: 0.75rem;
            border: none;
            font-size: clamp(1rem, 2.5vw, 1.45rem);
            font-weight: 800;
            cursor: pointer;
            transition: transform 0.15s ease, filter 0.15s ease;
            min-width: 7rem;
        }
        .controles #btnIniciar { background: #059669; color: white; }
        .controles #btnIniciar:hover:not(:disabled) { filter: brightness(1.08); transform: scale(1.04); }
        .controles #btnDetener { background: #dc2626; color: white; }
        .controles #btnDetener:hover:not(:disabled) { filter: brightness(1.08); transform: scale(1.04); }
        .controles button:disabled { opacity: 0.45; cursor: not-allowed; transform: none; }
        @keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:0.75;transform:scale(1.04)} }
        .config-dock {
            position: fixed;
            left: 0;
            bottom: 0;
            z-index: 3000;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            padding: 10px 12px 12px;
            max-width: min(100vw, 400px);
            pointer-events: none;
        }
        .config-dock > * { pointer-events: auto; }
        #configPanel {
            background: rgba(15, 23, 42, 0.95);
            border: 1px solid rgba(100, 116, 139, 0.4);
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 8px;
            display: none;
            width: 100%;
            box-shadow: 0 -4px 24px rgba(0,0,0,0.4);
        }
        .config-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        .config-grid label { display: block; font-weight: 700; margin-bottom: 0.35rem; font-size: 0.9rem; color: #cbd5e1; }
        .config-grid input {
            width: 100%;
            padding: 0.5rem;
            border-radius: 0.4rem;
            font-size: 1.2rem;
            font-weight: 700;
            text-align: center;
            background: rgba(30, 41, 59, 0.9);
            color: #f1f5f9;
            border: 2px solid rgba(71, 85, 105, 0.6);
        }
        .config-grid input:focus { outline: none; border-color: #2dd4bf; }
        #configPanel .btn-aplicar {
            width: 100%;
            background: #059669;
            color: white;
            border: none;
            padding: 0.6rem;
            border-radius: 0.45rem;
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
        }
        #configPanel .btn-aplicar:hover { filter: brightness(1.1); }
        .btn-config {
            background: rgba(15, 23, 42, 0.95);
            color: #e2e8f0;
            border: 1px solid rgba(100, 116, 139, 0.5);
            padding: 0.55rem 1rem;
            border-radius: 0.45rem;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.9rem;
        }
        .btn-config:hover { background: rgba(30, 41, 59, 0.98); }
    </style>
</head>
<body>

<button type="button" class="btn-cerrar-ventana" onclick="tryCerrar()" title="Cierra solo esta ventana">Cerrar ventana</button>

<div id="cronFloatingRoot">
    <div class="cron-banner" id="cronBanner">
        <div class="cron-banner-wrap">
            <button type="button" class="cron-banner-btn" id="btnBannerPrev" title="Anterior" aria-label="Anterior">‹</button>
            <div class="cron-banner-text" id="cronBannerTexto"></div>
            <button type="button" class="cron-banner-btn" id="btnBannerNext" title="Siguiente" aria-label="Siguiente">›</button>
        </div>
        <div class="cron-banner-dots" id="cronBannerDots"></div>
    </div>

    <div class="cronometro-box">
        <div class="cron-drag-handle" id="cronDragHandle" title="Arrastrar">
            <h1><?= $titulo_esc ?></h1>
        </div>
        <div class="display-area">
            <div id="tiempoDisplay">35:00</div>
            <div id="estadoDisplay">DETENIDO</div>
            <div class="controles">
                <button type="button" id="btnIniciar" onclick="iniciarCronometro()" title="Iniciar">Iniciar</button>
                <button type="button" id="btnDetener" onclick="detenerCronometro()" title="Detener" disabled>Detener</button>
            </div>
        </div>
    </div>
</div>

<div class="config-dock" id="configDock" aria-label="Configuración del tiempo">
    <div id="configPanel">
        <div class="config-grid">
            <div>
                <label for="configMinutos">Minutos</label>
                <input type="number" id="configMinutos" min="1" max="99" value="35">
            </div>
            <div>
                <label for="configSegundos">Segundos</label>
                <input type="number" id="configSegundos" min="0" max="59" value="0">
            </div>
        </div>
        <button type="button" onclick="aplicarConfiguracion()" class="btn-aplicar">Aplicar</button>
    </div>
    <button type="button" onclick="toggleConfig()" class="btn-config">Configurar</button>
</div>

<script>
const TORNEO_ID = <?= (int) $torneo_id ?>;
const BANNERS = <?= json_encode(array_values($banners_cronometro), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const LS_KEY = 'cron_public_activo_' + TORNEO_ID;
let tiempoRestante = 35 * 60;
let tiempoOriginal = 35 * 60;
let cronometroInterval = null;
let bannerIndex = 0;
let bannerTimer = null;
let estaCorriendo = false;
let alarmaReproducida = false;
let alarmaRepetida = false;

function tryCerrar() {
    window.close();
}

function initCronDrag() {
    const root = document.getElementById('cronFloatingRoot');
    const handle = document.getElementById('cronDragHandle');
    if (!root || !handle) return;
    let dragging = false;
    let startX = 0, startY = 0, origLeft = 0, origTop = 0;
    function snapToPixels() {
        const rect = root.getBoundingClientRect();
        root.style.transform = 'none';
        root.style.left = rect.left + 'px';
        root.style.top = rect.top + 'px';
        root.style.right = 'auto';
        root.style.margin = '0';
    }
    handle.addEventListener('mousedown', function(e) {
        if (e.button !== 0) return;
        e.preventDefault();
        snapToPixels();
        const rect = root.getBoundingClientRect();
        origLeft = rect.left;
        origTop = rect.top;
        startX = e.clientX;
        startY = e.clientY;
        dragging = true;
    });
    document.addEventListener('mousemove', function(e) {
        if (!dragging) return;
        const dx = e.clientX - startX;
        const dy = e.clientY - startY;
        const w = root.offsetWidth;
        const h = root.offsetHeight;
        let nl = origLeft + dx;
        let nt = origTop + dy;
        nl = Math.max(0, Math.min(nl, window.innerWidth - Math.min(w, window.innerWidth)));
        nt = Math.max(0, Math.min(nt, window.innerHeight - Math.min(h, window.innerHeight)));
        root.style.left = nl + 'px';
        root.style.top = nt + 'px';
    });
    document.addEventListener('mouseup', function() { dragging = false; });
}

function formatearTiempo(s) {
    const m = Math.floor(s / 60), segs = s % 60;
    return String(m).padStart(2,'0') + ':' + String(segs).padStart(2,'0');
}

function actualizarDisplay() {
    const d = document.getElementById('tiempoDisplay');
    const e = document.getElementById('estadoDisplay');
    d.textContent = formatearTiempo(tiempoRestante);
    d.style.color = tiempoRestante <= 30 ? '#f87171' : tiempoRestante <= 60 ? '#fbbf24' : '#f8fafc';
    d.style.animation = tiempoRestante <= 30 ? 'pulse 1s infinite' : 'none';
    e.textContent = estaCorriendo ? 'EN EJECUCIÓN' : 'DETENIDO';
    e.style.color = estaCorriendo ? '#6ee7b7' : 'rgba(226,232,240,0.95)';
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
    p.style.display = p.style.display === 'none' || p.style.display === '' ? 'block' : 'none';
}

function aplicarConfiguracion() {
    const m = parseInt(document.getElementById('configMinutos').value, 10) || 35;
    const s = parseInt(document.getElementById('configSegundos').value, 10) || 0;
    tiempoRestante = m * 60 + s;
    tiempoOriginal = tiempoRestante;
    if (!estaCorriendo) actualizarDisplay();
    document.getElementById('configPanel').style.display = 'none';
}

function renderBanner() {
    const texto = document.getElementById('cronBannerTexto');
    const dotsWrap = document.getElementById('cronBannerDots');
    if (!texto || !Array.isArray(BANNERS) || BANNERS.length === 0) return;
    const value = String(BANNERS[bannerIndex] || '').trim();
    const esc = (v) => v.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const animSeconds = Math.max(16, Math.min(44, Math.ceil(value.length / 3)));
    texto.innerHTML = '<div class="cron-banner-track">' +
        '<span class="cron-banner-item">' + esc(value) + '</span>' +
        '<span class="cron-banner-item clone">' + esc(value) + '</span>' +
        '</div>';
    const track = texto.querySelector('.cron-banner-track');
    if (track) track.style.animationDuration = animSeconds + 's';
    if (dotsWrap) {
        dotsWrap.querySelectorAll('.cron-banner-dot').forEach((dot, idx) => {
            dot.classList.toggle('is-active', idx === bannerIndex);
        });
    }
}

function moveBanner(step) {
    if (!Array.isArray(BANNERS) || BANNERS.length <= 1) return;
    bannerIndex = (bannerIndex + step + BANNERS.length) % BANNERS.length;
    renderBanner();
}

function startBannerRotation() {
    if (!Array.isArray(BANNERS) || BANNERS.length <= 1) return;
    if (bannerTimer) clearInterval(bannerTimer);
    bannerTimer = setInterval(() => moveBanner(1), 10000);
}

function initBanner() {
    const btnPrev = document.getElementById('btnBannerPrev');
    const btnNext = document.getElementById('btnBannerNext');
    const bannerBox = document.getElementById('cronBanner');
    const dotsWrap = document.getElementById('cronBannerDots');
    if (!Array.isArray(BANNERS) || BANNERS.length === 0) return;
    if (dotsWrap) {
        dotsWrap.innerHTML = '';
        BANNERS.forEach((_, idx) => {
            const dot = document.createElement('span');
            dot.className = 'cron-banner-dot' + (idx === 0 ? ' is-active' : '');
            dot.addEventListener('click', () => { bannerIndex = idx; renderBanner(); });
            dotsWrap.appendChild(dot);
        });
    }
    if (btnPrev) btnPrev.addEventListener('click', () => moveBanner(-1));
    if (btnNext) btnNext.addEventListener('click', () => moveBanner(1));
    renderBanner();
    startBannerRotation();
    if (bannerBox) {
        bannerBox.addEventListener('mouseenter', () => { if (bannerTimer) clearInterval(bannerTimer); });
        bannerBox.addEventListener('mouseleave', () => startBannerRotation());
    }
}

initCronDrag();
initBanner();
actualizarDisplay();
window.addEventListener('beforeunload', function() { localStorage.removeItem(LS_KEY); });
</script>
</body>
</html>
