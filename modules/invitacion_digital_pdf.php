<?php
/**
 * Exportación PDF de la Invitación Digital (tarjeta por token).
 * Misma capa de datos que invitacion_digital; genera PDF descargable.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/app_helpers.php';

$token = trim($_GET['token'] ?? '');
if ($token === '') {
    header('HTTP/1.1 400 Bad Request');
    echo 'Token requerido';
    exit;
}

$inv = null;
$torneo = null;
$club_invitado = null;
$organizador = null;

try {
    $pdo = DB::pdo();
    $tb = defined('TABLE_INVITATIONS') ? TABLE_INVITATIONS : 'invitaciones';
    $stmt = $pdo->prepare("
        SELECT i.id, i.torneo_id, i.club_id, i.token
        FROM {$tb} i
        WHERE i.token = ? AND (i.estado = 'activa' OR i.estado = 1)
    ");
    $stmt->execute([$token]);
    $inv = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inv) {
        header('HTTP/1.1 404 Not Found');
        echo 'Invitación no encontrada o no vigente';
        exit;
    }

    $cols = $pdo->query("SHOW COLUMNS FROM tournaments")->fetchAll(PDO::FETCH_COLUMN);
    $sel_lugar = in_array('lugar', $cols) ? 't.lugar' : 'NULL AS lugar';
    $sel_hora = in_array('hora_torneo', $cols) ? 't.hora_torneo' : (in_array('hora', $cols) ? 't.hora' : 'NULL AS hora_torneo');
    $stmt = $pdo->prepare("
        SELECT t.id, t.nombre, t.fechator, t.afiche, t.normas, t.invitacion, t.club_responsable, {$sel_lugar}, {$sel_hora}
        FROM tournaments t
        WHERE t.id = ?
    ");
    $stmt->execute([$inv['torneo_id']]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT id, nombre, delegado FROM clubes WHERE id = ?");
    $stmt->execute([$inv['club_id']]);
    $club_invitado = $stmt->fetch(PDO::FETCH_ASSOC);

    $org_id = (int)($torneo['club_responsable'] ?? 0);
    if ($org_id > 0) {
        $stmt = $pdo->prepare("SELECT id, nombre, logo, responsable, telefono, email FROM organizaciones WHERE id = ?");
        $stmt->execute([$org_id]);
        $organizador = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$organizador) {
        $organizador = ['nombre' => 'Organización', 'logo' => null, 'responsable' => '', 'telefono' => '', 'email' => ''];
    }
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Error al cargar la invitación';
    exit;
}

$club_invitado_nombre = $club_invitado['nombre'] ?? 'Su club';
$fecha_torneo = date('d/m/Y', strtotime($torneo['fechator']));
$lugar = $torneo['lugar'] ?? 'Por confirmar';
$hora = isset($torneo['hora_torneo']) && $torneo['hora_torneo'] !== '' && $torneo['hora_torneo'] !== null
    ? $torneo['hora_torneo'] : (isset($torneo['hora']) ? $torneo['hora'] : 'Por confirmar');
if (is_string($hora) && preg_match('/^\d{2}:\d{2}/', $hora)) {
    $hora = substr($hora, 0, 5);
}
$responsable = $organizador['responsable'] ?? '';
$tel_org = $organizador['telefono'] ?? '';
$email_org = $organizador['email'] ?? '';

$logo_src = '';
if (!empty($organizador['logo'])) {
    $path = $organizador['logo'];
    $full_path = (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__)) . '/' . ltrim(str_replace('\\', '/', $path), '/');
    if (file_exists($full_path)) {
        $logo_src = $path;
    }
}

$titulo_torneo = $torneo['nombre'] ?? '';
$org_nombre = $organizador['nombre'] ?? '';

$html = '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Invitación - ' . htmlspecialchars($titulo_torneo) . '</title>
<style>
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11pt; color: #333; margin: 0; padding: 20px; }
.card { max-width: 560px; margin: 0 auto; background: #fff; border: 1px solid #e0e0e0; border-radius: 12px; overflow: hidden; }
.header { padding: 20px; background: #f8f9fa; border-bottom: 1px solid #eee; overflow: hidden; }
.header table { width: 100%; border: 0; }
.header td:first-child { width: 120px; vertical-align: middle; }
.header td:last-child { text-align: right; vertical-align: middle; font-size: 14pt; font-weight: bold; color: #333; }
.header img { max-height: 60px; max-width: 120px; }
.body { padding: 24px; }
.lead { font-size: 11pt; color: #444; margin-bottom: 12px; line-height: 1.5; }
.event-title { font-size: 16pt; font-weight: bold; color: #333; margin: 14px 0; }
.event-box { background: #f0f4ff; border-left: 4px solid #667eea; padding: 14px 18px; margin: 16px 0; border-radius: 0 8px 8px 0; }
.event-box p { margin: 6px 0; color: #555; }
.footer { padding: 18px 24px; background: #f8f9fa; border-top: 1px solid #eee; font-size: 10pt; color: #555; }
.footer .resp-name { font-weight: bold; color: #333; }
</style>
</head>
<body>
<div class="card">
  <div class="header">
    <table><tr>
      <td>' . ($logo_src ? '<img src="' . htmlspecialchars($logo_src) . '" alt="">' : '') . '</td>
      <td>' . htmlspecialchars($org_nombre) . '</td>
    </tr></table>
  </div>
  <div class="body">
    <p class="lead">Estimado delegado del club <strong>' . htmlspecialchars($club_invitado_nombre) . '</strong>, le invitamos cordialmente al siguiente evento:</p>
    <div class="event-title">' . htmlspecialchars($titulo_torneo) . '</div>
    <div class="event-box">
      <p><strong>Fecha:</strong> ' . htmlspecialchars($fecha_torneo) . '</p>
      <p><strong>Hora:</strong> ' . htmlspecialchars($hora) . '</p>
      <p><strong>Lugar:</strong> ' . htmlspecialchars($lugar) . '</p>
    </div>
    <p><strong>Contacto:</strong></p>
    <p>' . htmlspecialchars($responsable) . ($tel_org ? ' &ndash; ' . htmlspecialchars($tel_org) : '') . ($email_org ? ' &ndash; ' . htmlspecialchars($email_org) : '') . '</p>
  </div>
  <div class="footer">
    <span class="resp-name">' . htmlspecialchars($responsable) . '</span><br>
    ' . ($tel_org ? htmlspecialchars($tel_org) . '<br>' : '') . '
    ' . ($email_org ? htmlspecialchars($email_org) : '') . '
  </div>
</div>
</body>
</html>';

if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    header('HTTP/1.1 503 Service Unavailable');
    echo 'Librería PDF no disponible';
    exit;
}
require_once __DIR__ . '/../vendor/autoload.php';

$options = new \Dompdf\Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('chroot', realpath(__DIR__ . '/..'));

$dompdf = new \Dompdf\Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'Invitacion_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $titulo_torneo) . '_' . date('Y-m-d') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
