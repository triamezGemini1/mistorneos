<?php
if (!isset($_SESSION)) { session_start(); }
if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin_general') {
  http_response_code(403);
  exit('Acceso restringido: admin_general solamente.');
}
