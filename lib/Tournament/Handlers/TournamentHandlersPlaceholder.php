<?php

declare(strict_types=1);

/**
 * La instantánea en C:\...\mistorneosnube\...\mistorneos_beta no incluye lib/Tournament/Handlers/.
 * Este archivo existe para que los require previsibles no fallen por ruta inexistente.
 * Sustituir por las clases reales cuando estén disponibles en el proyecto.
 */
final class TournamentHandlersPlaceholder
{
    public const NOTE = 'Origen nube sin carpeta Handlers; añadir aquí el namespace de acciones.';
}

if (!isset($GLOBALS['tgProjRoot'])) {
    /** Raíz del proyecto (desde lib/Tournament/Handlers hacia arriba). */
    $GLOBALS['tgProjRoot'] = dirname(__DIR__, 3);
}
