<?php
/**
 * Invitación de clubes al torneo actual.
 * Lista clubes del directorio de clubes para que el usuario seleccione a cuáles invitar.
 * Requiere torneo_id en la URL (desde el panel de control).
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../lib/app_helpers.php';
require_once __DIR__ . '/../lib/Pagination.php';
require_once __DIR__ . '/../lib/InvitationJoinResolver.php';
require_once __DIR__ . '/../public/simple_image_config.php';

Auth::requireRole(['admin_general', 'admin_torneo', 'admin_club']);

$torneo_id = isset($_GET['torneo_id']) ? (int)$_GET['torneo_id'] : 0;
$success_message = (isset($_GET['success']) && $_GET['success'] === '1') ? ($_GET['msg'] ?? 'Operación correcta.') : ($_GET['success'] ?? null);
$error_message = $_GET['error'] ?? null;
$torneo = null;
$clubs_list = [];
$pagination = null;
$ya_invitados = [];
$invitaciones_por_club = [];

if ($torneo_id <= 0) {
    $error_message = 'Debe indicar un torneo. Use el panel de control del torneo para acceder a Invitación de clubes.';
} else {
    try {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("SELECT id, nombre, fechator FROM tournaments WHERE id = ?");
        $stmt->execute([$torneo_id]);
        $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$torneo) {
            $error_message = 'Torneo no encontrado.';
        } else {
            if (!Auth::canAccessTournament($torneo_id)) {
                $error_message = 'No tiene permisos para invitar clubes a este torneo.';
                $torneo = null;
            } else {
                $tb_inv = defined('TABLE_INVITATIONS') ? TABLE_INVITATIONS : 'invitaciones';
                $cols_inv = $pdo->query("SHOW COLUMNS FROM {$tb_inv}")->fetchAll(PDO::FETCH_COLUMN);
                $sel_id_directorio = in_array('id_directorio_club', $cols_inv, true) ? ', i.id_directorio_club' : '';
                $stmt = $pdo->prepare("
                    SELECT i.id, i.club_id, i.token{$sel_id_directorio} FROM {$tb_inv} i
                    INNER JOIN clubes c ON c.id = i.club_id
                    WHERE i.torneo_id = ?
                ");
                $stmt->execute([$torneo_id]);
                $rows_inv = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $ya_invitados = array_map('intval', array_column($rows_inv, 'club_id'));
                $cols_dc = @$pdo->query("SHOW COLUMNS FROM directorio_clubes LIKE 'id_usuario'")->fetchAll();
                $has_id_usuario = !empty($cols_dc);
                foreach ($rows_inv as $r) {
                    $req_registro = true;
                    $id_dc = isset($r['id_directorio_club']) ? (int)$r['id_directorio_club'] : 0;
                    if ($id_dc <= 0 && !empty($r['club_id'])) {
                        $st = $pdo->prepare("SELECT c.nombre FROM clubes c WHERE c.id = ?");
                        $st->execute([$r['club_id']]);
                        $cn = $st->fetch(PDO::FETCH_ASSOC);
                        if ($cn) {
                            $st2 = $pdo->prepare("SELECT id FROM directorio_clubes WHERE nombre = ? LIMIT 1");
                            $st2->execute([$cn['nombre']]);
                            $dr = $st2->fetch(PDO::FETCH_ASSOC);
                            if ($dr) $id_dc = (int)$dr['id'];
                        }
                    }
                    if ($id_dc > 0 && $has_id_usuario) {
                        $st = $pdo->prepare("SELECT id_usuario FROM directorio_clubes WHERE id = ? LIMIT 1");
                        $st->execute([$id_dc]);
                        $ur = $st->fetch(PDO::FETCH_ASSOC);
                        if ($ur && isset($ur['id_usuario']) && $ur['id_usuario'] !== null && (string)$ur['id_usuario'] !== '') {
                            $req_registro = false;
                        }
                    }
                    $invitaciones_por_club[(int)$r['club_id']] = [
                        'id' => (int)$r['id'],
                        'token' => $r['token'],
                        'requiere_registro' => $req_registro
                    ];
                }

                $total = (int) $pdo->query("SELECT COUNT(*) FROM directorio_clubes")->fetchColumn();
                $current_page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
                $per_page_val = (int)($_GET['per_page'] ?? 0);
                $per_page = ($per_page_val >= 10 && $per_page_val <= 100) ? $per_page_val : 25;
                $pagination = new Pagination($total, $current_page, $per_page);
                $stmt = $pdo->prepare("
                    SELECT id, nombre, direccion, delegado, telefono, email, logo, estatus
                    FROM directorio_clubes
                    ORDER BY nombre ASC
                    LIMIT " . $pagination->getLimit() . " OFFSET " . $pagination->getOffset()
                );
                $stmt->execute();
                $clubs_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {
        $error_message = 'Error al cargar datos: ' . $e->getMessage();
    }
}

// POST: quitar invitaciones y/o crear invitaciones para los clubes seleccionados
if ($torneo && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'invitar_seleccionados') {
    // Evitar que cualquier salida accidental rompa el redirect (regla de oro: no echo/print_r antes de Location)
    ob_start();

    // Redirección RELATIVA para que en producción (proxy/rewrite) vuelva al mismo entry point y se cargue el layout con CSS
    $build_redirect = function (array $params) {
        $q = http_build_query($params);
        return 'index.php?' . $q;
    };

    try {
        CSRF::validate();
    } catch (Throwable $e) {
        if (function_exists('error_log')) {
            error_log('Invitacion_clubes CSRF: ' . $e->getMessage());
        }
        @ob_end_clean();
        header('Location: ' . $build_redirect(['page' => 'invitacion_clubes', 'torneo_id' => $torneo_id, 'error' => 'Sesión inválida o token expirado. Vuelva a intentar.']));
        exit;
    }

    $messages = [];
    $params = ['page' => 'invitacion_clubes', 'torneo_id' => $torneo_id];

    // Quitar invitaciones (desmarcar): eliminar las seleccionadas
    $quitar_ids = isset($_POST['quitar_inv']) && is_array($_POST['quitar_inv'])
        ? array_map('intval', array_filter($_POST['quitar_inv'])) : [];
    if (!empty($quitar_ids)) {
        try {
            $tb_inv = defined('TABLE_INVITATIONS') ? TABLE_INVITATIONS : 'invitaciones';
            $pdo = DB::pdo();
            $deleted = 0;
            foreach ($quitar_ids as $inv_id) {
                $stmt = $pdo->prepare("SELECT id, torneo_id FROM {$tb_inv} WHERE id = ? AND torneo_id = ?");
                $stmt->execute([$inv_id, $torneo_id]);
                if ($stmt->fetch() && Auth::canAccessTournament($torneo_id)) {
                    $del = $pdo->prepare("DELETE FROM {$tb_inv} WHERE id = ?");
                    if ($del->execute([$inv_id])) $deleted++;
                }
            }
            if ($deleted > 0) {
                $messages[] = $deleted === 1 ? 'Se quitó 1 invitación.' : "Se quitaron {$deleted} invitaciones.";
            }
        } catch (Exception $e) {
            $messages[] = 'Error al quitar invitaciones: ' . $e->getMessage();
        }
    }

    $ids_directorio = isset($_POST['directorio_ids']) && is_array($_POST['directorio_ids'])
        ? array_map('intval', array_filter($_POST['directorio_ids'])) : [];
    $acceso1 = $_POST['acceso1'] ?? null;
    $acceso2 = $_POST['acceso2'] ?? null;
    if (empty($acceso1) || empty($acceso2)) {
        $fechator = $torneo['fechator'] ?? date('Y-m-d');
        $acceso1 = date('Y-m-d', strtotime($fechator . ' -30 days'));
        $acceso2 = date('Y-m-d', strtotime($fechator . ' +7 days'));
    }
    if ($acceso1 > $acceso2) {
        $acceso2 = $acceso1;
    }

    // Si solo se quitaron invitaciones (sin agregar nuevas), redirigir ya
    if (empty($ids_directorio)) {
        $params['success'] = '1';
        if (!empty($messages)) {
            $params['msg'] = implode(' ', $messages);
        }
        @ob_end_clean();
        header('Location: ' . $build_redirect($params));
        exit;
    }

    $tb_inv = defined('TABLE_INVITATIONS') ? TABLE_INVITATIONS : 'invitaciones';
    $creadas = 0;
    $omitidas = 0;
    $errores = [];
    $pdo = DB::pdo();

    // Resolver admin_club_id (obligatorio en producción si la tabla tiene la columna sin default)
    $admin_club_id = 0;
    $u = Auth::user();
    if ($u && ($u['role'] ?? '') === 'admin_club') {
        $admin_club_id = Auth::id();
    } else {
        $stmt_org = $pdo->prepare("SELECT club_responsable FROM tournaments WHERE id = ? LIMIT 1");
        $stmt_org->execute([$torneo_id]);
        $row_t = $stmt_org->fetch(PDO::FETCH_ASSOC);
        if ($row_t && !empty($row_t['club_responsable'])) {
            $org_id = (int) $row_t['club_responsable'];
            $stmt_admin = $pdo->prepare("SELECT admin_user_id FROM organizaciones WHERE id = ? LIMIT 1");
            $stmt_admin->execute([$org_id]);
            $admin_user_id = $stmt_admin->fetchColumn();
            if ($admin_user_id !== false && $admin_user_id !== null && (int)$admin_user_id > 0) {
                $admin_club_id = (int) $admin_user_id;
            }
        }
        if ($admin_club_id <= 0) {
            $admin_club_id = Auth::id();
        }
    }
    if ($admin_club_id <= 0) {
        $cols_tb = $pdo->query("SHOW COLUMNS FROM {$tb_inv}")->fetchAll(PDO::FETCH_COLUMN);
        $has_admin_col = in_array('admin_club_id', $cols_tb, true)
            || in_array('admin_club_id', array_map('strtolower', $cols_tb), true);
        if ($has_admin_col) {
            $fallback = $pdo->query("SELECT id FROM usuarios WHERE role = 'admin_club' AND status = 0 LIMIT 1")->fetchColumn();
            if ($fallback === false || $fallback === null || (int)$fallback <= 0) {
                $fallback = @$pdo->query("SELECT id FROM usuarios WHERE role = 'admin_club' AND estatus = 0 LIMIT 1")->fetchColumn();
            }
            $admin_club_id = ($fallback !== false && $fallback !== null && (int)$fallback > 0) ? (int)$fallback : 0;
        }
    }
    // Si la tabla exige admin_club_id y seguimos en 0, usar el usuario actual para no fallar el INSERT
    $cols_inv_check = $pdo->query("SHOW COLUMNS FROM {$tb_inv}")->fetchAll(PDO::FETCH_COLUMN);
    $need_admin_club_id = in_array('admin_club_id', $cols_inv_check, true);
    if ($need_admin_club_id && $admin_club_id <= 0) {
        $admin_club_id = Auth::id();
    }

    try {
        $pdo->beginTransaction();
        foreach ($ids_directorio as $dir_id) {
            $stmt = $pdo->prepare("SELECT id, nombre, direccion, delegado, telefono, email FROM directorio_clubes WHERE id = ?");
            $stmt->execute([$dir_id]);
            $dir = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$dir) continue;
            $nombre = $dir['nombre'] ?? '';
            $stmt = $pdo->prepare("SELECT id FROM clubes WHERE nombre = ? AND estatus = 1 LIMIT 1");
            $stmt->execute([$nombre]);
            $club_row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$club_row) {
                $stmt = $pdo->prepare("INSERT INTO clubes (nombre, direccion, delegado, telefono, email, estatus) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt->execute([
                    $nombre,
                    $dir['direccion'] ?? null,
                    $dir['delegado'] ?? null,
                    $dir['telefono'] ?? null,
                    $dir['email'] ?? null
                ]);
                $club_id = (int) $pdo->lastInsertId();
            } else {
                $club_id = (int) $club_row['id'];
            }
            $stmt = $pdo->prepare("SELECT id FROM {$tb_inv} WHERE torneo_id = ? AND club_id = ?");
            $stmt->execute([$torneo_id, $club_id]);
            if ($stmt->fetch()) {
                $omitidas++;
                continue;
            }
            $token = bin2hex(random_bytes(32));
            $usuario_creador = (Auth::user() && isset(Auth::user()['id'])) ? (string) Auth::user()['id'] : '';
            $inv_delegado = $dir['delegado'] ?? null;
            $inv_email = $dir['email'] ?? null;
            $club_tel = $dir['telefono'] ?? null;
            // Usar solo columnas que existan en la tabla invitaciones (estructura real, sin inventar)
            $cols_inv = $pdo->query("SHOW COLUMNS FROM {$tb_inv}")->fetchAll(PDO::FETCH_COLUMN);
            $campos = [
                'torneo_id' => $torneo_id,
                'club_id' => $club_id,
                'invitado_delegado' => $inv_delegado,
                'invitado_email' => $inv_email,
                'acceso1' => $acceso1,
                'acceso2' => $acceso2,
                'usuario' => $usuario_creador,
                'club_email' => $inv_email,
                'club_telefono' => $club_tel,
                'club_delegado' => $inv_delegado,
                'token' => $token,
                'estado' => 'activa'
            ];
            if (in_array('id_directorio_club', $cols_inv, true)) {
                $campos['id_directorio_club'] = $dir_id;
            }
            foreach ($cols_inv as $col_name) {
                if (strtolower((string)$col_name) === 'admin_club_id') {
                    $campos[$col_name] = $admin_club_id > 0 ? $admin_club_id : Auth::id();
                    break;
                }
            }
            $cols = array_values(array_intersect($cols_inv, array_keys($campos)));
            $vals = array_map(function ($c) use ($campos) { return $campos[$c]; }, $cols);
            if (!empty($cols)) {
                $placeholders = implode(', ', array_fill(0, count($cols), '?'));
                $stmt = $pdo->prepare("INSERT INTO {$tb_inv} (" . implode(', ', $cols) . ") VALUES ({$placeholders})");
                $stmt->execute($vals);
            } else {
                $stmt = $pdo->prepare("INSERT INTO {$tb_inv} (torneo_id, club_id, acceso1, acceso2, usuario, token, estado) VALUES (?, ?, ?, ?, ?, ?, 'activa')");
                $stmt->execute([$torneo_id, $club_id, $acceso1, $acceso2, $usuario_creador, $token]);
            }
            $creadas++;
        }
        $pdo->commit();

        $params = ['page' => 'invitacion_clubes', 'torneo_id' => $torneo_id, 'success' => '1'];
        $msg_parts = $messages;
        if ($creadas > 0) {
            $msg_parts[] = $creadas === 1 ? 'Se creó 1 invitación.' : "Se crearon {$creadas} invitaciones.";
        }
        if ($omitidas > 0) {
            $msg_parts[] = "{$omitidas} ya estaban invitados.";
        }
        if (!empty($msg_parts)) {
            $params['msg'] = implode(' ', $msg_parts);
        }
        if (!empty($errores)) {
            $params['error'] = implode(' ', $errores);
        }
        @ob_end_clean();
        header('Location: ' . $build_redirect($params));
        exit;
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (function_exists('error_log')) {
            error_log('Invitacion_clubes: ' . $e->getMessage() . ' [' . $e->getFile() . ':' . $e->getLine() . ']');
        }
        @ob_end_clean();
        $params = ['page' => 'invitacion_clubes', 'torneo_id' => $torneo_id, 'error' => 'Error al crear invitaciones. Revise logs. ' . $e->getMessage()];
        header('Location: ' . $build_redirect($params));
        exit;
    }
}

$page_title = $torneo ? ('Invitación de clubes - ' . ($torneo['nombre'] ?? '')) : 'Invitación de clubes';
$fechator_fmt = $torneo && !empty($torneo['fechator']) ? date('d/m/Y', strtotime($torneo['fechator'])) : '';
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="fas fa-paper-plane me-2"></i>Invitación de clubes
                    </h1>
                    <p class="text-muted mb-0">
                        <?php if ($torneo): ?>
                            Torneo: <strong><?= htmlspecialchars($torneo['nombre'] ?? '') ?></strong>
                            <?php if ($fechator_fmt): ?> — <?= $fechator_fmt ?><?php endif; ?>
                        <?php else: ?>
                            Seleccione clubes del directorio para invitarlos al torneo.
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <?php if ($torneo): ?>
                        <a href="<?= htmlspecialchars(AppHelpers::dashboard('invitations')) ?>&filter_torneo=<?= $torneo_id ?>&return_to=invitacion_clubes&torneo_id=<?= $torneo_id ?>" class="btn btn-outline-primary">
                            <i class="fas fa-list me-2"></i>Ver invitaciones del torneo
                        </a>
                        <?php
                        $back_url = 'index.php?page=torneo_gestion&action=panel&torneo_id=' . $torneo_id;
                        if (defined('APP_ROOT') && strpos($_SERVER['REQUEST_URI'] ?? '', 'admin_torneo') !== false) {
                            $back_url = 'admin_torneo.php?action=panel&torneo_id=' . $torneo_id;
                        }
                        ?>
                        <a href="<?= htmlspecialchars($back_url) ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver al panel
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($torneo && !empty($clubs_list)): ?>
                <div class="card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span><i class="fas fa-address-book me-2"></i>Clubes del directorio — marque los que desea invitar; en los ya invitados marque el cuadro para <strong>quitar</strong> la invitación</span>
                        <span class="badge bg-secondary"><?= count($ya_invitados) ?> ya invitados a este torneo</span>
                    </div>
                    <form method="post" action="index.php?page=invitacion_clubes&torneo_id=<?= (int)$torneo_id ?>" id="formInvitar">
                        <?= CSRF::input() ?>
                        <input type="hidden" name="action" value="invitar_seleccionados">
                        <input type="hidden" name="acceso1" value="<?= htmlspecialchars(date('Y-m-d', strtotime(($torneo['fechator'] ?? 'today') . ' -30 days'))) ?>">
                        <input type="hidden" name="acceso2" value="<?= htmlspecialchars(date('Y-m-d', strtotime(($torneo['fechator'] ?? 'today') . ' +7 days'))) ?>">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 44px;" class="text-center">
                                                <input type="checkbox" id="selectAll" class="form-check-input" title="Marcar todos">
                                            </th>
                                            <th style="width: 50px;">Logo</th>
                                            <th>Nombre</th>
                                            <th>Delegado</th>
                                            <th>Teléfono</th>
                                            <th class="text-center">Estado</th>
                                            <th class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $club_ids_by_name = [];
                                        try {
                                            $stmt = $pdo->prepare("SELECT id, nombre FROM clubes WHERE estatus = 1");
                                            $stmt->execute();
                                            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                $club_ids_by_name[$r['nombre']] = (int)$r['id'];
                                            }
                                        } catch (Exception $e) {}
                                        $url_base_join = rtrim(AppHelpers::getPublicUrl(), '/') . '/join';
                                        $url_base_inv = rtrim(AppHelpers::getPublicUrl(), '/') . '/invitation/digital?token=';
                                        $torneo_nombre_inv = $torneo['nombre'] ?? '';
                                        foreach ($clubs_list as $row):
                                            $club_id_linked = $club_ids_by_name[$row['nombre'] ?? ''] ?? null;
                                            $ya_invitado = $club_id_linked && in_array($club_id_linked, $ya_invitados, true);
                                            $inv_data = $ya_invitado && $club_id_linked ? ($invitaciones_por_club[$club_id_linked] ?? null) : null;
                                            if ($inv_data) {
                                                $url_join = InvitationJoinResolver::buildJoinUrl($inv_data['token']);
                                                $url_tarjeta = $url_base_inv . urlencode($inv_data['token']);
                                                $requiere_reg = !empty($inv_data['requiere_registro']);
                                                $msg_wa = "Estimado delegado de " . ($row['nombre'] ?? '') . ", le invitamos a " . $torneo_nombre_inv . ". Use este enlace para acceder: " . $url_join;
                                                if ($requiere_reg) {
                                                    $msg_wa .= " — Si aún no está registrado, el primer paso es completar el registro en ese mismo enlace.";
                                                } else {
                                                    $msg_wa .= " — Acceso directo al formulario de inscripción.";
                                                }
                                                $url_wa = 'https://api.whatsapp.com/send?text=' . rawurlencode($msg_wa);
                                                $url_tg = 'https://t.me/share/url?url=' . rawurlencode($url_join) . '&text=' . rawurlencode($msg_wa);
                                            }
                                        ?>
                                            <tr class="<?= $ya_invitado ? 'table-warning' : '' ?>">
                                                <td class="text-center align-middle">
                                                    <?php if (!$ya_invitado): ?>
                                                        <input type="checkbox" name="directorio_ids[]" value="<?= (int)$row['id'] ?>" class="form-check-input cb-club">
                                                    <?php else: ?>
                                                        <input type="checkbox" name="quitar_inv[]" value="<?= (int)$inv_data['id'] ?>" class="form-check-input cb-quitar" title="Marcar para quitar esta invitación">
                                                    <?php endif; ?>
                                                </td>
                                                <td class="align-middle"><?= displayClubLogoTable($row) ?></td>
                                                <td class="align-middle">
                                                    <strong><?= htmlspecialchars($row['nombre'] ?? '') ?></strong>
                                                    <?php if (!empty($row['direccion'])): ?>
                                                        <br><small class="text-muted text-truncate d-inline-block" style="max-width: 220px;"><?= htmlspecialchars($row['direccion']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="align-middle small"><?= htmlspecialchars($row['delegado'] ?? '—') ?></td>
                                                <td class="align-middle small"><?= htmlspecialchars($row['telefono'] ?? '—') ?></td>
                                                <td class="text-center align-middle">
                                                    <span class="badge bg-<?= $row['estatus'] ? 'success' : 'secondary' ?>"><?= $row['estatus'] ? 'Activo' : 'Inactivo' ?></span>
                                                    <?php if ($ya_invitado): ?>
                                                        <br><span class="badge bg-info mt-1">Ya invitado</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center align-middle">
                                                    <?php if ($ya_invitado && $inv_data): ?>
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <a href="index.php?page=invitations&action=edit&id=<?= $inv_data['id'] ?>&filter_torneo=<?= $torneo_id ?>&return_to=invitacion_clubes&torneo_id=<?= $torneo_id ?>" class="btn btn-outline-warning" title="Editar Invitación"><i class="fas fa-edit"></i></a>
                                                            <button type="button" class="btn btn-outline-primary btn-copy-join" data-url="<?= htmlspecialchars($url_join) ?>" title="Copiar enlace de acceso (registro o inscripción)"><i class="fas fa-link"></i></button>
                                                            <a href="<?= htmlspecialchars($url_wa) ?>" class="btn btn-success" target="_blank" rel="noopener noreferrer" title="Enviar por WhatsApp"><i class="fab fa-whatsapp"></i></a>
                                                            <a href="<?= htmlspecialchars($url_tg) ?>" class="btn btn-info" target="_blank" rel="noopener noreferrer" title="Enviar por Telegram"><i class="fab fa-telegram"></i></a>
                                                        </div>
                                                        <?php if (!empty($inv_data['requiere_registro'])): ?>
                                                            <br><small class="text-muted" title="El delegado debe registrarse primero">Requiere registro</small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        —
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <button type="submit" class="btn btn-primary" id="btnInvitar">
                                    <i class="fas fa-paper-plane me-2"></i>Invitar clubes seleccionados
                                </button>
                            </div>
                            <?php if ($pagination): ?>
                                <div><?= $pagination->render() ?></div>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                                                <script>
                                                document.getElementById('selectAll').addEventListener('change', function() {
                                                    document.querySelectorAll('.cb-club').forEach(function(cb) { cb.checked = this.checked; }.bind(this));
                                                });
                                                document.getElementById('formInvitar').addEventListener('submit', function() {
                                                    var n = document.querySelectorAll('.cb-club:checked').length;
                                                    var q = document.querySelectorAll('.cb-quitar:checked').length;
                                                    if (n === 0 && q === 0) {
                                                        alert('Seleccione al menos un club para invitar o marque "Quitar invitación" en los que desee desmarcar.');
                                                        return false;
                                                    }
                                                    document.getElementById('btnInvitar').disabled = true;
                                                });
                                                document.querySelectorAll('.btn-copy-join').forEach(function(btn) {
                                                    btn.addEventListener('click', function() {
                                                        var url = this.getAttribute('data-url');
                                                        if (url && navigator.clipboard && navigator.clipboard.writeText) {
                                                            navigator.clipboard.writeText(url).then(function() { alert('Enlace copiado.'); }).catch(function() { prompt('Copie el enlace:', url); });
                                                        } else {
                                                            prompt('Copie el enlace:', url);
                                                        }
                                                    });
                                                });
                                                </script>
            <?php elseif ($torneo && empty($clubs_list)): ?>
                <div class="card">
                    <div class="card-body text-center text-muted py-5">
                        <i class="fas fa-address-book fa-3x mb-3"></i>
                        <p class="mb-0">No hay clubes en el directorio. Agregue clubes desde el <a href="<?= htmlspecialchars(AppHelpers::dashboard('directorio_clubes')) ?>">Directorio de Clubes</a> (requiere Admin General).</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
