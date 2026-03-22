<?php

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/db.php';
Auth::requireRole(['admin_general','admin_torneo','admin_club']);
$sql = "SELECT p.*, t.nombre AS torneo_nombre, c.nombre AS club_nombre FROM payments p JOIN tournaments t ON t.id=p.torneo_id JOIN clubes c ON c.id=p.club_id ORDER BY p.created_at DESC";
$rows = DB::pdo()->query($sql)->fetchAll();
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Pagos</title>
<style>body{font-family:system-ui;padding:1rem;background:#0f0f10;color:#f8fafc}
a{color:#93c5fd} table{border-collapse:collapse;width:100%} td,th{border:1px solid #334155;padding:.5rem}</style></head><body>
<h2>Pagos</h2>
<p><a href="new.php">Registrar pago</a> | <a href="../../public/index.php">Inicio</a></p>
<table>
<tr><th>#</th><th>Torneo</th><th>Club</th><th>Monto</th><th>Método</th><th>Referencia</th><th>Estatus</th><th>Fecha</th></tr>
<?php foreach($rows as $r): ?>
<tr>
  <td><?= (int)$r['id'] ?></td>
  <td><?= htmlspecialchars($r['torneo_nombre']) ?></td>
  <td><?= htmlspecialchars($r['club_nombre']) ?></td>
  <td><?= number_format((float)$r['amount'], 2) ?></td>
  <td><?= htmlspecialchars($r['method']) ?></td>
  <td><?= htmlspecialchars($r['reference'] ?? '') ?></td>
  <td><?= htmlspecialchars($r['status']) ?></td>
  <td><?= htmlspecialchars($r['created_at']) ?></td>
</tr>
<?php endforeach; ?>
</table>
</body></html>

