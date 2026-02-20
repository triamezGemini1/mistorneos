<?php
// Global configuration (edit for your environment)
return [
  'db' => [
    'host' => 'localhost',
    'port' => 3306,
    'name' => 'mistorneos',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4'
  ],
    'security' => [
    'session_name' => 'mistorneos_session',
    'csrf_key' => 'replace_with_random_32_chars',
    'password_algo' => PASSWORD_DEFAULT
  ],
  'app' => [
    'base_url' => '/', // change if app not in web root
  ]
];
