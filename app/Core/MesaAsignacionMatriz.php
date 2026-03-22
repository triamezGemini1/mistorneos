<?php

declare(strict_types=1);

/**
 * Utilidades puras para matrices de compañeros (sin acceso a BD).
 */
final class MesaAsignacionMatriz
{
    /**
     * @param list<array{0:int|mixed,1:int|mixed}> $parejas pares id_usuario
     * @return array<int, array<int, true>>
     */
    public static function crearMatrizCompañeros(array $parejas): array
    {
        $matriz = [];
        foreach ($parejas as $pareja) {
            if (count($pareja) >= 2) {
                $id1 = (int) $pareja[0];
                $id2 = (int) $pareja[1];
                $matriz[$id1][$id2] = true;
                $matriz[$id2][$id1] = true;
            }
        }

        return $matriz;
    }
}
