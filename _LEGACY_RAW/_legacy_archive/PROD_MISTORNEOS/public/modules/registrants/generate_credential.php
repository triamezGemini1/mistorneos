<?php
// Proxy loader to allow access to modules/registrants/generate_credential.php
// This file exists under public/ so URLs like ../modules/registrants/generate_credential.php
// resolve correctly when the web server document root is the `public/` folder.



// Forward the request to the original implementation
require_once __DIR__ . '/../../../modules/registrants/generate_credential.php';

// Note: the included file handles authentication, headers and output.

?>
