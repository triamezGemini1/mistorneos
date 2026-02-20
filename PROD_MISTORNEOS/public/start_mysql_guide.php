<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guía: Iniciar MySQL en WAMP</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #1a365d 0%, #2d3748 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #1a365d;
            text-align: center;
        }
        .step {
            background: #f3f4f6;
            padding: 1.5rem;
            border-radius: 10px;
            margin: 1rem 0;
            border-left: 4px solid #1a365d;
        }
        .step h3 {
            color: #1a365d;
            margin-top: 0;
        }
        .step ol, .step ul {
            color: #374151;
            line-height: 1.8;
        }
        .status {
            padding: 1rem;
            border-radius: 10px;
            margin: 1rem 0;
            text-align: center;
            font-weight: bold;
        }
        .status.error {
            background: #fee2e2;
            color: #991b1b;
            border: 2px solid #dc2626;
        }
        .status.success {
            background: #d1fae5;
            color: #065f46;
            border: 2px solid #059669;
        }
        .btn {
            display: inline-block;
            padding: 12px 32px;
            background: #1a365d;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            margin: 0.5rem;
            min-height: 44px;
        }
        .btn:hover {
            background: #152b4a;
        }
        .icon {
            font-size: 3rem;
            text-align: center;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔧</div>
        <h1>Guía: Iniciar MySQL en WAMP Server</h1>
        
        <?php
        // Verificar estado de MySQL
        $mysql_running = false;
        $connection = @fsockopen('localhost', 3306, $errno, $errstr, 2);
        if ($connection) {
            $mysql_running = true;
            fclose($connection);
        }
        ?>
        
        <div class="status <?= $mysql_running ? 'success' : 'error' ?>">
            <?php if ($mysql_running): ?>
                ✅ MySQL está corriendo correctamente
            <?php else: ?>
                ❌ MySQL NO está corriendo
            <?php endif; ?>
        </div>
        
        <?php if (!$mysql_running): ?>
        <div class="step">
            <h3>📋 Pasos para Iniciar MySQL:</h3>
            <ol>
                <li><strong>Localiza el icono de WAMP Server</strong>
                    <ul>
                        <li>Busca el icono en la bandeja del sistema (sistema de Windows, abajo a la derecha)</li>
                        <li>El icono puede ser: 🟢 Verde (todo OK), 🟠 Naranja (algunos servicios parados), o 🔴 Rojo (servicios detenidos)</li>
                    </ul>
                </li>
                
                <li><strong>Haz clic derecho en el icono de WAMP</strong></li>
                
                <li><strong>Selecciona: Tools → Services → MySQL</strong>
                    <ul>
                        <li>O también puedes usar: "Start/Resume Service" → "MySQL"</li>
                    </ul>
                </li>
                
                <li><strong>Verifica que el icono se ponga VERDE</strong>
                    <ul>
                        <li>Si el icono está verde, MySQL está corriendo</li>
                        <li>Espera unos segundos después de iniciar</li>
                    </ul>
                </li>
                
                <li><strong>Refresca esta página</strong> para verificar que MySQL esté corriendo</li>
            </ol>
        </div>
        
        <div class="step">
            <h3>🔍 Método Alternativo (si el anterior no funciona):</h3>
            <ol>
                <li>Abre el <strong>Administrador de Servicios de Windows</strong>
                    <ul>
                        <li>Presiona <code>Win + R</code></li>
                        <li>Escribe: <code>services.msc</code></li>
                        <li>Presiona Enter</li>
                    </ul>
                </li>
                
                <li>Busca el servicio <strong>"wampmysqld"</strong> o <strong>"MySQL"</strong></li>
                
                <li>Haz clic derecho → <strong>"Iniciar"</strong></li>
                
                <li>Espera a que el estado cambie a <strong>"En ejecución"</strong></li>
            </ol>
        </div>
        
        <div class="step">
            <h3>⚠️ Si MySQL no inicia:</h3>
            <ul>
                <li>Verifica que el puerto 3306 no esté siendo usado por otro programa</li>
                <li>Reinicia WAMP Server completamente (salir e iniciar de nuevo)</li>
                <li>Verifica los logs de MySQL en: <code>C:\wamp64\logs\mysql_error.log</code></li>
                <li>Puede ser necesario reinstalar WAMP si el problema persiste</li>
            </ul>
        </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 2rem;">
            <a href="check_mysql.php" class="btn">🔄 Verificar MySQL</a>
            <a href="test_simple.php" class="btn">✅ Test Simple</a>
            <?php if ($mysql_running): ?>
                <a href="index.php" class="btn">🚀 Ir a la Aplicación</a>
            <?php endif; ?>
        </div>
        
        <p style="text-align: center; margin-top: 2rem; color: #6b7280; font-size: 0.9rem;">
            <strong>⚠️ RECUERDA ELIMINAR ESTE ARCHIVO (start_mysql_guide.php) DESPUÉS DE USAR</strong>
        </p>
    </div>
</body>
</html>












