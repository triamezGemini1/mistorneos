<?php
/**
 * Acciones POST del formulario de inscripción por invitación: retirar e inscribir.
 * Solo se incluye cuando method=POST y action=retirar|register_player.
 * Redirige a la misma URL (GET) con mensaje de éxito o error.
 */
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || !in_array($_POST['action'], ['retirar', 'register_player'], true)) {
    return;
}

if (!class_exists('InvitationRegisterContext')) {
    require_once __DIR__ . '/../lib/InvitationRegisterContext.php';
}

$data = InvitationRegisterContext::load();
extract($data);

$base = $base !== '' ? rtrim($base, '/') : rtrim(class_exists('AppHelpers') ? AppHelpers::getPublicUrl() : ($GLOBALS['APP_CONFIG']['app']['base_url'] ?? ''), '/');
$same_url = $base . '/invitation/register?' . http_build_query(['token' => $token, 'torneo' => $torneo_id, 'club' => $club_id]);

if ($_POST['action'] === 'retirar') {
    $cur = Auth::user();
    $can = $cur && ($form_enabled ?? false);
    $id_r = (int)($_POST['id_inscripcion'] ?? 0);
    $tid = (int)($_POST['torneo_id'] ?? $torneo_id);
    $cid = (int)($_POST['club_id'] ?? $club_id);
    $error_message = '';
    $success_message = '';
    if ($can && $id_r > 0) {
        $inWindow = true;
        if ($invitation_data) {
            $now = new DateTime();
            $st = new DateTime($invitation_data['acceso1']);
            $ed = new DateTime($invitation_data['acceso2']);
            $inWindow = ($now >= $st && $now <= $ed);
        }
        if ($inWindow) {
            try {
                $pdo = DB::pdo();
                $stmt = $pdo->prepare("DELETE FROM inscritos WHERE id = ? AND torneo_id = ? AND id_club = ?");
                $stmt->execute([$id_r, $tid, $cid]);
                if ($stmt->rowCount() > 0) {
                    $success_message = "Inscripción retirada correctamente";
                } else {
                    $error_message = "No se encontró la inscripción o ya fue retirada.";
                }
            } catch (Throwable $e) {
                $error_message = "Error al retirar: " . $e->getMessage();
            }
        } else {
            $error_message = "Fuera del período permitido para retirar inscripciones.";
        }
    } else {
        if (!$can) {
            $error_message = "No tiene permiso para retirar inscripciones.";
        } elseif ($id_r <= 0) {
            $error_message = "Datos de inscripción inválidos.";
        }
    }
    $params = ['token' => $token, 'torneo' => $torneo_id, 'club' => $club_id];
    if ($success_message !== '') $params['success'] = $success_message;
    if ($error_message !== '') $params['error'] = $error_message;
    header('Location: ' . $base . '/invitation/register?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
    exit;
}

if ($_POST['action'] === 'register_player') {
    $current_user = Auth::user();
    $is_admin_general = $current_user && $current_user['role'] === 'admin_general';
    $is_admin_torneo = $current_user && $current_user['role'] === 'admin_torneo';
    $is_admin_club = $current_user && $current_user['role'] === 'admin_club';
    $id_vinculado_inv = $invitation_data ? (int)($invitation_data['id_usuario_vinculado'] ?? 0) : 0;
    $es_usuario_vinculado = $current_user && $id_vinculado_inv > 0 && (int)$current_user['id'] === $id_vinculado_inv;
    $puede_inscribir = $is_admin_general || $is_admin_torneo || $is_admin_club || $es_usuario_vinculado;
    $dentro_vigencia = $invitation_data && (new DateTime() >= new DateTime($invitation_data['acceso1']) && new DateTime() <= new DateTime($invitation_data['acceso2']));
    $error_message = '';
    $success_message = '';

    if ($puede_inscribir && $dentro_vigencia) {
        try {
            $cedula = preg_replace('/\D/', '', trim($_POST['cedula'] ?? ''));
            $nombre = trim($_POST['nombre'] ?? '');
            $sexo = in_array($_POST['sexo'] ?? '', ['M', 'F', 'O'], true) ? $_POST['sexo'] : 'M';
            $telefono = trim($_POST['telefono'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $fechnac = !empty($_POST['fechnac']) ? $_POST['fechnac'] : null;
            $nacionalidad = in_array($_POST['nacionalidad'] ?? '', ['V', 'E', 'J', 'P'], true) ? $_POST['nacionalidad'] : 'V';
            $id_club_insc = !empty($_POST['club_id']) ? (int)$_POST['club_id'] : (int)$club_id;
            $id_usuario_enviado = !empty($_POST['id_usuario']) ? (int)$_POST['id_usuario'] : 0;
            $torneo_id = (int)$torneo_id;

            $pdo = DB::pdo();

            if ($id_usuario_enviado > 0) {
                $stmt = $pdo->prepare("SELECT id, cedula, nacionalidad, nombre FROM usuarios WHERE id = ? LIMIT 1");
                $stmt->execute([$id_usuario_enviado]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$usuario) {
                    throw new Exception('El usuario indicado no existe. Busque de nuevo por cédula.');
                }
                $id_usuario = (int)$usuario['id'];
                try {
                    $st = $pdo->prepare("SELECT username FROM usuarios WHERE id = ? LIMIT 1");
                    $st->execute([$id_usuario]);
                    $row = $st->fetch(PDO::FETCH_ASSOC);
                    $email_actual = $email !== '' ? $email : (($row['username'] ?? '') . '@gmail.com');
                    if ($email_actual === '@gmail.com') $email_actual = 'inv@mistorneos.local';
                    $st = $pdo->prepare("UPDATE usuarios SET nombre = ?, sexo = ?, fechnac = ?, email = ? WHERE id = ?");
                    $st->execute([$nombre, $sexo, $fechnac ?: null, $email_actual, $id_usuario]);
                    $chk = @$pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'celular'");
                    if ($chk && $chk->fetch()) {
                        $pdo->prepare("UPDATE usuarios SET celular = ? WHERE id = ?")->execute([$telefono ?: null, $id_usuario]);
                    }
                } catch (Throwable $e) { /* ignorar */ }
            } else {
                if (empty($cedula) || empty($nombre) || empty($telefono)) {
                    throw new Exception('Los campos cédula, nombre y teléfono son requeridos');
                }
                $id_usuario = null;
                foreach (array_unique([$cedula, $nacionalidad . $cedula]) as $c) {
                    if ($c === '') continue;
                    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE cedula = ? LIMIT 1");
                    $stmt->execute([$c]);
                    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($usuario) {
                        $id_usuario = (int)$usuario['id'];
                        break;
                    }
                }

                if (!$id_usuario) {
                    $partes = preg_split('/\s+/u', trim($nombre), 2);
                    $nombre_part = $partes[0] ?? '';
                    $apellido_part = isset($partes[1]) ? trim($partes[1]) : '';
                    $slug = function ($s) {
                        $s = mb_strtolower($s, 'UTF-8');
                        $s = preg_replace('/[áàäâ]/u', 'a', $s);
                        $s = preg_replace('/[éèëê]/u', 'e', $s);
                        $s = preg_replace('/[íìïî]/u', 'i', $s);
                        $s = preg_replace('/[óòöô]/u', 'o', $s);
                        $s = preg_replace('/[úùüû]/u', 'u', $s);
                        $s = preg_replace('/[ñ]/u', 'n', $s);
                        $s = preg_replace('/[^a-z0-9]/u', '', $s);
                        return $s;
                    };
                    $username_base = $slug($nombre_part);
                    if ($apellido_part !== '') $username_base .= '.' . $slug($apellido_part);
                    if ($username_base === '') $username_base = 'usuario' . $cedula;
                    $username = $username_base;
                    $idx = 0;
                    while (true) {
                        $st = $pdo->prepare("SELECT id FROM usuarios WHERE username = ? LIMIT 1");
                        $st->execute([$username]);
                        if (!$st->fetch()) break;
                        $idx++;
                        $username = $username_base . '_' . $idx;
                    }
                    $password_hash = password_hash($cedula, PASSWORD_DEFAULT);
                    $email_val = $email !== '' ? $email : ($username . '@gmail.com');
                    $cols = "nombre, cedula, nacionalidad, sexo, fechnac, email, username, password_hash, role, club_id, entidad, status";
                    $placeholders = "?, ?, ?, ?, ?, ?, ?, ?, 'usuario', 0, 0, 0";
                    $params = [$nombre, $cedula, $nacionalidad, $sexo, $fechnac ?: null, $email_val, $username, $password_hash];
                    if (isset($GLOBALS['USUARIOS_HAS_CELULAR']) || @$pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'celular'")->fetch()) {
                        $cols .= ", celular";
                        $placeholders .= ", ?";
                        $params[] = $telefono ?: null;
                    }
                    $stmt = $pdo->prepare("INSERT INTO usuarios ({$cols}) VALUES ({$placeholders})");
                    $stmt->execute($params);
                    $id_usuario = (int) $pdo->lastInsertId();
                } else {
                    try {
                        $st = $pdo->prepare("SELECT username FROM usuarios WHERE id = ? LIMIT 1");
                        $st->execute([$id_usuario]);
                        $row = $st->fetch(PDO::FETCH_ASSOC);
                        $email_actual = $email !== '' ? $email : (($row['username'] ?? '') . '@gmail.com');
                        if ($email_actual === '@gmail.com') $email_actual = 'inv@mistorneos.local';
                        $st = $pdo->prepare("UPDATE usuarios SET nombre = ?, sexo = ?, fechnac = ?, email = ? WHERE id = ?");
                        $st->execute([$nombre, $sexo, $fechnac ?: null, $email_actual, $id_usuario]);
                        $chk = @$pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'celular'");
                        if ($chk && $chk->fetch()) {
                            $pdo->prepare("UPDATE usuarios SET celular = ? WHERE id = ?")->execute([$telefono ?: null, $id_usuario]);
                        }
                    } catch (Throwable $e) { /* ignorar */ }
                }
            }

            $stmt = $pdo->prepare("SELECT id FROM inscritos WHERE id_usuario = ? AND torneo_id = ? LIMIT 1");
            $stmt->execute([$id_usuario, $torneo_id]);
            if ($stmt->fetch()) {
                throw new Exception('Ya existe una inscripción para esta cédula en este torneo');
            }

            $inscrito_por = $current_user ? (int)$current_user['id'] : null;
            $nac_insc = in_array($nacionalidad, ['V', 'E', 'J', 'P'], true) ? $nacionalidad : 'V';
            $ced_insc = preg_replace('/\D/', '', (string)$cedula);
            $stmt = $pdo->prepare("
                INSERT INTO inscritos (id_usuario, torneo_id, id_club, estatus, inscrito_por, fecha_inscripcion, nacionalidad, cedula)
                VALUES (?, ?, ?, 'confirmado', ?, NOW(), ?, ?)
            ");
            $stmt->execute([$id_usuario, $torneo_id, $id_club_insc, $inscrito_por, $nac_insc, $ced_insc]);
            $success_message = "Jugador inscrito exitosamente";
        } catch (Throwable $e) {
            $error_message = "Error al inscribir jugador: " . $e->getMessage();
        }
    } else {
        if ($invitation_data && !$dentro_vigencia) {
            $error_message = "El período de inscripción está cerrado";
        } elseif ($current_user) {
            $error_message = in_array($current_user['role'], ['admin_club', 'usuario']) ? "Debe autenticarse como club para inscribir jugadores" : "No tiene permisos para inscribir jugadores";
        } else {
            $error_message = "Debe autenticarse para inscribir jugadores";
        }
    }

    $params = ['token' => $token, 'torneo' => $torneo_id, 'club' => $club_id];
    if ($success_message) $params['success'] = urlencode($success_message);
    if ($error_message) $params['error'] = urlencode($error_message);
    header('Location: ' . $base . '/invitation/register?' . http_build_query($params));
    exit;
}
