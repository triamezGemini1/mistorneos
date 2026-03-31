<?php
declare(strict_types=1);

/**
 * Crea clubes por entidad para la organización FVD y reasigna usuarios con numfvd.
 *
 * Reglas:
 * - Organización objetivo: "FEDERACIÓN VENEZOLANA DE DOMINÓ".
 * - Por cada entidad, crea (si no existe) un club con el nombre de la entidad.
 * - El club queda asociado a la organización FVD.
 * - Reasigna automáticamente usuarios con numfvd (>0) al club creado para su entidad,
 *   tomando como "código de club" el club_id actual del usuario.
 *
 * Uso:
 *   php scripts/crear_clubes_fvd_por_entidad.php
 *   php scripts/crear_clubes_fvd_por_entidad.php --dry-run
 */

$opts = getopt('', ['dry-run']);
$dryRun = isset($opts['dry-run']);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

$pdo = DB::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$orgNombre = 'FEDERACIÓN VENEZOLANA DE DOMINÓ';
$virtualClubId = 100000;

/**
 * Limpia nombre de entidad (espacios raros/no-break-space y repetidos).
 */
function limpiarNombreEntidad(string $s): string
{
    $s = str_replace("\xC2\xA0", ' ', $s);
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    return trim($s);
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "  Clubes FVD por entidad + asociación automática de usuarios\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo $dryRun ? "  [MODO DRY-RUN]\n\n" : "\n";

try {
    $pdo->beginTransaction();

    // 1) Resolver/crear organización FVD.
    $stmtOrg = $pdo->prepare(
        "SELECT id FROM organizaciones WHERE UPPER(TRIM(nombre)) = UPPER(TRIM(?)) LIMIT 1"
    );
    $stmtOrg->execute([$orgNombre]);
    $orgId = (int) ($stmtOrg->fetchColumn() ?: 0);

    if ($orgId <= 0) {
        $stmtAdmin = $pdo->query(
            "SELECT id
             FROM usuarios
             WHERE role = 'admin_general'
             ORDER BY id ASC
             LIMIT 1"
        );
        $adminId = (int) ($stmtAdmin->fetchColumn() ?: 0);
        if ($adminId <= 0) {
            throw new RuntimeException('No se encontró un usuario admin_general para crear la organización FVD.');
        }

        if ($dryRun) {
            echo "  [DRY-RUN] Se crearía organización: {$orgNombre}\n";
            $orgId = -1;
        } else {
            $insOrg = $pdo->prepare(
                "INSERT INTO organizaciones (nombre, entidad, admin_user_id, estatus, created_at, updated_at)
                 VALUES (?, 0, ?, 1, NOW(), NOW())"
            );
            $insOrg->execute([$orgNombre, $adminId]);
            $orgId = (int) $pdo->lastInsertId();
            echo "  ✅ Organización creada: {$orgNombre} (id={$orgId})\n";
        }
    } else {
        echo "  ✅ Organización encontrada: {$orgNombre} (id={$orgId})\n";
    }

    // 2) Cargar entidades y asegurar club por entidad dentro de la organización FVD.
    $entidades = $pdo->query("SELECT id, nombre FROM entidad ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($entidades)) {
        throw new RuntimeException('No hay registros en tabla entidad.');
    }

    $clubPorEntidad = [];
    $creados = 0;
    $existentes = 0;

    $selClub = $pdo->prepare(
        "SELECT id
         FROM clubes
         WHERE organizacion_id = ?
           AND entidad = ?
         ORDER BY id ASC
         LIMIT 1"
    );
    $insClub = $pdo->prepare(
        "INSERT INTO clubes
            (nombre, admin_club_id, organizacion_id, entidad, indica, estatus, created_at, updated_at)
         VALUES
            (?, 0, ?, ?, 0, 1, NOW(), NOW())"
    );

    foreach ($entidades as $e) {
        $entidadId = (int) ($e['id'] ?? 0);
        $entidadNombre = limpiarNombreEntidad((string) ($e['nombre'] ?? ''));
        if ($entidadId <= 0 || $entidadNombre === '') {
            continue;
        }

        $selClub->execute([$orgId > 0 ? $orgId : 0, $entidadId]);
        $clubId = (int) ($selClub->fetchColumn() ?: 0);

        if ($clubId > 0) {
            $clubPorEntidad[$entidadId] = $clubId;
            $existentes++;
            continue;
        }

        if ($dryRun) {
            echo "  [DRY-RUN] Crear club '{$entidadNombre}' para entidad {$entidadId}\n";
            $clubPorEntidad[$entidadId] = $virtualClubId++;
            continue;
        }

        $insClub->execute([$entidadNombre, $orgId, $entidadId]);
        $clubId = (int) $pdo->lastInsertId();
        $clubPorEntidad[$entidadId] = $clubId;
        $creados++;
    }

    if (!$dryRun) {
        $stmtMap = $pdo->prepare(
            "SELECT id, entidad
             FROM clubes
             WHERE organizacion_id = ?"
        );
        $stmtMap->execute([$orgId]);
        foreach ($stmtMap->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cid = (int) ($row['id'] ?? 0);
            $eid = (int) ($row['entidad'] ?? 0);
            if ($cid > 0 && $eid > 0) {
                $clubPorEntidad[$eid] = $cid;
            }
        }
    }

    // 3) Asociar usuarios registrados con numfvd al club de su entidad (según código club actual en usuarios.club_id).
    $reasignados = 0;
    $sinEntidadClub = 0;
    $porClubId = 0;
    $porEntidad = 0;

    $selUsers = $pdo->query(
        "SELECT id, club_id, entidad, numfvd
         FROM usuarios
         WHERE numfvd IS NOT NULL
           AND numfvd > 0"
    );
    $users = $selUsers->fetchAll(PDO::FETCH_ASSOC);

    $updUser = $pdo->prepare("UPDATE usuarios SET club_id = ?, entidad = ? WHERE id = ?");

    foreach ($users as $u) {
        $uid = (int) ($u['id'] ?? 0);
        $codigoClub = (int) ($u['club_id'] ?? 0);
        $codigoEntidad = (int) ($u['entidad'] ?? 0);
        if ($uid <= 0) {
            continue;
        }

        $clubNuevoId = 0;
        $entidadDestino = 0;
        if ($codigoClub > 0 && isset($clubPorEntidad[$codigoClub])) {
            $clubNuevoId = (int) $clubPorEntidad[$codigoClub];
            $entidadDestino = $codigoClub;
            $porClubId++;
        } elseif ($codigoEntidad > 0 && isset($clubPorEntidad[$codigoEntidad])) {
            $clubNuevoId = (int) $clubPorEntidad[$codigoEntidad];
            $entidadDestino = $codigoEntidad;
            $porEntidad++;
        }

        if ($clubNuevoId <= 0) {
            $sinEntidadClub++;
            continue;
        }

        if ($dryRun) {
            $reasignados++;
            continue;
        }

        $updUser->execute([$clubNuevoId, $entidadDestino, $uid]);
        $reasignados += $updUser->rowCount() > 0 ? 1 : 0;
    }

    if ($dryRun) {
        $pdo->rollBack();
    } else {
        $pdo->commit();
    }

    echo "\n";
    echo "Resumen:\n";
    echo "  Clubes creados: {$creados}\n";
    echo "  Clubes existentes reutilizados: {$existentes}\n";
    echo "  Usuarios reasignados: {$reasignados}\n";
    echo "    - por club_id como código entidad: {$porClubId}\n";
    echo "    - por entidad del usuario: {$porEntidad}\n";
    echo "  Usuarios sin entidad-club destino: {$sinEntidadClub}\n";
    echo "\n";
    echo $dryRun
        ? "✅ Simulación completada (sin cambios).\n"
        : "✅ Proceso completado.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "❌ Error: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

