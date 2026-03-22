<?php
/**
 * API: Guardar equipo — delega en guardar_equipo_v2.php.
 * El formulario de inscripción en sitio llama directamente a guardar_equipo_v2.php
 * para evitar OPcache con bytecode antiguo de este nombre de archivo.
 */
require __DIR__ . '/guardar_equipo_v2.php';
