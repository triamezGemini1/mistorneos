<?php
/**
 * Contenido web - Solo Admin General
 * Subir y gestionar: Documentos oficiales, Logos de clientes, Invitaciones FVD
 * Los archivos se pueden consultar y descargar desde la web pública.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/csrf.php';

Auth::requireRole(['admin_general']);

$base_dir = dirname(__DIR__);
$folders = [
    'documentos' => [
        'path' => 'upload/documentos_oficiales',
        'titulo' => 'Documentos oficiales de dominó',
        'desc' => 'PDF y documentos que se muestran en la sección "Documentos" del portal. Los visitantes pueden ver en línea y descargar.',
        'extensions' => ['pdf', 'doc', 'docx'],
    ],
    'logos_clientes' => [
        'path' => 'upload/logos_clientes',
        'titulo' => 'Logos de clientes',
        'desc' => 'Imágenes para el cintillo de clientes en el landing. Se muestran junto a los logos de clubes.',
        'extensions' => ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'],
    ],
    'invitaciones_fvd' => [
        'path' => 'upload/invitaciones_fvd',
        'titulo' => 'Invitaciones FVD',
        'desc' => 'Invitaciones y documentos de la FVD. Los visitantes pueden consultarlos y descargarlos desde el portal.',
        'extensions' => ['pdf', 'png', 'jpg', 'jpeg', 'doc', 'docx'],
    ],
];

// Crear carpetas si no existen
foreach ($folders as $key => $cfg) {
    $full = $base_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cfg['path']);
    if (!is_dir($full)) {
        @mkdir($full, 0755, true);
    }
}

$success_message = $_GET['success'] ?? null;
$error_message = $_GET['error'] ?? null;

// POST: subida o eliminación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::validate();
    $action = $_POST['action'] ?? '';
    $seccion = $_POST['seccion'] ?? '';

    if (!isset($folders[$seccion])) {
        header('Location: index.php?page=archivos_web&error=' . rawurlencode('Sección no válida'));
        exit;
    }

    $cfg = $folders[$seccion];
    $dir_full = $base_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cfg['path']);
    $dir_full = realpath($dir_full);
    $base_real = realpath($base_dir);
    if ($dir_full === false || $base_real === false || strpos($dir_full, $base_real) !== 0) {
        header('Location: index.php?page=archivos_web&error=' . rawurlencode('Ruta no permitida'));
        exit;
    }

    if ($action === 'delete') {
        $archivo = $_POST['archivo'] ?? '';
        $archivo = basename(str_replace(['../', '..\\'], '', $archivo));
        if ($archivo === '') {
            header('Location: index.php?page=archivos_web&error=' . rawurlencode('Archivo no indicado'));
            exit;
        }
        $full_path = $dir_full . DIRECTORY_SEPARATOR . $archivo;
        if (is_file($full_path) && strpos(realpath($full_path), $dir_full) === 0) {
            @unlink($full_path);
            header('Location: index.php?page=archivos_web&success=' . rawurlencode('Archivo eliminado'));
        } else {
            header('Location: index.php?page=archivos_web&error=' . rawurlencode('No se pudo eliminar'));
        }
        exit;
    }

    if ($action === 'upload' && !empty($_FILES['archivo']['name']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $name = $_FILES['archivo']['name'];
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $cfg['extensions'], true)) {
            header('Location: index.php?page=archivos_web&error=' . rawurlencode('Tipo de archivo no permitido. Use: ' . implode(', ', $cfg['extensions'])));
            exit;
        }
        $dest = $dir_full . DIRECTORY_SEPARATOR . $name;
        if (move_uploaded_file($_FILES['archivo']['tmp_name'], $dest)) {
            header('Location: index.php?page=archivos_web&success=' . rawurlencode('Archivo subido correctamente'));
        } else {
            header('Location: index.php?page=archivos_web&error=' . rawurlencode('Error al guardar el archivo'));
        }
        exit;
    }
}

// Listar archivos por carpeta
$listados = [];
foreach ($folders as $key => $cfg) {
    $dir_full = $base_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cfg['path']);
    $listados[$key] = [];
    if (is_dir($dir_full)) {
        foreach (new DirectoryIterator($dir_full) as $f) {
            if ($f->isDot() || !$f->isFile()) continue;
            $ext = strtolower($f->getExtension());
            if (in_array($ext, $cfg['extensions'], true)) {
                $listados[$key][] = ['nombre' => $f->getFilename(), 'path' => $cfg['path'] . '/' . $f->getFilename()];
            }
        }
    }
}

$current_user = Auth::user();
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h1 class="h2 mb-2 fw-bold"><i class="fas fa-folder-open me-2 text-primary"></i>Contenido web</h1>
            <p class="text-muted mb-0">Documentos oficiales, logos de clientes e invitaciones FVD para el portal público</p>
        </div>
    </div>

    <?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php foreach ($folders as $key => $cfg): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="mb-1">
                    <?php if ($key === 'documentos'): ?><i class="fas fa-file-alt me-2 text-primary"></i>
                    <?php elseif ($key === 'logos_clientes'): ?><i class="fas fa-images me-2 text-info"></i>
                    <?php else: ?><i class="fas fa-envelope-open-text me-2 text-success"></i>
                    <?php endif; ?>
                    <?= htmlspecialchars($cfg['titulo']) ?>
                </h5>
                <p class="text-muted small mb-0"><?= htmlspecialchars($cfg['desc']) ?></p>
            </div>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" class="mb-4">
                <?= CSRF::input() ?>
                <input type="hidden" name="action" value="upload">
                <input type="hidden" name="seccion" value="<?= htmlspecialchars($key) ?>">
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label">Subir archivo</label>
                        <input type="file" name="archivo" class="form-control" accept="<?= htmlspecialchars(implode(',', array_map(fn($e) => '.' . $e, $cfg['extensions']))) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-2"></i>Subir</button>
                    </div>
                </div>
            </form>

            <h6 class="text-muted mb-2">Archivos actuales (<?= count($listados[$key]) ?>)</h6>
            <?php if (empty($listados[$key])): ?>
            <p class="text-muted small">Aún no hay archivos. Suba el primero arriba.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead><tr><th>Nombre</th><th class="text-end">Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($listados[$key] as $f): ?>
                    <tr>
                        <td>
                            <?php if (in_array(strtolower(pathinfo($f['nombre'], PATHINFO_EXTENSION)), ['png','jpg','jpeg','gif','webp','svg'], true)): ?>
                            <img src="<?= htmlspecialchars((function_exists('app_base_url') ? rtrim(app_base_url(), '/') : '') . '/public/view_image.php?path=' . rawurlencode($f['path'])) ?>" alt="" style="max-height:32px;max-width:80px;object-fit:contain" class="me-2">
                            <?php endif; ?>
                            <span><?= htmlspecialchars($f['nombre']) ?></span>
                        </td>
                        <td class="text-end">
                            <?php
                            $url_ver = ($key === 'logos_clientes') ? 'view_image.php?path=' . rawurlencode($f['path']) : 'view_documento.php?path=' . rawurlencode($f['path']);
                            ?>
                            <a href="<?= htmlspecialchars($url_ver) ?>" target="_blank" class="btn btn-sm btn-outline-primary me-1" title="Ver"><i class="fas fa-external-link-alt"></i></a>
                            <?php if ($key !== 'logos_clientes'): ?>
                            <a href="<?= htmlspecialchars($url_ver . (strpos($url_ver, '?') !== false ? '&' : '?') . 'download=1') ?>" class="btn btn-sm btn-outline-secondary me-1" title="Descargar"><i class="fas fa-download"></i></a>
                            <?php endif; ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este archivo?');">
                                <?= CSRF::input() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="seccion" value="<?= htmlspecialchars($key) ?>">
                                <input type="hidden" name="archivo" value="<?= htmlspecialchars($f['nombre']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
