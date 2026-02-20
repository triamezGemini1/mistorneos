<?php
/**
 * Formulario público para cargar acta de resultados de mesa
 * Acceso: GET ?torneo_id=X&mesa_id=Y&ronda=Z
 * Escaneando el QR de la hoja de anotación se llega aquí con los parámetros pre-cargados.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

$torneo_id = (int)($_GET['torneo_id'] ?? 0);
$mesa_id = (int)($_GET['mesa_id'] ?? $_GET['mesa'] ?? 0);
$ronda = (int)($_GET['ronda'] ?? $_GET['partida'] ?? 0);

$error = '';
$jugadores = [];
$torneo = null;
$submit_url = '';

if ($torneo_id > 0 && $mesa_id > 0 && $ronda > 0) {
    try {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("SELECT nombre FROM tournaments WHERE id = ?");
        $stmt->execute([$torneo_id]);
        $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("
            SELECT pr.id_usuario, pr.secuencia, u.nombre
            FROM partiresul pr
            INNER JOIN usuarios u ON pr.id_usuario = u.id
            WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa = ?
            ORDER BY pr.secuencia ASC
        ");
        $stmt->execute([$torneo_id, $ronda, $mesa_id]);
        $jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($jugadores) !== 4) {
            $error = 'La mesa no tiene 4 jugadores asignados.';
            $jugadores = [];
        }
        
        $base = function_exists('app_base_url') ? app_base_url() : '/';
        $submit_url = rtrim($base, '/') . '/actions/public-score-submit';
    } catch (Exception $e) {
        $error = 'Error al cargar datos: ' . $e->getMessage();
    }
} else {
    $error = 'Faltan parámetros: torneo_id, mesa_id y ronda son requeridos.';
}

$letras = [1 => 'A', 2 => 'C', 3 => 'B', 4 => 'D'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cargar acta - Mesa <?= $mesa_id ?> Ronda <?= $ronda ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-4">
    <div class="container">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-camera me-2"></i>Cargar acta de resultados</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php elseif (!empty($jugadores)): ?>
                    <p class="text-muted mb-4">
                        <strong><?= htmlspecialchars($torneo['nombre'] ?? 'Torneo') ?></strong> — Ronda <?= $ronda ?> — Mesa <?= $mesa_id ?>
                    </p>
                    <form id="formActa" method="POST" action="<?= htmlspecialchars($submit_url) ?>" enctype="multipart/form-data">
                        <input type="hidden" name="torneo_id" value="<?= $torneo_id ?>">
                        <input type="hidden" name="mesa_id" value="<?= $mesa_id ?>">
                        <input type="hidden" name="ronda" value="<?= $ronda ?>">
                        <input type="hidden" name="origen" value="qr">
                        
                        <?php foreach ($jugadores as $i => $j): ?>
                        <div class="card mb-3">
                            <div class="card-body py-2">
                                <strong>Jugador <?= $letras[(int)$j['secuencia']] ?? $j['secuencia'] ?></strong> — <?= htmlspecialchars($j['nombre']) ?>
                                <input type="hidden" name="jugadores[<?= $i ?>][id_usuario]" value="<?= (int)$j['id_usuario'] ?>">
                                <input type="hidden" name="jugadores[<?= $i ?>][secuencia]" value="<?= (int)$j['secuencia'] ?>">
                                <div class="row g-2 mt-1">
                                    <div class="col-6">
                                        <label class="form-label small">Resultado 1</label>
                                        <input type="number" name="jugadores[<?= $i ?>][resultado1]" class="form-control form-control-sm" value="0" min="0" required>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small">Resultado 2</label>
                                        <input type="number" name="jugadores[<?= $i ?>][resultado2]" class="form-control form-control-sm" value="0" min="0" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">Foto del acta *</label>
                            <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp" required>
                            <small class="text-muted">Suba una foto del acta de la mesa (JPG, PNG, GIF o WebP)</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" id="btnSubmit">
                            <span class="btn-text">Enviar acta</span>
                        </button>
                    </form>
                    <div id="resultado" class="mt-3" style="display:none;"></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
    document.getElementById('formActa')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmit');
        const res = document.getElementById('resultado');
        btn.disabled = true;
        btn.querySelector('.btn-text').textContent = 'Enviando...';
        res.style.display = 'none';
        const fd = new FormData(this);
        try {
            const r = await fetch(this.action, { method: 'POST', body: fd });
            const data = await r.json();
            res.style.display = 'block';
            res.className = 'mt-3 alert alert-' + (data.success ? 'success' : 'danger');
            res.textContent = data.success ? (data.message || 'Acta enviada correctamente') : (data.error || 'Error');
            if (data.success) this.reset();
        } catch (err) {
            res.style.display = 'block';
            res.className = 'mt-3 alert alert-danger';
            res.textContent = 'Error de conexión: ' + err.message;
        }
        btn.disabled = false;
        btn.querySelector('.btn-text').textContent = 'Enviar acta';
    });
    </script>
</body>
</html>
