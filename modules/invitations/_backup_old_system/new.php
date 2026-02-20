<?php

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/csrf.php';
Auth::requireRole(['admin_general','admin_torneo','admin_club']);
$torneos = DB::pdo()->query("SELECT id,nombre FROM tournaments WHERE estatus=1 ORDER BY fechator DESC")->fetchAll();
$clubs = DB::pdo()->query("SELECT id,nombre FROM clubes WHERE estatus=1 ORDER BY nombre")->fetchAll();
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Nueva invitación</title>
<style>body{font-family:system-ui;padding:1rem;background:#0f0f10;color:#f8fafc} input,select{padding:.6rem;border-radius:8px;border:1px solid #334155;background:#0b0b0c;color:#f8fafc}form{display:grid;gap:.6rem}</style></head><body>
<h2>Nueva invitación</h2>
<form method="post" action="save.php">
  <?= CSRF::input(); ?>
  <select name="torneo_id" required>
    <option value="">-- Torneo --</option>
    <?php foreach($torneos as $t): ?><option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nombre']) ?></option><?php endforeach; ?>
  </select>
  <select name="club_id" required>
    <option value="">-- Club --</option>
    <?php foreach($clubs as $c): ?><option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option><?php endforeach; ?>
  </select>
  <label>Ventana de acceso</label>
  <input type="date" name="acceso1" required>
  <input type="date" name="acceso2" required>
  <button>Crear</button>
</form>
<p><a href="list.php">Volver</a></p>
</body></html>

