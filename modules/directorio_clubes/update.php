<?php
/**
 * Actualizar registro en tabla directorio_clubes (solo admin_general).
 */
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/admin_general_auth.php';

requireAdminGeneral();
CSRF::validate();

try {
    if (empty($_POST['id'])) {
        throw new Exception('ID del registro es requerido');
    }
    $id = (int)$_POST['id'];

    $stmt = DB::pdo()->prepare("SELECT id, logo FROM directorio_clubes WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new Exception('Registro no encontrado');
    }

    if (empty(trim($_POST['nombre'] ?? ''))) {
        throw new Exception('El nombre del club es requerido');
    }

    $nombre = trim($_POST['nombre']);
    $direccion = trim($_POST['direccion'] ?? '');
    $delegado = trim($_POST['delegado'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $estatus = (int)($_POST['estatus'] ?? 1);
    $logo = $row['logo'];

    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            throw new Exception('Solo se permiten imágenes JPG, PNG o GIF');
        }
        if ($_FILES['logo']['size'] > 5 * 1024 * 1024) {
            throw new Exception('El logo no puede superar 5MB');
        }
        $upload_dir = __DIR__ . '/../../upload/logos';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        if ($row['logo'] && file_exists(__DIR__ . '/../../' . $row['logo'])) {
            @unlink(__DIR__ . '/../../' . $row['logo']);
        }
        $logo_name = 'logo_dc_' . $id . '_' . time() . '.' . $ext;
        $logo_path = $upload_dir . '/' . $logo_name;
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $logo_path)) {
            $logo = 'upload/logos/' . $logo_name;
        } else {
            throw new Exception('Error al subir el logo');
        }
    }

    $stmt = DB::pdo()->prepare("
        UPDATE directorio_clubes SET
            nombre = :nombre, direccion = :direccion, delegado = :delegado,
            telefono = :telefono, email = :email, logo = :logo, estatus = :estatus
        WHERE id = :id
    ");
    $stmt->execute([
        ':id' => $id,
        ':nombre' => $nombre,
        ':direccion' => $direccion,
        ':delegado' => $delegado,
        ':telefono' => $telefono,
        ':email' => $email ?: null,
        ':logo' => $logo,
        ':estatus' => $estatus
    ]);

    header('Location: ' . \AppHelpers::dashboard('directorio_clubes') . '&success=' . urlencode('Registro actualizado en el directorio.'));
    exit;
} catch (Exception $e) {
    $id = isset($id) ? $id : (int)($_POST['id'] ?? 0);
    header('Location: ' . \AppHelpers::dashboard('directorio_clubes', ['action' => 'edit', 'id' => $id]) . '&error=' . urlencode($e->getMessage()));
    exit;
}
