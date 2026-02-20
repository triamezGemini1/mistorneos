<?php
/**
 * Login de Delegados mediante Token de Invitación
 */

session_start();

require_once __DIR__ . '/../../../config/bootstrap.php';
require_once __DIR__ . '/../../../config/db.php';

$error = null;
$token_from_url = trim($_GET['token'] ?? '');

// Función para procesar el token
function processToken($token) {
    global $error;
    
    if (empty($token)) {
        $error = "Por favor ingrese el token de invitación";
        return false;
    }
    
    try {
        $pdo = DB::pdo();
        
        // Buscar invitación por token
        $stmt = $pdo->prepare("
            SELECT 
                i.*,
                t.nombre as torneo_nombre,
                c.nombre as club_nombre
            FROM invitations i
            INNER JOIN tournaments t ON i.torneo_id = t.id
            INNER JOIN clubes c ON i.club_id = c.id
            WHERE i.token = ? AND i.estado = 'activa'
        ");
        $stmt->execute([$token]);
        $invitacion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invitacion) {
            $error = "Token inválido o invitación no activa";
            return false;
        }
        
        // Verificar vigencia
        $hoy = date('Y-m-d');
        if ($hoy < $invitacion['acceso1']) {
            $error = "La invitación aún no está vigente. Comienza el " . 
                     date('d/m/Y', strtotime($invitacion['acceso1']));
            return false;
        } elseif ($hoy > $invitacion['acceso2']) {
            $error = "La invitación ha expirado. Venció el " . 
                     date('d/m/Y', strtotime($invitacion['acceso2']));
            return false;
        }
        
        // Login exitoso
        $_SESSION['invitacion_id'] = $invitacion['id'];
        $_SESSION['torneo_id'] = $invitacion['torneo_id'];
        $_SESSION['club_id'] = $invitacion['club_id'];
        $_SESSION['torneo_nombre'] = $invitacion['torneo_nombre'];
        $_SESSION['club_nombre'] = $invitacion['club_nombre'];
        
        return true;
        
    } catch (PDOException $e) {
        $error = "Error de conexión: " . $e->getMessage();
        return false;
    }
}

// Si viene token en URL, procesar automáticamente
if (!empty($token_from_url)) {
    if (processToken($token_from_url)) {
        header("Location: index.php");
        exit;
    }
    // Si hay error, se mostrará abajo
}

// Si viene de POST (formulario manual)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['token'] ?? '');
    if (processToken($token)) {
        header("Location: index.php");
        exit;
    }
}

// Obtener error de URL si viene de redirect
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso - Inscripción de Jugadores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px 15px 0 0;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <h2 class="mb-0">?? Inscripción de Jugadores</h2>
        <p class="mb-0">Sistema de Gestión de Torneos</p>
    </div>
    
    <div class="p-4">
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <strong>?? Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($token_from_url)): ?>
            <div class="alert alert-info">
                <strong>?? Información:</strong><br>
                Para acceder al sistema de inscripción, puede:<br>
                • Usar el <strong>link de acceso directo</strong> que recibió<br>
                • O ingresar manualmente el token de invitación
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                <strong>Validando acceso...</strong><br>
                Si no es redirigido automáticamente, verifique que el token sea válido.
            </div>
        <?php endif; ?>
        
        <?php if (empty($token_from_url) || !empty($error)): ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Token de Invitación (Opcional)</label>
                    <input type="text" name="token" class="form-control form-control-lg" 
                           placeholder="Ej: a1b2c3d4e5f6..." 
                           value="<?= htmlspecialchars($token_from_url) ?>"
                           <?= empty($token_from_url) ? 'autofocus' : '' ?>>
                    <small class="text-muted">
                        Solo si el acceso directo no funcionó
                    </small>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg w-100">
                    ?? Acceder al Sistema
                </button>
            </form>
        <?php endif; ?>
        
        <hr>
        
        <div class="text-center">
            <small class="text-muted">
                <strong>?? Consejo:</strong> Use el link directo de WhatsApp para acceso inmediato<br>
                ¿Problemas? Contacte al organizador del torneo
            </small>
        </div>
    </div>
</div>

</body>
</html>










