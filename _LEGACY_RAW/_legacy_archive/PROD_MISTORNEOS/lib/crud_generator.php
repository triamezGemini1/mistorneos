<?php


/**
 * Generador de módulos CRUD
 * Crea módulos completos basados en configuración
 */
class CrudGenerator {
    
    /**
     * Generar módulo CRUD completo
     */
    public static function generateModule(array $config): string {
        $table = $config['table'];
        $entity_name = $config['entity_name'];
        $page_name = $config['page_name'];
        $allowed_roles = $config['allowed_roles'] ?? ['admin_general'];
        $fields = $config['fields'] ?? [];
        $file_fields = $config['file_fields'] ?? [];
        $display_fields = $config['display_fields'] ?? [];
        
        $php_code = self::generatePhpCode($config);
        $html_code = self::generateHtmlCode($config);
        
        return $php_code . $html_code;
    }
    
    /**
     * Generar código PHP del módulo
     */
    private static function generatePhpCode(array $config): string {
        $table = $config['table'];
        $entity_name = $config['entity_name'];
        $page_name = $config['page_name'];
        $allowed_roles = $config['allowed_roles'] ?? ['admin_general'];
        $file_fields = $config['file_fields'] ?? [];
        
        $required_fields = array_filter($config['fields'] ?? [], function($field) {
            return $field['required'] ?? false;
        });
        $required_field_names = array_column($required_fields, 'name');
        
        return "<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../lib/file_upload.php';
require_once __DIR__ . '/../lib/image_helper.php';
require_once __DIR__ . '/../lib/crud_base.php';

// Verificar permisos
Auth::requireRole(" . var_export($allowed_roles, true) . ");

// Configuración del módulo
\$crud_config = [
    'table' => '{$table}',
    'required_fields' => " . var_export($required_field_names, true) . ",
    'file_fields' => " . var_export($file_fields, true) . ",
    'allowed_roles' => " . var_export($allowed_roles, true) . ",
    'page_name' => '{$page_name}',
    'entity_name' => '{$entity_name}',
    'display_fields' => " . var_export($config['display_fields'] ?? [], true) . "
];

// Procesar solicitud CRUD
\$crud = new CrudBase(\$crud_config);
\$result = \$crud->processRequest();

// Obtener datos para la vista
\$action = \$_GET['action'] ?? 'list';
\$id = \$_GET['id'] ?? null;
\$success_message = \$_GET['success'] ?? null;
\$error_message = \$_GET['error'] ?? null;

// Si hay resultado de operación, redirigir
if (\$result['success'] && in_array(\$action, ['save', 'update', 'delete'])) {
    header('Location: ' . \$crud->getRedirectUrl(\$result['action'], ['success' => \$result['message']]));
    exit;
} elseif (!\$result['success'] && in_array(\$action, ['save', 'update', 'delete'])) {
    header('Location: ' . \$crud->getRedirectUrl(\$result['action'], ['error' => \$result['message']]));
    exit;
}

// Obtener datos para formularios y vistas
\${$page_name}_data = null;
if ((\$action === 'edit' || \$action === 'view') && \$id) {
    \$read_result = \$crud->read(\$id);
    if (\$read_result['success']) {
        \${$page_name}_data = \$read_result['data'];
    } else {
        \$error_message = \$read_result['message'];
        \$action = 'list';
    }
}

// Obtener lista para vista de lista
\${$page_name}_list = [];
if (\$action === 'list') {
    \$list_result = \$crud->list();
    if (\$list_result['success']) {
        \${$page_name}_list = \$list_result['data'];
    }
}
?>";
    }
    
    /**
     * Generar código HTML del módulo
     */
    private static function generateHtmlCode(array $config): string {
        $entity_name = $config['entity_name'];
        $page_name = $config['page_name'];
        $fields = $config['fields'] ?? [];
        $display_fields = $config['display_fields'] ?? [];
        
        $html = "
<div class=\"container-fluid\">
    <div class=\"row\">
        <div class=\"col-12\">
            <div class=\"d-flex justify-content-between align-items-center mb-4\">
                <div>
                    <h1 class=\"h3 mb-0\">" . ucfirst($entity_name) . "s</h1>
                    <p class=\"text-muted mb-0\">Administra los " . strtolower($entity_name) . "s del sistema</p>
                </div>
                <div>
                    <?php if (\$action === 'list'): ?>
                        <a href=\"index.php?page={$page_name}&action=new\" class=\"btn btn-primary\">
                            <i class=\"fas fa-plus me-2\"></i>Nuevo " . ucfirst($entity_name) . "
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Alertas -->
            <?php if (isset(\$success_message)): ?>
                <div class=\"alert alert-success alert-dismissible fade show\" role=\"alert\">
                    <i class=\"fas fa-check-circle me-2\"></i><?= htmlspecialchars(\$success_message) ?>
                    <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset(\$error_message)): ?>
                <div class=\"alert alert-danger alert-dismissible fade show\" role=\"alert\">
                    <i class=\"fas fa-exclamation-triangle me-2\"></i><?= htmlspecialchars(\$error_message) ?>
                    <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>";

        // Vista de lista
        $html .= "
    <?php if (\$action === 'list'): ?>
        <div class=\"card\">
            <div class=\"card-body\">
                <div class=\"table-responsive\">
                    <table class=\"table table-hover\">
                        <thead>
                            <tr>";
        
        foreach ($display_fields as $field) {
            $html .= "<th>" . ucfirst($field) . "</th>";
        }
        $html .= "<th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>";
        
        $html .= "<?php foreach (\${$page_name}_list as \$item): ?>
                            <tr>";
        
        foreach ($display_fields as $field) {
            $html .= "<td><?= htmlspecialchars(\$item['{$field}'] ?? '') ?></td>";
        }
        
        $html .= "<td>
                                <div class=\"btn-group\" role=\"group\">
                                    <a href=\"index.php?page={$page_name}&action=view&id=<?= \$item['id'] ?>\" 
                                       class=\"btn btn-outline-info\" title=\"Ver\">
                                        <i class=\"fas fa-eye\"></i>
                                    </a>
                                    <a href=\"index.php?page={$page_name}&action=edit&id=<?= \$item['id'] ?>\" 
                                       class=\"btn btn-outline-primary\" title=\"Editar\">
                                        <i class=\"fas fa-edit\"></i>
                                    </a>
                                    <a href=\"index.php?page={$page_name}&action=delete&id=<?= \$item['id'] ?>\" 
                                       class=\"btn btn-outline-danger\" title=\"Eliminar\"
                                       onclick=\"return confirm('¿Está seguro de eliminar este {$entity_name}?')\">
                                        <i class=\"fas fa-trash\"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>";
        
        // Vista individual
        $html .= "
    <?php elseif (\$action === 'view'): ?>
        <div class=\"card\">
            <div class=\"card-header\">
                <h5 class=\"card-title mb-0\">Ver " . ucfirst($entity_name) . "</h5>
            </div>
            <div class=\"card-body\">";
        
        foreach ($fields as $field) {
            $field_name = $field['name'];
            $field_label = $field['label'] ?? ucfirst($field_name);
            $field_type = $field['type'] ?? 'text';
            
            if ($field_type === 'file') {
                $html .= "
                <div class=\"mb-3\">
                    <label class=\"form-label\">{$field_label}</label>
                    <?php if (!empty(\${$page_name}_data['{$field_name}'])): ?>
                        <div>
                            <img src=\"<?= getSimpleImageUrl(\${$page_name}_data['{$field_name}']) ?>\" 
                                 class=\"img-thumbnail\" style=\"max-width: 200px; max-height: 200px;\">
                        </div>
                    <?php else: ?>
                        <p class=\"text-muted\">Sin archivo</p>
                    <?php endif; ?>
                </div>";
            } else {
                $html .= "
                <div class=\"mb-3\">
                    <label class=\"form-label\">{$field_label}</label>
                    <p class=\"form-control-plaintext\"><?= htmlspecialchars(\${$page_name}_data['{$field_name}'] ?? '') ?></p>
                </div>";
            }
        }
        
        $html .= "
            </div>
            <div class=\"card-footer\">
                <a href=\"index.php?page={$page_name}\" class=\"btn btn-secondary\">Volver a la Lista</a>
                <a href=\"index.php?page={$page_name}&action=edit&id=<?= \${$page_name}_data['id'] ?>\" class=\"btn btn-primary\">Editar</a>
            </div>
        </div>";
        
        // Formulario
        $html .= "
    <?php elseif (\$action === 'new' || \$action === 'edit'): ?>
        <div class=\"card\">
            <div class=\"card-header\">
                <h5 class=\"card-title mb-0\">" . ($action === 'edit' ? 'Editar' : 'Nuevo') . " " . ucfirst($entity_name) . "</h5>
            </div>
            <div class=\"card-body\">
                <form method=\"POST\" action=\"index.php?page={$page_name}&action=\" . (\$action === 'edit' ? 'update' : 'save') . \"\" enctype=\"multipart/form-data\">
                    <?php if (\$action === 'edit'): ?>
                        <input type=\"hidden\" name=\"id\" value=\"<?= htmlspecialchars(\${$page_name}_data['id']) ?>\">
                    <?php endif; ?>";
        
        foreach ($fields as $field) {
            $field_name = $field['name'];
            $field_label = $field['label'] ?? ucfirst($field_name);
            $field_type = $field['type'] ?? 'text';
            $field_required = $field['required'] ?? false;
            $field_options = $field['options'] ?? [];
            
            $html .= "
                    <div class=\"mb-3\">
                        <label for=\"{$field_name}\" class=\"form-label\">{$field_label}" . ($field_required ? ' *' : '') . "</label>";
            
            if ($field_type === 'file') {
                $html .= "
                        <input type=\"file\" class=\"form-control\" id=\"{$field_name}\" name=\"{$field_name}\" accept=\"image/*\">";
                
                if ($action === 'edit') {
                    $html .= "
                        <?php if (!empty(\${$page_name}_data['{$field_name}'])): ?>
                            <div class=\"mt-2\">
                                <small class=\"text-muted\">Archivo actual:</small><br>
                                <img src=\"<?= getSimpleImageUrl(\${$page_name}_data['{$field_name}']) ?>\" 
                                     class=\"img-thumbnail\" style=\"max-width: 100px; max-height: 100px;\">
                            </div>
                        <?php endif; ?>";
                }
            } elseif ($field_type === 'select') {
                $html .= "
                        <select class=\"form-select\" id=\"{$field_name}\" name=\"{$field_name}\"" . ($field_required ? ' required' : '') . ">
                            <option value=\"\">Seleccionar...</option>";
                
                foreach ($field_options as $value => $label) {
                    $html .= "
                            <option value=\"{$value}\"<?= (\$action === 'edit' && (\${$page_name}_data['{$field_name}'] ?? '') === '{$value}') ? ' selected' : '' ?>>{$label}</option>";
                }
                
                $html .= "
                        </select>";
            } else {
                $html .= "
                        <input type=\"{$field_type}\" class=\"form-control\" id=\"{$field_name}\" name=\"{$field_name}\" 
                               value=\"<?= htmlspecialchars(\$action === 'edit' ? (\${$page_name}_data['{$field_name}'] ?? '') : '') ?>\""
                               . ($field_required ? ' required' : '') . ">";
            }
            
            $html .= "
                    </div>";
        }
        
        $html .= "
                    <div class=\"d-flex justify-content-between\">
                        <a href=\"index.php?page={$page_name}\" class=\"btn btn-secondary\">Cancelar</a>
                        <button type=\"submit\" class=\"btn btn-primary\">
                            <i class=\"fas fa-save me-2\"></i>" . ($action === 'edit' ? 'Actualizar' : 'Crear') . "
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>";
        
        return $html;
    }
    
    /**
     * Crear archivo de módulo
     */
    public static function createModuleFile(string $file_path, array $config): bool {
        $content = self::generateModule($config);
        return file_put_contents($file_path, $content) !== false;
    }
}
?>
















