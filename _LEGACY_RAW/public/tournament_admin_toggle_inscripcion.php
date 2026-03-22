<?php
/**
 * Endpoint público para inscribir/desinscribir jugadores
 * Actúa como proxy para api/tournament_admin_toggle_inscripcion.php
 * para evitar el bloqueo de .htaccess
 * Iniciar sesión con el mismo mecanismo que index.php para que la cookie se reconozca.
 */
require_once __DIR__ . '/../config/session_start_early.php';
require_once __DIR__ . '/../api/tournament_admin_toggle_inscripcion.php';












