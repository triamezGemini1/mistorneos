<?php
declare(strict_types=1);

/**
 * HTML del bloque “mesa” para vista móvil QR jugador (reutilizable en JSON refresh).
 */
final class TorneoQrJugadorMesaPartial
{
    /**
     * @param array<string, mixed>|null $asignacion retorno de PublicInfoTorneoMesasService::resumenAsignacion
     */
    public static function renderBody(?array $asignacion, int $viewerId, bool $esEquipos, int $ronda): string
    {
        ob_start();
        if ($asignacion === null) {
            echo '<div class="alert-warn">Sin asignación en esta ronda (aún no generada o no participa).</div>';
        } elseif (($asignacion['tipo'] ?? '') === 'bye') {
            echo '<div class="bye-box">';
            echo '<p>Descanso (BYE) esta ronda.</p>';
            echo '<p class="info-mesa-yo">' . htmlspecialchars((string) ($asignacion['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>';
            if (!empty($asignacion['club_nombre'])) {
                echo '<p class="club-hint">(' . htmlspecialchars((string) $asignacion['club_nombre'], ENT_QUOTES, 'UTF-8') . ')</p>';
            }
            echo '</div>';
        } else {
            $num_mesa = (int) ($asignacion['mesa'] ?? 0);
            $jugadores = $asignacion['jugadores'] ?? [];
            $pareja_a = array_filter($jugadores, static function ($j) {
                return is_array($j) && isset($j['secuencia']) && in_array((int) $j['secuencia'], [1, 2], true);
            });
            $pareja_b = array_filter($jugadores, static function ($j) {
                return is_array($j) && isset($j['secuencia']) && in_array((int) $j['secuencia'], [3, 4], true);
            });
            $tiene_resultados = false;
            foreach ($jugadores as $j) {
                if (is_array($j) && (!empty($j['resultado1']) || !empty($j['resultado2']))) {
                    $tiene_resultados = true;
                    break;
                }
            }
            echo '<p class="mesa-num">Mesa ' . (int) $num_mesa . ' · Ronda ' . (int) $ronda . '</p>';
            if (count($jugadores) === 4) {
                echo '<div class="pareja-tit a">Pareja A</div><ul class="jugadores">';
                foreach ($pareja_a as $jugador) {
                    if (!is_array($jugador)) {
                        continue;
                    }
                    $uid = (int) ($jugador['jugador_uid'] ?? 0);
                    $yo = ($uid === $viewerId);
                    echo '<li class="' . ($yo ? 'info-mesa-yo' : '') . '"><i class="fas fa-user" aria-hidden="true"></i> ';
                    echo htmlspecialchars((string) ($jugador['nombre'] ?? $jugador['nombre_completo'] ?? 'Sin nombre'), ENT_QUOTES, 'UTF-8');
                    if ($esEquipos && !empty($jugador['codigo_equipo_inscrito'])) {
                        echo ' <strong>[' . htmlspecialchars((string) $jugador['codigo_equipo_inscrito'], ENT_QUOTES, 'UTF-8') . ']</strong>';
                    }
                    if (!empty($jugador['club_nombre'])) {
                        echo ' <span class="club-hint">(' . htmlspecialchars((string) $jugador['club_nombre'], ENT_QUOTES, 'UTF-8') . ')</span>';
                    }
                    echo '</li>';
                }
                echo '</ul><div class="pareja-tit b">Pareja B</div><ul class="jugadores">';
                foreach ($pareja_b as $jugador) {
                    if (!is_array($jugador)) {
                        continue;
                    }
                    $uid = (int) ($jugador['jugador_uid'] ?? 0);
                    $yo = ($uid === $viewerId);
                    echo '<li class="' . ($yo ? 'info-mesa-yo' : '') . '"><i class="fas fa-user" aria-hidden="true"></i> ';
                    echo htmlspecialchars((string) ($jugador['nombre'] ?? $jugador['nombre_completo'] ?? 'Sin nombre'), ENT_QUOTES, 'UTF-8');
                    if ($esEquipos && !empty($jugador['codigo_equipo_inscrito'])) {
                        echo ' <strong>[' . htmlspecialchars((string) $jugador['codigo_equipo_inscrito'], ENT_QUOTES, 'UTF-8') . ']</strong>';
                    }
                    if (!empty($jugador['club_nombre'])) {
                        echo ' <span class="club-hint">(' . htmlspecialchars((string) $jugador['club_nombre'], ENT_QUOTES, 'UTF-8') . ')</span>';
                    }
                    echo '</li>';
                }
                echo '</ul>';
                if ($tiene_resultados && !empty($jugadores[0]) && is_array($jugadores[0])) {
                    $primer = reset($jugadores);
                    $r1 = (int) ($primer['resultado1'] ?? 0);
                    $r2 = (int) ($primer['resultado2'] ?? 0);
                    echo '<div class="resultados"><strong>Resultados:</strong> Pareja A: ' . $r1 . ' | Pareja B: ' . $r2 . '</div>';
                }
            } else {
                echo '<ul class="jugadores">';
                foreach ($jugadores as $jugador) {
                    if (!is_array($jugador)) {
                        continue;
                    }
                    $uid = (int) ($jugador['jugador_uid'] ?? 0);
                    $yo = ($uid === $viewerId);
                    echo '<li class="' . ($yo ? 'info-mesa-yo' : '') . '"><i class="fas fa-user" aria-hidden="true"></i> ';
                    echo htmlspecialchars((string) ($jugador['nombre'] ?? $jugador['nombre_completo'] ?? 'Sin nombre'), ENT_QUOTES, 'UTF-8');
                    echo '</li>';
                }
                echo '</ul>';
            }
        }

        return (string) ob_get_clean();
    }
}
