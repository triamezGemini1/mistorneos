<?php
if (!isset($_SESSION)) { session_start(); }
if (empty($_SESSION['user'])) { header('Location: /modules/auth/login.php'); exit; }

require_once __DIR__ . '/../../config/csrf.php';

$isForced = isset($_GET['force']) && $_GET['force'] == '1';
$reason = $_SESSION['password_change_reason'] ?? '';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cambiar contraseña</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .card {
      max-width: 450px;
      border: none;
      border-radius: 16px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }
    .card-header {
      background: linear-gradient(135deg, #e94560 0%, #ff6b6b 100%);
      border-radius: 16px 16px 0 0 !important;
      padding: 1.5rem;
    }
    .btn-primary {
      background: linear-gradient(135deg, #e94560 0%, #ff6b6b 100%);
      border: none;
    }
    .btn-primary:hover {
      background: linear-gradient(135deg, #d63050 0%, #e55a5a 100%);
    }
  </style>
</head>
<body>

<div class="container">
  <div class="card mx-auto">
    <div class="card-header text-white text-center">
      <h4 class="mb-0">
        <?php if ($isForced): ?>
          <i class="bi bi-shield-exclamation"></i> Cambio de Contraseña Obligatorio
        <?php else: ?>
          Cambiar Contraseña
        <?php endif; ?>
      </h4>
    </div>
    <div class="card-body p-4">
      
      <?php if ($isForced && $reason): ?>
      <div class="alert alert-warning">
        <strong><i class="bi bi-exclamation-triangle"></i> Atención:</strong><br>
        <?= htmlspecialchars($reason) ?>
      </div>
      <?php endif; ?>
      
      <?php if (isset($_SESSION['password_error'])): ?>
      <div class="alert alert-danger">
        <?= htmlspecialchars($_SESSION['password_error']) ?>
        <?php unset($_SESSION['password_error']); ?>
      </div>
      <?php endif; ?>
      
      <?php if (isset($_SESSION['password_success'])): ?>
      <div class="alert alert-success">
        <?= htmlspecialchars($_SESSION['password_success']) ?>
        <?php unset($_SESSION['password_success']); ?>
      </div>
      <?php endif; ?>
      
      <form method="post" action="change_password_save.php">
        <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
        <input type="hidden" name="forced" value="<?= $isForced ? '1' : '0' ?>">
        
        <div class="mb-3">
          <label class="form-label">Nueva contraseña</label>
          <input type="password" name="new_password" class="form-control" minlength="8" required 
                 placeholder="Mínimo 8 caracteres">
          <div class="form-text">La contraseña debe tener al menos 8 caracteres.</div>
        </div>
        
        <div class="mb-3">
          <label class="form-label">Confirmar contraseña</label>
          <input type="password" name="confirm_password" class="form-control" minlength="8" required 
                 placeholder="Repite la contraseña">
        </div>
        
        <div class="d-grid gap-2">
          <button type="submit" class="btn btn-primary btn-lg">
            Guardar Nueva Contraseña
          </button>
          
          <?php if (!$isForced): ?>
          <a href="../../public/index.php" class="btn btn-outline-secondary">Cancelar</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
</body>
</html>
