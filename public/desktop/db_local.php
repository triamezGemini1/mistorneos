<?php
/**
 * Delegado: usa la BD central (mistorneos/desktop/data/mistorneos_local.db).
 * Misma conexión y esquema que desktop/core para que CLI e interfaz web compartan datos.
 */
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'desktop' . DIRECTORY_SEPARATOR . 'db_local.php';
