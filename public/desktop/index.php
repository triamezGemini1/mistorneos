<?php
/**
 * Índice Desktop — redirige al dashboard. El registro de jugadores está en registro_jugadores.php
 * y solo se muestra cuando se accede explícitamente (menú "Registro de jugador").
 */
declare(strict_types=1);
require_once __DIR__ . '/desktop_auth.php';

header('Location: dashboard.php');
exit;
