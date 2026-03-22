<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../config/auth.php';
require_once __DIR__ . '/../../../../config/db.php';
Auth::requireRole(['admin_general']);

require_once __DIR__ . '/../../../../lib/OrganizacionesData.php';

$resumen_entidades = OrganizacionesData::loadResumenEntidades();

include_once __DIR__ . '/../views/index.php';
