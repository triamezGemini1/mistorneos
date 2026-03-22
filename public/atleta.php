<?php

declare(strict_types=1);

/**
 * Perfil público mínimo del atleta (destino del QR en credenciales).
 * Ruta amigable futura: /atleta/{id} vía rewrite; por ahora ?id=
 */

$root = dirname(__DIR__);
require $root . '/config/bootstrap.php';
require_once $root . '/app/Database/ConnectionException.php';
require_once $root . '/app/Database/Connection.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$tabla = getenv('DB_AUTH_TABLE') ?: 'usuarios';
$tabla = in_array(strtolower(trim((string) $tabla)), ['usuarios', 'users'], true) ? strtolower(trim((string) $tabla)) : 'usuarios';

$row = null;
if ($id > 0) {
    try {
        $pdo = Connection::get();
        try {
            $st = $pdo->prepare("SELECT id, nombre, username, cedula, foto, avatar FROM `{$tabla}` WHERE id = ? LIMIT 1");
        } catch (Throwable $e) {
            $st = $pdo->prepare("SELECT id, nombre, username, cedula FROM `{$tabla}` WHERE id = ? LIMIT 1");
        }
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $row = null;
    }
}

header('Content-Type: text/html; charset=utf-8');

if ($row === false || $row === null) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Atleta</title></head><body><p>Perfil no encontrado.</p></body></html>';
    exit;
}

$nombre = trim((string) ($row['nombre'] ?? ''));
if ($nombre === '') {
    $nombre = (string) ($row['username'] ?? 'Atleta');
}
$cedula = trim((string) ($row['cedula'] ?? ''));
$foto = trim((string) ($row['foto'] ?? $row['avatar'] ?? ''));

$script = $_SERVER['SCRIPT_NAME'] ?? '';
$publicPrefix = str_contains($script, '/public/') ? '' : 'public/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?> — mistorneos</title>
  <link rel="stylesheet" href="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>assets/css/mistorneos-core.css" />
  <style>
    .mn-atleta-pub { max-width: 28rem; margin: 2rem auto; padding: 1.5rem; text-align: center; }
    .mn-atleta-pub__foto { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid var(--mn-border); margin-bottom: 1rem; background: var(--mn-surface); }
    .mn-atleta-pub__ph { width: 120px; height: 120px; border-radius: 50%; margin: 0 auto 1rem; background: linear-gradient(135deg, var(--mn-blue-mid), var(--mn-blue-accent)); color: #fff; display: grid; place-items: center; font-size: 2.5rem; font-weight: 800; }
  </style>
</head>
<body>
  <div class="mn-container mn-atleta-pub">
    <?php if ($foto !== '') : ?>
      <img class="mn-atleta-pub__foto" src="<?= htmlspecialchars($foto, ENT_QUOTES, 'UTF-8') ?>" alt="" />
    <?php else : ?>
      <div class="mn-atleta-pub__ph" aria-hidden="true"><?php
        $ini = function_exists('mb_substr')
            ? mb_strtoupper(mb_substr($nombre, 0, 1))
            : strtoupper(substr($nombre, 0, 1));
        echo htmlspecialchars($ini !== '' ? $ini : '?', ENT_QUOTES, 'UTF-8');
      ?></div>
    <?php endif; ?>
    <h1 style="margin:0 0 0.5rem;font-size:1.5rem;color:var(--mn-blue-deep);"><?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?></h1>
    <?php if ($cedula !== '') : ?>
      <p class="mn-hint" style="margin:0;">Documento: <?= htmlspecialchars($cedula, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <p class="mn-hint" style="margin-top:1.25rem;">Credencial verificada — mistorneos</p>
  </div>
</body>
</html>
