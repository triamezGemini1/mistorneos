<?php
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../config/auth.php';

Auth::logout();

header("Location: login.php");
exit;
