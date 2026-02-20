<?php

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
Auth::requireRole(['admin_general','admin_torneo','admin_club']);
$id = (int)($_GET['id'] ?? 0);
$stmt = DB::pdo()->prepare("UPDATE invitations SET estado='cancelada' WHERE id=:id");
$stmt->execute([':id'=>$id]);
header('Location: list.php');

