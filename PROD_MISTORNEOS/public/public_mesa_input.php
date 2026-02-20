<?php
/**
 * Interfaz limpia tipo App móvil para registro de resultados de mesa
 * Acceso: GET ?t=X&m=Y&r=Z&token=HASH (hojas QR) o ?torneo_id=X&mesa_id=Y&ronda=Z (legacy)
 * Validación: SOLO id_torneo + mesa + ronda_actual (partida). No bloquear por resultados de rondas anteriores.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/SancionesHelper.php';
require_once __DIR__ . '/../lib/QrMesaTokenHelper.php';

$torneo_id = (int)($_GET['t'] ?? $_GET['torneo_id'] ?? 0);
$mesa_id = (int)($_GET['m'] ?? $_GET['mesa_id'] ?? $_GET['mesa'] ?? 0);
$ronda = (int)($_GET['r'] ?? $_GET['ronda'] ?? $_GET['partida'] ?? 0);
$token = trim((string)($_GET['token'] ?? ''));
$mostrar_debug = false;
if (!empty($_GET['debug_block'])) {
    if (file_exists(__DIR__ . '/../config/auth.php')) {
        require_once __DIR__ . '/../config/auth.php';
        $u = class_exists('Auth') ? Auth::user() : null;
        $mostrar_debug = $u && in_array($u['role'] ?? '', ['admin_general', 'admin_torneo', 'admin_club']);
    } elseif (isset($_GET['debug_key']) && class_exists('Env') && Env::has('APP_KEY')) {
        $mostrar_debug = hash_equals((string)Env::get('APP_KEY'), (string)($_GET['debug_key'] ?? ''));
    }
}

$error = '';
$jugadores = [];
$torneo = null;
$debug_info = [];
$submit_url = '';
$tarjeta_previa = [];
$mesa_cerrada = false;

// Token de seguridad: incluye ronda; si la ronda cambia, el token es diferente
$usa_formato_qr = isset($_GET['t']) || isset($_GET['m']) || isset($_GET['r']);
if ($usa_formato_qr && $token === '') {
    $error = 'Enlace inválido. Use el código QR de la hoja de anotación oficial.';
} elseif ($token !== '' && !QrMesaTokenHelper::validar($torneo_id, $mesa_id, $ronda, $token)) {
    $error = 'Enlace inválido o expirado. Use el código QR de la hoja de anotación oficial.';
}

if ($error === '' && $torneo_id > 0 && $mesa_id > 0 && $ronda > 0) {
    try {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("SELECT nombre, locked FROM tournaments WHERE id = ?");
        $stmt->execute([$torneo_id]);
        $torneo = $stmt->fetch(PDO::FETCH_ASSOC);

        $torneo_lock = (int)($torneo['locked'] ?? 0) === 1;
        $mesa_confirmada = false;
        $qr_ya_enviado = false;
        $cols_pr = $pdo->query("SHOW COLUMNS FROM partiresul")->fetchAll(PDO::FETCH_COLUMN);

        // Consulta específica: SOLO id_torneo + partida (ronda_actual) + mesa. No mezclar rondas.
        $existe_mesa_ronda = false;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM partiresul WHERE id_torneo = ? AND partida = ? AND mesa = ?");
        $stmt->execute([$torneo_id, $ronda, $mesa_id]);
        $existe_mesa_ronda = ((int)$stmt->fetchColumn()) > 0;
        $debug_info['existe_mesa_ronda'] = $existe_mesa_ronda;
        $debug_info['params'] = ['torneo_id' => $torneo_id, 'ronda' => $ronda, 'mesa' => $mesa_id];

        if (!$existe_mesa_ronda) {
            $error = 'La mesa ' . $mesa_id . ' aún no está asignada para la ronda ' . $ronda . '.';
        } else {
            if (in_array('estatus', $cols_pr)) {
                $stmt = $pdo->prepare("SELECT estatus, id FROM partiresul WHERE id_torneo = ? AND partida = ? AND mesa = ? LIMIT 1");
                $stmt->execute([$torneo_id, $ronda, $mesa_id]);
                $row_est = $stmt->fetch(PDO::FETCH_ASSOC);
                $est = $row_est ? ($row_est['estatus'] ?? null) : null;
                $mesa_confirmada = ($est === 'confirmado');
                if ($mesa_confirmada) $debug_info['bloqueo_por'] = 'estatus_confirmado';
            }
            if (in_array('origen_dato', $cols_pr)) {
                $stmt = $pdo->prepare("SELECT id, origen_dato, registrado FROM partiresul WHERE id_torneo = ? AND partida = ? AND mesa = ? AND origen_dato = 'qr' AND registrado = 1 LIMIT 1");
                $stmt->execute([$torneo_id, $ronda, $mesa_id]);
                $row_qr = $stmt->fetch(PDO::FETCH_ASSOC);
                $qr_ya_enviado = ($row_qr !== false);
                if ($qr_ya_enviado) $debug_info['bloqueo_por'] = 'qr_ya_enviado';
            }
        }

        if ($torneo_lock) {
            $mesa_cerrada = true;
            $error = 'El torneo ha finalizado.';
            $debug_info['bloqueo_por'] = 'torneo_lock';
        } elseif (!$existe_mesa_ronda) {
            $mesa_cerrada = true;
        } elseif ($mesa_confirmada) {
            $mesa_cerrada = true;
            $error = 'Esta mesa ya ha sido procesada.';
        } elseif ($qr_ya_enviado) {
            $mesa_cerrada = true;
            $error = 'Envío Completado';
        }

        if (!$mesa_cerrada) {
        $stmt = $pdo->prepare("
            SELECT pr.id_usuario, pr.secuencia, u.nombre
            FROM partiresul pr
            INNER JOIN usuarios u ON pr.id_usuario = u.id
            WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa = ?
            ORDER BY pr.secuencia ASC
        ");
        $stmt->execute([$torneo_id, $ronda, $mesa_id]);
        $jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($jugadores) === 4) {
            $ids = array_column($jugadores, 'id_usuario');
            $tarjeta_previa = SancionesHelper::getTarjetaPreviaDesdePartidasAnteriores($pdo, $torneo_id, $ronda, $ids);
        } else {
            $error = 'La mesa no tiene 4 jugadores asignados.';
            $jugadores = [];
        }

        $base = function_exists('app_base_url') ? app_base_url() : '/';
        $submit_url = rtrim($base, '/') . '/actions/public-score-submit';
        } // !mesa_cerrada
    } catch (Exception $e) {
        $error = 'Error al cargar datos: ' . $e->getMessage();
    }
} elseif ($error === '') {
    $error = 'Faltan parámetros: torneo_id, mesa_id y ronda.';
}

$letras = [1 => 'A', 2 => 'C', 3 => 'B', 4 => 'D'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Mesa <?= $mesa_id ?> — Ronda <?= $ronda ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            color: #e2e8f0;
            padding-bottom: env(safe-area-inset-bottom, 20px);
        }
        .app-header {
            background: rgba(15, 23, 42, 0.95);
            padding: 16px 20px;
            padding-top: max(16px, env(safe-area-inset-top));
            text-align: center;
        }
        .app-header h1 { font-size: 1.1rem; font-weight: 600; }
        .app-header p { font-size: 0.8rem; color: #94a3b8; margin-top: 2px; }
        .content { padding: 16px 20px; }
        .player-card {
            background: rgba(30, 41, 59, 0.8);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
        }
        .player-label {
            font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .player-name {
            font-size: 1rem; font-weight: 600; margin: 4px 0 12px 0;
        }
        .player-name.has-prev-card { color: #fbbf24; }
        .player-name.has-prev-card::after {
            content: ' ⚠';
            font-size: 0.9em;
        }
        .row-inputs { display: flex; gap: 12px; margin-bottom: 12px; }
        .row-inputs > * { flex: 1; }
        .input-group label {
            display: block;
            font-size: 0.7rem;
            color: #64748b;
            margin-bottom: 4px;
        }
        input[type="number"] {
            width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid #334155;
            background: #0f172a;
            color: #e2e8f0;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .sancion-btns {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }
        .sancion-btn {
            flex: 1;
            padding: 10px 8px;
            border-radius: 10px;
            border: 2px solid #334155;
            background: #0f172a;
            color: #94a3b8;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        .sancion-btn.active { border-color: #3b82f6; background: #1e3a5f; color: #93c5fd; }
        .sancion-btn.s40.active { border-color: #eab308; background: #422006; color: #fde047; }
        .sancion-btn.s80.active { border-color: #ef4444; background: #450a0a; color: #fca5a5; }
        .sancion-btn:active { transform: scale(0.97); }
        .camera-section {
            margin-top: 24px;
            text-align: center;
        }
        .camera-hint {
            font-size: 0.8rem;
            color: #94a3b8;
            margin-bottom: 12px;
            line-height: 1.4;
            padding: 0 8px;
        }
        .camera-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            max-width: 280px;
            padding: 18px 24px;
            border-radius: 16px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(59, 130, 246, 0.4);
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .camera-btn:active { transform: scale(0.98); }
        .camera-btn svg { width: 28px; height: 28px; }
        input[type="file"] { display: none; }
        .submit-btn {
            width: 100%;
            padding: 18px;
            margin-top: 20px;
            border-radius: 16px;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(34, 197, 94, 0.4);
            transition: transform 0.15s;
        }
        .submit-btn:active { transform: scale(0.98); }
        .submit-btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .preview-img {
            max-width: 100%;
            max-height: 120px;
            border-radius: 12px;
            margin-top: 12px;
            border: 2px solid #334155;
        }
        .result-msg {
            margin-top: 16px;
            padding: 14px;
            border-radius: 12px;
            font-weight: 500;
        }
        .result-msg.success { background: #14532d; color: #86efac; }
        .result-msg.error { background: #450a0a; color: #fca5a5; }
        .legend {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 20px;
            padding: 12px;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 12px;
            line-height: 1.5;
        }
        .legend strong { color: #94a3b8; }
    </style>
</head>
<body>
    <header class="app-header">
        <h1><?= htmlspecialchars($torneo['nombre'] ?? 'Torneo') ?></h1>
        <p>Ronda <?= $ronda ?> — Mesa <?= $mesa_id ?></p>
    </header>

    <main class="content">
        <?php if ($error): ?>
            <div class="result-msg error"><?= htmlspecialchars($error) ?></div>
            <?php if ($mostrar_debug && !empty($debug_info)): ?>
                <div class="result-msg" style="background:#1e293b;color:#94a3b8;margin-top:12px;font-size:0.8rem;">
                    <strong>Debug (solo admin):</strong><br>
                    Parámetros: torneo=<?= (int)($debug_info['params']['torneo_id'] ?? 0) ?>, ronda=<?= (int)($debug_info['params']['ronda'] ?? 0) ?>, mesa=<?= (int)($debug_info['params']['mesa'] ?? 0) ?><br>
                    Existe mesa en ronda: <?= !empty($debug_info['existe_mesa_ronda']) ? 'Sí' : 'No' ?><br>
                    <?php if (!empty($debug_info['bloqueo_por'])): ?>Causa bloqueo: <?= htmlspecialchars($debug_info['bloqueo_por']) ?><?php endif; ?>
                </div>
            <?php endif; ?>
        <?php elseif (!empty($jugadores)): ?>
            <form id="formMesa" action="<?= htmlspecialchars($submit_url) ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="torneo_id" value="<?= $torneo_id ?>">
                <input type="hidden" name="mesa_id" value="<?= $mesa_id ?>">
                <input type="hidden" name="ronda" value="<?= $ronda ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <input type="hidden" name="origen" value="qr">

                <?php foreach ($jugadores as $i => $j):
                    $tp = (int)($tarjeta_previa[(int)$j['id_usuario']] ?? 0);
                    $tienePrevia = $tp >= SancionesHelper::TARJETA_AMARILLA;
                ?>
                <div class="player-card">
                    <span class="player-label">Jugador <?= $letras[(int)$j['secuencia']] ?? $j['secuencia'] ?></span>
                    <div class="player-name <?= $tienePrevia ? 'has-prev-card' : '' ?>" title="<?= $tienePrevia ? 'Tiene tarjeta previa. 80 pts = siguiente tarjeta.' : '' ?>">
                        <?= htmlspecialchars($j['nombre']) ?>
                    </div>
                    <input type="hidden" name="jugadores[<?= $i ?>][id_usuario]" value="<?= (int)$j['id_usuario'] ?>">
                    <input type="hidden" name="jugadores[<?= $i ?>][secuencia]" value="<?= (int)$j['secuencia'] ?>">

                    <div class="row-inputs">
                        <div class="input-group">
                            <label>Resultado 1</label>
                            <input type="number" name="jugadores[<?= $i ?>][resultado1]" value="0" min="0" required>
                        </div>
                        <div class="input-group">
                            <label>Resultado 2</label>
                            <input type="number" name="jugadores[<?= $i ?>][resultado2]" value="0" min="0" required>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Sanción</label>
                        <div class="sancion-btns" role="group">
                            <button type="button" class="sancion-btn active" data-val="0" data-idx="<?= $i ?>">Ninguna</button>
                            <button type="button" class="sancion-btn s40" data-val="40" data-idx="<?= $i ?>">40 pts</button>
                            <button type="button" class="sancion-btn s80" data-val="80" data-idx="<?= $i ?>">80 pts</button>
                        </div>
                        <input type="hidden" name="jugadores[<?= $i ?>][sancion]" id="sancion_<?= $i ?>" value="0">
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="camera-section">
                    <p class="camera-hint">Toma la foto de frente, con buena luz y asegúrate de que se vean las firmas.</p>
                    <label for="imgActa" class="camera-btn">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 13v7a2 2 0 01-2 2H7a2 2 0 01-2-2v-7"/>
                        </svg>
                        Tomar foto del acta
                    </label>
                    <input type="file" id="imgActa" name="image" accept="image/*" capture="camera" required>
                    <div id="previewWrap" style="display:none;">
                        <img id="previewImg" class="preview-img" alt="Vista previa">
                    </div>
                </div>

                <button type="submit" class="submit-btn" id="btnSubmit">
                    <span class="btn-text">Enviar resultados</span>
                </button>
            </form>

            <div class="legend">
                <strong>Sanción 40 pts:</strong> Amarilla administrativa (no resta puntos).<br>
                <strong>Sanción 80 pts:</strong> Si no tiene tarjeta previa → Amarilla (resta puntos). Si ya tiene → Roja/Negra.
            </div>
        <?php endif; ?>

        <div id="resultado" class="result-msg" style="display:none;"></div>
    </main>

    <script>
    (function() {
        document.querySelectorAll('.sancion-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const idx = this.getAttribute('data-idx');
                const val = this.getAttribute('data-val');
                const group = this.closest('.sancion-btns');
                group.querySelectorAll('.sancion-btn').forEach(function(b) { b.classList.remove('active'); });
                this.classList.add('active');
                document.getElementById('sancion_' + idx).value = val;
            });
        });

        var imgInput = document.getElementById('imgActa');
        if (imgInput) {
            imgInput.addEventListener('change', function() {
                var f = this.files[0];
                if (f && f.type.startsWith('image/')) {
                    var r = new FileReader();
                    r.onload = function() {
                        document.getElementById('previewImg').src = r.result;
                        document.getElementById('previewWrap').style.display = 'block';
                    };
                    r.readAsDataURL(f);
                }
            });
        }

        document.getElementById('formMesa')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            var btn = document.getElementById('btnSubmit');
            var res = document.getElementById('resultado');
            btn.disabled = true;
            btn.querySelector('.btn-text').textContent = 'Enviando...';
            res.style.display = 'none';
            try {
                var r = await fetch(this.action, { method: 'POST', body: new FormData(this) });
                var data = await r.json();
                res.style.display = 'block';
                res.className = 'result-msg ' + (data.success ? 'success' : 'error');
                res.textContent = data.success ? (data.message || 'Enviado correctamente') : (data.error || 'Error');
                if (data.success) this.reset();
            } catch (err) {
                res.style.display = 'block';
                res.className = 'result-msg error';
                res.textContent = 'Error de conexión';
            }
            btn.disabled = false;
            btn.querySelector('.btn-text').textContent = 'Enviar resultados';
        });
    })();
    </script>
</body>
</html>
