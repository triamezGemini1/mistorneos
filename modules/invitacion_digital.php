<?php
/**
 * Invitación Digital - Vista pública tipo tarjeta por token.
 * Recibe token por GET y muestra tarjeta elegante con datos del evento y organizador.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/app_helpers.php';
require_once __DIR__ . '/../lib/InvitationJoinResolver.php';

$token = trim($_GET['token'] ?? '');
$error = '';
$inv = null;
$torneo = null;
$club_invitado = null;
$organizador = null;
$url_tarjeta = '';

if ($token === '') {
    $error = 'Enlace inválido. Falta el token de invitación.';
} else {
    try {
        $pdo = DB::pdo();
        $tb = defined('TABLE_INVITATIONS') ? TABLE_INVITATIONS : 'invitaciones';
        $stmt = $pdo->prepare("
            SELECT i.id, i.torneo_id, i.club_id, i.token, i.acceso1, i.acceso2, i.estado
            FROM {$tb} i
            WHERE i.token = ? AND (i.estado = 'activa' OR i.estado = 1 OR i.estado = 'vinculado' OR i.estado = 0)
        ");
        $stmt->execute([$token]);
        $inv = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$inv) {
            $error = 'Invitación no encontrada o no vigente.';
        } else {
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

            $stmt = $pdo->prepare("SELECT id, nombre, delegado, telefono, email FROM clubes WHERE id = ?");
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

            $base = rtrim(AppHelpers::getPublicUrl(), '/');
            $url_tarjeta = $base . '/invitation/digital?token=' . urlencode($token);
        }
    } catch (Exception $e) {
        $error = 'Error al cargar la invitación.';
    }
}

$page_title = $inv && $torneo ? 'Invitación - ' . htmlspecialchars($torneo['nombre'] ?? '') : 'Invitación';
$club_invitado = $club_invitado ?? null;
$torneo = $torneo ?? null;
$organizador = $organizador ?? null;
$delegado_nombre = $club_invitado ? ($club_invitado['delegado'] ?? ('Club ' . ($club_invitado['nombre'] ?? ''))) : 'Delegado';
$club_invitado_nombre = $club_invitado ? ($club_invitado['nombre'] ?? 'Su club') : 'Su club';
$fecha_torneo = $torneo && !empty($torneo['fechator']) ? date('d/m/Y', strtotime($torneo['fechator'])) : '';
$lugar = $torneo ? ($torneo['lugar'] ?? 'Por confirmar') : 'Por confirmar';
$hora = 'Por confirmar';
if ($torneo && (isset($torneo['hora_torneo']) || isset($torneo['hora']))) {
    $hora = isset($torneo['hora_torneo']) && $torneo['hora_torneo'] !== '' && $torneo['hora_torneo'] !== null
        ? $torneo['hora_torneo'] : (isset($torneo['hora']) ? $torneo['hora'] : 'Por confirmar');
    if (is_string($hora) && preg_match('/^\d{2}:\d{2}/', $hora)) {
        $hora = substr($hora, 0, 5);
    }
}
$logo_org_url = '';
if ($organizador && !empty($organizador['logo'])) {
    $path = $organizador['logo'];
    $logo_org_url = (strpos($path, 'http') === 0) ? $path : (AppHelpers::getPublicUrl() . '/view_image.php?path=' . rawurlencode($path));
}
$responsable = $organizador ? ($organizador['responsable'] ?? '') : '';
$tel_org = $organizador ? ($organizador['telefono'] ?? '') : '';
$email_org = $organizador ? ($organizador['email'] ?? '') : '';
$tel_wa = preg_replace('/\D/', '', (string) $tel_org);
if (strlen($tel_wa) > 0 && substr($tel_wa, 0, 1) !== '0') {
    $tel_wa = '58' . ltrim($tel_wa, '0');
}
$wa_confirmar = 'https://wa.me/' . $tel_wa . '?text=' . rawurlencode('Hola, confirmo asistencia al torneo ' . ($torneo ? ($torneo['nombre'] ?? '') : '') . ' el ' . $fecha_torneo . '.');

$afiche_url = ($torneo && !empty($torneo['afiche'])) ? AppHelpers::tournamentFile($torneo['afiche']) : '';
$normas_url = ($torneo && !empty($torneo['normas'])) ? AppHelpers::tournamentFile($torneo['normas']) : '';
$invitacion_url = ($torneo && !empty($torneo['invitacion'])) ? AppHelpers::tournamentFile($torneo['invitacion']) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 1rem 0.5rem; font-family: 'Segoe UI', system-ui, sans-serif; }
        .invitation-card {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,.25), 0 0 0 1px rgba(0,0,0,.05);
            overflow: hidden;
        }
        .invitation-card .card-header-org {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.25rem 1.5rem;
            background: linear-gradient(180deg, #f8f9fa 0%, #fff 100%);
            border-bottom: 1px solid #eee;
            flex-wrap: wrap;
        }
        .invitation-card .card-header-org .logo-wrap { flex-shrink: 0; }
        .invitation-card .card-header-org img { max-height: 70px; max-width: 160px; object-fit: contain; display: block; }
        .invitation-card .card-header-org .org-name { font-size: 1.25rem; font-weight: 700; color: #333; margin: 0; text-align: right; }
        .invitation-card .card-body { padding: 1.5rem 1.75rem; }
        .invitation-card .lead { font-size: 1.05rem; color: #444; line-height: 1.6; }
        .invitation-card .event-title-big { font-size: 1.5rem; font-weight: 700; color: #333; margin: 1rem 0; line-height: 1.3; }
        .invitation-card .event-box {
            background: #f0f4ff;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin: 1.25rem 0;
            border-left: 4px solid #667eea;
        }
        .invitation-card .event-box .event-title { font-weight: 700; font-size: 1.15rem; color: #333; margin-bottom: 0.5rem; }
        .invitation-card .event-box p { margin: 0.35rem 0; color: #555; }
        .invitation-card .btn-doc {
            display: inline-block;
            margin: 0.35rem 0.35rem 0.35rem 0;
            padding: 0.6rem 1rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: transform .15s, box-shadow .15s;
        }
        .invitation-card .btn-doc:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.15); color: #fff; }
        .invitation-card .btn-afiche { background: #198754; color: #fff; }
        .invitation-card .btn-normas { background: #0d6efd; color: #fff; }
        .invitation-card .btn-formal { background: #6f42c1; color: #fff; }
        .invitation-card .btn-pdf { background: #dc3545; color: #fff; }
        .invitation-card .btn-pdf:hover { color: #fff; }
        .invitation-card .card-footer-org {
            padding: 1.25rem 1.75rem;
            background: #f8f9fa;
            border-top: 1px solid #eee;
            font-size: 0.95rem;
            color: #555;
        }
        .invitation-card .card-footer-org .resp-name { font-weight: 700; color: #333; }
        .invitation-card .btn-wa-confirm {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1rem;
            padding: 0.75rem 1.5rem;
            background: #25d366;
            color: #fff;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            text-decoration: none;
            box-shadow: 0 4px 14px rgba(37,211,102,.4);
        }
        .invitation-card .btn-wa-confirm:hover { background: #20bd5a; color: #fff; }
        .alert-inv { max-width: 600px; margin: 0 auto; border-radius: 12px; }
        @media (max-width: 576px) {
            .invitation-card .card-header-org { flex-direction: column; text-align: center; }
            .invitation-card .card-header-org .org-name { text-align: center; }
        }
    </style>
</head>
<body>
<?php if ($error): ?>
    <div class="container"><div class="row"><div class="col-12"><div class="alert alert-danger alert-inv" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
    </div></div></div></div>
<?php else: ?>
    <div class="container"><div class="row"><div class="col-12">
    <div class="invitation-card">
        <div class="card-header-org">
            <div class="logo-wrap">
                <?php if ($logo_org_url): ?>
                    <img src="<?= htmlspecialchars($logo_org_url) ?>" alt="Logo organizador">
                <?php else: ?>
                    <i class="fas fa-trophy fa-3x text-secondary"></i>
                <?php endif; ?>
            </div>
            <h2 class="org-name"><?= htmlspecialchars($organizador ? ($organizador['nombre'] ?? '') : '') ?></h2>
        </div>
        <div class="card-body">
            <p class="lead">
                Estimado delegado del club <strong><?= htmlspecialchars($club_invitado_nombre) ?></strong>,
                le invitamos cordialmente al siguiente evento:
            </p>
            <h3 class="event-title-big"><?= htmlspecialchars($torneo['nombre'] ?? '') ?></h3>
            <div class="event-box">
                <p><i class="fas fa-calendar-alt me-2 text-primary"></i><strong>Fecha:</strong> <?= htmlspecialchars($fecha_torneo) ?></p>
                <p><i class="fas fa-clock me-2 text-secondary"></i><strong>Hora:</strong> <?= htmlspecialchars($hora) ?></p>
                <p><i class="fas fa-map-marker-alt me-2 text-danger"></i><strong>Lugar:</strong> <?= htmlspecialchars($lugar) ?></p>
            </div>
            <?php $url_acceso = InvitationJoinResolver::buildJoinUrl($token); ?>
            <div class="mb-3 p-3 rounded" style="background: #e8f4fd; border: 1px solid #0d6efd;">
                <p class="mb-2"><strong><i class="fas fa-link me-1"></i>Enlace de acceso (registro e inscripción de jugadores):</strong></p>
                <a href="<?= htmlspecialchars($url_acceso) ?>" class="btn btn-primary d-inline-flex align-items-center gap-2" style="word-break: break-all;">
                    <i class="fas fa-sign-in-alt"></i> Ir al formulario de acceso
                </a>
                <p class="small text-muted mt-2 mb-0">Use este enlace para registrarse como delegado o, si ya está registrado, para acceder al formulario de inscripción de jugadores. El sistema le dirigirá al paso que corresponda.</p>
            </div>
            <p class="mb-2"><strong>Documentos:</strong></p>
            <div class="d-flex flex-wrap">
                <?php if ($afiche_url): ?>
                    <a href="<?= htmlspecialchars($afiche_url) ?>" class="btn-doc btn-afiche" target="_blank" rel="noopener noreferrer"><i class="fas fa-image me-1"></i>Ver Afiche</a>
                <?php endif; ?>
                <?php if ($normas_url): ?>
                    <a href="<?= htmlspecialchars($normas_url) ?>" class="btn-doc btn-normas" target="_blank" rel="noopener noreferrer"><i class="fas fa-book me-1"></i>Normas de Juego</a>
                <?php endif; ?>
                <?php if ($invitacion_url): ?>
                    <a href="<?= htmlspecialchars($invitacion_url) ?>" class="btn-doc btn-formal" target="_blank" rel="noopener noreferrer"><i class="fas fa-file-pdf me-1"></i>Invitación Formal</a>
                <?php endif; ?>
                <?php
                $pdf_url = rtrim(AppHelpers::getPublicUrl(), '/') . '/invitation/digital/pdf?token=' . urlencode($token);
                ?>
                <a href="<?= htmlspecialchars($pdf_url) ?>" class="btn-doc btn-pdf" target="_blank" rel="noopener noreferrer"><i class="fas fa-file-pdf me-1"></i>Descargar Invitación Oficial (PDF)</a>
                <?php if (!$afiche_url && !$normas_url && !$invitacion_url): ?>
                    <span class="text-muted small ms-2">No hay más documentos adjuntos.</span>
                <?php endif; ?>
            </div>
            <?php if ($tel_wa): ?>
                <a href="<?= htmlspecialchars($wa_confirmar) ?>" class="btn-wa-confirm" target="_blank" rel="noopener noreferrer">
                    <i class="fab fa-whatsapp fa-lg"></i> Confirmar Asistencia vía WhatsApp
                </a>
            <?php endif; ?>
        </div>
        <div class="card-footer-org">
            <span class="resp-name"><?= htmlspecialchars($responsable) ?></span>
            <?php if ($tel_org): ?><br><i class="fas fa-phone me-1"></i><?= htmlspecialchars($tel_org) ?><?php endif; ?>
            <?php if ($email_org): ?><br><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($email_org) ?><?php endif; ?>
        </div>
    </div>
    </div></div></div>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
