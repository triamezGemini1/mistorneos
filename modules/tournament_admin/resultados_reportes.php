<?php
/**
 * Reportes segmentados (mismo modelo que cada pantalla de resultados). PDF Letter por tipo.
 */
require_once __DIR__ . '/../../lib/app_helpers.php';

$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php'], true);
$base = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';
$sep = $use_standalone ? '?' : '&';
$torneo_id = (int)($torneo_id ?? 0);
$exportBase = rtrim(AppHelpers::getBaseUrl(), '/') . '/modules/tournament_admin/';
$esEquipos = (int)($torneo['modalidad'] ?? 0) === 3;

function rp_url_pdf(string $exportBase, int $tid, string $tipo): string {
    return $exportBase . 'resultados_export_pdf.php?torneo_id=' . $tid . '&tipo=' . rawurlencode($tipo);
}
function rp_url_print(string $base, string $sep, int $tid, string $tipo): string {
    return $base . $sep . 'action=resultados_reportes_print&torneo_id=' . $tid . '&tipo=' . rawurlencode($tipo);
}
function rp_url_xlsx(string $exportBase, int $tid): string {
    return $exportBase . 'resultados_export_excel.php?torneo_id=' . $tid;
}

$bloques = [
    [
        'tipo' => 'por_club',
        'titulo' => 'Resultados por club',
        'desc' => 'Top por club (pareclub), resumen y detalle por club — como la vista «Resultados clubes».',
        'action_origen' => 'resultados_por_club',
        'siempre' => true,
    ],
    [
        'tipo' => 'general',
        'titulo' => 'Resultados general (individual)',
        'desc' => 'Clasificación individual con equipo — como «Resultados general».',
        'action_origen' => 'resultados_general',
        'siempre' => false,
    ],
    [
        'tipo' => 'equipos_resumido',
        'titulo' => 'Resultados equipos — resumido',
        'desc' => 'Tabla de equipos con G, P, efectividad, puntos — como vista resumida.',
        'action_origen' => 'resultados_equipos_resumido',
        'siempre' => false,
    ],
    [
        'tipo' => 'equipos_detallado',
        'titulo' => 'Resultados equipos — detallado',
        'desc' => 'Por equipo, listado de jugadores — como vista detallada.',
        'action_origen' => 'resultados_equipos_detallado',
        'siempre' => false,
    ],
    [
        'tipo' => 'consolidado',
        'titulo' => 'Reporte consolidado',
        'desc' => 'Rondas, equipos (si aplica), por club y clasificación en un solo documento.',
        'action_origen' => 'resultados_reportes',
        'siempre' => true,
    ],
];
?>

<link rel="stylesheet" href="assets/dist/output.css">

<div class="min-h-screen bg-gradient-to-br from-slate-700 via-slate-800 to-slate-900 p-6">
    <div class="mb-4">
        <a href="<?= htmlspecialchars($base . $sep . 'action=panel&torneo_id=' . $torneo_id) ?>"
           class="inline-flex items-center px-5 py-2.5 bg-amber-200 hover:bg-amber-300 text-black font-bold rounded-lg border border-gray-800">
            <i class="fas fa-arrow-left mr-2"></i> Volver al panel
        </a>
    </div>

    <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-5xl mx-auto mb-6">
        <h1 class="text-2xl font-bold text-gray-900"><i class="fas fa-file-alt text-amber-600 mr-2"></i> Reportes de resultados</h1>
        <p class="text-gray-700 font-medium"><?= htmlspecialchars($torneo['nombre'] ?? '') ?></p>
        <p class="text-sm text-gray-500 mt-2">Cada bloque corresponde a un tipo de vista de resultados. PDF en formato <strong>Letter</strong> vertical.</p>
        <div class="mt-4 p-4 bg-green-50 border border-green-300 rounded-lg">
            <strong class="text-black">Excel completo</strong> (todas las hojas):
            <a href="<?= htmlspecialchars(rp_url_xlsx($exportBase, $torneo_id)) ?>" class="ml-2 text-black font-bold underline">Descargar .xlsx</a>
        </div>
    </div>

    <?php foreach ($bloques as $b):
        if (!$b['siempre'] && !$esEquipos && in_array($b['tipo'], ['general', 'equipos_resumido', 'equipos_detallado'], true)) {
            continue;
        }
        $origen = $base . $sep . 'action=' . $b['action_origen'] . '&torneo_id=' . $torneo_id;
        $pdf = rp_url_pdf($exportBase, $torneo_id, $b['tipo']);
        $print = rp_url_print($base, $sep, $torneo_id, $b['tipo']);
    ?>
    <div class="bg-white rounded-xl shadow-lg p-6 max-w-5xl mx-auto mb-4 border-2 border-gray-200">
        <h2 class="text-lg font-bold text-gray-900 mb-1"><?= htmlspecialchars($b['titulo']) ?></h2>
        <p class="text-sm text-gray-600 mb-4"><?= htmlspecialchars($b['desc']) ?></p>
        <div class="flex flex-wrap gap-2 items-center">
            <a href="<?= htmlspecialchars($origen) ?>"
               class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-black font-bold rounded-lg border border-gray-700 text-sm">
                <i class="fas fa-arrow-left mr-1"></i> Volver al origen
            </a>
            <a href="<?= htmlspecialchars($pdf) ?>"
               class="inline-flex items-center px-4 py-2 bg-red-200 hover:bg-red-300 text-black font-bold rounded-lg border border-red-600 text-sm">
                <i class="fas fa-file-pdf mr-1"></i> PDF
            </a>
            <a href="<?= htmlspecialchars($print) ?>" target="_blank"
               class="inline-flex items-center px-4 py-2 bg-blue-200 hover:bg-blue-300 text-black font-bold rounded-lg border border-blue-700 text-sm">
                <i class="fas fa-print mr-1"></i> Imprimir / vista
            </a>
            <a href="<?= htmlspecialchars($base . $sep . 'action=resultados_reportes&torneo_id=' . $torneo_id) ?>"
               class="inline-flex items-center px-4 py-2 bg-amber-100 hover:bg-amber-200 text-black font-bold rounded-lg border border-amber-700 text-sm">
                <i class="fas fa-list mr-1"></i> Índice reportes
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
