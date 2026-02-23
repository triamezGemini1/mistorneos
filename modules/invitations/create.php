<?php
/**
 * Crear Nueva Invitaci�n
 */



error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/csrf.php';

Auth::requireRole(['admin_general','admin_torneo']);

$title = "Nueva Invitaci�n";
$errors = [];

try {
    $pdo = DB::pdo();
    
    // Obtener torneos disponibles
    $stmt = $pdo->query("SELECT id, nombre, fechator FROM tournaments WHERE estatus=1 ORDER BY fechator DESC");
    $torneos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener clubes
    $stmt = $pdo->query("SELECT id, nombre, delegado, telefono FROM clubes WHERE estatus=1 ORDER BY nombre ASC");
    $clubes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Procesar formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        CSRF::validate();
        
        $torneo_id = (int)$_POST['torneo_id'];
        $club_id = (int)$_POST['club_id'];
        $acceso1 = $_POST['acceso1'];
        $acceso2 = $_POST['acceso2'];
        $usuario = trim($_POST['usuario'] ?? '');
        $estado = $_POST['estado'] ?? 'activa';
        
        // Validaciones
        if (!$torneo_id) {
            $errors[] = "Debe seleccionar un torneo";
        }
        
        if (!$club_id) {
            $errors[] = "Debe seleccionar un club";
        }
        
        if (empty($acceso1) || empty($acceso2)) {
            $errors[] = "Las fechas de acceso son requeridas";
        } elseif ($acceso1 > $acceso2) {
            $errors[] = "La fecha de inicio no puede ser mayor que la fecha fin";
        }
        
        // Verificar duplicado
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM " . TABLE_INVITATIONS . " WHERE torneo_id = ? AND club_id = ?");
            $stmt->execute([$torneo_id, $club_id]);
            if ($stmt->fetch()) {
                $errors[] = "Ya existe una invitaci�n para este torneo y club";
            }
        }
        
        // Insertar
        if (empty($errors)) {
            // Generar token �nico
            $token = bin2hex(random_bytes(32));
            
            // Verificar que el token se gener� correctamente
            if (empty($token) || strlen($token) != 64) {
                $errors[] = "Error al generar token de seguridad";
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO " . TABLE_INVITATIONS . "
                        (torneo_id, club_id, acceso1, acceso2, usuario, token, estado)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    if ($stmt->execute([$torneo_id, $club_id, $acceso1, $acceso2, $usuario, $token, $estado])) {
                        header("Location: index.php?msg=" . urlencode("Invitaci�n creada exitosamente"));
                        exit;
                    } else {
                        $errorInfo = $stmt->errorInfo();
                        $errors[] = "Error al crear la invitaci�n: " . $errorInfo[2];
                    }
                } catch (PDOException $e) {
                    $errors[] = "Error de base de datos: " . $e->getMessage();
                }
            }
        }
    }
    
} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">? Nueva Invitaci�n a Torneo</h4>
                </div>
                <div class="card-body">
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <strong>?? Errores:</strong>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($torneos)): ?>
                        <div class="alert alert-warning">
                            ?? No hay torneos disponibles. <a href="../tournaments/new.php">Crear torneo</a>
                        </div>
                    <?php elseif (empty($clubes)): ?>
                        <div class="alert alert-warning">
                            ?? No hay clubes registrados. <a href="../clubs/new.php">Crear club</a>
                        </div>
                    <?php else: ?>
                    
                    <form method="POST">
                        <?= CSRF::input(); ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Torneo <span class="text-danger">*</span></label>
                            <select name="torneo_id" class="form-control" required>
                                <option value="">-- Seleccionar Torneo --</option>
                                <?php foreach ($torneos as $t): ?>
                                    <option value="<?= $t['id'] ?>" <?= (isset($_POST['torneo_id']) && $_POST['torneo_id'] == $t['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($t['nombre']) ?> - <?= date('d/m/Y', strtotime($t['fechator'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Club <span class="text-danger">*</span></label>
                            <select name="club_id" id="club_id" class="form-control" required>
                                <option value="">-- Seleccionar Club --</option>
                                <?php foreach ($clubes as $c): ?>
                                    <option value="<?= $c['id'] ?>" 
                                            data-nombre="<?= htmlspecialchars($c['nombre']) ?>"
                                            <?= (isset($_POST['club_id']) && $_POST['club_id'] == $c['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Acceso Desde <span class="text-danger">*</span></label>
                                    <input type="date" name="acceso1" class="form-control" 
                                           value="<?= $_POST['acceso1'] ?? date('Y-m-d') ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Acceso Hasta <span class="text-danger">*</span></label>
                                    <input type="date" name="acceso2" class="form-control" 
                                           value="<?= $_POST['acceso2'] ?? '' ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Usuario (Opcional)</label>
                            <input type="text" name="usuario" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>"
                                   placeholder="Nombre del usuario/delegado">
                            <small class="text-muted">Nombre de referencia del responsable de la inscripci�n</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Estado</label>
                            <select name="estado" class="form-control">
                                <option value="activa" <?= (($_POST['estado'] ?? 'activa') === 'activa') ? 'selected' : '' ?>>Activa</option>
                                <option value="expirada" <?= (($_POST['estado'] ?? '') === 'expirada') ? 'selected' : '' ?>>Expirada</option>
                                <option value="cancelada" <?= (($_POST['estado'] ?? '') === 'cancelada') ? 'selected' : '' ?>>Cancelada</option>
                            </select>
                        </div>

                        <div class="alert alert-info">
                            <small>
                                <strong>?? Nota:</strong> El token de seguridad se generar� autom�ticamente.
                                Este token ser� necesario para que el club pueda inscribir jugadores.
                            </small>
                        </div>

                        <div class="border-top pt-3 mt-3">
                            <button type="submit" class="btn btn-success">
                                ? Crear Invitaci�n
                            </button>
                            <a href="index.php" class="btn btn-secondary">
                                ? Cancelar
                            </a>
                        </div>
                    </form>
                    
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
</body>
</html>

