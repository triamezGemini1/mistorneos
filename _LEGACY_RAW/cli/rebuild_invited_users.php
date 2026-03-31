<?php
// cli/rebuild_invited_users.php
// Creates invitado{club_id} users for clubs that don't have one yet.
require_once __DIR__ . '/../vendor/autoload.php';
$pdo = DB::pdo();

$clubs = $pdo->query("SELECT id, email FROM clubes WHERE estatus=1")->fetchAll();
$created = 0;
foreach ($clubs as $c) {
  $username = 'invitado' . (int)$c['id'];
  $chk = $pdo->prepare("SELECT id FROM users WHERE username=:u");
  $chk->execute([':u'=>$username]);
  if ($chk->fetch()) { continue; }

require_once __DIR__ . '/../lib/security.php';
$hash = Security::hashPassword(Security::defaultClubPassword());
  $ins = $pdo->prepare("INSERT INTO users (username, password_hash, email, club_id, role, status, must_change_password)
                        VALUES (:u,:p,:e,:c,'admin_club',1,1)");
  $ins->execute([':u'=>$username, ':p'=>$hash, ':e'=>$c['email'] ?? null, ':c'=>$c['id']]);
  $created++;
}
echo "Created {$created} invitado users.\n";
