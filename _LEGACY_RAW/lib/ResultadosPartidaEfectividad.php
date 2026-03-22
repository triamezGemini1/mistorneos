<?php
declare(strict_types=1);

/**
 * Cálculo de efectividad y topes de puntos por partida (individual / equipos).
 * Lógica pura reutilizable desde servicios y desde torneo_gestion.
 */
final class ResultadosPartidaEfectividad
{
    public static function maximoPuntosPermitidos(int $puntosTorneo): int
    {
        return (int)round($puntosTorneo * 1.6);
    }

    public static function validarTopePuntos(int $puntos, int $puntosTorneo): int
    {
        $maximo = self::maximoPuntosPermitidos($puntosTorneo);
        return $puntos > $maximo ? $maximo : $puntos;
    }

    public static function calcularEfectividadAlcanzo(int $resultado1, int $resultado2, int $puntosTorneo): int
    {
        if ($resultado1 == $resultado2) {
            return 0;
        }
        if ($resultado1 > $resultado2) {
            return $puntosTorneo - $resultado2;
        }
        return -($puntosTorneo - $resultado1);
    }

    public static function calcularEfectividadNoAlcanzo(int $resultado1, int $resultado2): int
    {
        if ($resultado1 == $resultado2) {
            return 0;
        }
        if ($resultado1 > $resultado2) {
            return $resultado1 - $resultado2;
        }
        return -($resultado2 - $resultado1);
    }

    /**
     * @return array{resultado_ajustado: int, gano: bool, efectividad: int}
     */
    public static function evaluarSancionIndividual(int $resultado1, int $resultado2, int $sancion, int $puntosTorneo): array
    {
        $resultadoAjustado = max(0, $resultado1 - $sancion);
        $gano = ($resultadoAjustado > $resultado2);
        $mayor = max($resultadoAjustado, $resultado2);

        if ($gano) {
            if ($mayor >= $puntosTorneo) {
                $efectividad = self::calcularEfectividadAlcanzo($resultadoAjustado, $resultado2, $puntosTorneo);
            } else {
                $efectividad = self::calcularEfectividadNoAlcanzo($resultadoAjustado, $resultado2);
            }
        } else {
            if ($mayor >= $puntosTorneo) {
                $efectividad = -($puntosTorneo - $resultadoAjustado);
            } else {
                $efectividad = -($resultado2 - $resultadoAjustado);
            }
        }

        return [
            'resultado_ajustado' => $resultadoAjustado,
            'gano' => $gano,
            'efectividad' => $efectividad,
        ];
    }

    public static function calcularEfectividadConSancion(
        int $resultado1Ajustado,
        int $resultadoOponente,
        int $puntosTorneo,
        int $sancion
    ): int {
        if ($resultado1Ajustado <= $resultadoOponente) {
            $mayor = max($resultado1Ajustado, $resultadoOponente);
            if ($mayor >= $puntosTorneo) {
                return -($puntosTorneo - $resultado1Ajustado);
            }
            return -($resultadoOponente - $resultado1Ajustado);
        }
        $mayor = max($resultado1Ajustado, $resultadoOponente);
        if ($mayor >= $puntosTorneo) {
            return self::calcularEfectividadAlcanzo($resultado1Ajustado, $resultadoOponente, $puntosTorneo);
        }
        return self::calcularEfectividadNoAlcanzo($resultado1Ajustado, $resultadoOponente);
    }

    public static function calcularEfectividad(
        int $resultado1,
        int $resultado2,
        int $puntosTorneo,
        int $ff,
        int $tarjeta,
        int $sancion = 0
    ): int {
        $resultado1 = self::validarTopePuntos($resultado1, $puntosTorneo);
        $resultado2 = self::validarTopePuntos($resultado2, $puntosTorneo);
        $resultado1Ajustado = max(0, $resultado1 - $sancion);

        if ($ff == 1) {
            return -$puntosTorneo;
        }
        if ($tarjeta == 3 || $tarjeta == 4) {
            return -$puntosTorneo;
        }

        $mayor = max($resultado1Ajustado, $resultado2);
        if ($mayor >= $puntosTorneo) {
            return self::calcularEfectividadAlcanzo($resultado1Ajustado, $resultado2, $puntosTorneo);
        }
        return self::calcularEfectividadNoAlcanzo($resultado1Ajustado, $resultado2);
    }

    /**
     * @return array{efectividad: int, resultado1: int, resultado2: int}
     */
    public static function calcularEfectividadForfait(bool $tieneForfait, int $puntosTorneo): array
    {
        if ($tieneForfait) {
            return [
                'efectividad' => -$puntosTorneo,
                'resultado1' => 0,
                'resultado2' => $puntosTorneo,
            ];
        }
        return [
            'efectividad' => (int)($puntosTorneo / 2),
            'resultado1' => $puntosTorneo,
            'resultado2' => 0,
        ];
    }

    /**
     * @return array{efectividad: int, resultado1: int, resultado2: int}
     */
    public static function calcularEfectividadTarjetaGrave(bool $tieneTarjetaGrave, int $puntosTorneo): array
    {
        if ($tieneTarjetaGrave) {
            return [
                'efectividad' => -$puntosTorneo,
                'resultado1' => 0,
                'resultado2' => $puntosTorneo,
            ];
        }
        return [
            'efectividad' => $puntosTorneo,
            'resultado1' => $puntosTorneo,
            'resultado2' => 0,
        ];
    }
}
