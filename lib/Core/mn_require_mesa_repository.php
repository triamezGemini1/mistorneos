<?php

declare(strict_types=1);

/**
 * Carga MesaRepository (y, vía su propio require, MesaAsignacionMatriz + MesaRepositoryPersistTrait).
 * Orden: primero app/Core (instalación completa), si no existe lib/Core (hosting sin carpeta app/).
 *
 * @throws RuntimeException
 */
function mn_require_mesa_repository(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    $libCoreDir = __DIR__;
    $projectRoot = dirname($libCoreDir, 2);

    $candidates = [
        $projectRoot . '/app/Core/MesaRepository.php',
        $libCoreDir . '/MesaRepository.php',
    ];

    foreach ($candidates as $file) {
        if (is_readable($file)) {
            require_once $file;
            $loaded = true;

            return;
        }
    }

    throw new RuntimeException(
        'MesaRepository no encontrado. Suba al servidor al menos: lib/Core/MesaRepository.php, '
        . 'MesaRepositoryPersistTrait.php y MesaAsignacionMatriz.php (o la carpeta completa app/Core/).'
    );
}
