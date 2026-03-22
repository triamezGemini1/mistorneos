<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

// Verificar permisos
Auth::requireRole(['admin_general', 'admin_torneo']);

// Obtener acción solicitada
$action = $_GET['action'] ?? 'list';
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
            header('Location: index.php?page=home&success=' . urlencode($success_message));
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
            header('Location: index.php?page=home&success=' . urlencode($success_message));
            exit;
        } catch (Exception $e) {
            $error_message = "Error al actualizar plantilla: " . $e->getMessage();
        }
    }
    
    if ($action === 'delete') {
        try {
            $template_id = $_POST['template_id'] ?? '';
            
            if (empty($template_id)) {
                throw new Exception('ID de plantilla requerido');
            }
            
            // Verificar si es la plantilla predeterminada
            $stmt = DB::pdo()->prepare("SELECT is_default FROM whatsapp_templates WHERE id = ?");
            $stmt->execute([$template_id]);
            $template = $stmt->fetch();
            
            if ($template && $template['is_default']) {
                throw new Exception('No se puede eliminar la plantilla predeterminada');
            }
            
            $stmt = DB::pdo()->prepare("DELETE FROM whatsapp_templates WHERE id = ?");
            $stmt->execute([$template_id]);
            
            $success_message = "Plantilla eliminada exitosamente";
            header('Location: index.php?page=home&success=' . urlencode($success_message));
            exit;
        } catch (Exception $e) {
            $error_message = "Error al eliminar plantilla: " . $e->getMessage();
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
            $action = 'list';
        }
    } catch (Exception $e) {
        $error_message = "Error al obtener plantilla: " . $e->getMessage();
        $action = 'list';
    }
}

// Obtener lista para vista de lista
$templates_list = [];
if ($action === 'list') {
    try {
        $stmt = DB::pdo()->query("SELECT * FROM whatsapp_templates ORDER BY is_default DESC, name ASC");
        $templates_list = $stmt->fetchAll();
    } catch (Exception $e) {
        $error_message = "Error al obtener plantillas: " . $e->getMessage();
    }
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

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Plantillas de WhatsApp</h1>
                    <p class="text-muted mb-0">Configura los mensajes de invitación por WhatsApp</p>
                </div>
                <div>
                    <?php if ($action === 'list'): ?>
                        <a href="index.php?page=whatsapp_templates&action=new" class="btn btn-primary me-2">
                            <i class="fas fa-plus me-2"></i>Nueva Plantilla
                        </a>
                        <a href="index.php?page=home" class="btn btn-outline-primary">
                            <i class="fas fa-home me-2"></i>Dashboard
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
        </div>
    </div>
</div>

<?php if ($action === 'list'): ?>
    <!-- Vista de Lista -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Plantilla</th>
                            <th>Predeterminada</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($templates_list as $template): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($template['name']) ?></strong>
                                    <?php if ($template['is_default']): ?>
                                        <span class="badge bg-primary ms-2">Predeterminada</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-muted" style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?= htmlspecialchars(substr($template['template'], 0, 100)) ?>...
                                    </div>
                                </td>
                                <td>
                                    <?php if ($template['is_default']): ?>
                                        <i class="fas fa-check text-success"></i>
                                    <?php else: ?>
                                        <i class="fas fa-times text-muted"></i>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="index.php?page=whatsapp_templates&action=view&id=<?= $template['id'] ?>" 
                                           class="btn btn-outline-info" title="Ver">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="index.php?page=whatsapp_templates&action=edit&id=<?= $template['id'] ?>" 
                                           class="btn btn-outline-primary" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if (!$template['is_default']): ?>
                                            <a href="index.php?page=whatsapp_templates&action=delete&id=<?= $template['id'] ?>" 
                                               class="btn btn-outline-danger" title="Eliminar"
                                               onclick="return confirm('¿Está seguro de eliminar esta plantilla?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($action === 'view'): ?>
    <!-- Vista Individual -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Ver Plantilla</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nombre</label>
                        <p class="form-control-plaintext"><?= htmlspecialchars($template_data['name']) ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Predeterminada</label>
                        <p class="form-control-plaintext">
                            <?php if ($template_data['is_default']): ?>
                                <span class="badge bg-primary">Sí</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">No</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Creada</label>
                        <p class="form-control-plaintext"><?= date('d/m/Y H:i', strtotime($template_data['created_at'])) ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Actualizada</label>
                        <p class="form-control-plaintext"><?= date('d/m/Y H:i', strtotime($template_data['updated_at'])) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-bold">Plantilla</label>
                <div class="border rounded p-3 bg-light">
                    <pre style="white-space: pre-wrap; font-family: inherit;"><?= htmlspecialchars($template_data['template']) ?></pre>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-bold">Vista Previa</label>
                <div class="border rounded p-3 bg-light">
                    <div style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; white-space: pre-wrap;"><?= htmlspecialchars($template_data['template']) ?></div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <div class="d-flex justify-content-between">
                <div>
                    <a href="index.php?page=whatsapp_templates" class="btn btn-secondary me-2">
                        <i class="fas fa-arrow-left me-1"></i>Volver a la Lista
                    </a>
                    <a href="index.php?page=home" class="btn btn-outline-primary">
                        <i class="fas fa-home me-1"></i>Dashboard
                    </a>
                </div>
                <a href="index.php?page=whatsapp_templates&action=edit&id=<?= $template_data['id'] ?>" class="btn btn-primary">
                    <i class="fas fa-edit me-1"></i>Editar
                </a>
            </div>
        </div>
    </div>

<?php elseif ($action === 'new' || $action === 'edit'): ?>
    <!-- Formulario -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><?= $action === 'edit' ? 'Editar' : 'Nueva' ?> Plantilla</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="index.php?page=whatsapp_templates&action=<?= $action === 'edit' ? 'update' : 'save' ?>">
                        <?php if ($action === 'edit'): ?>
                            <input type="hidden" name="template_id" value="<?= htmlspecialchars($template_data['id']) ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Nombre de la Plantilla *</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?= htmlspecialchars($action === 'edit' ? $template_data['name'] : '') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="template" class="form-label">Plantilla *</label>
                            <textarea class="form-control" id="template" name="template" rows="15" required><?= htmlspecialchars($action === 'edit' ? $template_data['template'] : '') ?></textarea>
                            <div class="form-text">
                                <strong>Variables disponibles:</strong> Puedes usar las variables de la lista lateral para personalizar el mensaje.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_default" name="is_default" 
                                       <?= ($action === 'edit' && $template_data['is_default']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_default">
                                    Marcar como plantilla predeterminada
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <div>
                                <a href="index.php?page=whatsapp_templates" class="btn btn-secondary me-2">
                                    <i class="fas fa-arrow-left me-1"></i>Volver a Lista
                                </a>
                                <a href="index.php?page=home" class="btn btn-outline-primary">
                                    <i class="fas fa-home me-1"></i>Dashboard
                                </a>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i><?= $action === 'edit' ? 'Actualizar' : 'Crear' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">Variables Disponibles</h6>
                </div>
                <div class="card-body">
                    <?php foreach ($available_variables as $variable => $description): ?>
                        <div class="mb-2">
                            <code class="text-primary" style="cursor: pointer;" onclick="insertVariable('<?= $variable ?>')"><?= htmlspecialchars($variable) ?></code>
                            <div class="small text-muted"><?= htmlspecialchars($description) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">Vista Previa</h6>
                </div>
                <div class="card-body">
                    <div id="preview" class="border rounded p-3 bg-light" style="min-height: 200px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; white-space: pre-wrap;">
                        Escribe tu plantilla para ver la vista previa...
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

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
