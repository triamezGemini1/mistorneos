<?php
echo "<pre>";
// Intentamos ejecutar composer usando la ruta de PHP 8.2
$comando = "/usr/local/bin/php82 /usr/local/bin/composer install --no-dev 2>&1";
system($comando, $retorno);
echo "\nFinalizado con código: $retorno";
echo "</pre>";
?>