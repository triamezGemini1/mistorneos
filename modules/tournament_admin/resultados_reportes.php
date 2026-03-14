<?php
/**
 * Reportes segmentados. PDF/Excel vía index.php (modules/ no es público).
 */
require_once __DIR__ . '/../../lib/app_helpers.php';

$torneo_id = (int)($torneo_id ?? 0);
$esEquipos = (int)($torneo['modalidad'] ?? 0) === 3;

$urlPdf = static function (string $tipo) use ($torneo_id): string {
    return AppHelpers::torneoGestionUrl('export_resultados_pdf', $torneo_id, ['tipo' => $tipo]);
};
$urlExcel = AppHelpers::torneoGestionUrl('export_resultados_excel', $torneo_id);
$urlPrint = static function (string $tipo) use ($torneo_id): string {
    return AppHelpers::torneoGestionUrl('resultados_reportes_print', $torneo_id, ['tipo' => $tipo]);
};
$urlPanel = AppHelpers::torneoGestionUrl('panel', $torneo_id);

$bloques = [
    ['tipo' => 'posiciones', 'titulo' => 'Tabla de posiciones', 'desc' => 'Misma clasificación que la vista Posiciones (todos los inscritos).', 'action_origen' => 'posiciones', 'siempre' => true],
    ['tipo' => 'por_club', 'titulo' => 'Resultados por club', 'desc' => 'Top por club (pareclub), resumen y detalle.', 'action_origen' => 'resultados_por_club', 'siempre' => true],
    ['tipo' => 'general', 'titulo' => 'Resultados general (individual)', 'desc' => 'Clasificación individual con equipo.', 'action_origen' => 'resultados_general', 'siempre' => false],
    ['tipo' => 'equipos_resumido', 'titulo' => 'Resultados equipos — resumido', 'desc' => 'Tabla de equipos.', 'action_origen' => 'resultados_equipos_resumido', 'siempre' => false],
    ['tipo' => 'equipos_detallado', 'titulo' => 'Resultados equipos — detallado', 'desc' => 'Por equipo + jugadores.', 'action_origen' => 'resultados_equipos_detallado', 'siempre' => false],
    ['tipo' => 'consolidado', 'titulo' => 'Reporte consolidado', 'desc' => 'Rondas, equipos, por club y clasificación.', 'action_origen' => 'resultados_reportes', 'siempre' => true],
];
?>

<link rel="stylesheet" href="assets/dist/output.css">

<div class="min-h-screen bg-gradient-to-br from-slate-700 via-slate-800 to-slate-900 p-6">
    <div class="mb-4">
        <a href="<?= htmlspecialchars($urlPanel) ?>" class="inline-flex items-center px-5 py-2.5 bg-amber-200 hover:bg-amber-300 text-black font-bold rounded-lg border border-gray-800">
            <i class="fas fa-arrow-left mr-2"></i> Volver al panel
        </a>
    </div>

    <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-5xl mx-auto mb-6">
        <h1 class="text-2xl font-bold text-gray-900"><i class="fas fa-file-alt text-amber-600 mr-2"></i> Reportes de resultados</h1>
        <p class="text-gray-700 font-medium"><?= htmlspecialchars($torneo['nombre'] ?? '') ?></p>
        <p class="text-sm text-gray-500 mt-2">PDF en <strong>Letter</strong>. Los archivos se sirven por la app (enlace correcto).</p>
        <div class="mt-4 p-4 bg-green-50 border border-green-300 rounded-lg">
            <strong class="text-black">Excel completo:</strong>
            <a href="<?= htmlspecialchars($urlExcel) ?>" class="ml-2 text-black font-bold underline">Descargar .xlsx</a>
        </div>
    </div>

    <?php foreach ($bloques as $b):
        if (!$b['siempre'] && !$esEquipos && in_array($b['tipo'], ['general', 'equipos_resumido', 'equipos_detallado'], true)) {
            continue;
        }
        $origen = AppHelpers::torneoGestionUrl($b['action_origen'], $torneo_id);
        $idx = AppHelpers::torneoGestionUrl('resultados_reportes', $torneo_id);
    ?>
    <div class="bg-white rounded-xl shadow-lg p-6 max-w-5xl mx-auto mb-4 border-2 border-gray-200">
        <h2 class="text-lg font-bold text-gray-900 mb-1"><?= htmlspecialchars($b['titulo']) ?></h2>
        <p class="text-sm text-gray-600 mb-4"><?= htmlspecialchars($b['desc']) ?></p>
        <div class="flex flex-wrap gap-2 items-center">
            <a href="<?= htmlspecialchars($origen) ?>" class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-black font-bold rounded-lg border border-gray-700 text-sm">
                <i class="fas fa-arrow-left mr-1"></i> Volver al origen
            </a>
            <a href="<?= htmlspecialchars($urlPdf($b['tipo'])) ?>" class="inline-flex items-center px-4 py-2 bg-red-200 hover:bg-red-300 text-black font-bold rounded-lg border border-red-600 text-sm">
                <i class="fas fa-file-pdf mr-1"></i> PDF
            </a>
            <a href="<?= htmlspecialchars($urlPrint($b['tipo'])) ?>" target="_blank" rel="noopener" class="inline-flex items-center px-4 py-2 bg-blue-200 hover:bg-blue-300 text-black font-bold rounded-lg border border-blue-700 text-sm">
                <i class="fas fa-print mr-1"></i> Imprimir / vista
            </a>
            <a href="<?= htmlspecialchars($idx) ?>" class="inline-flex items-center px-4 py-2 bg-amber-100 hover:bg-amber-200 text-black font-bold rounded-lg border border-amber-700 text-sm">
                <i class="fas fa-list mr-1"></i> Índice reportes
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
