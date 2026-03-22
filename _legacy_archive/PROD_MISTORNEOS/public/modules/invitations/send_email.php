<?php
$target = __DIR__ . '/../../../modules/invitations/send_email.php';
if (!file_exists($target)) {
  http_response_code(500);
  exit('Shim error: destino no encontrado -> ' . $target);
}
require $target;
