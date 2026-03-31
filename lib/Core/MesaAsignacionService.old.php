<?php

declare(strict_types=1);

/**
 * Respaldo por renombrado (fantasma / monolítica retirada).
 *
 * Si en su servidor `MesaAsignacionService.php` era la copia grande (~2000 líneas) con INSERT en
 * partiresul que incluía `entidad_id`, ese contenido debe vivir SOLO en este archivo .old (o en git),
 * no en MesaAsignacionService.php.
 *
 * NO hacer require_once de este archivo. La clase cargada por TorneoMesaAsignacionResolver es
 * únicamente MesaAsignacionService.php (fachada con propiedad $repo → MesaRepository).
 */
