<?php
declare(strict_types=1);

/**
 * Sincroniza campos desde atletas hacia usuarios usando cédula como llave.
 *
 * Campos sincronizados:
 * - usuarios.sexo    <- atletas.sexo
 * - usuarios.numfvd  <- atletas.numfvd
 * - usuarios.club_id <- atletas.asociacion
 * - usuarios.celular <- atletas.celular
 * - usuarios.email   <- atletas.email
 * - usuarios.fechnac <- atletas.fechnac
 *
 * Uso:
 *   php scripts/sync_usuarios_desde_atletas.php
 *   php scripts/sync_usuarios_desde_atletas.php --dry-run
 */

$opts = getopt('', ['dry-run']);
$dryRun = isset($opts['dry-run']);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

function normalizarCedula(string $cedula): string
{
    return preg_replace('/\D/', '', $cedula) ?? '';
}

function normalizarSexo($sexo): string
{
    $raw = strtoupper(trim((string)$sexo));
    if (in_array($raw, ['M', 'F', 'O'], true)) {
        return $raw;
    }
    if ($raw === '2' || strpos($raw, 'F') !== false) {
        return 'F';
    }
    if ($raw === '3' || strpos($raw, 'O') !== false) {
        return 'O';
    }
    return 'M';
}

function normalizarFecha($fecha): ?string
{
    $s = trim((string)$fecha);
    if ($s === '') {
        return null;
    }
    $ts = strtotime($s);
    if ($ts === false) {
        return null;
    }
    return date('Y-m-d', $ts);
}

$pdo = DB::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "═══════════════════════════════════════════════════════════════\n";
echo "  Sincronización usuarios <- atletas (por cédula)\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo $dryRun ? "  [MODO DRY-RUN]\n\n" : "\n";

try {
    $stmtUsers = $pdo->query("SELECT id, cedula FROM usuarios");
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
    $userByCedula = [];
    foreach ($users as $u) {
        $uid = (int)($u['id'] ?? 0);
        $ced = normalizarCedula((string)($u['cedula'] ?? ''));
        if ($uid > 0 && $ced !== '') {
            $userByCedula[$ced] = $uid;
        }
    }

    $stmtAtletas = $pdo->query(
        "SELECT cedula, sexo, numfvd, asociacion, celular, email, fechnac
         FROM atletas"
    );
    $atletas = $stmtAtletas->fetchAll(PDO::FETCH_ASSOC);

    $totalAtletas = count($atletas);
    $conMatch = 0;
    $actualizados = 0;
    $sinMatch = 0;

    if (!$dryRun) {
        $pdo->beginTransaction();
    }

    $upd = $pdo->prepare(
        "UPDATE usuarios
         SET sexo = ?, numfvd = ?, club_id = ?, celular = ?, email = ?, fechnac = ?
         WHERE id = ?"
    );

    foreach ($atletas as $a) {
        $ced = normalizarCedula((string)($a['cedula'] ?? ''));
        if ($ced === '' || !isset($userByCedula[$ced])) {
            $sinMatch++;
            continue;
        }

        $conMatch++;
        $uid = (int)$userByCedula[$ced];
        $sexo = normalizarSexo($a['sexo'] ?? 'M');
        $numfvd = (int)($a['numfvd'] ?? 0);
        $clubId = (int)($a['asociacion'] ?? 0);
        $celular = trim((string)($a['celular'] ?? ''));
        $email = trim((string)($a['email'] ?? ''));
        $fechnac = normalizarFecha($a['fechnac'] ?? null);

        if ($dryRun) {
            $actualizados++;
            continue;
        }

        $upd->execute([
            $sexo,
            $numfvd,
            $clubId,
            $celular === '' ? null : $celular,
            $email === '' ? null : $email,
            $fechnac,
            $uid,
        ]);
        $actualizados += $upd->rowCount() > 0 ? 1 : 0;
    }

    if ($dryRun) {
        echo "✅ Simulación completada.\n\n";
    } else {
        $pdo->commit();
        echo "✅ Sincronización completada.\n\n";
    }

    echo "Resumen:\n";
    echo "  Atletas procesados: {$totalAtletas}\n";
    echo "  Coincidencias por cédula: {$conMatch}\n";
    echo "  Usuarios actualizados: {$actualizados}\n";
    echo "  Atletas sin usuario por cédula: {$sinMatch}\n";
} catch (Throwable $e) {
    if (!$dryRun && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "❌ Error: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

