<?php
/**
 * Capa de presentación del módulo torneo_gestion (contenedor LVD, include de vistas, helpers HTML).
 *
 * Las vistas concretas (tablas, modales, formularios) siguen en gestion_torneos/ y tournament_admin/;
 * aquí se unifica el envoltorio responsivo para laptop ~14" y móvil (ancho fluido + tope max-w-7xl ≈ 80rem).
 *
 * Recomendación para tablas en vistas: rodear con <div class="table-responsive"> (Bootstrap) o llamar
 * torneo_gestion_render_table_scroll_open() / _close().
 */

/**
 * Abre contenedor principal del área de contenido (equiv. mental: w-full + max-w-7xl).
 */
function torneo_gestion_render_lvd_shell_open(): void
{
    static $css_injected = false;
    if (!$css_injected) {
        echo '<style id="torneo-gestion-lvd-shell-css">'
            . '.torneo-gestion-lvd-shell{width:100%;max-width:80rem;margin-left:auto;margin-right:auto;}'
            . '.torneo-gestion-lvd-shell .table-responsive{overflow-x:auto;-webkit-overflow-scrolling:touch;}'
            . '.torneo-gestion-lvd-shell table{max-width:100%;}'
            . '</style>';
        $css_injected = true;
    }
    echo '<div class="torneo-gestion-lvd-shell w-100 px-2 px-sm-3 px-lg-4 py-3">';
}

function torneo_gestion_render_lvd_shell_close(): void
{
    echo '</div>';
}

/**
 * Wrapper estándar para tablas anchas (overflow horizontal en móvil).
 */
function torneo_gestion_render_table_scroll_open(?string $extra_class = null): void
{
    $cls = 'table-responsive w-100' . ($extra_class !== null && $extra_class !== '' ? ' ' . $extra_class : '');
    echo '<div class="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '">';
}

function torneo_gestion_render_table_scroll_close(): void
{
    echo '</div>';
}

/**
 * Incluye una vista PHP con extract; sin envoltorio (cronómetro, impresión, etc.).
 *
 * @throws Exception si la vista no existe
 */
function torneo_gestion_render_view_extract_include(string $view_file, array $view_data): void
{
    if ($view_file === '' || $view_file === null || !is_file($view_file)) {
        throw new Exception('Vista no encontrada: ' . ($view_file !== '' && $view_file !== null ? basename($view_file) : '(vacío)'));
    }
    extract($view_data);
    include $view_file;
}

/**
 * Vista con shell LVD (flujo normal index / admin contenido).
 */
function torneo_gestion_render_view_in_lvd_shell(string $view_file, array $view_data): void
{
    torneo_gestion_render_lvd_shell_open();
    torneo_gestion_render_view_extract_include($view_file, $view_data);
    torneo_gestion_render_lvd_shell_close();
}

/**
 * Fallback cuando headers_sent() impide Location (edit/view/new torneo).
 */
function torneo_gestion_render_meta_refresh_redirect(string $target): void
{
    echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '"><p>Redirigiendo...</p>';
}

/**
 * Inyecta estilos del grid de mesas (equiv. Tailwind: grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4).
 */
function torneo_gestion_render_lvd_mesas_grid_styles_once(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    echo '<style id="torneo-gestion-lvd-mesas-grid-css">'
        . '.lvd-mesas-grid{display:grid;grid-template-columns:1fr;gap:1rem;}'
        . '@media (min-width:768px){.lvd-mesas-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}'
        . '@media (min-width:1024px){.lvd-mesas-grid{grid-template-columns:repeat(4,minmax(0,1fr));}}'
        . '.lvd-mesa-card{position:relative;background:#fff;border-radius:0.5rem;box-shadow:0 1px 2px rgba(0,0,0,.08);border:1px solid #e5e7eb;padding:1rem 0.875rem 0.75rem;min-height:100%;display:flex;flex-direction:column;}'
        . '.lvd-mesa-badge{position:absolute;top:0.65rem;right:0.65rem;width:2.35rem;height:2.35rem;border-radius:9999px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.8rem;line-height:1;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;box-shadow:0 1px 3px rgba(0,0,0,.12);}'
        . '.lvd-mesa-body{flex:1 1 auto;padding-right:2.5rem;}'
        . '.lvd-mesa-player{font-size:0.875rem;font-weight:600;line-height:1.35;color:#1f2937;}'
        . '.lvd-mesa-club{font-size:0.75rem;font-weight:400;color:#6b7280;}'
        . '.lvd-mesa-footer{margin-top:auto;padding-top:0.75rem;}'
        . '.lvd-mesa-label{font-size:0.7rem;text-transform:uppercase;letter-spacing:.04em;font-weight:600;color:#6b7280;margin-bottom:0.35rem;}'
        . '</style>';
}

/**
 * Grid responsivo de mesas (tarjetas). Sustituye el antiguo layout fila/columna Bootstrap de mesas.php.
 *
 * @param array $mesas_normales Lista de entradas con 'numero' (int) y 'jugadores' (array de filas partiresul + join).
 * @param string $base_url URL base del script (admin_torneo / index…).
 * @param string $action_param '?' o '&' según standalone.
 * @param int $torneo_id
 * @param int $ronda
 */
function render_lvd_grid_mesas(array $mesas_normales, string $base_url, string $action_param, int $torneo_id, int $ronda): void
{
    if ($mesas_normales === []) {
        return;
    }

    torneo_gestion_render_lvd_mesas_grid_styles_once();

    echo '<div class="lvd-mesas-grid w-100">';

    foreach ($mesas_normales as $mesa_data) {
        $num_mesa = $mesa_data['numero'] ?? 0;
        $jugadores = $mesa_data['jugadores'] ?? [];
        $n = (int)$num_mesa;
        if ($n <= 0) {
            continue;
        }

        echo '<div class="lvd-mesa-card" id="mesa-' . $n . '">';
        echo '<span class="lvd-mesa-badge" title="Mesa ' . $n . '">' . $n . '</span>';
        echo '<div class="lvd-mesa-body">';

        if (count($jugadores) === 4) {
            $pareja_a = array_filter($jugadores, static function ($j) {
                return is_array($j) && isset($j['secuencia']) && in_array((int)$j['secuencia'], [1, 2], true);
            });
            $pareja_b = array_filter($jugadores, static function ($j) {
                return is_array($j) && isset($j['secuencia']) && in_array((int)$j['secuencia'], [3, 4], true);
            });

            echo '<div class="mb-2">';
            echo '<div class="lvd-mesa-label text-primary">Pareja A</div>';
            echo '<ul class="list-unstyled mb-0 small">';
            foreach ($pareja_a as $jugador) {
                if (!is_array($jugador)) {
                    continue;
                }
                $nom = htmlspecialchars((string)($jugador['nombre'] ?? $jugador['nombre_completo'] ?? 'Sin nombre'), ENT_QUOTES, 'UTF-8');
                $club = !empty($jugador['club_nombre']) ? htmlspecialchars((string)$jugador['club_nombre'], ENT_QUOTES, 'UTF-8') : '';
                echo '<li class="mb-1"><i class="fas fa-user me-1 text-muted" style="font-size:0.75rem;"></i>';
                echo '<span class="lvd-mesa-player">' . $nom . '</span>';
                if ($club !== '') {
                    echo ' <span class="lvd-mesa-club">(' . $club . ')</span>';
                }
                echo '</li>';
            }
            echo '</ul></div>';

            echo '<div class="mb-2">';
            echo '<div class="lvd-mesa-label text-success">Pareja B</div>';
            echo '<ul class="list-unstyled mb-0 small">';
            foreach ($pareja_b as $jugador) {
                if (!is_array($jugador)) {
                    continue;
                }
                $nom = htmlspecialchars((string)($jugador['nombre'] ?? $jugador['nombre_completo'] ?? 'Sin nombre'), ENT_QUOTES, 'UTF-8');
                $club = !empty($jugador['club_nombre']) ? htmlspecialchars((string)$jugador['club_nombre'], ENT_QUOTES, 'UTF-8') : '';
                echo '<li class="mb-1"><i class="fas fa-user me-1 text-muted" style="font-size:0.75rem;"></i>';
                echo '<span class="lvd-mesa-player">' . $nom . '</span>';
                if ($club !== '') {
                    echo ' <span class="lvd-mesa-club">(' . $club . ')</span>';
                }
                echo '</li>';
            }
            echo '</ul></div>';

            $tiene_resultados = false;
            foreach ($jugadores as $j) {
                if (is_array($j) && (!empty($j['resultado1']) || !empty($j['resultado2']))) {
                    $tiene_resultados = true;
                    break;
                }
            }

            if ($tiene_resultados && !empty($jugadores) && is_array($jugadores[0])) {
                $primer_jugador = reset($jugadores);
                $resultado1 = (int)($primer_jugador['resultado1'] ?? 0);
                $resultado2 = (int)($primer_jugador['resultado2'] ?? 0);
                echo '<div class="small text-muted border-top pt-2 mt-1">';
                echo '<strong class="text-dark">Resultados:</strong> ';
                echo 'Pareja A: ' . $resultado1 . ' | Pareja B: ' . $resultado2;
                echo '</div>';
            }
        } else {
            echo '<p class="text-muted small mb-2">Mesa incompleta (' . count($jugadores) . ' jugadores)</p>';
            echo '<ul class="list-unstyled small">';
            foreach ($jugadores as $jugador) {
                if (!is_array($jugador)) {
                    continue;
                }
                $nom = htmlspecialchars((string)($jugador['nombre'] ?? $jugador['nombre_completo'] ?? 'Sin nombre'), ENT_QUOTES, 'UTF-8');
                echo '<li class="mb-1"><i class="fas fa-user me-1 text-muted"></i><span class="lvd-mesa-player">' . $nom . '</span></li>';
            }
            echo '</ul>';
        }

        echo '</div>'; // .lvd-mesa-body

        $href_resultados = htmlspecialchars(
            $base_url . $action_param . 'action=registrar_resultados&torneo_id=' . $torneo_id . '&ronda=' . $ronda . '&mesa=' . $n,
            ENT_QUOTES,
            'UTF-8'
        );
        $href_reasignar = htmlspecialchars(
            $base_url . $action_param . 'action=reasignar_mesa&torneo_id=' . $torneo_id . '&ronda=' . $ronda . '&mesa=' . $n,
            ENT_QUOTES,
            'UTF-8'
        );

        echo '<div class="lvd-mesa-footer">';
        echo '<a href="' . $href_resultados . '" class="btn btn-primary w-100 mb-2" title="Registrar resultados">';
        echo '<i class="fas fa-keyboard me-1"></i>Cargar resultado</a>';
        echo '<a href="' . $href_reasignar . '" class="btn btn-outline-secondary w-100 btn-sm" title="Intercambiar posiciones de jugadores">';
        echo '<i class="fas fa-exchange-alt me-1"></i>Reasignar</a>';
        echo '</div>';

        echo '</div>'; // .lvd-mesa-card
    }

    echo '</div>'; // .lvd-mesas-grid
}
