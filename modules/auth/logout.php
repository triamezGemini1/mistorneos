<?php

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/auth.php';
Auth::logout();
header('Location: ../../public/index.php');

