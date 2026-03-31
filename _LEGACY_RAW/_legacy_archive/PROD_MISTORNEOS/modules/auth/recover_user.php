<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/app_helpers.php';

$success = null;
$error = null;
$cedula = trim($_POST['cedula'] ?? '');
$contacto = trim($_POST['contacto'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($cedula === '' || $contacto === '') {
            throw new Exception('Ingresa tu cédula y tu teléfono o correo.');
        }

        $pdo = DB::pdo();

        // Detectar columnas disponibles
        $cols = $pdo->query("SHOW COLUMNS FROM usuarios")->fetchAll(PDO::FETCH_ASSOC);
        $hasEmail = false; $hasCel = false; $hasTel = false;
        foreach ($cols as $c) {
            $f = strtolower($c['Field'] ?? $c['field'] ?? '');
            if ($f === 'email') $hasEmail = true;
            if ($f === 'celular') $hasCel = true;
            if ($f === 'telefono') $hasTel = true;
        }

        $contactConds = [];
        $params = [$cedula];
        if ($hasEmail) { $contactConds[] = "email = ?"; $params[] = $contacto; }
        if ($hasCel)   { $contactConds[] = "celular = ?"; $params[] = $contacto; }
        if ($hasTel)   { $contactConds[] = "telefono = ?"; $params[] = $contacto; }

        if (empty($contactConds)) {
            throw new Exception('No hay columnas de contacto configuradas (email/celular/telefono). Contacta al administrador.');
        }

        $sql = "SELECT username, email FROM usuarios WHERE cedula = ? AND (" . implode(' OR ', $contactConds) . ") LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $username = htmlspecialchars($user['username']);
            $success = 'Tu usuario es: <span style="font-weight:700; font-size:1.5em;">' . $username . '</span>. Si no recuerdas tu contraseña, usa Recuperar contraseña.';
        } else {
            throw new Exception('No se encontró coincidencia. Verifica los datos o contacta al administrador general para ayuda.');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
        error_log("recover_user error: " . $e->getMessage());
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Recuperar usuario</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
          <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-user-circle me-2"></i>Recuperar usuario</h5>
          </div>
          <div class="card-body">
            <?php if ($error): ?>
              <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
              <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= $success ?></div>
            <?php endif; ?>

            <form method="post">
              <div class="mb-3">
                <label class="form-label">Cédula</label>
                <input type="text" name="cedula" class="form-control" required value="<?= htmlspecialchars($cedula) ?>">
              </div>
              <div class="mb-3">
                <label class="form-label">Teléfono o correo registrado</label>
                <input type="text" name="contacto" class="form-control" required value="<?= htmlspecialchars($contacto) ?>">
                <small class="text-muted">Debe coincidir con el dato que usaste al registrarte.</small>
              </div>
              <button type="submit" class="btn btn-primary w-100">
                <i class="fas fa-search me-1"></i>Buscar mi usuario
              </button>
            </form>
            <div class="mt-3 d-flex justify-content-between">
              <a href="<?= htmlspecialchars(AppHelpers::url('login.php')) ?>" class="small text-decoration-none"><i class="fas fa-arrow-left me-1"></i>Volver al login</a>
              <a href="<?= htmlspecialchars(AppHelpers::url('modules/auth/forgot_password.php')) ?>" class="small text-decoration-none">Recuperar contraseña</a>
            </div>
            <div class="mt-3">
              <small class="text-muted">Si no recuerdas estos datos, escribe al administrador general solicitando ayuda.</small>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>


