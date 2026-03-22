<?php

declare(strict_types=1);

/**
 * Consola de operaciones del torneo (14") — 3 columnas: Pre-torneo, Operaciones, Reportes.
 */

$root = dirname(__DIR__);
require $root . '/config/bootstrap.php';
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

if ($scope === false) {
    $error = 'Sin organización en sesión. Esta consola requiere workspace de organizador.';
} elseif ($torneoId <= 0) {
    $error = 'Indique el torneo: admin_panel.php?torneo_id=ID';
} else {
    try {
        $pdo = Connection::get();
        $torneo = TournamentEngineService::getTorneo($pdo, $torneoId, $scope);
        if ($torneo === null) {
            $error = 'Torneo no encontrado o fuera de su organización.';
        } elseif (!OrganizacionService::adminPuedeGestionarTorneo($admin, $torneo)) {
            $error = 'No tiene permisos para este torneo.';
            $torneo = null;
        }
    } catch (Throwable $e) {
        $error = 'Error de conexión.';
    }
}

$tipoRaw = $torneo !== null ? strtolower(trim((string) ($torneo['tipo_torneo'] ?? 'individual'))) : '';
if ($tipoRaw === '' || !in_array($tipoRaw, ['individual', 'parejas', 'equipos'], true)) {
    $tipoRaw = 'individual';
}

if ($tipoRaw === 'parejas') {
    $tipoEtiqueta = 'Parejas';
} elseif ($tipoRaw === 'equipos') {
    $tipoEtiqueta = 'Equipos';
} else {
    $tipoEtiqueta = 'Individual';
}

$esIndividual = $tipoRaw === 'individual';
$esParejas = $tipoRaw === 'parejas';
$esEquipos = $tipoRaw === 'equipos';

$identUrl = $publicPrefix . 'identificaciones_torneo.php?torneo_id=' . $torneoId;
$checkinUrl = $publicPrefix . 'checkin.php?torneo_id=' . $torneoId;
$torneosUrl = $publicPrefix . 'admin_torneo.php';

/**
 * @param array{href:string,label:string,sub?:string,theme:string,disabled?:bool,icon:string} $a
 */
function mn_admin_op_button(array $a): void
{
    $href = (string) $a['href'];
    $label = (string) $a['label'];
    $sub = (string) ($a['sub'] ?? '');
    $theme = (string) $a['theme'];
    $disabled = (bool) ($a['disabled'] ?? false);
    $icon = (string) $a['icon'];

    $tag = (!$disabled && $href !== '' && $href !== '#') ? 'a' : 'span';
    $cls = 'mn-admin-panel__btn mn-admin-panel__btn--' . $theme;
    if ($disabled || $href === '' || $href === '#') {
        $cls .= ' mn-admin-panel__btn--disabled';
    }

    echo '<' . $tag . ' class="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '"';
    if ($tag === 'a') {
        echo ' href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"';
    } else {
        echo ' role="button" aria-disabled="true"';
    }
    echo '>';
    echo '<span class="mn-admin-panel__btn-icon" aria-hidden="true">' . $icon . '</span>';
    echo '<span class="mn-admin-panel__btn-text">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    if ($sub !== '') {
        echo '<span class="mn-admin-panel__btn-sub">' . htmlspecialchars($sub, ENT_QUOTES, 'UTF-8') . '</span>';
    }
    echo '</' . $tag . '>';
}

// Iconos SVG minimalistas (24px)
$icoId = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16v16H4zM9 9h6v6H9zM8 2v4M16 2v4M8 18v4M16 18v4"/></svg>';
$icoGrid = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h7v7H4zm9 0h7v7h-7zM4 13h7v7H4zm9 0h7v7h-7z"/></svg>';
$icoCarga = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>';
$icoDoc = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 2h9l5 5v15H6zM14 2v6h6M8 13h8M8 17h8"/></svg>';
$icoRank = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3l2.4 7.3H22l-6 4.6 2.3 7L12 17.8 5.7 22 8 14.9 2 10.3h7.6z"/></svg>';
$icoUser = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20v-1a8 8 0 0116 0v1"/></svg>';
$icoTeam = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="9" cy="7" r="3"/><circle cx="17" cy="9" r="2"/><path d="M3 20v-1a6 6 0 0112 0v1M13 14a5 5 0 015 5v1"/></svg>';
$icoPair = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="8" cy="8" r="3"/><circle cx="16" cy="8" r="3"/><path d="M4 20v0a8 8 0 0116 0"/></svg>';
$icoPod = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 21h8M12 3v4M8 21V10l4-3 4 3v11"/></svg>';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Consola del torneo — mistorneos</title>
  <link rel="stylesheet" href="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>assets/css/mistorneos-core.css" />
</head>
<body class="mn-admin-panel-body">
  <div class="mn-admin-panel">
    <div class="mn-admin-panel__top">
      <a class="mn-admin-panel__back" href="<?= htmlspecialchars($torneosUrl, ENT_QUOTES, 'UTF-8') ?>">← Torneos</a>
      <?php if ($torneo !== null) : ?>
        <a class="mn-admin-panel__aux" href="<?= htmlspecialchars($checkinUrl, ENT_QUOTES, 'UTF-8') ?>">Check-in</a>
      <?php endif; ?>
    </div>

    <?php if ($error !== null) : ?>
      <p class="mn-admin-panel__error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php else : ?>

      <header class="mn-admin-panel__hero">
        <h1 class="mn-admin-panel__title"><?= htmlspecialchars((string) $torneo['nombre'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="mn-admin-panel__tipo">Tipo de torneo: <strong><?= htmlspecialchars($tipoEtiqueta, ENT_QUOTES, 'UTF-8') ?></strong></p>
      </header>

      <div class="mn-admin-panel__grid">
        <section class="mn-admin-panel__col mn-admin-panel__col--pre" aria-labelledby="mn-ap-pre">
          <h2 id="mn-ap-pre" class="mn-admin-panel__col-title">Pre-torneo</h2>
          <div class="mn-admin-panel__col-body">
            <?php
            mn_admin_op_button([
                'href' => $identUrl,
                'label' => 'Impresión de identificaciones',
                'sub' => 'Vista imprimible con QR y foto (perfil /atleta.php)',
                'theme' => 'pre',
                'disabled' => false,
                'icon' => $icoId,
            ]);
            ?>
          </div>
        </section>

        <section class="mn-admin-panel__col mn-admin-panel__col--op" aria-labelledby="mn-ap-op">
          <h2 id="mn-ap-op" class="mn-admin-panel__col-title">Operaciones</h2>
          <div class="mn-admin-panel__col-body">
            <?php
            mn_admin_op_button([
                'href' => $publicPrefix . 'carga_resultados.php?torneo_id=' . (int) $torneoId . '&mesa_id=1&partida=1',
                'label' => 'Carga de resultados',
                'sub' => 'Puntos, sets y extras por mesa (ajuste mesa_id en la URL).',
                'theme' => 'op',
                'disabled' => false,
                'icon' => $icoCarga,
            ]);
            mn_admin_op_button([
                'href' => '#',
                'label' => 'Cuadrícula de mesas',
                'sub' => 'Generación técnica (Fase 4)',
                'theme' => 'op',
                'disabled' => true,
                'icon' => $icoGrid,
            ]);
            mn_admin_op_button([
                'href' => '#',
                'label' => 'Hojas de anotación (carta)',
                'sub' => 'PDF para árbitros',
                'theme' => 'op',
                'disabled' => true,
                'icon' => $icoDoc,
            ]);
            mn_admin_op_button([
                'href' => '#',
                'label' => 'Clasificación de jugadores',
                'sub' => 'Ranking y siembra',
                'theme' => 'op',
                'disabled' => true,
                'icon' => $icoRank,
            ]);
            ?>
          </div>
        </section>

        <section class="mn-admin-panel__col mn-admin-panel__col--rep" aria-labelledby="mn-ap-rep">
          <h2 id="mn-ap-rep" class="mn-admin-panel__col-title">Reportes</h2>
          <div class="mn-admin-panel__col-body">
            <?php
            mn_admin_op_button([
                'href' => '#',
                'label' => 'Resultados individuales',
                'sub' => 'Filtros por ronda y mesa',
                'theme' => 'rep',
                'disabled' => true,
                'icon' => $icoUser,
            ]);
            if ($esEquipos) {
                mn_admin_op_button([
                    'href' => '#',
                    'label' => 'Equipos (resumen)',
                    'sub' => 'Vista agregada',
                    'theme' => 'rep',
                    'disabled' => true,
                    'icon' => $icoTeam,
                ]);
                mn_admin_op_button([
                    'href' => '#',
                    'label' => 'Equipos (detalle)',
                    'sub' => 'Desglose completo',
                    'theme' => 'rep',
                    'disabled' => true,
                    'icon' => $icoTeam,
                ]);
            }
            if ($esParejas) {
                mn_admin_op_button([
                    'href' => '#',
                    'label' => 'Parejas',
                    'sub' => 'Resultados por pareja',
                    'theme' => 'rep',
                    'disabled' => true,
                    'icon' => $icoPair,
                ]);
            }
            mn_admin_op_button([
                'href' => '#',
                'label' => 'Podios',
                'sub' => 'Posiciones finales',
                'theme' => 'rep',
                'disabled' => true,
                'icon' => $icoPod,
            ]);
            ?>
          </div>
          <p class="mn-admin-panel__hint">
            <?php if ($esIndividual) : ?>
              Modo <strong>individual</strong>: informes de equipos y parejas no se muestran.
            <?php elseif ($esParejas) : ?>
              Modo <strong>parejas</strong>: sin bloque de equipos.
            <?php else : ?>
              Modo <strong>equipos</strong>: resumen y detalle de equipos activos.
            <?php endif; ?>
          </p>
        </section>
      </div>

    <?php endif; ?>
  </div>
</body>
</html>
