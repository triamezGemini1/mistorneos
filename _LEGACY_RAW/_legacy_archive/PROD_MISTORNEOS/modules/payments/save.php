<?php

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../lib/validation.php';
Auth::requireRole(['admin_general','admin_torneo','admin_club']);
CSRF::validate();

$data = [
  ':torneo_id' => V::int($_POST['torneo_id'] ?? 0,1),
  ':club_id' => V::int($_POST['club_id'] ?? 0,1),
  ':amount' => (float)($_POST['amount'] ?? 0),
  ':method' => V::str($_POST['method'] ?? 'transferencia',1,30),
  ':reference' => ($_POST['reference'] ?? null),
  ':status' => V::enum($_POST['status'] ?? 'pendiente', ['pendiente','confirmado','rechazado']),
];
$stmt = DB::pdo()->prepare("INSERT INTO payments (torneo_id, club_id, amount, method, reference, status) VALUES (:torneo_id,:club_id,:amount,:method,:reference,:status)");
$stmt->execute($data);
header('Location: list.php');

