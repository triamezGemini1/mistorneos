<?php

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/db.php';
Auth::requireRole(['admin_general','admin_torneo','admin_club']);
$sql = "SELECT i.*, t.nombre AS torneo_nombre, c.nombre AS club_nombre FROM invitations i JOIN tournaments t ON t.id=i.torneo_id JOIN clubes c ON c.id=i.club_id ORDER BY i.fecha_creacion DESC";
$rows = DB::pdo()->query($sql)->fetchAll();
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Invitaciones</title>
<style>body{font-family:system-ui;padding:1rem;background:#0f0f10;color:#f8fafc}
a{color:#93c5fd} table{border-collapse:collapse;width:100%} td,th{border:1px solid #334155;padding:.5rem}</style></head><body>
<h2>Invitaciones</h2>
<p><a href="new.php">Nueva invitación</a> | <a href="../../public/index.php">Inicio</a></p>
<table>
<tr><th>#</th><th>Torneo</th><th>Club</th><th>Desde</th><th>Hasta</th><th>Usuario</th><th>Estado</th><th>Acciones</th></tr>
<?php foreach($rows as $r): ?>
<tr>
  <td><?= (int)$r['id'] ?></td>
  <td><?= htmlspecialchars($r['torneo_nombre']) ?></td>
  <td><?= htmlspecialchars($r['club_nombre']) ?></td>
  <td><?= htmlspecialchars($r['acceso1']) ?></td>
  <td><?= htmlspecialchars($r['acceso2']) ?></td>
  <td><?= htmlspecialchars($r['usuario']) ?></td>
  <td><?= htmlspecialchars($r['estado']) ?></td>
  <td><a href="revoke.php?id=<?= (int)$r['id'] ?>" onclick="return confirm('¿Revocar invitación?')">Revocar</a></td>
</tr>
<?php endforeach; ?>
</table>
</body></html>

