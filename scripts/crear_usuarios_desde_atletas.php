<?php
/**
 * Convierte registros de la tabla `atletas` en usuarios.
 *
 * Reglas:
 *   - Username: único por atleta. Formato "user00" + numfvd (solo dígitos) si numfvd existe;
 *     si no hay numfvd, "user00" + id del atleta. Si hay conflicto de unicidad se añade sufijo _2, _3...
 *   - Password: número de cédula (solo dígitos); mínimo 6 caracteres (relleno si hace falta).
 *   - club_id: valor de asociación (normalizado a entero).
 *   - Se normaliza toda la información (trim, sexo M/F/O, estatus 0/1, email, fechas, etc.).
 *
 * Acceso: usuario = user00XXXXX (numfvd o id), contraseña = cédula del atleta.
 * Mantiene username UNIQUE en BD (mejor práctica de seguridad).
 *
 * Uso:
 *   php scripts/crear_usuarios_desde_atletas.php
 *   php scripts/crear_usuarios_desde_atletas.php --dry-run
 *   php scripts/crear_usuarios_desde_atletas.php --limit=100
 */

$opts = getopt('', ['dry-run', 'limit:', 'desde-id:', 'hasta-id:']);
$dry_run = isset($opts['dry-run']);
$limit = isset($opts['limit']) ? max(1, (int)$opts['limit']) : null;
$desde_id = isset($opts['desde-id']) ? max(0, (int)$opts['desde-id']) : null;
$hasta_id = isset($opts['hasta-id']) ? max(0, (int)$opts['hasta-id']) : null;

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/Security.php';

echo "═══════════════════════════════════════════════════════════════\n";
echo "  Crear usuarios desde tabla atletas\n";
echo "  Username: user00+numfvd (o user00+id atleta) | Password: cédula | club_id: asociación\n";
echo "═══════════════════════════════════════════════════════════════\n\n";
if ($dry_run) {
    echo "  [MODO DRY-RUN: No se creará ningún usuario]\n\n";
}

$pdo = DB::pdo();

// Verificar que existe la tabla atletas
try {
    $pdo->query("SELECT 1 FROM atletas LIMIT 1");
} catch (Throwable $e) {
    die("❌ Error: La tabla 'atletas' no existe o no es accesible. " . $e->getMessage() . "\n");
}

// Construir SELECT (nombres de columna en BD: id, asociacion, direccion, etc.)
$sql = "SELECT id, cedula, sexo, numfvd, asociacion, estatus, categ, nombre, direccion, celular, email, fechnac, foto, created_at, updated_at FROM atletas WHERE 1=1";
$params = [];
if ($desde_id !== null) {
    $sql .= " AND id >= ?";
    $params[] = $desde_id;
}
if ($hasta_id !== null) {
    $sql .= " AND id <= ?";
    $params[] = $hasta_id;
}
$sql .= " ORDER BY id ASC";
if ($limit !== null) {
    $sql .= " LIMIT " . (int)$limit;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$atletas = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "  Atletas a procesar: " . count($atletas) . "\n\n";

if (empty($atletas)) {
    echo "  No hay registros que procesar.\n";
    exit(0);
}

// Cédulas ya existentes en usuarios (omitir duplicados)
$stmt_ced = $pdo->prepare("SELECT cedula FROM usuarios WHERE cedula != ''");
$stmt_ced->execute();
$cedulas_existentes = array_fill_keys($stmt_ced->fetchAll(PDO::FETCH_COLUMN), true);

// Usernames ya usados (BD + los que vamos creando en esta ejecución)
$stmt_usr = $pdo->prepare("SELECT username FROM usuarios");
$stmt_usr->execute();
$usernames_usados = array_fill_keys($stmt_usr->fetchAll(PDO::FETCH_COLUMN), true);

/**
 * Genera username único: user00+numfvd o user00+id_atleta; si existe, añade _2, _3...
 */
function generarUsernameAtleta(array $row, int $id_atleta, array &$usernames_usados): string {
    $numfvd = isset($row['numfvd']) && trim((string)$row['numfvd']) !== ''
        ? preg_replace('/\D/', '', (string)$row['numfvd'])
        : '';
    $base = 'user00' . ($numfvd !== '' ? $numfvd : (string)$id_atleta);
    $base = preg_replace('/[^a-zA-Z0-9_\.]/', '', $base) ?: 'user00' . $id_atleta;
    $username = $base;
    $sufijo = 2;
    while (isset($usernames_usados[$username])) {
        $username = $base . '_' . $sufijo;
        $sufijo++;
    }
    $usernames_usados[$username] = true;
    return $username;
}

/**
 * Normaliza valor para usuarios.
 */
function normalizarAtletaParaUsuario(array $row, string $username): ?array {
    $cedula = preg_replace('/\D/', '', (string)($row['cedula'] ?? ''));
    if ($cedula === '') {
        return null;
    }
    $nombre = trim((string)($row['nombre'] ?? ''));
    if ($nombre === '') {
        $nombre = 'Atleta ' . $cedula;
    }
    $nombre = mb_substr($nombre, 0, 62);

    $sexo = strtoupper(trim((string)($row['sexo'] ?? 'M')));
    if (!in_array($sexo, ['M', 'F', 'O'], true)) {
        $sexo = ($sexo === 'F' || $sexo === '2' || stripos($sexo, 'F') !== false) ? 'F' : 'M';
    }

    $estatus = $row['estatus'] ?? 0;
    if (is_string($estatus)) {
        $estatus = in_array(strtolower($estatus), ['activo', 'active', 'approved', '1', 'si', 'sí'], true) ? 0 : 1;
    } else {
        $estatus = (int)$estatus;
        if ($estatus !== 0) {
            $estatus = 1;
        }
    }

    $email = trim((string)($row['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = $username . '@atletas.local';
    }

    $password = $cedula;
    if (strlen($password) < 6) {
        $password = str_pad($cedula, 6, '0', STR_PAD_LEFT);
    }

    $fechnac = $row['fechnac'] ?? null;
    if ($fechnac !== null && $fechnac !== '') {
        if (is_string($fechnac)) {
            $ts = strtotime($fechnac);
            $fechnac = $ts ? date('Y-m-d', $ts) : null;
        } else {
            $fechnac = null;
        }
    } else {
        $fechnac = null;
    }

    $club_id = (int)($row['asociacion'] ?? 0);
    $celular = trim((string)($row['celular'] ?? ''));
    if ($celular === '') {
        $celular = null;
    }
    $foto = isset($row['foto']) && trim((string)$row['foto']) !== '' ? trim((string)$row['foto']) : null;

    return [
        'username'   => $username,
        'password'   => $password,
        'nombre'     => $nombre,
        'cedula'     => $cedula,
        'nacionalidad' => 'V',
        'email'      => $email,
        'role'       => 'usuario',
        'status'     => $estatus,
        'sexo'       => $sexo,
        'fechnac'    => $fechnac,
        'celular'    => $celular,
        'club_id'    => $club_id,
        'entidad'    => 0,
        'photo_path' => $foto,
        '_allow_club_for_usuario' => $club_id > 0,
    ];
}

$creados = 0;
$omitidos_cedula = 0;
$omitidos_datos = 0;
$errores = 0;

foreach ($atletas as $i => $row) {
    $id_atleta = (int)($row['id'] ?? $i + 1);
    $cedula_raw = (string)($row['cedula'] ?? '');
    $cedula_digitos = preg_replace('/\D/', '', $cedula_raw);

    if (isset($cedulas_existentes[$cedula_digitos])) {
        $omitidos_cedula++;
        if ($dry_run || $creados + $omitidos_cedula + $omitidos_datos + $errores <= 20) {
            echo "  ⏭️  Omitido (cédula ya existe): Id=$id_atleta cedula=$cedula_raw\n";
        }
        continue;
    }

    $username = generarUsernameAtleta($row, $id_atleta, $usernames_usados);
    $data = normalizarAtletaParaUsuario($row, $username);
    if ($data === null) {
        $omitidos_datos++;
        if ($dry_run || $creados + $omitidos_cedula + $omitidos_datos + $errores <= 20) {
            echo "  ⏭️  Omitido (datos insuficientes): Id=$id_atleta cedula=$cedula_raw\n";
        }
        continue;
    }

    if ($dry_run) {
        $creados++;
        echo "  [DRY-RUN] Id=$id_atleta | {$data['nombre']} | {$data['username']} | club_id={$data['club_id']}\n";
        $cedulas_existentes[$data['cedula']] = true;
        continue;
    }

    $result = Security::createUser($data);
    if ($result['success']) {
        $creados++;
        $cedulas_existentes[$data['cedula']] = true;
        echo "  ✅ [$creados] Id=$id_atleta | {$data['nombre']} | {$data['username']} | club_id={$data['club_id']}\n";
    } else {
        $errores++;
        echo "  ❌ Id=$id_atleta | {$data['nombre']} - " . implode(', ', $result['errors']) . "\n";
    }
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "  Resumen\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "  Usuarios creados:     $creados\n";
echo "  Omitidos (cédula):    $omitidos_cedula\n";
echo "  Omitidos (datos):     $omitidos_datos\n";
echo "  Errores:              $errores\n";
echo "\n";
echo "  Credenciales: usuario = user00+numfvd (o user00+id atleta), contraseña = cédula. club_id = asociación.\n";
echo "\n";
