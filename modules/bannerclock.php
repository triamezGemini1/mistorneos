<?php
/**
 * CRUD Banner Clock
 * Tabla: bannerclock (id, nivel, selector, contenido, estatus)
 * Acceso: admin_general y admin_club (organizacion)
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';

Auth::requireRole(['admin_general', 'admin_club']);

$pdo = DB::pdo();
$user = Auth::user();
$is_admin_general = Auth::isAdminGeneral();
$user_id = (int)($user['id'] ?? 0);

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$torneo_id_prefill = isset($_GET['torneo_id']) ? (int)$_GET['torneo_id'] : 0;
$mensaje_error = '';
$mensaje_ok = $_GET['success'] ?? '';

// Crear tabla si no existe (hardening para entornos nuevos)
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bannerclock (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nivel INT NOT NULL,
            selector INT NOT NULL DEFAULT 0,
            contenido TEXT NOT NULL,
            estatus TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_bannerclock_nivel (nivel),
            INDEX idx_bannerclock_selector (selector),
            INDEX idx_bannerclock_estatus (estatus)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) {
    $mensaje_error = 'No se pudo validar la tabla bannerclock: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::validate();
    $post_action = $_POST['post_action'] ?? '';

    try {
        if ($post_action === 'save') {
            $registro_id = (int)($_POST['id'] ?? 0);
            $selector = max(0, (int)($_POST['selector'] ?? 0));
            $contenido = trim((string)($_POST['contenido'] ?? ''));
            $estatus = isset($_POST['estatus']) ? 1 : 0;
            $nivel = $is_admin_general ? (int)($_POST['nivel'] ?? 1) : $user_id;
            if ($nivel <= 0) {
                $nivel = 1;
            }
            if (!$is_admin_general) {
                // Regla operativa: organizadores publican solo en selector 0.
                $selector = 0;
            }
            // Registro maestro solo puede crearlo/editarlo admin general.
            if ($nivel === 0 && !$is_admin_general) {
                throw new Exception('Solo el administrador general puede gestionar el registro maestro.');
            }

            if ($contenido === '') {
                throw new Exception('El contenido del banner es requerido.');
            }

            if ($registro_id > 0) {
                if ($is_admin_general) {
                    $stmt = $pdo->prepare("UPDATE bannerclock SET nivel = ?, selector = ?, contenido = ?, estatus = ? WHERE id = ?");
                    $stmt->execute([$nivel, $selector, $contenido, $estatus, $registro_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE bannerclock SET selector = ?, contenido = ?, estatus = ? WHERE id = ? AND nivel = ?");
                    $stmt->execute([$selector, $contenido, $estatus, $registro_id, $user_id]);
                    if ($stmt->rowCount() === 0) {
                        throw new Exception('No tiene permisos para editar este registro.');
                    }
                }
                header('Location: index.php?page=bannerclock&success=' . urlencode('Banner actualizado correctamente'));
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO bannerclock (nivel, selector, contenido, estatus) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nivel, $selector, $contenido, $estatus]);
            header('Location: index.php?page=bannerclock&success=' . urlencode('Banner creado correctamente'));
            exit;
        }

        if ($post_action === 'delete') {
            $registro_id = (int)($_POST['id'] ?? 0);
            if ($registro_id <= 0) {
                throw new Exception('Registro inválido.');
            }

            if ($is_admin_general) {
                $stmt = $pdo->prepare("DELETE FROM bannerclock WHERE id = ?");
                $stmt->execute([$registro_id]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM bannerclock WHERE id = ? AND nivel = ?");
                $stmt->execute([$registro_id, $user_id]);
                if ($stmt->rowCount() === 0) {
                    throw new Exception('No tiene permisos para eliminar este registro.');
                }
            }

            header('Location: index.php?page=bannerclock&success=' . urlencode('Banner eliminado correctamente'));
            exit;
        }

        if ($post_action === 'toggle_estatus') {
            $registro_id = (int)($_POST['id'] ?? 0);
            if ($registro_id <= 0) {
                throw new Exception('Registro inválido.');
            }

            if ($is_admin_general) {
                $stmtGet = $pdo->prepare("SELECT estatus FROM bannerclock WHERE id = ?");
                $stmtGet->execute([$registro_id]);
            } else {
                $stmtGet = $pdo->prepare("SELECT estatus FROM bannerclock WHERE id = ? AND nivel = ?");
                $stmtGet->execute([$registro_id, $user_id]);
            }
            $estatus_actual = $stmtGet->fetchColumn();
            if ($estatus_actual === false) {
                throw new Exception('No tiene permisos para cambiar estatus de este registro.');
            }

            $nuevo_estatus = ((int)$estatus_actual === 1) ? 0 : 1;
            if ($is_admin_general) {
                $stmtUpd = $pdo->prepare("UPDATE bannerclock SET estatus = ? WHERE id = ?");
                $stmtUpd->execute([$nuevo_estatus, $registro_id]);
            } else {
                $stmtUpd = $pdo->prepare("UPDATE bannerclock SET estatus = ? WHERE id = ? AND nivel = ?");
                $stmtUpd->execute([$nuevo_estatus, $registro_id, $user_id]);
            }

            header('Location: index.php?page=bannerclock&success=' . urlencode('Estatus de banner actualizado.'));
            exit;
        }

        if ($post_action === 'limpieza_auto') {
            if (!$is_admin_general) {
                throw new Exception('Solo el administrador general puede ejecutar la limpieza automática.');
            }

            // Mantener solo el último maestro publicado (nivel=0, selector=0).
            $stmtLastMaster = $pdo->query("
                SELECT id
                FROM bannerclock
                WHERE nivel = 0 AND selector = 0
                ORDER BY id DESC
                LIMIT 1
            ");
            $last_master_id = (int)$stmtLastMaster->fetchColumn();
            if ($last_master_id > 0) {
                $stmtDisableMaster = $pdo->prepare("
                    UPDATE bannerclock
                    SET estatus = 0
                    WHERE nivel = 0 AND selector = 0 AND id <> ?
                ");
                $stmtDisableMaster->execute([$last_master_id]);
            }

            // Mantener solo el último por organizador (nivel>0, selector=0).
            $stmtNiveles = $pdo->query("
                SELECT DISTINCT nivel
                FROM bannerclock
                WHERE nivel > 0 AND selector = 0
            ");
            $niveles = $stmtNiveles->fetchAll(PDO::FETCH_COLUMN);
            $stmtLastByNivel = $pdo->prepare("
                SELECT id
                FROM bannerclock
                WHERE nivel = ? AND selector = 0
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmtDisableByNivel = $pdo->prepare("
                UPDATE bannerclock
                SET estatus = 0
                WHERE nivel = ? AND selector = 0 AND id <> ?
            ");
            foreach ($niveles as $nivelRaw) {
                $nivel = (int)$nivelRaw;
                if ($nivel <= 0) {
                    continue;
                }
                $stmtLastByNivel->execute([$nivel]);
                $last_id = (int)$stmtLastByNivel->fetchColumn();
                if ($last_id > 0) {
                    $stmtDisableByNivel->execute([$nivel, $last_id]);
                }
            }

            header('Location: index.php?page=bannerclock&success=' . urlencode('Limpieza automática aplicada: se conserva solo el último maestro y el último banner por organizador.'));
            exit;
        }
    } catch (Exception $e) {
        $mensaje_error = $e->getMessage();
    }
}

$registro_editar = null;
if ($action === 'edit' && $id > 0) {
    if ($is_admin_general) {
        $stmt = $pdo->prepare("SELECT * FROM bannerclock WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM bannerclock WHERE id = ? AND nivel = ?");
        $stmt->execute([$id, $user_id]);
    }
    $registro_editar = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$registro_editar) {
        $mensaje_error = 'Registro no encontrado o sin permisos.';
        $action = 'list';
    }
}

try {
    if ($is_admin_general) {
        $stmt = $pdo->query("
            SELECT b.*, u.username AS creador_username, u.nombre AS creador_nombre
            FROM bannerclock b
            LEFT JOIN usuarios u ON u.id = b.nivel
            ORDER BY b.estatus DESC, b.id DESC
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT b.*, u.username AS creador_username, u.nombre AS creador_nombre
            FROM bannerclock b
            LEFT JOIN usuarios u ON u.id = b.nivel
            WHERE b.nivel = ?
            ORDER BY b.estatus DESC, b.id DESC
        ");
        $stmt->execute([$user_id]);
    }
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $registros = [];
    if ($mensaje_error === '') {
        $mensaje_error = 'Error al consultar registros: ' . $e->getMessage();
    }
}

$csrf_token = CSRF::token();
$nivel_default = $is_admin_general ? 1 : $user_id;
$selector_default = ($registro_editar['selector'] ?? null) !== null
    ? (int)$registro_editar['selector']
    : max(0, $torneo_id_prefill);
?>

<div class="container py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1"><i class="fas fa-bullhorn me-2"></i>Banner del reloj</h1>
            <p class="text-muted mb-0">Gestiona mensajes para el cronometro independiente.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($is_admin_general): ?>
            <form method="POST" onsubmit="return confirm('Se desactivarán registros antiguos y solo quedará activo el último maestro y el último por organizador. ¿Continuar?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="post_action" value="limpieza_auto">
                <button type="submit" class="btn btn-outline-warning">
                    <i class="fas fa-broom me-1"></i>Limpieza automática
                </button>
            </form>
            <?php endif; ?>
            <a href="index.php?page=bannerclock&action=new<?= $torneo_id_prefill > 0 ? '&torneo_id=' . $torneo_id_prefill : '' ?>" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>Nuevo banner
            </a>
        </div>
    </div>

    <?php if ($mensaje_ok): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje_ok) ?></div>
    <?php endif; ?>
    <?php if ($mensaje_error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($mensaje_error) ?></div>
    <?php endif; ?>

    <?php if ($action === 'new' || $action === 'edit'): ?>
        <div class="card mb-4">
            <div class="card-header">
                <strong><?= $action === 'edit' ? 'Editar banner' : 'Crear banner' ?></strong>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="post_action" value="save">
                    <input type="hidden" name="id" value="<?= (int)($registro_editar['id'] ?? 0) ?>">

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Nivel</label>
                            <?php if ($is_admin_general): ?>
                                <input type="number" name="nivel" class="form-control" min="0" value="<?= (int)($registro_editar['nivel'] ?? $nivel_default) ?>" required>
                                <small class="text-muted">Use 0 para registro maestro global (siempre visible).</small>
                            <?php else: ?>
                                <input type="number" class="form-control" value="<?= (int)$nivel_default ?>" readonly>
                                <input type="hidden" name="nivel" value="<?= (int)$nivel_default ?>">
                                <small class="text-muted">Tu nivel queda asociado a tu usuario organizador.</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Selector</label>
                            <?php if ($is_admin_general): ?>
                                <input type="number" name="selector" class="form-control" min="0" value="<?= (int)$selector_default ?>" required>
                                <small class="text-muted">Para esta lógica se recomienda 0 (global).</small>
                            <?php else: ?>
                                <input type="number" class="form-control" value="0" readonly>
                                <input type="hidden" name="selector" value="0">
                                <small class="text-muted">En organizador siempre se usa selector 0.</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="estatus" id="estatusBanner" <?= (int)($registro_editar['estatus'] ?? 1) === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="estatusBanner">Publicado</label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Contenido</label>
                        <textarea name="contenido" class="form-control" rows="4" required><?= htmlspecialchars((string)($registro_editar['contenido'] ?? '')) ?></textarea>
                    </div>

                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i>Guardar
                        </button>
                        <a href="index.php?page=bannerclock<?= $torneo_id_prefill > 0 ? '&torneo_id=' . $torneo_id_prefill : '' ?>" class="btn btn-secondary">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><strong>Registros</strong></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nivel</th>
                            <th>Selector</th>
                            <th>Contenido</th>
                            <th>Estatus</th>
                            <th>Creador</th>
                            <th style="width: 240px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($registros)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Sin registros</td></tr>
                        <?php else: ?>
                            <?php foreach ($registros as $r): ?>
                                <tr>
                                    <td><?= (int)$r['id'] ?></td>
                                    <td><?= (int)$r['nivel'] ?></td>
                                    <td><?= (int)$r['selector'] ?></td>
                                    <td><?= htmlspecialchars(mb_strimwidth((string)$r['contenido'], 0, 120, '...')) ?></td>
                                    <td>
                                        <?php if ((int)$r['estatus'] === 1): ?>
                                            <span class="badge bg-success">Publicado</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Oculto</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars((string)($r['creador_nombre'] ?: $r['creador_username'] ?: 'N/A')) ?></td>
                                    <td>
                                        <a href="index.php?page=bannerclock&action=edit&id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-primary">Editar</a>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <input type="hidden" name="post_action" value="toggle_estatus">
                                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                            <?php if ((int)$r['estatus'] === 1): ?>
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">Desactivar</button>
                                            <?php else: ?>
                                                <button type="submit" class="btn btn-sm btn-outline-success">Activar</button>
                                            <?php endif; ?>
                                        </form>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este registro?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <input type="hidden" name="post_action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
