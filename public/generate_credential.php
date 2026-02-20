<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

// Verificar autenticación
if (!isset($_SESSION['user'])) {
    header('Location: ' . AppHelpers::url('login.php'));
    exit;
}

$user = $_SESSION['user'];
$pdo = DB::pdo();
$base_url = app_base_url();

// Obtener datos actualizados del usuario
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([Auth::id()]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_data) {
    die('Usuario no encontrado');
}

// Generar HTML de la credencial para descargar
$photo_url = !empty($user_data['photo_path']) && file_exists(__DIR__ . '/../' . $user_data['photo_path'])
    ? $base_url . '/' . $user_data['photo_path']
    : null;

$qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($user_data['uuid'] ?? 'N/A');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credencial - <?= htmlspecialchars($user_data['nombre'] ?? $user_data['username']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" defer></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #f0f0f0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .credential-wrapper {
            margin-bottom: 2rem;
        }
        
        .credential {
            width: 350px;
            height: 550px;
            background: linear-gradient(135deg, #1a365d 0%, #2d3748 100%);
            border-radius: 20px;
            padding: 2rem;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        
        .credential::before {
            content: '';
            position: absolute;
            top: -100px;
            right: -100px;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }
        
        .credential::after {
            content: '';
            position: absolute;
            bottom: -50px;
            left: -50px;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(72,187,120,0.2) 0%, transparent 70%);
            border-radius: 50%;
        }
        
        .credential-header {
            text-align: center;
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 1;
        }
        
        .credential-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: 1px;
        }
        
        .credential-header small {
            opacity: 0.7;
            font-size: 0.75rem;
        }
        
        .credential-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #48bb78;
            margin: 0 auto 1rem;
            display: block;
            position: relative;
            z-index: 1;
        }
        
        .credential-photo-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            position: relative;
            z-index: 1;
        }
        
        .credential-info {
            text-align: center;
            position: relative;
            z-index: 1;
        }
        
        .credential-name {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .credential-cedula {
            opacity: 0.8;
            font-size: 0.95rem;
            margin-bottom: 1rem;
        }
        
        .credential-uuid {
            background: rgba(255,255,255,0.15);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-family: 'Courier New', monospace;
            font-size: 0.7rem;
            letter-spacing: 0.5px;
            display: inline-block;
            margin-bottom: 1rem;
        }
        
        .credential-qr {
            background: white;
            padding: 10px;
            border-radius: 10px;
            display: inline-block;
            margin-top: 0.5rem;
        }
        
        .credential-qr img {
            display: block;
            width: 100px;
            height: 100px;
        }
        
        .credential-footer {
            position: absolute;
            bottom: 1.5rem;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 0.65rem;
            opacity: 0.5;
            z-index: 1;
        }
        
        .actions {
            display: flex;
            gap: 1rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #48bb78;
            color: white;
        }
        
        .btn-primary:hover {
            background: #38a169;
        }
        
        .btn-secondary {
            background: #4a5568;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #2d3748;
        }
    </style>
</head>
<body>
    <div class="credential-wrapper">
        <div class="credential" id="credential">
            <div class="credential-header">
                <h3>🎲 LA ESTACIÓN DEL DOMINÓ</h3>
                <small>Credencial de Jugador</small>
            </div>
            
            <?php if ($photo_url): ?>
                <img src="<?= htmlspecialchars($photo_url) ?>" alt="Foto" class="credential-photo" crossorigin="anonymous">
            <?php else: ?>
                <div class="credential-photo-placeholder">👤</div>
            <?php endif; ?>
            
            <div class="credential-info">
                <div class="credential-name"><?= htmlspecialchars($user_data['nombre'] ?? $user_data['username']) ?></div>
                <div class="credential-cedula"><?= htmlspecialchars($user_data['cedula'] ?? 'N/A') ?></div>
                <div class="credential-uuid"><?= htmlspecialchars($user_data['uuid'] ?? 'N/A') ?></div>
                <div class="credential-qr">
                    <img src="<?= $qr_url ?>" alt="QR Code" crossorigin="anonymous">
                </div>
            </div>
            
            <div class="credential-footer">
                Válido para participación en torneos oficiales
            </div>
        </div>
    </div>
    
    <div class="actions">
        <button class="btn btn-primary" onclick="downloadCredential()">
            📥 Descargar Imagen
        </button>
        <a href="user_portal.php?section=credencial" class="btn btn-secondary">
            ← Volver
        </a>
    </div>
    
    <script>
    function downloadCredential() {
        const credential = document.getElementById('credential');
        
        html2canvas(credential, {
            scale: 2,
            useCORS: true,
            allowTaint: true,
            backgroundColor: null
        }).then(canvas => {
            const link = document.createElement('a');
            link.download = 'credencial_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $user_data['nombre'] ?? $user_data['username']) ?>.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        });
    }
    </script>
</body>
</html>


