<?php
/**
 * Vista: Inscribir Pareja en Sitio (modalidad 4)
 * Mismo procedimiento que equipos pero con 2 jugadores; búsqueda por cédula.
 */
$torneo = $view_data['torneo'] ?? [];
$clubes_disponibles = $view_data['clubes_disponibles'] ?? [];
$parejas_registradas = $view_data['parejas_registradas'] ?? [];
$torneo_iniciado = !empty($view_data['torneo_iniciado']);

$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php']);
$base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';
$api_base_path = (function_exists('AppHelpers') ? AppHelpers::getPublicPath() : '/mistorneos/public/') . 'api/';

require_once __DIR__ . '/../../config/csrf.php';
$csrf_token = class_exists('CSRF') ? CSRF::token() : '';
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
    body { background-color: #f8f9fa; }
    .row-jugador { border: 1px solid #dee2e6; border-radius: 6px; padding: 12px; margin-bottom: 12px; background: #fff; }
    .row-jugador .form-control:read-only { background-color: #e9ecef; }
</style>

<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=index">Gestión de Torneos</a></li>
            <li class="breadcrumb-item"><a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=panel&torneo_id=<?php echo (int)$torneo['id']; ?>"><?php echo htmlspecialchars($torneo['nombre']); ?></a></li>
            <li class="breadcrumb-item active">Inscribir pareja en sitio</li>
        </ol>
    </nav>

    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h2 class="h4 mb-1"><i class="fas fa-handshake text-primary me-2"></i>Inscribir pareja en sitio</h2>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($torneo['nombre']); ?> — 2 jugadores por pareja. Busque por cédula.</p>
                </div>
                <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=gestionar_inscripciones_parejas_fijas&torneo_id=<?php echo (int)$torneo['id']; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Gestionar inscripciones</a>
            </div>
        </div>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <?php if ($torneo_iniciado): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>El torneo ya inició. No se permiten nuevas inscripciones.
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-primary text-white">
            <strong>Nueva pareja</strong> — Busque cada jugador por cédula (al salir del campo se buscan los datos). Nombre de pareja opcional.
        </div>
        <div class="card-body">
            <form method="post" action="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=guardar_pareja_fija&torneo_id=<?php echo (int)$torneo['id']; ?>" id="formPareja">
                <?php if ($csrf_token): ?>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <?php endif; ?>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Club *</label>
                        <select name="id_club" id="id_club" class="form-select" required <?= $torneo_iniciado ? 'disabled' : '' ?>>
                            <option value="">Seleccionar club...</option>
                            <?php foreach ($clubes_disponibles as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Nombre de la pareja (opcional)</label>
                        <input type="text" name="nombre_equipo" class="form-control" maxlength="100" placeholder="Ej: Los Duendes" <?= $torneo_iniciado ? 'readonly' : '' ?>>
                    </div>
                </div>
                <hr>
                <div class="row-jugador">
                    <label class="form-label fw-bold">Jugador 1 *</label>
                    <div class="row g-2">
                        <div class="col-md-1">
                            <label class="form-label small">Nac.</label>
                            <select name="nacionalidad_1" id="nacionalidad_1" class="form-select form-select-sm" <?= $torneo_iniciado ? 'disabled' : '' ?>>
                                <option value="V">V</option><option value="E">E</option><option value="J">J</option><option value="P">P</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Cédula</label>
                            <input type="text" class="form-control form-control-sm" name="cedula_1" id="cedula_1" placeholder="Cédula" maxlength="10" <?= $torneo_iniciado ? 'readonly' : '' ?> onblur="buscarJugadorPareja(1)">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Nombre</label>
                            <input type="text" class="form-control form-control-sm" name="nombre_1" id="nombre_1" placeholder="Se busca al salir de cédula" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Tel.</label>
                            <input type="text" class="form-control form-control-sm" name="telefono_1" id="telefono_1" placeholder="Tel." readonly>
                        </div>
                    </div>
                    <input type="hidden" name="id_usuario_1" id="id_usuario_1" value="">
                </div>
                <div class="row-jugador">
                    <label class="form-label fw-bold">Jugador 2 *</label>
                    <div class="row g-2">
                        <div class="col-md-1">
                            <label class="form-label small">Nac.</label>
                            <select name="nacionalidad_2" id="nacionalidad_2" class="form-select form-select-sm" <?= $torneo_iniciado ? 'disabled' : '' ?>>
                                <option value="V">V</option><option value="E">E</option><option value="J">J</option><option value="P">P</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Cédula</label>
                            <input type="text" class="form-control form-control-sm" name="cedula_2" id="cedula_2" placeholder="Cédula" maxlength="10" <?= $torneo_iniciado ? 'readonly' : '' ?> onblur="buscarJugadorPareja(2)">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Nombre</label>
                            <input type="text" class="form-control form-control-sm" name="nombre_2" id="nombre_2" placeholder="Se busca al salir de cédula" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Tel.</label>
                            <input type="text" class="form-control form-control-sm" name="telefono_2" id="telefono_2" placeholder="Tel." readonly>
                        </div>
                    </div>
                    <input type="hidden" name="id_usuario_2" id="id_usuario_2" value="">
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-success" id="btnGuardarPareja" <?= $torneo_iniciado ? 'disabled' : '' ?>><i class="fas fa-save me-1"></i>Guardar pareja</button>
                    <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=inscribir_pareja_sitio&torneo_id=<?php echo (int)$torneo['id']; ?>" class="btn btn-outline-secondary ms-2">Nueva pareja</a>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($parejas_registradas)): ?>
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header">Parejas inscritas (<?php echo count($parejas_registradas); ?>)</div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                <?php foreach ($parejas_registradas as $pa): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><span class="badge bg-secondary me-2"><?php echo htmlspecialchars($pa['codigo_equipo'] ?? ''); ?></span> <?php echo htmlspecialchars($pa['nombre_equipo'] ?? 'Sin nombre'); ?> — <?php echo htmlspecialchars($pa['jugadores'][0]['nombre'] ?? ''); ?> / <?php echo htmlspecialchars($pa['jugadores'][1]['nombre'] ?? ''); ?></span>
                    <span class="text-muted small"><?php echo htmlspecialchars($pa['nombre_club'] ?? ''); ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
<script>
(function() {
    const TORNEO_ID = <?php echo (int)($torneo['id'] ?? 0); ?>;
    const API_BASE = <?php echo json_encode(rtrim($api_base_path, '/')); ?>;

    function limpiarFila(num) {
        document.getElementById('nombre_' + num).value = '';
        document.getElementById('telefono_' + num).value = '';
        document.getElementById('id_usuario_' + num).value = '';
    }

    window.buscarJugadorPareja = async function(num) {
        var cedulaEl = document.getElementById('cedula_' + num);
        var nacEl = document.getElementById('nacionalidad_' + num);
        var cedula = (cedulaEl && cedulaEl.value || '').trim().replace(/\D/g, '');
        var nacionalidad = (nacEl && nacEl.value) || 'V';
        if (!cedula) {
            limpiarFila(num);
            return;
        }
        try {
            var url = API_BASE + '/search_persona.php?cedula=' + encodeURIComponent(cedula) + '&nacionalidad=' + encodeURIComponent(nacionalidad) + '&torneo_id=' + TORNEO_ID;
            var res = await fetch(url);
            var data = await res.json();
            var accion = (data.accion || data.status || '').toString().toLowerCase();
            if (accion === 'ya_inscrito') {
                alert(data.mensaje || 'El jugador ya está en este torneo.');
                limpiarFila(num);
                cedulaEl.value = '';
                return;
            }
            if (accion === 'error') {
                alert(data.mensaje || data.error || 'Error en la búsqueda.');
                limpiarFila(num);
                return;
            }
            if (accion === 'encontrado_usuario' || accion === 'encontrado_persona' || ((data.encontrado || data.success) && (data.persona || data.data))) {
                var p = data.persona || data.data;
                if (p && p.id) {
                    document.getElementById('nombre_' + num).value = p.nombre || '';
                    document.getElementById('telefono_' + num).value = (p.celular || p.telefono || '');
                    document.getElementById('id_usuario_' + num).value = p.id;
                    return;
                }
            }
            if (accion === 'nuevo' || accion === 'no_encontrado') {
                alert('No encontrado en la plataforma. Para inscribir por invitación puede completar datos y crear usuario allí.');
                limpiarFila(num);
                return;
            }
            limpiarFila(num);
        } catch (e) {
            console.error(e);
            alert('Error al buscar por cédula.');
            limpiarFila(num);
        }
    };

    document.getElementById('cedula_1').addEventListener('input', function() { this.value = this.value.replace(/[^0-9]/g, ''); });
    document.getElementById('cedula_2').addEventListener('input', function() { this.value = this.value.replace(/[^0-9]/g, ''); });

    document.getElementById('formPareja').addEventListener('submit', function(e) {
        var id1 = document.getElementById('id_usuario_1').value.trim();
        var id2 = document.getElementById('id_usuario_2').value.trim();
        if (!id1 || !id2) {
            e.preventDefault();
            alert('Debe buscar e indicar ambos jugadores por cédula (salga del campo cédula de cada uno).');
            return false;
        }
        if (id1 === id2) {
            e.preventDefault();
            alert('Los dos jugadores deben ser distintos.');
            return false;
        }
    });
})();
</script>
