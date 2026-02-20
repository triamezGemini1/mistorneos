<?php
/**
 * API para subir fotos de torneos
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../lib/file_upload.php';

header('Content-Type: application/json');

// Verificar permisos
Auth::requireRole(['admin_general', 'admin_torneo', 'admin_club']);

$user = Auth::user();
$pdo = DB::pdo();

try {
    // Verificar que se envió un archivo
    if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No se ha seleccionado ningún archivo o hubo un error en la subida');
    }
    
    // Obtener ID del torneo
    $torneo_id = isset($_POST['torneo_id']) ? (int)$_POST['torneo_id'] : 0;
    if ($torneo_id <= 0) {
        throw new Exception('ID de torneo inválido');
    }
    
    // Verificar acceso al torneo
    if (!Auth::canAccessTournament($torneo_id)) {
        throw new Exception('No tiene permisos para acceder a este torneo');
    }
    
    // Verificar límite de fotos (20 fotos máximo por torneo)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM club_photos WHERE torneo_id = ?");
    $stmt->execute([$torneo_id]);
    $totalFotos = (int)$stmt->fetchColumn();
    
    if ($totalFotos >= 20) {
        throw new Exception('Se ha alcanzado el límite máximo de 20 fotos por torneo');
    }
    
    // Validar archivo
    $file = $_FILES['foto'];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 10 * 1024 * 1024; // 10MB
    
    if ($file['size'] > $max_size) {
        throw new Exception('El archivo es demasiado grande. Máximo 10MB');
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception('Tipo de archivo no permitido. Solo se permiten imágenes JPG, PNG, GIF, WEBP');
    }
    
    // Crear directorio si no existe
    $upload_dir = __DIR__ . '/../../upload/tournaments/photos/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generar nombre único
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'photo_' . $torneo_id . '_' . time() . '_' . uniqid() . '.' . $extension;
    $file_path = $upload_dir . $filename;
    
    // Mover archivo
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        throw new Exception('Error al guardar el archivo');
    }
    
    // Guardar en base de datos
    $ruta_imagen = 'upload/tournaments/photos/' . $filename;
    $orden = $totalFotos + 1;
    $titulo = pathinfo($file['name'], PATHINFO_FILENAME);
    $descripcion = null; // Se puede agregar después si es necesario
    $activa = 1; // Activa por defecto
    
    $stmt = $pdo->prepare("
        INSERT INTO club_photos (torneo_id, club_id, ruta_imagen, titulo, descripcion, nombre_archivo, orden, subido_por, activa)
        VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $torneo_id,
        $ruta_imagen,
        $titulo,
        $descripcion,
        $file['name'],
        $orden,
        Auth::id(),
        $activa
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Foto subida correctamente',
        'foto_id' => $pdo->lastInsertId()
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}



