<?php
/**
 * Invitar Administradores de Organización
 * Permite invitar a personas para que se conviertan en administradores de club
 * Solo accesible para admin_general
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../lib/app_helpers.php';

Auth::requireRole(['admin_general']);

$pdo = DB::pdo();
$current_user = Auth::user();
$error = '';
$success = '';
$whatsapp_enlace = null;

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::validate();
    
    $nombre_invitado = trim($_POST['nombre_invitado'] ?? '');
    $telefono_invitado = trim($_POST['telefono_invitado'] ?? '');
    $notas = trim($_POST['notas'] ?? '');
    
    if (empty($nombre_invitado) || empty($telefono_invitado)) {
        $error = 'El nombre y el teléfono son requeridos';
    } else {
        try {
            require_once __DIR__ . '/../../lib/whatsapp_sender.php';
            
            // Generar mensaje de invitación para WhatsApp
            $base_url = AppHelpers::getBaseUrl();
            $mensaje = "🎯 *INVITACIÓN A ADMINISTRADOR DE CLUB*\n\n";
            $mensaje .= "Hola *" . $nombre_invitado . "*,\n\n";
            $mensaje .= "Te invitamos a formar parte de *La Estación del Dominó* como *Administrador de Organización*.\n\n";
            $mensaje .= "📋 *BENEFICIOS:*\n";
            $mensaje .= "✅ Gestión completa de tu club desde una plataforma centralizada\n";
            $mensaje .= "✅ Organización de torneos propios\n";
            $mensaje .= "✅ Gestión de afiliados y estadísticas\n";
            $mensaje .= "✅ Invitaciones personalizadas por WhatsApp\n";
            $mensaje .= "✅ Galería de fotos para tus eventos\n";
            $mensaje .= "✅ Soporte técnico y capacitación\n\n";
            
            if (!empty($notas)) {
                $mensaje .= "📝 *MENSAJE PERSONALIZADO:*\n" . $notas . "\n\n";
            }
            
            $mensaje .= "🔗 *ACCESO AL SISTEMA:*\n";
            $mensaje .= $base_url . "\n\n";
            $mensaje .= "Para más información, contáctanos.\n\n";
            $mensaje .= "¡Esperamos tu respuesta! 🎲";
            
            // Generar enlace de WhatsApp
            $whatsapp_link = WhatsAppSender::generateWhatsAppLink($telefono_invitado, $mensaje);
            
            $success = 'Enlace de WhatsApp generado correctamente. Haz clic en el botón para enviar la invitación.';
            $whatsapp_enlace = $whatsapp_link;
            
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
            error_log("Error en invitar admin_club: " . $e->getMessage());
        }
    }
}

// Obtener lista de invitaciones enviadas (si existe tabla)
$invitaciones_enviadas = [];
try {
    $stmt = $pdo->query("
        SELECT id, nombre_invitado, telefono_invitado, fecha_invitacion
        FROM admin_club_invitations 
        ORDER BY fecha_invitacion DESC 
        LIMIT 20
    ");
    $invitaciones_enviadas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // La tabla puede no existir aún, no es crítico
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitar Administradores de Organización - La Estación del Dominó</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .invitation-card {
            border: 2px solid #667eea;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .benefits-section {
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .benefit-item {
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .benefit-item:last-child {
            border-bottom: none;
        }
        .benefit-icon {
            color: #25D366;
            font-size: 1.5rem;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="fas fa-user-plus text-primary me-2"></i>
                    Invitar Administradores de Organización
                </h2>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Formulario de Invitación -->
            <div class="col-lg-6">
                <div class="card invitation-card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fab fa-whatsapp me-2"></i>Datos del Invitado
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="invitationForm">
                            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
                            
                            <div class="mb-3">
                                <label for="nombre_invitado" class="form-label">
                                    <i class="fas fa-user me-1"></i>Nombre Completo <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="nombre_invitado" 
                                       name="nombre_invitado" 
                                       value="<?= htmlspecialchars($_POST['nombre_invitado'] ?? '') ?>"
                                       required>
                            </div>

                            <div class="mb-3">
                                <label for="telefono_invitado" class="form-label">
                                    <i class="fab fa-whatsapp me-1"></i>Teléfono WhatsApp <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="telefono_invitado" 
                                       name="telefono_invitado" 
                                       value="<?= htmlspecialchars($_POST['telefono_invitado'] ?? '') ?>"
                                       placeholder="Ej: +584241526722 o 04241526722"
                                       required>
                                <small class="form-text text-muted">Ingrese el número de teléfono con código de país (ej: +58) o sin él (ej: 0424)</small>
                            </div>

                            <div class="mb-3">
                                <label for="notas" class="form-label">
                                    <i class="fas fa-sticky-note me-1"></i>Notas Adicionales (Opcional)
                                </label>
                                <textarea class="form-control" 
                                          id="notas" 
                                          name="notas" 
                                          rows="3"
                                          placeholder="Mensaje personalizado para el invitado..."><?= htmlspecialchars($_POST['notas'] ?? '') ?></textarea>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fab fa-whatsapp me-2"></i>Generar Enlace de WhatsApp
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Vista Previa de Ventajas -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-star me-2"></i>Ventajas de Ser Administrador de Organización
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="benefits-section">
                            <div class="benefit-item">
                                <i class="fas fa-check-circle benefit-icon"></i>
                                <strong>Gestión Completa de Tu Club</strong>
                                <p class="text-muted mb-0 small">Administra todos los aspectos de tu club desde una plataforma centralizada.</p>
                            </div>
                            <div class="benefit-item">
                                <i class="fas fa-check-circle benefit-icon"></i>
                                <strong>Organización de Torneos</strong>
                                <p class="text-muted mb-0 small">Crea y gestiona torneos propios con herramientas profesionales de administración.</p>
                            </div>
                            <div class="benefit-item">
                                <i class="fas fa-check-circle benefit-icon"></i>
                                <strong>Gestión de Afiliados</strong>
                                <p class="text-muted mb-0 small">Administra la lista de afiliados, sus datos y estadísticas de participación.</p>
                            </div>
                            <div class="benefit-item">
                                <i class="fas fa-check-circle benefit-icon"></i>
                                <strong>Reportes y Estadísticas</strong>
                                <p class="text-muted mb-0 small">Accede a reportes detallados y estadísticas de tu club y torneos.</p>
                            </div>
                            <div class="benefit-item">
                                <i class="fas fa-check-circle benefit-icon"></i>
                                <strong>Invitaciones Personalizadas</strong>
                                <p class="text-muted mb-0 small">Invita a jugadores a tus torneos mediante WhatsApp con enlaces personalizados.</p>
                            </div>
                            <div class="benefit-item">
                                <i class="fas fa-check-circle benefit-icon"></i>
                                <strong>Galería de Fotos</strong>
                                <p class="text-muted mb-0 small">Comparte las mejores fotos de tus eventos y torneos.</p>
                            </div>
                            <div class="benefit-item">
                                <i class="fas fa-check-circle benefit-icon"></i>
                                <strong>Soporte Técnico</strong>
                                <p class="text-muted mb-0 small">Recibe asistencia técnica y capacitación para aprovechar al máximo la plataforma.</p>
                            </div>
                            <div class="benefit-item">
                                <i class="fas fa-check-circle benefit-icon"></i>
                                <strong>Comunidad Activa</strong>
                                <p class="text-muted mb-0 small">Forma parte de una red de clubes y administradores activos en el mundo del dominó.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enlace de WhatsApp Generado -->
        <?php if ($whatsapp_enlace): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card border-success">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fab fa-whatsapp me-2"></i>Enlace de WhatsApp Generado
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-3">El enlace de WhatsApp ha sido generado. Haz clic en el botón para abrir WhatsApp y enviar la invitación al invitado.</p>
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="<?= htmlspecialchars($whatsapp_enlace) ?>" 
                                   class="btn btn-success btn-lg" 
>
                                    <i class="fab fa-whatsapp me-2"></i>Enviar por WhatsApp
                                </a>
                                <button type="button" 
                                        class="btn btn-outline-secondary"
                                        onclick="copiarEnlace('<?= htmlspecialchars($whatsapp_enlace) ?>')">
                                    <i class="fas fa-copy me-2"></i>Copiar Enlace
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Historial de Invitaciones -->
        <?php if (!empty($invitaciones_enviadas)): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-history me-2"></i>Invitaciones Enviadas Recientemente
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Nombre</th>
                                            <th>Teléfono</th>
                                            <th>Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invitaciones_enviadas as $inv): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($inv['nombre_invitado']) ?></td>
                                                <td><?= htmlspecialchars($inv['telefono_invitado'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($inv['fecha_invitacion']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    <script>
        function copiarEnlace(url) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(() => {
                    alert('Enlace copiado al portapapeles');
                }).catch(() => {
                    fallbackCopiar(url);
                });
            } else {
                fallbackCopiar(url);
            }
        }

        function fallbackCopiar(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                alert('Enlace copiado al portapapeles');
            } catch (err) {
                alert('Error al copiar. Seleccione manualmente el enlace.');
            }
            document.body.removeChild(textArea);
        }
    </script>
</body>
</html>


