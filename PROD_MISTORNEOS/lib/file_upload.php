<?php


class FileUpload {
    private static $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
    private static $max_file_size = 5 * 1024 * 1024; // 5MB
    private static $upload_path = __DIR__ . '/../upload/';

    public static function uploadLogo($file, $club_id = null) {
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            throw new Exception('No se ha seleccionado ningún archivo');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error en la subida del archivo: ' . $file['error']);
        }

        // Validar tamaño
        if ($file['size'] > self::$max_file_size) {
            throw new Exception('El archivo es demasiado grande. Máximo 5MB');
        }

        // Obtener información del archivo
        $file_info = pathinfo($file['name']);
        $extension = strtolower($file_info['extension']);

        // Validar extensión
        if (!in_array($extension, self::$allowed_extensions)) {
            throw new Exception('Tipo de archivo no permitido. Solo se permiten: ' . implode(', ', self::$allowed_extensions));
        }

        // Generar nombre único
        $filename = 'logo_' . ($club_id ? $club_id : 'new') . '_' . time() . '.' . $extension;
        $upload_dir = self::$upload_path . 'logos/';
        
        // Crear directorio si no existe
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_path = $upload_dir . $filename;

        // Mover archivo
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            // Fallback: usar copy() si move_uploaded_file() falla
            if (!copy($file['tmp_name'], $file_path)) {
                throw new Exception('Error al guardar el archivo');
            }
        }

        return 'upload/logos/' . $filename;
    }

    public static function uploadTournamentFile($file, $type, $tournament_id = null) {
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            throw new Exception('No se ha seleccionado ningún archivo');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error en la subida del archivo: ' . $file['error']);
        }

        // Validar tamaño
        if ($file['size'] > self::$max_file_size) {
            throw new Exception('El archivo es demasiado grande. Máximo 5MB');
        }

        // Obtener información del archivo
        $file_info = pathinfo($file['name']);
        $extension = strtolower($file_info['extension']);

        // Validar extensión
        if (!in_array($extension, self::$allowed_extensions)) {
            throw new Exception('Tipo de archivo no permitido. Solo se permiten: ' . implode(', ', self::$allowed_extensions));
        }

        // Determinar el subdirectorio según el tipo y validar extensiones permitidas
        $subdir = '';
        $allowed_extensions_for_type = [];
        
        switch ($type) {
            case 'invitation':
            case 'invitacion':
                $subdir = 'invitations';
                $allowed_extensions_for_type = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif'];
                break;
            case 'norms':
            case 'normas':
                $subdir = 'norms';
                $allowed_extensions_for_type = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif'];
                break;
            case 'poster':
            case 'afiche':
                $subdir = 'posters';
                $allowed_extensions_for_type = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif'];
                break;
            default:
                throw new Exception('Tipo de archivo no válido');
        }
        
        // Validar extensión específica para el tipo
        if (!in_array($extension, $allowed_extensions_for_type)) {
            $allowed_list = implode(', ', array_map('strtoupper', $allowed_extensions_for_type));
            throw new Exception("Tipo de archivo no permitido para $type. Solo se permiten: $allowed_list");
        }

        // Generar nombre único
        $filename = $type . '_' . ($tournament_id ? $tournament_id : 'new') . '_' . time() . '.' . $extension;
        $upload_dir = self::$upload_path . 'tournaments/' . $subdir . '/';
        
        // Crear directorio si no existe
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_path = $upload_dir . $filename;

        // Mover archivo
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            // Fallback: usar copy() si move_uploaded_file() falla
            if (!copy($file['tmp_name'], $file_path)) {
                throw new Exception('Error al guardar el archivo');
            }
        }

        return 'upload/tournaments/' . $subdir . '/' . $filename;
    }

    public static function deleteFile($file_path) {
        if (!$file_path) {
            return false;
        }
        
        // Si la ruta ya incluye 'upload/', usar la ruta completa
        if (strpos($file_path, 'upload/') === 0) {
            $full_path = __DIR__ . '/../' . $file_path;
        } else {
            $full_path = self::$upload_path . $file_path;
        }
        
        if (file_exists($full_path)) {
            return unlink($full_path);
        }
        
        return false;
    }

    public static function getFileUrl($file_path) {
        if (!$file_path) return null;
        return '/' . $file_path;
    }

    public static function validateFile($file) {
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return ['valid' => false, 'message' => 'No se ha seleccionado ningún archivo'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'message' => 'Error en la subida del archivo'];
        }

        if ($file['size'] > self::$max_file_size) {
            return ['valid' => false, 'message' => 'El archivo es demasiado grande. Máximo 5MB'];
        }

        $file_info = pathinfo($file['name']);
        $extension = strtolower($file_info['extension']);

        if (!in_array($extension, self::$allowed_extensions)) {
            return ['valid' => false, 'message' => 'Tipo de archivo no permitido. Solo se permiten: ' . implode(', ', self::$allowed_extensions)];
        }

        return ['valid' => true, 'message' => 'Archivo válido'];
    }

    public static function getFileExplorerData() {
        $logos_dir = self::$upload_path . 'logos/';
        $files = [];

        if (is_dir($logos_dir)) {
            $dir_files = scandir($logos_dir);
            foreach ($dir_files as $file) {
                if ($file !== '.' && $file !== '..' && !is_dir($logos_dir . $file)) {
                    $file_info = pathinfo($file);
                    $extension = strtolower($file_info['extension']);
                    
                    if (in_array($extension, self::$allowed_extensions)) {
                        $files[] = [
                            'name' => $file,
                            'path' => 'upload/logos/' . $file,
                            'size' => filesize($logos_dir . $file),
                            'type' => $extension,
                            'url' => self::getFileUrl('upload/logos/' . $file)
                        ];
                    }
                }
            }
        }

        return $files;
    }
}
