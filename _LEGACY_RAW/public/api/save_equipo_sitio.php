<?php
/**
 * Endpoint dedicado inscripción equipos en sitio (nombre único = sin bytecode viejo en OPcache).
 * Misma lógica que guardar_equipo_v2.php
 */
error_log('=== API save_equipo_sitio.php (endpoint inscripción sitio) ===');
require __DIR__ . '/guardar_equipo_v2.php';
