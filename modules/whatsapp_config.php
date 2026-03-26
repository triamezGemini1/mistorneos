<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

// Verificar permisos
Auth::requireRole(['admin_general', 'admin_torneo']);

// Obtener acción solicitada
$action = $_GET['action'] ?? 'view';
$template_id = $_GET['id'] ?? null;

// Obtener mensajes de éxito y error de la URL
$success_message = $_GET['success'] ?? null;
$error_message = $_GET['error'] ?? null;

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'save') {
        try {
            $name = trim($_POST['name'] ?? '');
            $template = trim($_POST['template'] ?? '');
            $is_default = isset($_POST['is_default']) ? 1 : 0;
            
            if (empty($name) || empty($template)) {
                throw new Exception('Nombre y plantilla son requeridos');
            }
            
            // Si se marca como predeterminada, desmarcar las otras
            if ($is_default) {
                $stmt = DB::pdo()->prepare("UPDATE whatsapp_templates SET is_default = 0");
                $stmt->execute();
            }
            
            $stmt = DB::pdo()->prepare("INSERT INTO whatsapp_templates (name, template, is_default) VALUES (?, ?, ?)");
            $stmt->execute([$name, $template, $is_default]);
            
            $success_message = "Plantilla creada exitosamente";
            AppHelpers::redirectToDashboard('whatsapp_config', ['success' => $success_message]);
            exit;
        } catch (Exception $e) {
            $error_message = "Error al crear plantilla: " . $e->getMessage();
        }
    }
    
    if ($action === 'update') {
        try {
            $template_id = $_POST['template_id'] ?? '';
            $name = trim($_POST['name'] ?? '');
            $template = trim($_POST['template'] ?? '');
            $is_default = isset($_POST['is_default']) ? 1 : 0;
            
            if (empty($template_id) || empty($name) || empty($template)) {
                throw new Exception('Todos los campos son requeridos');
            }
            
            // Si se marca como predeterminada, desmarcar las otras
            if ($is_default) {
                $stmt = DB::pdo()->prepare("UPDATE whatsapp_templates SET is_default = 0");
                $stmt->execute();
            }
            
            $stmt = DB::pdo()->prepare("UPDATE whatsapp_templates SET name = ?, template = ?, is_default = ? WHERE id = ?");
            $stmt->execute([$name, $template, $is_default, $template_id]);
            
            $success_message = "Plantilla actualizada exitosamente";
            AppHelpers::redirectToDashboard('whatsapp_config', ['success' => $success_message]);
            exit;
        } catch (Exception $e) {
            $error_message = "Error al actualizar plantilla: " . $e->getMessage();
        }
    }
}

// Obtener datos para formularios y vistas
$template_data = null;
if (($action === 'edit' || $action === 'view') && $template_id) {
    try {
        $stmt = DB::pdo()->prepare("SELECT * FROM whatsapp_templates WHERE id = ?");
        $stmt->execute([$template_id]);
        $template_data = $stmt->fetch();
        
        if (!$template_data) {
            $error_message = "Plantilla no encontrada";
            $action = 'view';
        }
    } catch (Exception $e) {
        $error_message = "Error al obtener plantilla: " . $e->getMessage();
        $action = 'view';
    }
}

// Obtener plantilla predeterminada para vista principal
$default_template = null;
$all_templates = [];
try {
    $stmt = DB::pdo()->prepare("SELECT * FROM whatsapp_templates WHERE is_default = 1 LIMIT 1");
    $stmt->execute();
    $default_template = $stmt->fetch();
    
    $stmt = DB::pdo()->query("SELECT * FROM whatsapp_templates ORDER BY is_default DESC, name ASC");
    $all_templates = $stmt->fetchAll();
} catch (Exception $e) {
    $error_message = "Error al obtener plantillas: " . $e->getMessage();
}

// Variables disponibles para las plantillas
$available_variables = [
    '{club_delegado}' => 'Nombre del delegado del club invitado',
    '{club_name}' => 'Nombre del club invitado',
    '{organizer_club_name}' => 'Nombre del club organizador',
    '{organizer_delegado}' => 'Nombre del delegado del club organizador',
    '{tournament_name}' => 'Nombre del torneo',
    '{tournament_date}' => 'Fecha del torneo',
    '{login_url}' => 'URL de acceso al sistema',
    '{username}' => 'Usuario de acceso',
    '{password}' => 'Contraseña de acceso',
    '{sender_phone}' => 'Teléfono del remitente',
    '{invitation_file_url}' => 'Enlace al archivo de invitación del torneo',
    '{norms_file_url}' => 'Enlace al archivo de normas del torneo',
    '{poster_file_url}' => 'Enlace al archivo de afiche del torneo'
];
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">
            <i class="fab fa-whatsapp me-2 text-success"></i>Mensajes WhatsApp
        </h1>
        <p class="text-muted mb-0">Configura los mensajes de invitación por WhatsApp</p>
    </div>
    <div>
        <?php if ($action === 'view'): ?>
            <a href="<?= htmlspecialchars(AppHelpers::dashboard('whatsapp_config', ['action' => 'new'])) ?>" class="btn btn-success">
                <i class="fas fa-plus me-2"></i>Nueva Plantilla
            </a>
        <?php endif; ?>
    </div>
</div>
    
<!-- Alertas -->
<?php if (isset($success_message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($action === 'view'): ?>
    <!-- Vista Principal -->
    <div class="row g-4">
        <!-- Plantilla Predeterminada -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fab fa-whatsapp me-2"></i>Plantilla Predeterminada
                        </h5>
                        <?php if ($default_template): ?>
                            <a href="<?= htmlspecialchars(AppHelpers::dashboard('whatsapp_config', ['action' => 'edit', 'id' => $default_template['id']])) ?>" 
                               class="btn btn-light btn-sm">
                                <i class="fas fa-edit me-1"></i>Editar
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($default_template): ?>
                        <div class="mb-3">
                            <h5 class="text-primary"><?= htmlspecialchars($default_template['name']) ?></h5>
                            <small class="text-muted">
                                <i class="fas fa-calendar me-1"></i>
                                Creada: <?= date('d/m/Y H:i', strtotime($default_template['created_at'])) ?>
                                <?php if ($default_template['updated_at'] !== $default_template['created_at']): ?>
                                    | Actualizada: <?= date('d/m/Y H:i', strtotime($default_template['updated_at'])) ?>
                                <?php endif; ?>
                            </small>
                        </div>
                        
                        <div class="border rounded p-4 bg-light">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0 text-muted">Vista Previa del Mensaje</h6>
                                <span class="badge bg-success">Activa</span>
                            </div>
                            <div class="whatsapp-preview" style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; white-space: pre-wrap; line-height: 1.6;">
                                <?= htmlspecialchars($default_template['template']) ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="text-muted mb-4">
                                <i class="fab fa-whatsapp" style="font-size: 4rem;"></i>
                            </div>
                            <h5 class="text-muted mb-3">No hay plantilla predeterminada configurada</h5>
                            <p class="text-muted mb-4">Crea una plantilla para personalizar los mensajes de invitación por WhatsApp</p>
                            <a href="<?= htmlspecialchars(AppHelpers::dashboard('whatsapp_config', ['action' => 'new'])) ?>" class="btn btn-primary btn-lg">
                                <i class="fas fa-plus me-2"></i>Crear Primera Plantilla
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Panel de Variables -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-code me-2"></i>Variables Disponibles
                    </h5>
                </div>
                <div class="card-body">
                    <div class="variables-list" style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($available_variables as $variable => $description): ?>
                            <div class="variable-item mb-3 p-2 border rounded">
                                <code class="text-primary fw-bold" style="cursor: pointer;" 
                                      onclick="copyToClipboard('<?= $variable ?>')" 
                                      title="Click para copiar">
                                    <?= htmlspecialchars($variable) ?>
                                </code>
                                <div class="small text-muted mt-1">
                                    <?= htmlspecialchars($description) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Estadísticas -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i>Estadísticas
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <h3 class="text-primary mb-1"><?= count($all_templates) ?></h3>
                            <small class="text-muted">Plantillas</small>
                        </div>
                        <div class="col-6">
                            <h3 class="text-success mb-1"><?= $default_template ? '1' : '0' ?></h3>
                            <small class="text-muted">Predeterminada</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Lista de Plantillas -->
    <?php if (count($all_templates) > 0): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>Todas las Plantillas
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Estado</th>
                                    <th>Creada</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_templates as $template): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($template['name']) ?></strong>
                                            <?php if ($template['is_default']): ?>
                                                <span class="badge bg-primary ms-2">Predeterminada</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($template['is_default']): ?>
                                                <span class="badge bg-success">Activa</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactiva</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($template['created_at'])) ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="<?= htmlspecialchars(AppHelpers::dashboard('whatsapp_config', ['action' => 'view', 'id' => $template['id']])) ?>" 
                                                   class="btn btn-outline-info btn-sm" title="Ver">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="<?= htmlspecialchars(AppHelpers::dashboard('whatsapp_config', ['action' => 'edit', 'id' => $template['id']])) ?>" 
                                                   class="btn btn-outline-primary btn-sm" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </div>
                                        </td>
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

<?php elseif ($action === 'new' || $action === 'edit'): ?>
    <!-- Formulario -->
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-<?= $action === 'edit' ? 'edit' : 'plus' ?> me-2"></i>
                        <?= $action === 'edit' ? 'Editar' : 'Nueva' ?> Plantilla
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= htmlspecialchars(AppHelpers::dashboard('whatsapp_config', ['action' => $action === 'edit' ? 'update' : 'save'])) ?>">
                        <?php if ($action === 'edit'): ?>
                            <input type="hidden" name="template_id" value="<?= htmlspecialchars($template_data['id']) ?>">
                        <?php endif; ?>
                        
                        <div class="mb-4">
                            <label for="name" class="form-label fw-bold">Nombre de la Plantilla *</label>
                            <input type="text" class="form-control form-control-lg" id="name" name="name" 
                                   value="<?= htmlspecialchars($action === 'edit' ? $template_data['name'] : '') ?>" 
                                   placeholder="Ej: Plantilla Principal" required>
                        </div>
                        
                        <div class="mb-4">
                            <label for="template" class="form-label fw-bold">Contenido de la Plantilla *</label>
                            <textarea class="form-control" id="template" name="template" rows="15" 
                                      placeholder="Escribe tu plantilla aquí..." required><?= htmlspecialchars($action === 'edit' ? $template_data['template'] : '') ?></textarea>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Usa las variables de la lista lateral para personalizar el mensaje
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_default" name="is_default" 
                                       <?= ($action === 'edit' && $template_data['is_default']) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold" for="is_default">
                                    <i class="fas fa-star me-1"></i>Marcar como plantilla predeterminada
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <div>
                                <a href="<?= htmlspecialchars(AppHelpers::dashboard('whatsapp_config')) ?>" class="btn btn-secondary me-2">
                                    <i class="fas fa-arrow-left me-1"></i>Volver
                                </a>
                                <a href="<?= htmlspecialchars(AppHelpers::dashboard()) ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-home me-1"></i>Dashboard
                                </a>
                            </div>
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-save me-2"></i><?= $action === 'edit' ? 'Actualizar' : 'Crear' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Variables -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-code me-2"></i>Variables Disponibles
                    </h5>
                </div>
                <div class="card-body">
                    <div class="variables-list" style="max-height: 300px; overflow-y: auto;">
                        <?php foreach ($available_variables as $variable => $description): ?>
                            <div class="variable-item mb-2 p-2 border rounded">
                                <code class="text-primary fw-bold" style="cursor: pointer;" 
                                      onclick="insertVariable('<?= $variable ?>')" 
                                      title="Click para insertar">
                                    <?= htmlspecialchars($variable) ?>
                                </code>
                                <div class="small text-muted mt-1">
                                    <?= htmlspecialchars($description) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Vista Previa -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-eye me-2"></i>Vista Previa
                    </h5>
                </div>
                <div class="card-body">
                    <div id="preview" class="border rounded p-3 bg-light" 
                         style="min-height: 200px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; white-space: pre-wrap; line-height: 1.6;">
                        Escribe tu plantilla para ver la vista previa...
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
.whatsapp-preview {
    background: #f0f0f0;
    border-radius: 8px;
    padding: 16px;
    border-left: 4px solid #25D366;
}

.variable-item:hover {
    background-color: #f8f9fa;
    transition: background-color 0.2s;
}

.variable-item code:hover {
    background-color: #e3f2fd;
    padding: 2px 4px;
    border-radius: 4px;
}
</style>

<script>
function insertVariable(variable) {
    const textarea = document.getElementById('template');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    
    textarea.value = text.substring(0, start) + variable + text.substring(end);
    textarea.focus();
    textarea.setSelectionRange(start + variable.length, start + variable.length);
    
    // Actualizar vista previa
    updatePreview();
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // Mostrar notificación temporal
        const notification = document.createElement('div');
        notification.className = 'alert alert-success position-fixed';
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 200px;';
        notification.innerHTML = '<i class="fas fa-check me-2"></i>Variable copiada';
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 2000);
    });
}

function updatePreview() {
    const template = document.getElementById('template').value;
    const preview = document.getElementById('preview');
    
    if (template.trim()) {
        preview.textContent = template;
    } else {
        preview.textContent = 'Escribe tu plantilla para ver la vista previa...';
    }
}

// Actualizar vista previa en tiempo real
document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.getElementById('template');
    if (textarea) {
        textarea.addEventListener('input', updatePreview);
        updatePreview(); // Vista previa inicial
    }
});
</script>
