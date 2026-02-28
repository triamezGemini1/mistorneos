<?php
/**
 * Acceso a Organizaciones: listado por entidad → detalle organización → detalle club con afiliados
 */

if (!defined('APP_BOOTSTRAPPED')) {
    require_once __DIR__ . '/../config/bootstrap.php';
}
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

Auth::requireRole(['admin_club', 'admin_general']);

$current_user = Auth::user();
$is_admin_general = Auth::isAdminGeneral();
$organizacion_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : null;
$entidad_id = isset($_GET['entidad_id']) ? (int)$_GET['entidad_id'] : null;

// Admin organización: si entran sin id, redirigir a su organización
if (!$is_admin_general && !$organizacion_id) {
    $org_id = Auth::getUserOrganizacionId();
    if ($org_id) {
        header('Location: index.php?page=organizaciones&id=' . $org_id);
        exit;
    }
    header('Location: index.php?page=mi_organizacion');
    exit;
}

$pdo = DB::pdo();

// ---------- Vista: Detalle de club (con afiliados) ----------
if ($organizacion_id && $club_id) {
    require_once __DIR__ . '/../lib/ClubHelper.php';
    $club = null;
    $organizacion = null;
    $afiliados = [];
    if ($is_admin_general) {
        $stmt = $pdo->prepare("SELECT * FROM clubes WHERE id = ? AND estatus = 1");
        $stmt->execute([$club_id]);
        $club = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($club && $club['organizacion_id']) {
            $stmt = $pdo->prepare("SELECT o.*, e.nombre as entidad_nombre FROM organizaciones o LEFT JOIN entidad e ON o.entidad = e.id WHERE o.id = ?");
            $stmt->execute([$club['organizacion_id']]);
            $organizacion = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } else {
        $club_ids = ClubHelper::getClubesByAdminClubId($current_user['id']);
        if (in_array($club_id, $club_ids)) {
            $stmt = $pdo->prepare("SELECT * FROM clubes WHERE id = ? AND estatus = 1");
            $stmt->execute([$club_id]);
            $club = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($club && $club['organizacion_id'] == $organizacion_id) {
                $stmt = $pdo->prepare("SELECT o.*, e.nombre as entidad_nombre FROM organizaciones o LEFT JOIN entidad e ON o.entidad = e.id WHERE o.id = ?");
                $stmt->execute([$organizacion_id]);
                $organizacion = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    }
    if ($club && $organizacion) {
        $stmt = $pdo->prepare("
            SELECT u.id, u.cedula, u.nombre, u.email, u.celular, u.status, u.created_at
            FROM usuarios u
            WHERE u.club_id = ? AND u.role = 'usuario'
            ORDER BY u.nombre ASC
        ");
        $stmt->execute([$club_id]);
        $afiliados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if (!$club || !$organizacion) {
        header('Location: index.php?page=organizaciones&id=' . $organizacion_id);
        exit;
    }
    include __DIR__ . '/organizaciones/club_detail.php';
    return;
}

// ---------- Vista: Detalle de organización (con clubes y estadísticas) ----------
if ($organizacion_id) {
    $organizacion = null;
    $clubes = [];
    if ($is_admin_general) {
        $stmt = $pdo->prepare("
            SELECT o.*, e.nombre as entidad_nombre, u.nombre as admin_nombre, u.email as admin_email
            FROM organizaciones o
            LEFT JOIN entidad e ON o.entidad = e.id
            LEFT JOIN usuarios u ON o.admin_user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->execute([$organizacion_id]);
        $organizacion = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $org_id = Auth::getUserOrganizacionId();
        if ($org_id == $organizacion_id) {
            $stmt = $pdo->prepare("
                SELECT o.*, e.nombre as entidad_nombre, u.nombre as admin_nombre, u.email as admin_email
                FROM organizaciones o
                LEFT JOIN entidad e ON o.entidad = e.id
                LEFT JOIN usuarios u ON o.admin_user_id = u.id
                WHERE o.id = ? AND o.estatus = 1
            ");
            $stmt->execute([$organizacion_id]);
            $organizacion = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    if (!$organizacion) {
        $organizacion_id = null;
    }
    if ($organizacion) {
    $stmt = $pdo->prepare("
        SELECT c.id, c.nombre, c.delegado, c.telefono, c.direccion, c.estatus,
               COUNT(DISTINCT u.id) as total_afiliados,
               SUM(CASE WHEN u.sexo = 'M' OR UPPER(u.sexo) = 'M' THEN 1 ELSE 0 END) as hombres,
               SUM(CASE WHEN u.sexo = 'F' OR UPPER(u.sexo) = 'F' THEN 1 ELSE 0 END) as mujeres
        FROM clubes c
        LEFT JOIN usuarios u ON u.club_id = c.id AND u.role = 'usuario' AND u.status = 0
        WHERE c.organizacion_id = ? AND c.estatus = 1
        GROUP BY c.id, c.nombre, c.delegado, c.telefono, c.direccion, c.estatus
        ORDER BY c.nombre ASC
    ");
    $stmt->execute([$organizacion_id]);
    $clubes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stats_operadores = 0;
    $stats_admin_torneo = 0;
    if (!empty($clubes)) {
        $club_ids = array_column($clubes, 'id');
        $ph = implode(',', array_fill(0, count($club_ids), '?'));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE club_id IN ($ph) AND role = 'operador'");
        $stmt->execute($club_ids);
        $stats_operadores = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE club_id IN ($ph) AND role = 'admin_torneo'");
        $stmt->execute($club_ids);
        $stats_admin_torneo = (int)$stmt->fetchColumn();
    }
    include __DIR__ . '/organizaciones/org_detail.php';
    return;
    }
}

// ---------- Vista: Listado de organizaciones de una entidad (entidad_id) ----------
if ($is_admin_general && !$organizacion_id && !$club_id && $entidad_id > 0) {
    $entidad_nombre = 'Entidad ' . $entidad_id;
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM entidad")->fetchAll(PDO::FETCH_ASSOC);
        $codeCol = $nameCol = null;
        foreach ($cols as $c) {
            $f = strtolower($c['Field'] ?? '');
            if (!$codeCol && in_array($f, ['codigo', 'cod_entidad', 'id', 'code'], true)) $codeCol = $f;
            if (!$nameCol && in_array($f, ['nombre', 'descripcion', 'entidad', 'nombre_entidad'], true)) $nameCol = $f;
        }
        if ($codeCol && $nameCol) {
            $stmt = $pdo->prepare("SELECT {$nameCol} AS nombre FROM entidad WHERE {$codeCol} = ?");
            $stmt->execute([$entidad_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['nombre'])) $entidad_nombre = $row['nombre'];
        }
    } catch (Exception $e) {}
    $organizaciones = [];
    try {
        $stmt = $pdo->prepare("
            SELECT o.id, o.nombre, o.estatus,
                   (SELECT COUNT(*) FROM clubes WHERE organizacion_id = o.id AND estatus = 1) as total_clubes,
                   (SELECT COUNT(*) FROM tournaments WHERE club_responsable = o.id) as total_torneos
            FROM organizaciones o
            WHERE o.entidad = ?
            ORDER BY o.estatus DESC, o.nombre ASC
        ");
        $stmt->execute([$entidad_id]);
        $organizaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($organizaciones as &$org) {
            $stmt2 = $pdo->prepare("
                SELECT COUNT(*) FROM usuarios u
                INNER JOIN clubes c ON u.club_id = c.id
                WHERE c.organizacion_id = ? AND c.estatus = 1 AND u.role = 'usuario' AND u.status = 0
            ");
            $stmt2->execute([$org['id']]);
            $org['total_afiliados'] = (int)$stmt2->fetchColumn();
        }
        unset($org);
    } catch (Exception $e) {}
    include __DIR__ . '/organizaciones/listado_organizaciones_entidad.php';
    return;
}

// ---------- Vista: Listado de entidades con resumen (solo admin_general, sin id ni entidad_id) ----------
if ($is_admin_general && !$organizacion_id && !$club_id) {
    $resumen_entidades = [];
    try {
        $stmt = $pdo->query("SELECT DISTINCT entidad FROM organizaciones WHERE entidad IS NOT NULL AND entidad != 0 ORDER BY entidad ASC");
        $entidad_codes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $entidad_codes = [];
    }
    $entidad_nombres = [];
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM entidad")->fetchAll(PDO::FETCH_ASSOC);
        $codeCol = $nameCol = null;
        foreach ($cols as $c) {
            $f = strtolower($c['Field'] ?? '');
            if (!$codeCol && in_array($f, ['codigo', 'cod_entidad', 'id', 'code'], true)) $codeCol = $f;
            if (!$nameCol && in_array($f, ['nombre', 'descripcion', 'entidad', 'nombre_entidad'], true)) $nameCol = $f;
        }
        if ($codeCol && $nameCol && $entidad_codes) {
            $placeholders = implode(',', array_fill(0, count($entidad_codes), '?'));
            $stmt = $pdo->prepare("SELECT {$codeCol} AS cod, {$nameCol} AS nombre FROM entidad WHERE {$codeCol} IN ($placeholders)");
            $stmt->execute($entidad_codes);
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $entidad_nombres[$r['cod']] = $r['nombre'];
        }
    } catch (Exception $e) {}
    foreach ($entidad_codes as $cod) {
        $nombre = $entidad_nombres[$cod] ?? ('Entidad ' . $cod);
        $stmt = $pdo->prepare("SELECT id FROM organizaciones WHERE entidad = ?");
        $stmt->execute([$cod]);
        $org_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $total_organizaciones = count($org_ids);
        $total_clubes = $total_afiliados = $total_torneos = 0;
        if ($org_ids) {
            $ph = implode(',', array_fill(0, count($org_ids), '?'));
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM clubes WHERE organizacion_id IN ($ph) AND estatus = 1");
            $stmt->execute($org_ids);
            $total_clubes = (int)$stmt->fetchColumn();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tournaments WHERE club_responsable IN ($ph)");
            $stmt->execute($org_ids);
            $total_torneos = (int)$stmt->fetchColumn();
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM usuarios u
                INNER JOIN clubes c ON u.club_id = c.id
                WHERE c.organizacion_id IN ($ph) AND c.estatus = 1 AND u.role = 'usuario' AND u.status = 0
            ");
            $stmt->execute($org_ids);
            $total_afiliados = (int)$stmt->fetchColumn();
        }
        $resumen_entidades[] = [
            'entidad_id' => $cod,
            'entidad_nombre' => $nombre,
            'total_organizaciones' => $total_organizaciones,
            'total_clubes' => $total_clubes,
            'total_afiliados' => $total_afiliados,
            'total_torneos' => $total_torneos,
        ];
    }
    include __DIR__ . '/organizaciones/listado_entidades.php';
    return;
}

// ---------- Vista: Listado por entidad (agrupado, fallback) ----------
try {
    $stmt = $pdo->query("
        SELECT o.*, e.id as entidad_id, e.nombre as entidad_nombre,
               (SELECT COUNT(*) FROM clubes WHERE organizacion_id = o.id AND estatus = 1) as total_clubes,
               (SELECT COUNT(*) FROM tournaments WHERE club_responsable = o.id) as total_torneos
        FROM organizaciones o
        LEFT JOIN entidad e ON o.entidad = e.id
        WHERE o.estatus = 1
        ORDER BY e.nombre ASC, o.nombre ASC
    ");
    $todas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $todas = [];
}
$por_entidad = [];
foreach ($todas as $org) {
    $key = $org['entidad_nombre'] ?? 'Sin entidad';
    if (!isset($por_entidad[$key])) {
        $por_entidad[$key] = [];
    }
    $por_entidad[$key][] = $org;
}
include __DIR__ . '/organizaciones/list_by_entidad.php';
