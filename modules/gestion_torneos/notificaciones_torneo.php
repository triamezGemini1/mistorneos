<?php
/**
 * Enviar notificación del torneo usando plantillas.
 * Sin selector de torneo (ya estamos en el panel del torneo) ni de destinatarios:
 * el mensaje se envía según la plantilla: a inscritos del torneo o a todos los usuarios del administrador.
 */
$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php']);
$base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';
$sep = $use_standalone ? '?' : '&';

$torneo = $torneo ?? null;
$torneo_id = isset($torneo_id) ? (int)$torneo_id : (int)($torneo['id'] ?? 0);
$plantillas = $plantillas ?? [];
$ultima_ronda = (int)($ultima_ronda ?? 0);
$inscritos_prueba = $inscritos_prueba ?? [];

if (!$torneo || $torneo_id <= 0) {
    echo '<div class="alert alert-danger">Torneo no encontrado.</div>';
    return;
}
$flash_success = $_SESSION['success'] ?? null;
$flash_error = $_SESSION['error'] ?? null;
if (isset($_SESSION['success'])) unset($_SESSION['success']);
if (isset($_SESSION['error'])) unset($_SESSION['error']);
?>
<link rel="stylesheet" href="assets/dist/output.css">
<div class="tw-panel max-w-2xl mx-auto">
    <?php if ($flash_success): ?>
    <div class="alert alert-success mb-4"><?= htmlspecialchars($flash_success) ?></div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
    <div class="alert alert-danger mb-4"><?= htmlspecialchars($flash_error) ?></div>
    <?php endif; ?>
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="flex items-center space-x-2 text-sm text-gray-500">
            <li><a href="<?php echo $base_url . $sep; ?>action=index" class="hover:text-blue-600">Gestión de Torneos</a></li>
            <li><i class="fas fa-chevron-right text-xs mx-2"></i></li>
            <li><a href="<?php echo $base_url . $sep; ?>action=panel&torneo_id=<?php echo $torneo_id; ?>" class="hover:text-blue-600"><?php echo htmlspecialchars($torneo['nombre'] ?? 'Panel'); ?></a></li>
            <li><i class="fas fa-chevron-right text-xs mx-2"></i></li>
            <li class="text-gray-700 font-medium">Enviar Notificación</li>
        </ol>
    </nav>

    <div class="bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden">
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-5 py-4">
            <h2 class="text-white font-bold text-xl flex items-center">
                <i class="fas fa-bell mr-3"></i> Enviar notificación
            </h2>
            <p class="text-white/80 text-sm mt-1">Elija la plantilla; el envío será a inscritos del torneo o a todos los usuarios del club según la plantilla.</p>
        </div>
        <div class="p-5">
            <form method="POST" action="<?php echo $base_url; ?>" id="form-notif-torneo">
                <input type="hidden" name="action" value="enviar_notificacion_torneo">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(CSRF::token()); ?>">
                <input type="hidden" name="torneo_id" value="<?php echo $torneo_id; ?>">

                <div class="mb-4">
                    <label class="block text-gray-700 font-semibold mb-2">Plantilla del mensaje</label>
                    <select name="plantilla_clave" id="plantilla_clave" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
                        <option value="">-- Elige una plantilla --</option>
                        <?php foreach ($plantillas as $p): ?>
                            <?php $dest = $p['destinatarios'] ?? 'inscritos'; ?>
                            <option value="<?php echo htmlspecialchars($p['nombre_clave']); ?>" data-cuerpo="<?php echo htmlspecialchars($p['cuerpo_mensaje']); ?>" data-destinatarios="<?php echo htmlspecialchars($dest); ?>">
                                <?php echo htmlspecialchars($p['titulo_visual']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p id="destinatarios-leyenda" class="text-xs text-gray-500 mt-1 hidden"></p>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 font-semibold mb-2">Número de ronda (para variables {ronda})</label>
                    <input type="number" name="ronda" id="ronda" min="1" max="99" value="<?php echo $ultima_ronda > 0 ? $ultima_ronda : 1; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                    <small class="text-gray-500">Última ronda generada: <?php echo $ultima_ronda ?: '—'; ?></small>
                </div>

                <div id="preview-container" class="mb-4 hidden">
                    <label class="block text-gray-700 font-semibold mb-2">Vista previa del mensaje</label>
                    <div id="preview" class="bg-gray-100 border border-gray-200 rounded-lg p-4 text-gray-700 whitespace-pre-wrap"></div>
                    <p class="text-xs text-gray-500 mt-1">Variables: {nombre}, {ronda}, {torneo}, {ganados}, {perdidos}, {efectividad}, {puntos}, {mesa}, {pareja}, {url_resumen}</p>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="tw-btn bg-indigo-600 hover:bg-indigo-700 text-white">
                        <i class="fas fa-paper-plane mr-2"></i> Programar envío
                    </button>
                    <a href="<?php echo $base_url . $sep; ?>action=panel&torneo_id=<?php echo $torneo_id; ?>" class="tw-btn bg-gray-400 hover:bg-gray-500 text-white">
                        <i class="fas fa-arrow-left mr-2"></i> Volver al panel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($inscritos_prueba)): ?>
    <?php
    require_once __DIR__ . '/../../lib/NotificationManager.php';
    require_once __DIR__ . '/../../lib/app_helpers.php';
    $nm_prueba = new NotificationManager(DB::pdo());
    $plantilla_nueva_ronda = $nm_prueba->obtenerPlantilla('nueva_ronda');
    $torneo_nombre = $torneo['nombre'] ?? 'Torneo';
    $ronda_prueba = $ultima_ronda > 0 ? $ultima_ronda : 1;
    $primer_inscrito = $inscritos_prueba[0];
    $url_resumen_prueba = (string)($primer_inscrito['url_resumen'] ?? AppHelpers::url('index.php', ['page' => 'torneo_gestion', 'action' => 'resumen_individual', 'torneo_id' => $torneo_id, 'inscrito_id' => (int)($primer_inscrito['id'] ?? 0), 'from' => 'notificaciones']));
    $url_clasificacion_prueba = AppHelpers::url('index.php', ['page' => 'torneo_gestion', 'action' => 'posiciones', 'torneo_id' => $torneo_id, 'from' => 'notificaciones']);
    $primer_id = (int)($primer_inscrito['id'] ?? 0);
    $primer_nombre = htmlspecialchars((string)($primer_inscrito['nombre'] ?? ''));
    $primer_mesa = htmlspecialchars((string)($primer_inscrito['mesa'] ?? '—'));
    $primer_pareja = htmlspecialchars((string)($primer_inscrito['pareja'] ?? '—'));
    $primer_posicion = htmlspecialchars((string)($primer_inscrito['posicion'] ?? '0'));
    $primer_ganados = htmlspecialchars((string)($primer_inscrito['ganados'] ?? '0'));
    $primer_perdidos = htmlspecialchars((string)($primer_inscrito['perdidos'] ?? '0'));
    $primer_efectividad = htmlspecialchars((string)($primer_inscrito['efectividad'] ?? '0'));
    $primer_puntos = htmlspecialchars((string)($primer_inscrito['puntos'] ?? '0'));
    $pareja_id_prueba = (int)($primer_inscrito['pareja_id'] ?? 0);
    ?>
    <style>
    .preview-notif-dispositivo { max-width: 350px; margin: 0 auto; }
    .preview-notif-dispositivo .notif-preview-card { display: flex; background: #fff; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); padding: 16px; border-left: 6px solid #1a365d; }
    .preview-notif-dispositivo .notif-preview-card.nueva-ronda { border: 2px solid #dc2626 !important; text-align: center; }
    .preview-notif-dispositivo .notif-preview-content { flex: 1; min-width: 0; position: relative; }
    .preview-notif-dispositivo .notif-nueva-ronda-header { font-size: 18px; font-weight: 700; margin-bottom: 6px; }
    .preview-notif-dispositivo .notif-nueva-ronda-atleta { font-size: 14px; margin-bottom: 4px; }
    .preview-notif-dispositivo .notif-nueva-ronda-mesa { font-size: 18px; font-weight: 700; margin-bottom: 6px; }
    .preview-notif-dispositivo .notif-nueva-ronda-pareja { font-size: 14px; margin-bottom: 8px; }
    .preview-notif-dispositivo .notif-nueva-ronda-stats { display: grid; grid-template-columns: repeat(5, 1fr); grid-template-rows: auto auto; gap: 2px 8px; justify-items: center; margin: 8px auto; }
    .preview-notif-dispositivo .notif-stats-label { font-weight: 700; font-size: 14px; color: #333; }
    .preview-notif-dispositivo .notif-stats-value { font-size: 14px; font-weight: 700; }
    .preview-notif-dispositivo .notif-preview-actions { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin-top: 12px; }
    .preview-notif-dispositivo .btn-ver-preview { display: inline-flex; align-items: center; font-size: 12px; padding: 6px 12px; border-radius: 6px; text-decoration: none; background: #1a365d; color: #fff !important; }
    .preview-notif-dispositivo .btn-clasif-preview { display: inline-flex; align-items: center; font-size: 12px; padding: 6px 12px; border-radius: 6px; text-decoration: none; background: #2563eb; color: #fff !important; }
    </style>
    <div class="bg-amber-50 border border-amber-200 rounded-xl shadow-sm mt-6 overflow-hidden">
        <div class="px-5 py-3 border-b border-amber-200 bg-amber-100">
            <h3 class="font-bold text-amber-800 flex items-center">
                <i class="fas fa-vial mr-2"></i> Prueba con datos reales (tabla inscritos)
            </h3>
            <p class="text-sm text-amber-700 mt-1">Se usa la plantilla "Nueva Ronda" y los datos del primer inscrito. Envía 1 notificación de prueba a un inscrito elegido.</p>
        </div>
        <div class="p-5">
            <div class="mb-4">
                <label class="block text-gray-700 font-semibold mb-2">Así se verá la notificación en el dispositivo del jugador (ejemplo: <?= $primer_nombre ?>)</label>
                <div class="preview-notif-dispositivo">
                    <div class="notif-preview-card nueva-ronda">
                        <div class="notif-preview-content">
                            <div class="notif-nueva-ronda-header">RONDA <?= $ronda_prueba ?></div>
                            <div class="notif-nueva-ronda-atleta">Atleta: <?= $primer_id ?> <?= $primer_nombre ?></div>
                            <div class="notif-nueva-ronda-mesa">Juega en Mesa: <?= $primer_mesa ?></div>
                            <div class="notif-nueva-ronda-pareja" title="Compañero de juego">Pareja: <?= $pareja_id_prueba ? (string)$pareja_id_prueba . ' ' : '' ?><?= $primer_pareja ?></div>
                            <div class="notif-nueva-ronda-stats">
                                <span class="notif-stats-label">Pos.</span>
                                <span class="notif-stats-label">Gana</span>
                                <span class="notif-stats-label">Perdi</span>
                                <span class="notif-stats-label">Efect</span>
                                <span class="notif-stats-label">Ptos</span>
                                <span class="notif-stats-value"><?= $primer_posicion ?></span>
                                <span class="notif-stats-value"><?= $primer_ganados ?></span>
                                <span class="notif-stats-value"><?= $primer_perdidos ?></span>
                                <span class="notif-stats-value"><?= $primer_efectividad ?></span>
                                <span class="notif-stats-value"><?= $primer_puntos ?></span>
                            </div>
                            <div class="notif-preview-actions">
                                <a href="<?= htmlspecialchars($url_resumen_prueba) ?>" class="btn-ver-preview" target="_blank">Resumen jugador</a>
                                <a href="<?= htmlspecialchars($url_clasificacion_prueba) ?>" class="btn-clasif-preview" target="_blank">Listado de clasificación</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <form method="POST" action="<?php echo $base_url; ?>">
                <input type="hidden" name="action" value="enviar_notificacion_torneo">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(CSRF::token()); ?>">
                <input type="hidden" name="torneo_id" value="<?php echo $torneo_id; ?>">
                <input type="hidden" name="plantilla_clave" value="nueva_ronda">
                <input type="hidden" name="ronda" value="<?php echo $ronda_prueba; ?>">
                <input type="hidden" name="prueba" value="1">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-gray-700 font-semibold mb-1">Enviar prueba a</label>
                        <select name="inscrito_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500" required>
                            <?php foreach ($inscritos_prueba as $ip): ?>
                            <option value="<?= (int)$ip['id'] ?>"><?= htmlspecialchars($ip['nombre']) ?> (ID <?= $ip['id'] ?>) · Mesa <?= htmlspecialchars($ip['mesa'] ?? '—') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="tw-btn bg-amber-600 hover:bg-amber-700 text-white">
                        <i class="fas fa-paper-plane mr-2"></i> Enviar 1 notificación de prueba
                    </button>
                </div>
            </form>
            <p class="text-xs text-amber-700 mt-2">La notificación se encolará con prefijo [Prueba]. Inicia sesión con ese usuario y revisa la campanita o Telegram.</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
(function() {
    const plantillaSelect = document.getElementById('plantilla_clave');
    const rondaInput = document.getElementById('ronda');
    const previewContainer = document.getElementById('preview-container');
    const previewEl = document.getElementById('preview');
    const destinatariosLeyenda = document.getElementById('destinatarios-leyenda');
    const torneoNombre = <?php echo json_encode($torneo['nombre'] ?? 'Torneo'); ?>;

    function updatePreview() {
        const opt = plantillaSelect.options[plantillaSelect.selectedIndex];
        if (!opt || !opt.value) {
            previewContainer.classList.add('hidden');
            destinatariosLeyenda.classList.add('hidden');
            destinatariosLeyenda.textContent = '';
            return;
        }
        const cuerpo = opt.getAttribute('data-cuerpo') || '';
        const dest = opt.getAttribute('data-destinatarios') || 'inscritos';
        destinatariosLeyenda.textContent = dest === 'todos_usuarios_admin' ? 'Se enviará a: todos los usuarios del club.' : 'Se enviará a: inscritos del torneo.';
        destinatariosLeyenda.classList.remove('hidden');

        const ronda = rondaInput.value || '1';
        const urlResumenEjemplo = window.location.origin + window.location.pathname.replace(/[^/]+$/, '') + 'index.php?page=torneo_gestion&action=resumen_individual&torneo_id=1&inscrito_id=1';
        let text = cuerpo
            .replace(/\{nombre\}/g, 'Juan Pérez')
            .replace(/\{ronda\}/g, ronda)
            .replace(/\{torneo\}/g, torneoNombre)
            .replace(/\{ganados\}/g, '2')
            .replace(/\{perdidos\}/g, '1')
            .replace(/\{efectividad\}/g, '150')
            .replace(/\{puntos\}/g, '450')
            .replace(/\{mesa\}/g, '3')
            .replace(/\{pareja\}/g, 'María García')
            .replace(/\{url_resumen\}/g, urlResumenEjemplo);
        previewEl.textContent = text;
        previewContainer.classList.remove('hidden');
    }

    plantillaSelect.addEventListener('change', updatePreview);
    rondaInput.addEventListener('input', updatePreview);
    updatePreview();
})();
</script>
