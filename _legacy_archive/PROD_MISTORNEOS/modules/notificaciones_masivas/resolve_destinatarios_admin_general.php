<?php
/**
 * Resuelve la lista de destinatarios para notificaciones masivas (admin_general)
 * según tipo_filtro, alcance_filtro, admin_ids, torneo_id.
 * Devuelve array de destinatarios con id, nombre, email, celular, telegram_chat_id, sexo, club_nombre, identificador.
 */
if (!defined('APP_BOOTSTRAPPED')) {
    require_once __DIR__ . '/../../config/bootstrap.php';
}
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/ClubHelper.php';

function notif_resolve_destinatarios_admin_general(string $tipo_filtro, string $alcance_filtro, array $admin_ids, int $torneo_id): array {
    $pdo = DB::pdo();
    $destinatarios = [];

    if ($tipo_filtro === 'admins_club') {
        if ($alcance_filtro === 'todos') {
            $stmt = $pdo->query("
                SELECT u.id, u.nombre, u.email, u.celular, u.telegram_chat_id, u.sexo, c.nombre as club_nombre
                FROM usuarios u
                LEFT JOIN clubes c ON u.club_id = c.id
                WHERE u.role = 'admin_club' AND (u.status = 'approved' OR u.status = 'pending' OR u.status = 1)
                ORDER BY c.nombre, u.nombre ASC
            ");
            $destinatarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif (!empty($admin_ids)) {
            $admin_ids = array_map('intval', array_filter($admin_ids));
            if (empty($admin_ids)) return [];
            $ph = implode(',', array_fill(0, count($admin_ids), '?'));
            $stmt = $pdo->prepare("
                SELECT u.id, u.nombre, u.email, u.celular, u.telegram_chat_id, u.sexo, c.nombre as club_nombre
                FROM usuarios u
                LEFT JOIN clubes c ON u.club_id = c.id
                WHERE u.role = 'admin_club' AND u.id IN ($ph)
                ORDER BY c.nombre, u.nombre ASC
            ");
            $stmt->execute($admin_ids);
            $destinatarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } elseif ($tipo_filtro === 'usuarios_admins') {
        $club_ids = [];
        if ($alcance_filtro === 'todos') {
            $stmt = $pdo->query("SELECT id FROM clubes WHERE estatus = 1");
            $club_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } elseif (!empty($admin_ids)) {
            $admin_ids = array_map('intval', array_filter($admin_ids));
            $ph = implode(',', array_fill(0, count($admin_ids), '?'));
            $stmt = $pdo->prepare("SELECT club_id FROM usuarios WHERE id IN ($ph) AND role = 'admin_club'");
            $stmt->execute($admin_ids);
            $admin_club_ids = array_filter($stmt->fetchAll(PDO::FETCH_COLUMN));
            foreach ($admin_club_ids as $cid) {
                $supervised = ClubHelper::getClubesSupervised((int)$cid);
                $club_ids = array_merge($club_ids, $supervised);
            }
            $club_ids = array_unique($club_ids);
        }
        if (!empty($club_ids)) {
            $ph = implode(',', array_fill(0, count($club_ids), '?'));
            $stmt = $pdo->prepare("
                SELECT u.id, u.nombre, u.email, u.celular, u.telegram_chat_id, u.sexo, c.nombre as club_nombre
                FROM usuarios u
                LEFT JOIN clubes c ON u.club_id = c.id
                WHERE u.role = 'usuario' AND (u.status = 'approved' OR u.status = 1) AND u.club_id IN ($ph)
                ORDER BY c.nombre, u.nombre ASC
            ");
            $stmt->execute($club_ids);
            $destinatarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } elseif ($tipo_filtro === 'inscritos_torneo' && $torneo_id > 0) {
        $stmt = $pdo->prepare("
            SELECT u.id, u.nombre, u.email, u.celular, u.telegram_chat_id, u.sexo, c.nombre as club_nombre,
                   u.id as identificador
            FROM inscritos i
            INNER JOIN usuarios u ON i.id_usuario = u.id
            LEFT JOIN clubes c ON u.club_id = c.id
            WHERE i.torneo_id = ?
            ORDER BY u.nombre ASC
        ");
        $stmt->execute([$torneo_id]);
        $destinatarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    foreach ($destinatarios as &$d) {
        if (!isset($d['identificador'])) $d['identificador'] = $d['id'] ?? '';
        if (!isset($d['sexo'])) $d['sexo'] = 'M';
    }
    unset($d);
    return $destinatarios;
}
