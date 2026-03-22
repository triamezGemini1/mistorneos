<?php
if (!isset($_SESSION)) { session_start(); }
$pdo = DB::pdo();
$token = $_GET['token'] ?? '';
$q = $pdo->prepare("SELECT * FROM invitations WHERE token=:tk AND estado='activa'");
$q->execute([':tk'=>$token]);
$inv = $q->fetch();
if (!$inv) { http_response_code(404); exit('Invitación no encontrada'); }
$today = date('Y-m-d');
if ($today < $inv['acceso1'] || $today > $inv['acceso2']) {
  exit('La invitación no está vigente.');
}
if (empty($_SESSION['user'])) {
  $_SESSION['after_login'] = '/modules/invitations/open.php?token=' . urlencode($token);
  header('Location: /modules/auth/login.php');
  exit;
}
if (!empty($_SESSION['user']['club_id']) && (int)$_SESSION['user']['club_id'] !== (int)$inv['club_id']
    && ($_SESSION['user']['role'] ?? '') !== 'admin_general') {
  exit('No tienes permisos para esta invitación.');
}
header('Location: /modules/registrants/new.php?torneo_id='.(int)$inv['torneo_id'].'&club_id='.(int)$inv['club_id']);
