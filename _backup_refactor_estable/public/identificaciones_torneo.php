<?php

declare(strict_types=1);

/**
 * Hoja imprimible de identificaciones (QR → /atleta.php?id=) para inscritos del torneo.
 */

$root = dirname(__DIR__);
require $root . '/config/bootstrap.php';
require $root . '/config/internal_to_landing.php';
require_once $root . '/app/Database/ConnectionException.php';
require_once $root . '/app/Database/Connection.php';
require_once $root . '/app/Core/TournamentEngineService.php';
require_once $root . '/app/Core/OrganizacionService.php';
require_once $root . '/app/Helpers/AdminApi.php';

if (mn_admin_session() === null) {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $to = str_contains($script, '/public/') ? 'index.php' : 'public/index.php';
    header('Location: ' . $to . '?acceso=admin', true, 303);
    exit;
}

$admin = mn_admin_session();
$scope = mn_admin_torneo_query_scope();

$script = $_SERVER['SCRIPT_NAME'] ?? '';
$publicPrefix = str_contains($script, '/public/') ? '' : 'public/';

$torneoId = isset($_GET['torneo_id']) ? (int) $_GET['torneo_id'] : 0;
$error = null;
$torneo = null;
$rows = [];

if ($scope === false) {
    $error = 'Sin organización en sesión.';
} elseif ($torneoId <= 0) {
    $error = 'Indique torneo_id.';
} else {
    try {
        $pdo = Connection::get();
        $torneo = TournamentEngineService::getTorneo($pdo, $torneoId, $scope);
        if ($torneo === null || !OrganizacionService::adminPuedeGestionarTorneo($admin, $torneo)) {
            $error = 'Torneo no disponible o sin permiso.';
            $torneo = null;
        } else {
            $tabla = getenv('DB_AUTH_TABLE') ?: 'usuarios';
            $tabla = in_array(strtolower(trim((string) $tabla)), ['usuarios', 'users'], true) ? strtolower(trim((string) $tabla)) : 'usuarios';
            try {
                $sql = <<<SQL
                    SELECT i.id_usuario, u.nombre, u.cedula, u.foto, u.avatar
                    FROM inscritos i
                    INNER JOIN `{$tabla}` u ON u.id = i.id_usuario
                    WHERE i.torneo_id = ?
                    ORDER BY u.nombre ASC
                    SQL;
                $st = $pdo->prepare($sql);
                $st->execute([$torneoId]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                $sql = <<<SQL
                    SELECT i.id_usuario, u.nombre, u.cedula
                    FROM inscritos i
                    INNER JOIN `{$tabla}` u ON u.id = i.id_usuario
                    WHERE i.torneo_id = ?
                    ORDER BY u.nombre ASC
                    SQL;
                $st = $pdo->prepare($sql);
                $st->execute([$torneoId]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Throwable $e) {
        $error = 'Error de conexión.';
    }
}

$qrApi = $publicPrefix . 'api/atleta_qr_png.php?torneo_id=' . $torneoId . '&usuario_id=';
$panelUrl = $publicPrefix . 'admin_panel.php?torneo_id=' . $torneoId;

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Identificaciones — <?= htmlspecialchars((string) ($torneo['nombre'] ?? 'Torneo'), ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>assets/css/mistorneos-core.css" />
  <style>
    .mn-id-toolbar { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; justify-content: space-between; margin-bottom: 1rem; padding: 0.75rem 0; border-bottom: 1px solid var(--mn-border); }
    .mn-id-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.75rem; }
    @media print {
      .mn-id-toolbar { display: none; }
      .mn-id-grid { gap: 0.5rem; }
    }
    @media (max-width: 900px) {
      .mn-id-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    .mn-id-card {
      border: 2px solid var(--mn-blue-deep);
      border-radius: 10px;
      padding: 0.5rem;
      text-align: center;
      break-inside: avoid;
      page-break-inside: avoid;
      background: #fff;
    }
    .mn-id-card__foto {
      width: 72px; height: 72px; border-radius: 8px; object-fit: cover; margin: 0 auto 0.35rem; display: block; background: var(--mn-surface);
    }
    .mn-id-card__ph {
      width: 72px; height: 72px; border-radius: 8px; margin: 0 auto 0.35rem; display: grid; place-items: center; background: var(--mn-blue-mid); color: #fff; font-weight: 800; font-size: 1.5rem;
    }
    .mn-id-card__qr { width: 100px; height: 100px; margin: 0.25rem auto; display: block; }
    .mn-id-card__nom { font-weight: 700; font-size: 0.8rem; line-height: 1.2; color: var(--mn-blue-deep); }
    .mn-id-card__doc { font-size: 0.7rem; color: var(--mn-slate-muted); margin-top: 0.15rem; }
    .mn-id-card__id { font-size: 0.65rem; color: var(--mn-slate-muted); margin-top: 0.2rem; }
  </style>
</head>
<body style="background:#f0f3f6;">
  <div class="mn-container" style="padding:1rem 0 2rem;">
    <div class="mn-id-toolbar">
      <div>
        <a href="<?= htmlspecialchars($panelUrl, ENT_QUOTES, 'UTF-8') ?>">← Consola del torneo</a>
        <span class="mn-hint" style="margin-left:0.75rem;">Use imprimir y “Guardar como PDF” en el navegador.</span>
      </div>
      <button type="button" class="mn-btn mn-btn--success" onclick="window.print()">Imprimir / PDF</button>
    </div>

    <?php if ($error !== null) : ?>
      <p class="mn-hint mn-hint--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php else : ?>
      <h1 style="margin:0 0 1rem;font-size:1.25rem;color:var(--mn-blue-deep);">
        Identificaciones — <?= htmlspecialchars((string) $torneo['nombre'], ENT_QUOTES, 'UTF-8') ?>
      </h1>
      <div class="mn-id-grid">
        <?php foreach ($rows as $r) :
            $uid = (int) ($r['id_usuario'] ?? 0);
            $nom = trim((string) ($r['nombre'] ?? ''));
            $ced = trim((string) ($r['cedula'] ?? ''));
            $foto = trim((string) ($r['foto'] ?? $r['avatar'] ?? ''));
            $ini = function_exists('mb_substr') ? mb_strtoupper(mb_substr($nom !== '' ? $nom : '?', 0, 1)) : strtoupper(substr($nom !== '' ? $nom : '?', 0, 1));
            ?>
          <div class="mn-id-card">
            <?php if ($foto !== '') : ?>
              <img class="mn-id-card__foto" src="<?= htmlspecialchars($foto, ENT_QUOTES, 'UTF-8') ?>" alt="" />
            <?php else : ?>
              <div class="mn-id-card__ph" aria-hidden="true"><?= htmlspecialchars($ini, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <img class="mn-id-card__qr" src="<?= htmlspecialchars($qrApi . $uid, ENT_QUOTES, 'UTF-8') ?>" width="100" height="100" alt="QR" />
            <div class="mn-id-card__nom"><?= htmlspecialchars($nom !== '' ? $nom : 'Sin nombre', ENT_QUOTES, 'UTF-8') ?></div>
            <?php if ($ced !== '') : ?>
              <div class="mn-id-card__doc"><?= htmlspecialchars($ced, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <div class="mn-id-card__id">ID <?= $uid ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($rows === []) : ?>
        <p class="mn-hint">No hay inscritos en este torneo.</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</body>
</html>
