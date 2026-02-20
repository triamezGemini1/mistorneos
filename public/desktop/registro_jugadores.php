<?php
/**
 * Registro de jugadores (Desktop). Solo se muestra al acceder explícitamente.
 * Formulario de registro inline (colapsable), lista de jugadores y sincronización.
 */
declare(strict_types=1);
require_once __DIR__ . '/desktop_auth.php';
require_once __DIR__ . '/db_local.php';

$pendingCount = 0;
$jugadores = [];
$entidades = [];
$organizaciones = [];
$clubes = [];
$context = [];

try {
    $pdo = DB_Local::pdo();
    $stmt = $pdo->query("SELECT COUNT(*) AS n FROM usuarios WHERE sync_status = 0");
    $pendingCount = (int)$stmt->fetchColumn();
    $jugadores = $pdo->query("SELECT id, uuid, nombre, cedula, nacionalidad, sexo, fechnac, email, username, categ, club_id, entidad, last_updated, role FROM usuarios WHERE role = 'usuario' OR role = '' OR role IS NULL ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    $entidades = $pdo->query("SELECT codigo, nombre FROM entidad ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    $organizaciones = $pdo->query("SELECT id, nombre FROM organizaciones ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    $clubes = $pdo->query("SELECT id, nombre FROM clubes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
}

$contextFile = __DIR__ . '/session_context.json';
if (is_readable($contextFile)) {
    $context = json_decode((string)file_get_contents($contextFile), true) ?: [];
}
$default_entidad = $context['entidad_nombre'] ?? '';
$default_org = $context['organizacion_nombre'] ?? '';
$default_club = $context['club_nombre'] ?? '';

$pageTitle = 'Registro de Jugadores';
$desktopActive = 'registro';

$core_bridge_ok = false;
try {
    require_once __DIR__ . '/../../desktop/core/db_bridge.php';
    DB::pdo();
    $core_bridge_ok = true;
} catch (Throwable $e) {
}
// DESKTOP_VERSION y RELOAD_INTERVAL vienen de core/config.php vía db_bridge
if (!defined('DESKTOP_VERSION')) {
    require_once __DIR__ . '/../../desktop/core/config.php';
}
$ultima_sincronizacion = null;
try {
    $pdoAudit = DB_Local::pdo();
    $stmt = $pdoAudit->query("SELECT fecha FROM auditoria ORDER BY fecha DESC LIMIT 1");
    if ($stmt && $row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $ultima_sincronizacion = $row['fecha'];
    }
} catch (Throwable $e) {
}

require_once __DIR__ . '/desktop_layout.php';
?>
<style>
.card { transition: box-shadow 0.2s; }
.card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
#sync-status {
    position: fixed; top: 1rem; right: 1rem; width: 20px; height: 20px; border-radius: 50%;
    border: 2px solid rgba(0,0,0,0.15); box-shadow: 0 1px 4px rgba(0,0,0,0.2); z-index: 9999;
    transition: background-color 0.3s;
}
#sync-status[data-state="ok"] { background-color: #198754; }
#sync-status[data-state="pending"] { background-color: #fd7e14; }
#sync-status[data-state="offline"] { background-color: #dc3545; }
#sync-status[title] { cursor: help; }
/* Footer técnico Enterprise */
.desktop-footer-tech {
    border-top: 1px solid #E5E7EB;
    padding: 1rem;
    color: #6B7280;
    font-size: 0.875rem;
}
.desktop-footer-tech .footer-led {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 0.5rem;
    vertical-align: middle;
    flex-shrink: 0;
}
.desktop-footer-tech .footer-led.ok { background-color: #22c55e; }
.desktop-footer-tech .footer-led.error { background-color: #ef4444; }
.desktop-footer-tech .footer-left { display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem 1rem; }
.desktop-footer-tech .footer-right { text-align: right; }
@media (max-width: 575.98px) {
    .desktop-footer-tech .footer-row { flex-direction: column; align-items: flex-start; gap: 0.75rem; }
    .desktop-footer-tech .footer-right { text-align: left; }
}
.desktop-form-card .card-header { cursor: pointer; user-select: none; }
#lista-jugadores tbody tr { vertical-align: middle; }
.desktop-alert-inline { min-height: 2.5rem; }
</style>
<div id="sync-status" data-state="<?= $pendingCount > 0 ? 'pending' : 'ok' ?>" data-pending="<?= $pendingCount ?>" title="Estado de sincronización"></div>

<div class="container-fluid py-3">
        <h2 class="h4 mb-2"><i class="fas fa-user-plus me-2"></i>Registro de jugadores</h2>
        <p class="text-muted mb-4">Registro local (SQLite) y sincronización con la web.</p>

        <div class="card desktop-form-card mb-4">
            <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between py-3" data-bs-toggle="collapse" data-bs-target="#formRegistroCollapse" aria-expanded="true">
                <span><i class="fas fa-user-plus me-2"></i>Registro de jugador</span>
                <i class="fas fa-chevron-down collapse-icon"></i>
            </div>
            <div class="collapse show" id="formRegistroCollapse">
                <div class="card-body">
                    <div id="formAlert" class="desktop-alert-inline mb-3"></div>
                    <form id="formRegistro" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Cédula / DNI *</label>
                            <input type="text" name="cedula" class="form-control" placeholder="Solo números" required maxlength="20">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nombre *</label>
                            <input type="text" name="nombre" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Apellido</label>
                            <input type="text" name="apellido" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha de nacimiento</label>
                            <input type="date" name="fechnac" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Categoría</label>
                            <input type="number" name="categ" class="form-control" min="0" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Nacionalidad</label>
                            <select name="nacionalidad" class="form-select">
                                <option value="V">V</option>
                                <option value="E">E</option>
                                <option value="J">J</option>
                                <option value="P">P</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sexo</label>
                            <select name="sexo" class="form-select">
                                <option value="M">M</option>
                                <option value="F">F</option>
                                <option value="O">O</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Usuario *</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Entidad</label>
                            <input type="text" name="entidad_text" id="inputEntidad" class="form-control" list="datalistEntidad" placeholder="Escriba o elija" value="<?= htmlspecialchars($default_entidad) ?>">
                            <datalist id="datalistEntidad">
                                <?php foreach ($entidades as $e): ?>
                                    <option value="<?= htmlspecialchars($e['nombre']) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Organización</label>
                            <input type="text" name="organizacion_text" id="inputOrganizacion" class="form-control" list="datalistOrganizacion" placeholder="Escriba o elija" value="<?= htmlspecialchars($default_org) ?>">
                            <datalist id="datalistOrganizacion">
                                <?php foreach ($organizaciones as $o): ?>
                                    <option value="<?= htmlspecialchars($o['nombre']) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Club</label>
                            <input type="text" name="club_text" id="inputClub" class="form-control" list="datalistClub" placeholder="Escriba o elija" value="<?= htmlspecialchars($default_club) ?>">
                            <datalist id="datalistClub">
                                <?php foreach ($clubes as $c): ?>
                                    <option value="<?= htmlspecialchars($c['nombre']) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary" id="btnGuardar">
                                <i class="fas fa-save me-1"></i>Guardar jugador
                            </button>
                            <button type="reset" class="btn btn-outline-secondary ms-2">Limpiar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4 desktop-hide-mobile-actions">
            <div class="col-md-4">
                <a href="export_to_web.php" class="btn btn-success w-100"><i class="fas fa-cloud-upload-alt me-1"></i>Sincronizar con la web</a>
            </div>
            <div class="col-md-4">
                <a href="import_from_web.php" class="btn btn-outline-primary w-100"><i class="fas fa-cloud-download-alt me-1"></i>Importar desde web</a>
            </div>
            <div class="col-md-4">
                <a href="debug_db.php" class="btn btn-outline-secondary w-100"><i class="fas fa-database me-1"></i>Ver base local</a>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Jugadores registrados</h5>
            </div>
            <div class="card-body p-0">
                <div class="desktop-table-card-wrap">
                    <table class="table table-hover mb-0" id="lista-jugadores">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre</th>
                                <th>Cédula</th>
                                <th>Usuario</th>
                                <th>Club / Entidad</th>
                                <th>Últ. actualización</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $clubNames = [];
                            $entidadNames = [];
                            foreach ($clubes as $c) { $clubNames[(int)$c['id']] = $c['nombre']; }
                            foreach ($entidades as $e) { $entidadNames[(int)$e['codigo']] = $e['nombre']; }
                            foreach ($jugadores as $j):
                                $clubNom = $clubNames[(int)($j['club_id'] ?? 0)] ?? ('ID ' . (int)($j['club_id'] ?? 0));
                                $entNom = $entidadNames[(int)($j['entidad'] ?? 0)] ?? ('ID ' . (int)($j['entidad'] ?? 0));
                                $lastUpd = $j['last_updated'] ?? '';
                            ?>
                            <tr data-id="<?= (int)$j['id'] ?>" data-nombre="<?= htmlspecialchars($j['nombre']) ?>" data-cedula="<?= htmlspecialchars($j['cedula']) ?>" data-username="<?= htmlspecialchars($j['username']) ?>" data-club="<?= htmlspecialchars($clubNom) ?>" data-entidad="<?= htmlspecialchars($entNom) ?>" data-last-updated="<?= htmlspecialchars($lastUpd) ?>">
                                <td data-label="Nombre"><?= htmlspecialchars($j['nombre']) ?></td>
                                <td data-label="Cédula"><?= htmlspecialchars($j['cedula']) ?></td>
                                <td data-label="Usuario"><?= htmlspecialchars($j['username']) ?></td>
                                <td data-label="Club / Entidad"><span class="text-muted"><?= htmlspecialchars($clubNom) ?></span> / <?= htmlspecialchars($entNom) ?></td>
                                <td class="desktop-col-detail" data-label="Últ. actualización"><small class="text-muted"><?= htmlspecialchars($lastUpd) ?></small></td>
                                <td class="text-end" data-label="">
                                    <span class="desktop-btn-detalles me-2"><button type="button" class="btn btn-outline-secondary btn-sm btn-detalles-jugador" title="Ver detalles"><i class="fas fa-info-circle me-1"></i>Detalles</button></span>
                                    <button type="button" class="btn btn-outline-danger btn-sm btn-delete-jugador" data-id="<?= (int)$j['id'] ?>" data-nombre="<?= htmlspecialchars($j['nombre']) ?>" title="Eliminar jugador"><i class="fas fa-trash-alt"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($jugadores)): ?>
                            <tr id="rowEmpty"><td colspan="6" class="text-center text-muted py-4">No hay jugadores. Use el formulario de arriba para registrar.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <footer class="desktop-footer-tech mt-4" role="contentinfo">
        <div class="container-fluid">
            <div class="d-flex flex-wrap justify-content-between align-items-center footer-row">
                <div class="footer-left">
                    <span class="footer-led <?= $core_bridge_ok ? 'ok' : 'error' ?>" aria-hidden="true"></span>
                    <span>Motor de Lógica: <?= $core_bridge_ok ? 'Activo (Local)' : 'Error' ?></span>
                    <span class="text-muted">·</span>
                    <span>v<?php echo DESKTOP_VERSION; ?></span>
                </div>
                <div class="footer-right">
                    <?php if ($ultima_sincronizacion): ?>
                    <span>Última sincronización con la nube: <?= htmlspecialchars($ultima_sincronizacion) ?></span>
                    <?php else: ?>
                    <span class="text-muted">Última sincronización: —</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </footer>

    <!-- Modal Detalles (móvil <576px: datos no esenciales) -->
    <div class="modal fade" id="modalDetallesJugador" tabindex="-1" aria-labelledby="modalDetallesJugadorLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDetallesJugadorLabel"><i class="fas fa-user me-2"></i>Detalles</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4 text-muted small">Nombre</dt><dd class="col-sm-8" id="detNombre"></dd>
                        <dt class="col-sm-4 text-muted small">Cédula</dt><dd class="col-sm-8" id="detCedula"></dd>
                        <dt class="col-sm-4 text-muted small">Usuario</dt><dd class="col-sm-8" id="detUsuario"></dd>
                        <dt class="col-sm-4 text-muted small">Club / Entidad</dt><dd class="col-sm-8" id="detClubEntidad"></dd>
                        <dt class="col-sm-4 text-muted small">Últ. actualización</dt><dd class="col-sm-8" id="detLastUpdated"></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- Speed Dial móvil: principal (+) expande Sync y Nuevo hacia arriba -->
    <div class="desktop-speed-dial" id="speedDial" aria-hidden="true">
        <div class="desktop-speed-dial-overlay" id="speedDialOverlay"></div>
        <div class="desktop-speed-dial-actions">
            <a href="export_to_web.php" class="desktop-speed-dial-btn desktop-speed-dial-sync" id="speedDialSync" title="Sincronizar con la web">
                <span class="speed-dial-label">Sync</span>
                <span class="speed-dial-badge" id="speedDialBadge" aria-label="Pendientes de subir"></span>
                <i class="fas fa-sync-alt"></i>
            </a>
            <a href="#formRegistroCollapse" class="desktop-speed-dial-btn desktop-speed-dial-new desktop-speed-dial-new-btn" title="Nuevo jugador">
                <span class="speed-dial-label">Nuevo</span>
                <i class="fas fa-user-plus"></i>
            </a>
        </div>
        <button type="button" class="desktop-speed-dial-main" id="speedDialMain" aria-expanded="false" aria-label="Abrir acciones">
            <i class="fas fa-plus"></i>
        </button>
    </div>

    <script src="idb.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function () {
        if ('serviceWorker' in navigator) {
            var isSecure = window.location.protocol === 'https:' || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
            if (!isSecure) {
                console.warn('[MisTorneos Desktop] El Service Worker no se registra: se requiere HTTPS (o localhost). Actual: ' + window.location.protocol + ' // ' + window.location.hostname);
            }
            navigator.serviceWorker.register('sw.js', { scope: './' })
                .then(function (reg) { if (reg) console.log('[MisTorneos Desktop] Service Worker registrado:', reg.scope); })
                .catch(function (err) {
                    console.warn('[MisTorneos Desktop] Error al registrar Service Worker:', err.message || err);
                    if (err.message && err.message.indexOf('Secure') !== -1) {
                        console.warn('[MisTorneos Desktop] Solución: sirva la aplicación por HTTPS para activar el modo offline.');
                    }
                });
        }

        var form = document.getElementById('formRegistro');
        var formAlert = document.getElementById('formAlert');
        var btnGuardar = document.getElementById('btnGuardar');
        var tbody = document.querySelector('#lista-jugadores tbody');
        var rowEmpty = document.getElementById('rowEmpty');
        var syncEl = document.getElementById('sync-status');

        function showAlert(msg, type) {
            formAlert.innerHTML = '<div class="alert alert-' + (type || 'info') + ' py-2 mb-0">' + (msg || '') + '</div>';
        }
        function clearAlert() {
            formAlert.innerHTML = '';
        }

        function addRowFromPayload(payload, isOffline) {
            var nombre = (payload.nombre || '').trim() + (payload.apellido ? ' ' + (payload.apellido || '').trim() : '');
            var club = payload.club_text || payload.club || '';
            var ent = payload.entidad_text || payload.entidad || '';
            var id = isOffline ? 'offline-' + Date.now() : (payload.id || '');
            if (rowEmpty) rowEmpty.remove();
            var tr = document.createElement('tr');
            tr.setAttribute('data-id', id);
            if (isOffline) tr.setAttribute('data-offline', '1');
            tr.setAttribute('data-nombre', nombre);
            tr.setAttribute('data-cedula', payload.cedula || '');
            tr.setAttribute('data-username', payload.username || '');
            tr.setAttribute('data-club', club);
            tr.setAttribute('data-entidad', ent);
            tr.setAttribute('data-last-updated', isOffline ? 'Pendiente de subir' : (payload.last_updated || ''));
            tr.innerHTML = '<td data-label="Nombre">' + escapeHtml(nombre) + '</td><td data-label="Cédula">' + escapeHtml(payload.cedula || '') + '</td><td data-label="Usuario">' + escapeHtml(payload.username || '') + '</td><td data-label="Club / Entidad"><span class="text-muted">' + escapeHtml(club) + '</span> / ' + escapeHtml(ent) + '</td><td class="desktop-col-detail" data-label="Últ. actualización"><small class="text-muted">' + escapeHtml(isOffline ? 'Pendiente de subir' : (payload.last_updated || '')) + '</small></td><td class="text-end" data-label=""><span class="desktop-btn-detalles me-2"><button type="button" class="btn btn-outline-secondary btn-sm btn-detalles-jugador" title="Ver detalles"><i class="fas fa-info-circle me-1"></i>Detalles</button></span>' + (isOffline ? '' : '<button type="button" class="btn btn-outline-danger btn-sm btn-delete-jugador" data-id="' + id + '" data-nombre="' + escapeHtml(nombre) + '" title="Eliminar jugador"><i class="fas fa-trash-alt"></i></button>') + '</td>';
            tbody.insertBefore(tr, tbody.firstChild);
            bindDetallesButtons();
        }

        function incrementColaCount() {
            var n = parseInt(syncEl.getAttribute('data-cola') || '0', 10) + 1;
            syncEl.setAttribute('data-cola', n);
            syncEl.setAttribute('data-state', 'pending');
            if (typeof updateSpeedDialBadge === 'function') updateSpeedDialBadge();
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            clearAlert();
            btnGuardar.disabled = true;
            var fd = new FormData(form);
            var obj = {};
            fd.forEach(function (v, k) { obj[k] = v; });
            if (!navigator.onLine && typeof MistorneosIDB !== 'undefined' && MistorneosIDB.cola) {
                MistorneosIDB.cola.add('jugador', obj).then(function () {
                    incrementColaCount();
                    addRowFromPayload(obj, true);
                    showAlert('Guardado offline. Se subirá cuando haya conexión.', 'success');
                    form.reset();
                    document.getElementById('inputEntidad').value = '<?= htmlspecialchars($default_entidad, ENT_QUOTES) ?>';
                    document.getElementById('inputOrganizacion').value = '<?= htmlspecialchars($default_org, ENT_QUOTES) ?>';
                    document.getElementById('inputClub').value = '<?= htmlspecialchars($default_club, ENT_QUOTES) ?>';
                    setTimeout(clearAlert, 3000);
                }).catch(function () { showAlert('No se pudo guardar en cola local.', 'danger'); }).then(function () { btnGuardar.disabled = false; });
                return;
            }
            fetch('save_jugador.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(obj)
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btnGuardar.disabled = false;
                if (data.ok && data.jugador) {
                    form.reset();
                    document.getElementById('inputEntidad').value = '<?= htmlspecialchars($default_entidad, ENT_QUOTES) ?>';
                    document.getElementById('inputOrganizacion').value = '<?= htmlspecialchars($default_org, ENT_QUOTES) ?>';
                    document.getElementById('inputClub').value = '<?= htmlspecialchars($default_club, ENT_QUOTES) ?>';
                    showAlert('Jugador guardado correctamente. Se sincronizará cuando haya conexión.', 'success');
                    if (rowEmpty) rowEmpty.remove();
                    var j = data.jugador;
                    var tr = document.createElement('tr');
                    tr.setAttribute('data-id', j.id);
                    var clubLabel = escapeHtml(j.club_nombre || ('ID ' + j.club_id));
                    var entLabel = escapeHtml(j.entidad_nombre || ('ID ' + j.entidad));
                    tr.setAttribute('data-nombre', j.nombre || '');
                    tr.setAttribute('data-cedula', j.cedula || '');
                    tr.setAttribute('data-username', j.username || '');
                    tr.setAttribute('data-club', j.club_nombre || ('ID ' + j.club_id));
                    tr.setAttribute('data-entidad', j.entidad_nombre || ('' + j.entidad));
                    tr.setAttribute('data-last-updated', j.last_updated || '');
                    tr.innerHTML = '<td data-label="Nombre">' + escapeHtml(j.nombre) + '</td><td data-label="Cédula">' + escapeHtml(j.cedula) + '</td><td data-label="Usuario">' + escapeHtml(j.username) + '</td><td data-label="Club / Entidad"><span class="text-muted">' + clubLabel + '</span> / ' + entLabel + '</td><td class="desktop-col-detail" data-label="Últ. actualización"><small class="text-muted">' + escapeHtml(j.last_updated || '') + '</small></td><td class="text-end" data-label=""><span class="desktop-btn-detalles me-2"><button type="button" class="btn btn-outline-secondary btn-sm btn-detalles-jugador" title="Ver detalles"><i class="fas fa-info-circle me-1"></i>Detalles</button></span><button type="button" class="btn btn-outline-danger btn-sm btn-delete-jugador" data-id="' + j.id + '" data-nombre="' + escapeHtml(j.nombre) + '" title="Eliminar jugador"><i class="fas fa-trash-alt"></i></button></td>';
                    tbody.insertBefore(tr, tbody.firstChild);
                    bindDeleteButtons();
                    bindDetallesButtons();
                    var pending = parseInt(syncEl.getAttribute('data-pending'), 10) || 0;
                    syncEl.setAttribute('data-pending', pending + 1);
                    syncEl.setAttribute('data-state', 'pending');
                    syncEl.title = (pending + 1) + ' registro(s) pendiente(s) de subir.';
                    if (typeof updateSpeedDialBadge === 'function') updateSpeedDialBadge();
                    setTimeout(clearAlert, 3000);
                } else {
                    showAlert(data.error || 'Error al guardar.', 'danger');
                }
            })
            .catch(function () {
                if (typeof MistorneosIDB !== 'undefined' && MistorneosIDB.cola) {
                    MistorneosIDB.cola.add('jugador', obj).then(function () {
                        incrementColaCount();
                        addRowFromPayload(obj, true);
                        showAlert('Error de conexión. Guardado en cola local. Se subirá cuando haya conexión.', 'success');
                        form.reset();
                        document.getElementById('inputEntidad').value = '<?= htmlspecialchars($default_entidad, ENT_QUOTES) ?>';
                        document.getElementById('inputOrganizacion').value = '<?= htmlspecialchars($default_org, ENT_QUOTES) ?>';
                        document.getElementById('inputClub').value = '<?= htmlspecialchars($default_club, ENT_QUOTES) ?>';
                        setTimeout(clearAlert, 3000);
                    }).catch(function () {
                        showAlert('Error de conexión. No se pudo guardar en cola.', 'danger');
                    }).then(function () { btnGuardar.disabled = false; });
                } else {
                    showAlert('Error de conexión.', 'danger');
                    btnGuardar.disabled = false;
                }
            });
        });

        function escapeHtml(s) {
            if (!s) return '';
            var div = document.createElement('div');
            div.textContent = s;
            return div.innerHTML;
        }

        var INTERVAL_MS = <?php echo (int) (defined('RELOAD_INTERVAL') ? RELOAD_INTERVAL : 30000); ?>;
        function setState(online, pending) {
            var state = !online ? 'offline' : (pending > 0 ? 'pending' : 'ok');
            syncEl.setAttribute('data-state', state);
            syncEl.setAttribute('data-pending', pending);
            syncEl.title = state === 'ok' ? 'Sincronizado.' : (state === 'pending' ? pending + ' pendiente(s).' : 'Sin conexión.');
            if (typeof updateSpeedDialBadge === 'function') updateSpeedDialBadge();
        }
        function fetchPending() {
            fetch('pending_count.php').then(function (r) { return r.json(); })
                .then(function (d) { setState(navigator.onLine, d.pending || 0); })
                .catch(function () { setState(navigator.onLine, parseInt(syncEl.getAttribute('data-pending'), 10) || 0); });
        }
        function syncColaToServer() {
            if (typeof MistorneosIDB === 'undefined' || !MistorneosIDB.cola) return Promise.resolve();
            return MistorneosIDB.cola.getAll().then(function (items) {
                if (items.length === 0) return fetchPending();
                var i = 0;
                function next() {
                    if (i >= items.length) return fetchPending();
                    var item = items[i];
                    if (item.tipo !== 'jugador' || !item.payload) { i++; return next(); }
                    return fetch('save_jugador.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify(item.payload)
                    }).then(function (r) { return r.json(); }).then(function (data) {
                        if (data.ok) return MistorneosIDB.cola.remove(item.id);
                    }).catch(function () {}).then(function () {
                        var cola = parseInt(syncEl.getAttribute('data-cola') || '0', 10);
                        if (cola > 0) syncEl.setAttribute('data-cola', Math.max(0, cola - 1));
                        if (typeof updateSpeedDialBadge === 'function') updateSpeedDialBadge();
                        i++;
                        return next();
                    });
                }
                return next();
            });
        }
        setState(navigator.onLine, parseInt(syncEl.getAttribute('data-pending'), 10) || 0);
        if (typeof MistorneosIDB !== 'undefined' && MistorneosIDB.cola) {
            MistorneosIDB.cola.getAll().then(function (r) {
                syncEl.setAttribute('data-cola', (r && r.length) || 0);
                if (typeof updateSpeedDialBadge === 'function') updateSpeedDialBadge();
            }).catch(function () {});
        }
        window.addEventListener('online', function () {
            syncColaToServer().then(function () { fetchPending(); }).catch(function () { fetchPending(); });
        });
        window.addEventListener('offline', function () { setState(false, parseInt(syncEl.getAttribute('data-pending'), 10) || 0); });
        setInterval(function () {
            if (!navigator.onLine) return;
            fetch('export_to_web.php?background=1', { credentials: 'same-origin' }).then(function () { fetchPending(); }).catch(function () { fetchPending(); });
        }, INTERVAL_MS);

        function bindDeleteButtons() {
            document.querySelectorAll('.btn-delete-jugador').forEach(function (btn) {
                if (btn._bound) return;
                btn._bound = true;
                btn.addEventListener('click', function () {
                    var id = this.getAttribute('data-id');
                    var nombre = this.getAttribute('data-nombre') || 'este jugador';
                    if (!confirm('¿Eliminar a "' + nombre + '"? Esta acción no se puede deshacer.')) return;
                    var row = this.closest('tr');
                    fetch('delete_jugador.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({ id: parseInt(id, 10) })
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.ok && row) {
                            row.remove();
                            var empty = document.getElementById('rowEmpty');
                            if (tbody.children.length === 0 && !empty) {
                                var tr = document.createElement('tr');
                                tr.id = 'rowEmpty';
                                tr.innerHTML = '<td colspan="6" class="text-center text-muted py-4">No hay jugadores. Use el formulario de arriba para registrar.</td>';
                                tbody.appendChild(tr);
                            }
                        } else if (!data.ok) {
                            alert(data.error || 'Error al eliminar');
                        }
                    })
                    .catch(function () { alert('Error de conexión'); });
                });
            });
        }
        function bindDetallesButtons() {
            var modalDetalles = document.getElementById('modalDetallesJugador');
            var detNombre = document.getElementById('detNombre');
            var detCedula = document.getElementById('detCedula');
            var detUsuario = document.getElementById('detUsuario');
            var detClubEntidad = document.getElementById('detClubEntidad');
            var detLastUpdated = document.getElementById('detLastUpdated');
            document.querySelectorAll('.btn-detalles-jugador').forEach(function(btn) {
                if (btn._detallesBound) return;
                btn._detallesBound = true;
                btn.addEventListener('click', function() {
                    var tr = this.closest('tr');
                    if (!tr || !modalDetalles) return;
                    if (detNombre) detNombre.textContent = tr.getAttribute('data-nombre') || '';
                    if (detCedula) detCedula.textContent = tr.getAttribute('data-cedula') || '';
                    if (detUsuario) detUsuario.textContent = tr.getAttribute('data-username') || '';
                    if (detClubEntidad) detClubEntidad.textContent = (tr.getAttribute('data-club') || '') + ' / ' + (tr.getAttribute('data-entidad') || '');
                    if (detLastUpdated) detLastUpdated.textContent = tr.getAttribute('data-last-updated') || '—';
                    var modal = bootstrap.Modal.getOrCreateInstance(modalDetalles);
                    modal.show();
                });
            });
        }
        bindDeleteButtons();
        bindDetallesButtons();

        var collapseEl = document.getElementById('formRegistroCollapse');
        var collapseIcon = document.querySelector('.collapse-icon');
        if (collapseEl && collapseIcon) {
            collapseEl.addEventListener('show.bs.collapse', function () { collapseIcon.className = 'fas fa-chevron-down collapse-icon'; });
            collapseEl.addEventListener('hide.bs.collapse', function () { collapseIcon.className = 'fas fa-chevron-up collapse-icon'; });
        }
        var speedDial = document.getElementById('speedDial');
        var speedDialMain = document.getElementById('speedDialMain');
        var speedDialOverlay = document.getElementById('speedDialOverlay');
        var speedDialSync = document.getElementById('speedDialSync');
        var speedDialBadge = document.getElementById('speedDialBadge');
        function closeSpeedDial() {
            if (speedDial) speedDial.classList.remove('is-open');
            if (speedDialMain) speedDialMain.setAttribute('aria-expanded', 'false');
        }
        function updateSpeedDialBadge() {
            if (!speedDialBadge || !syncEl) return;
            var pending = parseInt(syncEl.getAttribute('data-pending'), 10) || 0;
            var cola = parseInt(syncEl.getAttribute('data-cola') || '0', 10) || 0;
            if (pending + cola > 0) {
                speedDialBadge.classList.add('is-visible');
                speedDialBadge.setAttribute('aria-label', (pending + cola) + ' pendiente(s) de subir');
            } else {
                speedDialBadge.classList.remove('is-visible');
                speedDialBadge.setAttribute('aria-label', '');
            }
        }
        if (speedDialMain) {
            speedDialMain.addEventListener('click', function () {
                var open = speedDial.classList.toggle('is-open');
                speedDialMain.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }
        if (speedDialOverlay) {
            speedDialOverlay.addEventListener('click', closeSpeedDial);
        }
        if (speedDialSync) {
            speedDialSync.addEventListener('click', function () {
                closeSpeedDial();
            });
        }
        document.querySelectorAll('.desktop-speed-dial-new-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                if (window.innerWidth < 768) {
                    e.preventDefault();
                    closeSpeedDial();
                    if (collapseEl) {
                        var collapse = bootstrap.Collapse.getInstance(collapseEl) || new bootstrap.Collapse(collapseEl, { toggle: true });
                        collapse.show();
                    }
                    var card = document.querySelector('.desktop-form-card');
                    if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
        updateSpeedDialBadge();
    })();
    </script>
</main></body></html>
