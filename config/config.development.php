<?php
/**
 * Configuración de Desarrollo
 * 
 * NOTA: Para búsqueda de personas en desarrollo, necesitas tener
 * una copia local de la tabla dbo.persona en la base de datos fvdadmin
 * o crear una tabla de prueba dbo_persona_staging
 */
return [
  'db' => [
    'host' => 'localhost',
    'port' => 3306,
    'name' => 'mistorneos',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4'
  ],
  
  // Base de datos para búsqueda de personas
  // En desarrollo: base "personas", tabla "dbo_persona"
  'persona_db' => [
    'host' => 'localhost',
    'port' => 3306,
    'name' => 'personas',
    'name_dev' => 'personas',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4',
    'table' => 'dbo_persona',
    'table_dev' => 'dbo_persona'  // Tabla en BD personas
  ],
  
  'security' => [
    'session_name' => 'mistorneos_session_dev',
    'csrf_key' => 'dev_csrf_key_replace_with_random_32_chars',
    'password_algo' => PASSWORD_DEFAULT
  ],
  'app' => [
    'base_url' => '/mistorneos',
    'debug' => true,
    'environment' => 'development'
  ],
  'whatsapp' => [
    'base_url' => 'http://localhost/mistorneos'
  ]
];




