<?php

declare(strict_types=1);

/**
 * Controlador de vista: formularios de carga de resultados (Modelo A estándar / Modelo B parejas).
 * GET: torneo_id, mesa_id, partida (opcional, default 1).
 */

$root = dirname(__DIR__);
require $root . '/config/bootstrap.php';
require_once $root . '/app/Database/ConnectionException.php';
require_once $root . '/app/Database/Connection.php';
require_once $root . '/app/Core/TournamentEngineService.php';
require_once $root . '/app/Core/OrganizacionService.php';
require_once $root . '/app/Core/CargaResultadosService.php';
require_once $root . '/app/Helpers/AdminApi.php';

if (mn_admin_session() === null) {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $to = str_contains($script, '/public/') ? 'index.php' : 'public/index.php';
    header('Location: ' . $to . '?acceso=admin', true, 303);
    exit;
}

$admin = mn_admin_session();
$scope = mn_admin_torneo_query_scope();
$adminId = (int) ($admin['id'] ?? 0);

$script = $_SERVER['SCRIPT_NAME'] ?? '';
$publicPrefix = str_contains($script, '/public/') ? '' : 'public/';

$torneoId = isset($_REQUEST['torneo_id']) ? (int) $_REQUEST['torneo_id'] : 0;
$mesaId = isset($_REQUEST['mesa_id']) ? (int) $_REQUEST['mesa_id'] : 0;
$partida = isset($_REQUEST['partida']) ? (int) $_REQUEST['partida'] : 1;
if ($partida <= 0) {
    $partida = 1;
}

$error = null;
$okMsg = null;
if (!empty($_SESSION['carga_resultados_ok'])) {
    $okMsg = (string) $_SESSION['carga_resultados_ok'];
    unset($_SESSION['carga_resultados_ok']);
}
if (!empty($_SESSION['carga_resultados_err'])) {
    $error = (string) $_SESSION['carga_resultados_err'];
    unset($_SESSION['carga_resultados_err']);
}

$torneo = null;
$filas = [];
$puntosObjetivo = CargaResultadosService::puntosObjetivoMesa();
$tipoRaw = 'individual';
$esParejas = false;

if ($scope === false) {
    $error = $error ?? 'Sin organización en sesión.';
} elseif ($torneoId <= 0 || $mesaId <= 0) {
    $error = $error ?? 'Indique torneo_id y mesa_id (ej. carga_resultados.php?torneo_id=1&mesa_id=1&partida=1).';
} else {
    try {
        $pdo = Connection::get();
    } catch (ConnectionException $e) {
        $pdo = null;
        $error = $error ?? 'Sin conexión a la base de datos.';
    }

    if (isset($pdo) && $pdo instanceof PDO) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
            if (!csrf_validate($token)) {
                $error = 'Sesión de seguridad inválida. Recargue la página.';
            } else {
                $action = (string) ($_POST['action'] ?? '');
                try {
                    if ($action === 'guardar_estandar') {
                        $lineas = $_POST['lineas'] ?? [];
                        if (!is_array($lineas)) {
                            throw new InvalidArgumentException('Datos de formulario inválidos.');
                        }
                        $idsOrden = [];
                        $porFila = [];
                        $sumP = 0;
                        foreach ($lineas as $row) {
                            if (!is_array($row)) {
                                continue;
                            }
                            $pid = (int) ($row['partiresul_id'] ?? 0);
                            if ($pid <= 0) {
                                continue;
                            }
                            $p = (float) ($row['puntos'] ?? 0);
                            $sumP += $p;
                            $idsOrden[] = $pid;
                            $porFila[] = [
                                'puntos' => $p,
                                'sets' => (float) ($row['sets'] ?? 0),
                                'chancleta' => (float) ($row['chancleta'] ?? 0),
                                'zapato' => (float) ($row['zapato'] ?? 0),
                            ];
                        }
                        if ($idsOrden === []) {
                            throw new InvalidArgumentException('No hay líneas válidas para guardar.');
                        }
                        if ((int) round($sumP) !== $puntosObjetivo) {
                            throw new InvalidArgumentException(
                                'La suma de puntos debe ser exactamente ' . $puntosObjetivo . ' (reglamento de mesa).'
                            );
                        }
                        CargaResultadosService::guardarEstandar($pdo, $torneoId, $idsOrden, $porFila, $adminId);
                        $_SESSION['carga_resultados_ok'] = 'Resultados guardados correctamente.';
                        header('Location: ' . $publicPrefix . 'carga_resultados.php?torneo_id=' . $torneoId . '&mesa_id=' . $mesaId . '&partida=' . $partida, true, 303);
                        exit;
                    }

                    if ($action === 'guardar_parejas') {
                        $pa1 = (int) ($_POST['pid_a1'] ?? 0);
                        $pa2 = (int) ($_POST['pid_a2'] ?? 0);
                        $pb1 = (int) ($_POST['pid_b1'] ?? 0);
                        $pb2 = (int) ($_POST['pid_b2'] ?? 0);
                        if ($pa1 <= 0 || $pa2 <= 0 || $pb1 <= 0 || $pb2 <= 0) {
                            throw new InvalidArgumentException('Faltan identificadores de pareja.');
                        }
                        $pA = (float) ($_POST['puntos_A'] ?? 0);
                        $pB = (float) ($_POST['puntos_B'] ?? 0);
                        if ((int) round($pA + $pB) !== $puntosObjetivo) {
                            throw new InvalidArgumentException(
                                'Puntos pareja A + puntos pareja B deben sumar ' . $puntosObjetivo . '.'
                            );
                        }
                        $datos = [
                            'puntos_A' => $pA,
                            'sets_A' => (float) ($_POST['sets_A'] ?? 0),
                            'chancleta_A' => (float) ($_POST['chancleta_A'] ?? 0),
                            'zapato_A' => (float) ($_POST['zapato_A'] ?? 0),
                            'puntos_B' => $pB,
                            'sets_B' => (float) ($_POST['sets_B'] ?? 0),
                            'chancleta_B' => (float) ($_POST['chancleta_B'] ?? 0),
                            'zapato_B' => (float) ($_POST['zapato_B'] ?? 0),
                        ];
                        CargaResultadosService::guardarParejas(
                            $pdo,
                            $torneoId,
                            [$pa1, $pa2],
                            [$pb1, $pb2],
                            $datos,
                            $adminId
                        );
                        $_SESSION['carga_resultados_ok'] = 'Resultados de parejas guardados (replicados a ambos integrantes).';
                        header('Location: ' . $publicPrefix . 'carga_resultados.php?torneo_id=' . $torneoId . '&mesa_id=' . $mesaId . '&partida=' . $partida, true, 303);
                        exit;
                    }
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                }
            }
        }

        $torneo = TournamentEngineService::getTorneo($pdo, $torneoId, $scope);
        if ($torneo === null || !OrganizacionService::adminPuedeGestionarTorneo($admin, $torneo)) {
            $error = $error ?? 'Torneo no encontrado o sin permiso.';
            $torneo = null;
        } else {
            $tipoRaw = strtolower(trim((string) ($torneo['tipo_torneo'] ?? 'individual')));
            if (!in_array($tipoRaw, ['individual', 'parejas', 'equipos'], true)) {
                $tipoRaw = 'individual';
            }
            $esParejas = $tipoRaw === 'parejas';
            $filas = CargaResultadosService::obtenerFilasMesa($pdo, $torneoId, $partida, $mesaId);
        }
    }
}

$csrfToken = csrf_token();
$partials = $root . '/public/views/partials';

$tipoEtiqueta = $tipoRaw === 'parejas' ? 'Parejas' : ($tipoRaw === 'equipos' ? 'Equipos' : 'Individual');
$panelBack = $torneoId > 0
    ? $publicPrefix . 'admin_panel.php?torneo_id=' . $torneoId
    : $publicPrefix . 'admin_torneo.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Carga de resultados — mistorneos</title>
  <link rel="stylesheet" href="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>assets/css/mistorneos-core.css" />
</head>
<body class="mn-carga-res-body">
  <div class="mn-container mn-carga-res-wrap">
    <div class="mn-carga-res-bar">
      <a class="mn-carga-res-link" href="<?= htmlspecialchars($panelBack, ENT_QUOTES, 'UTF-8') ?>">← Consola del torneo</a>
      <span class="mn-hint">Mesa <?= (int) $mesaId ?> · Partida <?= (int) $partida ?> · Suma puntos objetivo: <?= (int) $puntosObjetivo ?> <span class="mn-carga-res-env">(variable <code>MESA_PUNTOS_OBJETIVO</code> en .env)</span></span>
    </div>

    <?php if ($torneo !== null) : ?>
      <header class="mn-carga-res-hero">
        <h1 class="mn-carga-res-hero-title"><?= htmlspecialchars((string) $torneo['nombre'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="mn-carga-res-hero-tipo">
          Tipo: <strong><?= htmlspecialchars($tipoEtiqueta, ENT_QUOTES, 'UTF-8') ?></strong>
        </p>
      </header>
    <?php endif; ?>

    <?php if ($okMsg !== null) : ?>
      <p class="mn-hint" style="background:#e8f7ef;border:1px solid var(--mn-success);border-radius:8px;padding:0.75rem 1rem;color:#0d281f;"><?= htmlspecialchars($okMsg, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($error !== null) : ?>
      <p class="mn-hint mn-hint--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <?php if ($torneo !== null && $filas !== []) : ?>
      <div
        class="mn-carga-res-forms"
        data-puntos-objetivo="<?= (int) $puntosObjetivo ?>"
        data-modo="<?= $esParejas ? 'parejas' : 'estandar' ?>"
      >
        <?php if ($esParejas) : ?>
          <?php if (count($filas) >= 4) : ?>
            <?php require $partials . '/form_carga_parejas.php'; ?>
          <?php else : ?>
            <p class="mn-hint mn-hint--error">El modo parejas requiere 4 jugadores asignados en esta mesa y partida (filas en partiresul).</p>
          <?php endif; ?>
        <?php else : ?>
          <?php require $partials . '/form_carga_estandar.php'; ?>
        <?php endif; ?>
      </div>
    <?php elseif ($torneo !== null && $filas === [] && $error === null) : ?>
      <p class="mn-hint">No hay registros en <code>partiresul</code> para esta mesa y partida. Genere la cuadrícula desde el motor de mesas primero.</p>
    <?php endif; ?>
  </div>
  <script src="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>assets/js/carga-resultados.js" defer></script>
</body>
</html>
