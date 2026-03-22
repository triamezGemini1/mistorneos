<?php
// Redirigir a la página 404 personalizada
header('Location: ' . app_base_url() . '/public/404.php', true, 404);
exit;
