<?php
/**
 * Sección Reportes: PDF, Excel e impresión de resultados del torneo.
 */
require_once __DIR__ . '/../../lib/app_helpers.php';

$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php'], true);
$base = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';
$sep = $use_standalone ? '?' : '&';
$torneo_id = (int)($torneo_id ?? 0);

$exportBase = rtrim(AppHelpers::getBaseUrl(), '/') . '/modules/tournament_admin/';
$urlPdf = $exportBase . 'resultados_export_pdf.php?torneo_id=' . $torneo_id;
$urlXlsx = $exportBase . 'resultados_export_excel.php?torneo_id=' . $torneo_id;
$urlPrint = $base . $sep . 'action=resultados_reportes_print&torneo_id=' . $torneo_id;
?>

<link rel="stylesheet" href="assets/dist/output.css">

<div class="min-h-screen bg-gradient-to-br from-slate-700 via-slate-800 to-slate-900 p-6">
    <div class="mb-4 flex flex-wrap gap-3">
        <a href="<?= htmlspecialchars($base . $sep . 'action=panel&torneo_id=' . $torneo_id) ?>"
           class="inline-flex items-center px-5 py-2.5 bg-amber-200 hover:bg-amber-300 text-black font-bold rounded-lg shadow border border-gray-400">
            <i class="fas fa-arrow-left mr-2 text-black"></i> Volver al panel
        </a>
    </div>

    <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-4xl mx-auto">
        <h1 class="text-2xl font-bold text-gray-900 mb-1">
            <i class="fas fa-file-alt text-amber-600 mr-2"></i> Reportes de resultados
        </h1>
        <p class="text-gray-600 mb-2"><?= htmlspecialchars($torneo['nombre'] ?? 'Torneo') ?></p>
        <p class="text-sm text-gray-500 mb-8">
            Descarga o imprime la clasificación individual, resumen por club, rondas y, si el torneo es por equipos, la tabla de equipos.
        </p>

        <div class="grid md:grid-cols-2 gap-6">
            <div class="border border-gray-200 rounded-xl p-6 bg-gradient-to-br from-red-50 to-white">
                <div class="text-red-700 text-3xl mb-3"><i class="fas fa-file-pdf"></i></div>
                <h2 class="text-lg font-bold text-gray-900 mb-2">PDF</h2>
                <p class="text-sm text-gray-600 mb-4">Un solo documento listo para archivar o enviar (orientación horizontal).</p>
                <a href="<?= htmlspecialchars($urlPdf) ?>"
                   class="inline-flex items-center px-5 py-3 bg-red-200 hover:bg-red-300 text-black font-bold rounded-lg shadow border border-red-400">
                    <i class="fas fa-download mr-2 text-black"></i> Descargar PDF
                </a>
            </div>

            <div class="border border-gray-200 rounded-xl p-6 bg-gradient-to-br from-green-50 to-white">
                <div class="text-green-700 text-3xl mb-3"><i class="fas fa-file-excel"></i></div>
                <h2 class="text-lg font-bold text-gray-900 mb-2">Excel</h2>
                <p class="text-sm text-gray-600 mb-4">Varias hojas: torneo, rondas, por club, equipos (si aplica) y clasificación completa.</p>
                <a href="<?= htmlspecialchars($urlXlsx) ?>"
                   class="inline-flex items-center px-5 py-3 bg-green-200 hover:bg-green-300 text-black font-bold rounded-lg shadow border border-green-500">
                    <i class="fas fa-download mr-2 text-black"></i> Descargar Excel (.xlsx)
                </a>
            </div>
        </div>

        <div class="mt-8 border border-gray-200 rounded-xl p-6 bg-gray-50">
            <div class="text-gray-700 text-2xl mb-3"><i class="fas fa-print"></i></div>
            <h2 class="text-lg font-bold text-gray-900 mb-2">Vista para imprimir</h2>
            <p class="text-sm text-gray-600 mb-4">Se abre una página optimizada para imprimir o guardar como PDF desde el navegador.</p>
            <a href="<?= htmlspecialchars($urlPrint) ?>" target="_blank"
               class="inline-flex items-center px-5 py-3 bg-slate-200 hover:bg-slate-300 text-black font-bold rounded-lg shadow mr-3 border border-slate-500">
                <i class="fas fa-external-link-alt mr-2 text-black"></i> Abrir vista imprimible
            </a>
            <button type="button" onclick="var w=window.open('<?= htmlspecialchars($urlPrint, ENT_QUOTES) ?>','_blank'); if(w) w.onload=function(){ w.print(); };"
                    class="mt-3 md:mt-0 inline-flex items-center px-5 py-3 bg-blue-200 hover:bg-blue-300 text-black font-bold rounded-lg shadow border border-blue-600">
                <i class="fas fa-print mr-2 text-black"></i> Imprimir (nueva ventana)
            </button>
        </div>

        <div class="mt-8 text-sm text-gray-500">
            <p class="font-semibold text-gray-700 mb-2">Contenido incluido</p>
            <ul class="list-disc pl-5 space-y-1">
                <li>Datos del torneo y conteo de participantes activos</li>
                <li>Resumen por ronda (mesas y registros)</li>
                <li>Agregados por club</li>
                <li>Clasificación individual completa (G, P, efectividad, puntos, ranking, GFF, sanciones, tarjeta)</li>
                <li>Torneos por equipos: tabla de clasificación de equipos</li>
            </ul>
        </div>
    </div>
</div>
