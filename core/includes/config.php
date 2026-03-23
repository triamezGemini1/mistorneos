<?php
/**
 * Configuración centralizada: bootstrap + conexión a base de datos.
 * Uso: require_once __DIR__ . '/../core/includes/config.php';
 * No modifica consultas SQL ni variables de negocio (torneos, etc.).
 */
if (!defined('APP_BOOTSTRAPPED')) {
    require_once __DIR__ . '/../../config/bootstrap.php';
}
require_once __DIR__ . '/../../config/db.php';
