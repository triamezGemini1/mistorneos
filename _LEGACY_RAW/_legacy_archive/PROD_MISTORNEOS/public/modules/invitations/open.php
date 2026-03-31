<?php
$target = __DIR__ . '/../../../modules/invitations/open.php';
if (!file_exists($target)) {
  http_response_code(500);
  exit('Shim error: destino no encontrado -> ' . $target);
}
require $target;
