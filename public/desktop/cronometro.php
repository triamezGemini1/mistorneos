<?php
declare(strict_types=1);
require_once __DIR__ . '/desktop_auth.php';
require_once __DIR__ . '/db_local.php';
$torneo_id = isset($_GET['torneo_id']) ? (int)$_GET['torneo_id'] : 0;
$ronda = isset($_GET['ronda']) ? (int)$_GET['ronda'] : 1;
$torneo = ['id' => 0, 'nombre' => 'Torneo', 'tiempo' => 35];
if ($torneo_id > 0) {
    $stmt = DB_Local::pdo()->prepare("SELECT id, nombre, COALESCE(tiempo, 35) AS tiempo FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $torneo = $row;
}
$minutos = (int)($torneo['tiempo'] ?? 35);
if ($minutos < 1) $minutos = 35;
$url_panel = 'panel_torneo.php?torneo_id=' . $torneo_id;
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
        body { font-family: 'Segoe UI', system-ui, sans-serif; min-height: 100vh; background: linear-gradient(135deg, #1e1b4b 0%, #4c1d95 50%, #831843 100%); color: white; display: flex; align-items: center; justify-content: center; }
        .cronometro-box { width: 90%; max-width: 505px; background: rgba(0,0,0,0.3); border-radius: 1rem; padding: 1.5rem; box-shadow: 0 20px 40px rgba(0,0,0,0.4); }
        .cronometro-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .cronometro-header h1 { font-size: 1.25rem; font-weight: 700; }
        .btn-retornar { background: #3b82f6; color: white; padding: 0.5rem 1rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-retornar:hover { color: white; background: #2563eb; }
        #tiempoDisplay { font-family: 'Arial Black', sans-serif; font-size: clamp(3rem, 12vw, 6rem); font-weight: 900; text-align: center; text-shadow: 0 4px 20px rgba(0,0,0,0.5); }
        #estadoDisplay { text-align: center; margin-top: 0.5rem; opacity: 0.9; }
        .controles { display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem; }
        .controles button { padding: 0.75rem 1.5rem; border-radius: 0.5rem; border: none; font-size: 1.25rem; cursor: pointer; }
        .controles #btnIniciar { background: #22c55e; color: white; }
        .controles #btnDetener { background: #ef4444; color: white; }
        .controles button:disabled { opacity: 0.5; cursor: not-allowed; }
    </style>
</head>
<body>
    <div class="cronometro-box">
        <div class="cronometro-header">
            <h1><i class="fas fa-clock me-2"></i>Cronómetro - <?= htmlspecialchars($torneo['nombre'] ?? 'Ronda') ?></h1>
            <a href="<?= htmlspecialchars($url_panel) ?>" class="btn-retornar"><i class="fas fa-arrow-left"></i> Retornar al Panel</a>
        </div>
        <div id="tiempoDisplay"><?= str_pad((string)$minutos, 2, '0', STR_PAD_LEFT) ?>:00</div>
        <div id="estadoDisplay"><i class="fas fa-pause-circle me-1"></i>DETENIDO</div>
        <div class="controles">
            <button id="btnIniciar" onclick="iniciar()"><i class="fas fa-play"></i></button>
            <button id="btnDetener" onclick="detener()" disabled><i class="fas fa-stop"></i></button>
        </div>
    </div>
    <script>
    var minutosTorneo = <?= $minutos ?>;
    var tiempoRestante = minutosTorneo * 60, tiempoOriginal = minutosTorneo * 60, interval = null, estaCorriendo = false;
    function fmt(s) { var m = Math.floor(s/60), se = s % 60; return String(m).padStart(2,'0') + ':' + String(se).padStart(2,'0'); }
    function actualizar() {
        document.getElementById('tiempoDisplay').textContent = fmt(tiempoRestante);
        document.getElementById('tiempoDisplay').style.color = tiempoRestante <= 30 ? '#ef4444' : tiempoRestante <= 60 ? '#fbbf24' : 'white';
        document.getElementById('estadoDisplay').innerHTML = estaCorriendo ? '<i class="fas fa-play-circle me-1"></i>EN EJECUCIÓN' : '<i class="fas fa-pause-circle me-1"></i>DETENIDO';
    }
    function iniciar() {
        if (tiempoRestante <= 0) tiempoRestante = tiempoOriginal;
        estaCorriendo = true;
        document.getElementById('btnIniciar').disabled = true;
        document.getElementById('btnDetener').disabled = false;
        interval = setInterval(function() { tiempoRestante--; actualizar(); if (tiempoRestante <= 0) { clearInterval(interval); estaCorriendo = false; document.getElementById('btnIniciar').disabled = false; document.getElementById('btnDetener').disabled = true; } }, 1000);
        actualizar();
    }
    function detener() {
        estaCorriendo = false;
        clearInterval(interval);
        document.getElementById('btnIniciar').disabled = false;
        document.getElementById('btnDetener').disabled = true;
        actualizar();
    }
    actualizar();
    </script>
</body>
</html>
