<?php


/**
 * Clase base para operaciones CRUD genéricas
 * Proporciona funcionalidad reutilizable para Create, Read, Update, Delete
 */
class CrudBase {
    protected string $table;
    protected array $required_fields;
    protected array $file_fields;
    protected array $allowed_roles;
    protected string $page_name;
    protected string $entity_name;
    protected array $display_fields;
    
    public function __construct(array $config) {
        $this->table = $config['table'];
        $this->required_fields = $config['required_fields'] ?? [];
        $this->file_fields = $config['file_fields'] ?? [];
        $this->allowed_roles = $config['allowed_roles'] ?? ['admin_general'];
        $this->page_name = $config['page_name'];
        $this->entity_name = $config['entity_name'];
        $this->display_fields = $config['display_fields'] ?? [];
    }
    
    /**
     * Procesa todas las operaciones CRUD
     */
    public function processRequest(): array {
        $result = [
            'success' => false,
            'message' => '',
            'action' => 'list',
            'data' => null
        ];
        
        try {
            // Verificar permisos
            Auth::requireRole($this->allowed_roles);
            
            $action = $_GET['action'] ?? 'list';
            $id = $_GET['id'] ?? null;
            
            // Procesar solicitudes POST
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                switch ($action) {
                    case 'save':
                        $result = $this->create();
                        break;
                    case 'update':
                        $update_id = $_POST['id'] ?? null;
                        $result = $this->update($update_id);
                        break;
                    case 'delete':
                        $result = $this->delete($id);
                        break;
                }
            } else {
                // Procesar solicitudes GET
                switch ($action) {
                    case 'delete':
                        $result = $this->delete($id);
                        break;
                    case 'view':
                    case 'edit':
                        $result = $this->read($id);
                        break;
                    case 'list':
                    default:
                        $result = $this->list();
                        break;
                }
            }
            
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
            $result['success'] = false;
        }
        
        return $result;
    }
    
    /**
     * Crear nuevo registro
     */
    public function create(): array {
        try {
            $data = $this->validateAndPrepareData($_POST);
            
            // Manejar archivos
            foreach ($this->file_fields as $field) {
                $data[$field] = $this->handleFileUpload($field);
            }
            
            $fields = array_keys($data);
            $placeholders = array_fill(0, count($fields), '?');
            
            $sql = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = DB::pdo()->prepare($sql);
            $stmt->execute(array_values($data));
            
            return [
                'success' => true,
                'message' => "{$this->entity_name} creado exitosamente",
                'action' => 'list',
                'data' => null
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Error al crear {$this->entity_name}: " . $e->getMessage(),
                'action' => 'new',
                'data' => $_POST
            ];
        }
    }
    
    /**
     * Actualizar registro existente
     */
    public function update($id): array {
        try {
            if (!$id) {
                throw new Exception("ID requerido para actualización");
            }
            
            $data = $this->validateAndPrepareData($_POST);
            
            // Obtener archivos actuales
            $current_files = $this->getCurrentFiles($id);
            
            // Manejar archivos
            foreach ($this->file_fields as $field) {
                if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                    // Eliminar archivo anterior si existe
                    if (!empty($current_files[$field])) {
                        FileUpload::deleteFile($current_files[$field]);
                    }
                    $data[$field] = $this->handleFileUpload($field);
                } else {
                    // Mantener archivo actual
                    $data[$field] = $current_files[$field] ?? '';
                }
            }
            
            $fields = array_keys($data);
            $set_clause = implode(' = ?, ', $fields) . ' = ?';
            
            $sql = "UPDATE {$this->table} SET {$set_clause} WHERE id = ?";
            $stmt = DB::pdo()->prepare($sql);
            $params = array_values($data);
            $params[] = $id;
            $stmt->execute($params);
            
            // Nota: rowCount() puede ser 0 si no hay cambios, lo cual es válido
            // No lanzamos excepción si rowCount es 0
            
            return [
                'success' => true,
                'message' => "{$this->entity_name} actualizado exitosamente",
                'action' => 'list',
                'data' => null
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Error al actualizar {$this->entity_name}: " . $e->getMessage(),
                'action' => 'edit',
                'data' => $_POST
            ];
        }
    }
    
    /**
     * Eliminar registro
     */
    public function delete($id): array {
        try {
            if (!$id) {
                throw new Exception("ID requerido para eliminación");
            }
            
            // Obtener archivos asociados
            $files = $this->getCurrentFiles($id);
            
            // Eliminar archivos físicos
            foreach ($this->file_fields as $field) {
                if (!empty($files[$field])) {
                    FileUpload::deleteFile($files[$field]);
                }
            }
            
            // Eliminar registro de la base de datos
            $stmt = DB::pdo()->prepare("DELETE FROM {$this->table} WHERE id = ?");
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("No se encontró el registro para eliminar");
            }
            
            return [
                'success' => true,
                'message' => "{$this->entity_name} eliminado exitosamente",
                'action' => 'list',
                'data' => null
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Error al eliminar {$this->entity_name}: " . $e->getMessage(),
                'action' => 'list',
                'data' => null
            ];
        }
    }
    
    /**
     * Leer registro específico
     */
    public function read($id): array {
        try {
            if (!$id) {
                throw new Exception("ID requerido");
            }
            
            $stmt = DB::pdo()->prepare("SELECT * FROM {$this->table} WHERE id = ?");
            $stmt->execute([$id]);
            $data = $stmt->fetch();
            
            if (!$data) {
                throw new Exception("Registro no encontrado");
            }
            
            return [
                'success' => true,
                'message' => '',
                'action' => 'view',
                'data' => $data
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'action' => 'list',
                'data' => null
            ];
        }
    }
    
    /**
     * Listar todos los registros
     */
    public function list(): array {
        try {
            $stmt = DB::pdo()->query("SELECT * FROM {$this->table} ORDER BY id DESC");
            $data = $stmt->fetchAll();
            
            return [
                'success' => true,
                'message' => '',
                'action' => 'list',
                'data' => $data
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'action' => 'list',
                'data' => []
            ];
        }
    }
    
    /**
     * Validar y preparar datos del formulario
     */
    protected function validateAndPrepareData(array $post_data): array {
        $data = [];
        
        // Validar campos requeridos
        foreach ($this->required_fields as $field) {
            if (empty($post_data[$field])) {
                throw new Exception("El campo {$field} es requerido");
            }
            $data[$field] = trim($post_data[$field]);
        }
        
        // Procesar otros campos del POST
        foreach ($post_data as $key => $value) {
            if (!in_array($key, $this->file_fields)) {
                $data[$key] = is_string($value) ? trim($value) : $value;
            }
        }
        
        return $data;
    }
    
    /**
     * Manejar subida de archivos
     */
    protected function handleFileUpload(string $field): ?string {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        
        $validation = FileUpload::validateFile($_FILES[$field]);
        if (!$validation['valid']) {
            throw new Exception($validation['message']);
        }
        
        // Usar método específico según el tipo de archivo
        if ($field === 'logo') {
            return FileUpload::uploadLogo($_FILES[$field]);
        } elseif (in_array($field, ['invitacion', 'normas', 'afiche'])) {
            return FileUpload::uploadTournamentFile($_FILES[$field], $field);
        }
        
        // Método genérico para otros archivos (usar uploadTournamentFile como fallback)
        return FileUpload::uploadTournamentFile($_FILES[$field], $field);
    }
    
    /**
     * Obtener archivos actuales del registro
     */
    protected function getCurrentFiles($id): array {
        $files = [];
        
        if (empty($this->file_fields)) {
            return $files;
        }
        
        $fields = implode(', ', $this->file_fields);
        $stmt = DB::pdo()->prepare("SELECT {$fields} FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        if ($result) {
            foreach ($this->file_fields as $field) {
                $files[$field] = $result[$field] ?? null;
            }
        }
        
        return $files;
    }
    
    /**
     * Generar URL para redirección
     */
    public function getRedirectUrl(string $action, array $params = []): string {
        $url = "index.php?page={$this->page_name}";
        
        if ($action !== 'list') {
            $url .= "&action={$action}";
        }
        
        foreach ($params as $key => $value) {
            $url .= "&{$key}=" . urlencode($value);
        }
        
        return $url;
    }
    
    /**
     * Generar mensaje de éxito/error
     */
    public function getMessage(bool $success, string $message): string {
        return $message;
    }
}
?>
