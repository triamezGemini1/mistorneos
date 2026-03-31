<?php

declare(strict_types=1);

/**
 * Login administrador: SOLO la tabla maestra `usuarios` / `users` (BD principal, DB_AUTH_TABLE).
 * La BD auxiliar de personas no interviene en el login ni en credenciales.
 * password_verify() únicamente sobre hashes generados con password_hash() en PHP.
 */

$root = dirname(__DIR__, 2);
require $root . '/config/bootstrap.php';
require $root . '/app/Database/ConnectionException.php';
require $root . '/app/Database/Connection.php';
require $root . '/app/Helpers/PasswordHashInspector.php';
require $root . '/app/Core/OrganizacionService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php', true, 303);
    exit;
}

$token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null;
if (!csrf_validate($token)) {
    header('Location: index.php?auth=csrf', true, 303);
    exit;
}

$usuario = isset($_POST['usuario']) ? trim((string) $_POST['usuario']) : '';
$password = isset($_POST['password']) ? (string) $_POST['password'] : '';

if ($usuario === '' || strlen($usuario) > 128 || strlen($password) < 8) {
    header('Location: index.php?auth=invalid', true, 303);
    exit;
}

$adminRoles = ['admin_general', 'admin_torneo', 'admin_club', 'operador'];

try {
    $pdo = Connection::get();
} catch (ConnectionException $e) {
    header('Location: index.php?auth=db', true, 303);
    exit;
}

$tablaAuth = mn_tabla_auth_operativa();
$sql = sprintf(
    'SELECT id, username, password_hash, email, role, status, club_id, nombre FROM `%s` WHERE username = :u1 OR email = :u2 LIMIT 1',
    $tablaAuth
);

$stmt = $pdo->prepare($sql);
$stmt->execute(['u1' => $usuario, 'u2' => $usuario]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row === false) {
    header('Location: index.php?auth=invalid', true, 303);
    exit;
}

$hash = (string) ($row['password_hash'] ?? '');
$result = PasswordHashInspector::verify($password, $hash);

if ($result['legacy_insecure']) {
    header('Location: index.php?auth=unsafe_storage', true, 303);
    exit;
}

if (!$result['ok']) {
    header('Location: index.php?auth=invalid', true, 303);
    exit;
}

if (!in_array((string) ($row['role'] ?? ''), $adminRoles, true)) {
    header('Location: index.php?auth=invalid', true, 303);
    exit;
}

if ((int) ($row['status'] ?? 1) !== 0) {
    header('Location: index.php?auth=inactive', true, 303);
    exit;
}

if (mn_usuario_bloqueado_por_is_active($pdo, (int) $row['id'])) {
    header('Location: index.php?auth=inactive', true, 303);
    exit;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
}

$orgId = OrganizacionService::ensureWorkspaceForAdmin(
    $pdo,
    (int) $row['id'],
    (string) ($row['role'] ?? ''),
    (string) ($row['nombre'] ?? ''),
    (string) ($row['email'] ?? '')
);

$_SESSION['admin_user'] = [
    'id' => (int) $row['id'],
    'username' => (string) $row['username'],
    'email' => (string) $row['email'],
    'role' => (string) $row['role'],
    'club_id' => (int) ($row['club_id'] ?? 0),
    'organizacion_id' => $orgId,
];

if ($orgId !== null && (int) $orgId > 0) {
    try {
        $st = $pdo->prepare(
            'SELECT nombre, entidad_id, entidad FROM organizaciones WHERE id = ? LIMIT 1'
        );
        $st->execute([(int) $orgId]);
        $orgRow = $st->fetch(PDO::FETCH_ASSOC);
        if (is_array($orgRow)) {
            $_SESSION['admin_user']['organizacion_nombre'] = (string) ($orgRow['nombre'] ?? '');
            $eid = (int) ($orgRow['entidad_id'] ?? 0);
            if ($eid <= 0 && isset($orgRow['entidad'])) {
                $eid = (int) $orgRow['entidad'];
            }
            if ($eid > 0) {
                $_SESSION['admin_user']['entidad_id'] = $eid;
                $st2 = $pdo->prepare('SELECT nombre FROM entidades WHERE id = ? LIMIT 1');
                $st2->execute([$eid]);
                $en = $st2->fetchColumn();
                if ($en !== false) {
                    $_SESSION['admin_user']['entidad_nombre'] = (string) $en;
                }
            }
        }
    } catch (Throwable $e) {
        // entidades / entidad_id opcionales hasta migración
    }
}

header('Location: admin_torneo.php', true, 303);
exit;

/**
 * Columna opcional is_active (1 = permitido).
 */
function mn_tabla_auth_operativa(): string
{
    $t = strtolower(trim((string) (getenv('DB_AUTH_TABLE') ?: 'usuarios')));

    return in_array($t, ['usuarios', 'users'], true) ? $t : 'usuarios';
}

function mn_usuario_bloqueado_por_is_active(PDO $pdo, int $userId): bool
{
    $tabla = mn_tabla_auth_operativa();
    try {
        $st = $pdo->prepare(sprintf('SELECT is_active FROM `%s` WHERE id = ? LIMIT 1', $tabla));
        $st->execute([$userId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if ($r === false || !array_key_exists('is_active', $r)) {
            return false;
        }

        return (int) $r['is_active'] !== 1;
    } catch (Throwable $e) {
        return false;
    }
}
