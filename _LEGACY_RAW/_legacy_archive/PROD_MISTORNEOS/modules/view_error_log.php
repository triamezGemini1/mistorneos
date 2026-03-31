<?php
/**
 * Visor de Logs de Error - Solo Invitaciones
 * Accesible desde: index.php?page=view_error_log
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/auth.php';

// Solo admins
Auth::requireRole(['admin_general']);

header('Content-Type: text/html; charset=UTF-8');

// Función para leer las últimas líneas de un archivo
function tail($filepath, $lines = 100) {
    if (!file_exists($filepath)) {
        return [];
    }
    
    $file = fopen($filepath, 'r');
    if (!$file) {
        return [];
    }
    
    // Ir al final del archivo
    fseek($file, -1, SEEK_END);
    
    $line_count = 0;
    $output = [];
    $current_line = '';
    
    // Leer hacia atrás
    while ($line_count < $lines && ftell($file) > 0) {
        $char = fgetc($file);
        
        if ($char === "\n") {
            $line_count++;
            if ($current_line !== '') {
                $output[] = strrev($current_line);
                $current_line = '';
            }
        } else {
            $current_line .= $char;
        }
        
        fseek($file, -2, SEEK_CUR);
    }
    
    // Agregar la última línea
    if ($current_line !== '') {
        $output[] = strrev($current_line);
    }
    
    fclose($file);
    return array_reverse($output);
}

// Posibles ubicaciones del log
$possible_logs = [
    ini_get('error_log'),
    __DIR__ . '/../error_log',
    __DIR__ . '/../../error_log',
    '/home/laestaci/public_html/error_log',
    '/home/laestaci/logs/error_log',
    $_SERVER['DOCUMENT_ROOT'] . '/error_log',
    $_SERVER['DOCUMENT_ROOT'] . '/../logs/error_log',
];

$log_file = null;
foreach ($possible_logs as $path) {
    if ($path && file_exists($path) && is_readable($path)) {
        $log_file = $path;
        break;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Visor de Logs - Invitaciones</title>
    <style>
        body { font-family: 'Courier New', monospace; margin: 20px; background: #1e1e1e; color: #d4d4d4; }
        .container { max-width: 1400px; margin: 0 auto; background: #252526; padding: 20px; border-radius: 8px; }
        .header { background: #007bff; color: white; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .log-line { padding: 8px; margin: 2px 0; border-left: 3px solid #444; background: #2d2d30; }
        .log-line.error { border-left-color: #f44336; background: #3d1e1e; }
        .log-line.warning { border-left-color: #ff9800; background: #3d2e1e; }
        .log-line.info { border-left-color: #2196F3; background: #1e2a3d; }
        .log-line.invitation { border-left-color: #4CAF50; background: #1e3d1e; font-weight: bold; }
        .controls { margin: 20px 0; padding: 15px; background: #2d2d30; border-radius: 5px; }
        .btn { padding: 10px 20px; margin: 5px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        .info-box { padding: 15px; background: #2d2d30; border-left: 4px solid #2196F3; margin: 15px 0; }
        .timestamp { color: #858585; }
        .highlight { background: #4a4a1a; }
        pre { white-space: pre-wrap; word-wrap: break-word; }
        .no-log { text-align: center; padding: 40px; color: #858585; }
        .filter-active { background: #4CAF50 !important; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>?? Visor de Logs - Sistema de Invitaciones</h1>
            <p>Últimas entradas del log de errores filtradas</p>
        </div>

        <?php if ($log_file): ?>
            <div class="info-box">
                <strong>?? Archivo de log encontrado:</strong> <?= htmlspecialchars($log_file) ?><br>
                <strong>?? Tamaño:</strong> <?= number_format(filesize($log_file) / 1024 / 1024, 2) ?> MB<br>
                <strong>?? Última modificación:</strong> <?= date('Y-m-d H:i:s', filemtime($log_file)) ?>
            </div>

            <div class="controls">
                <strong>Filtros:</strong>
                <button class="btn btn-success filter-active" onclick="showAll()">Todas las Líneas</button>
                <button class="btn" onclick="filterInvitations()">Solo Invitaciones</button>
                <button class="btn" onclick="filterErrors()">Solo Errores</button>
                <button class="btn" onclick="filterToday()">Solo Hoy</button>
                
                <br><br>
                
                <strong>Cantidad:</strong>
                <button class="btn btn-secondary" onclick="loadLines(50)">50 líneas</button>
                <button class="btn btn-secondary" onclick="loadLines(100)">100 líneas</button>
                <button class="btn btn-secondary" onclick="loadLines(200)">200 líneas</button>
                <button class="btn btn-secondary" onclick="loadLines(500)">500 líneas</button>
            </div>

            <?php
            $lines_to_show = isset($_GET['lines']) ? min((int)$_GET['lines'], 1000) : 200;
            $lines = tail($log_file, $lines_to_show);
            
            if (empty($lines)) {
                echo "<div class='no-log'>?? No hay entradas en el log o el archivo está vacío</div>";
            } else {
                echo "<div class='info-box'>";
                echo "<strong>?? Mostrando las últimas {$lines_to_show} líneas</strong>";
                echo "</div>";
                
                echo "<div id='log-container'>";
                
                $invitation_count = 0;
                $error_count = 0;
                $today = date('Y-m-d');
                
                foreach ($lines as $index => $line) {
                    if (empty(trim($line))) continue;
                    
                    // Determinar tipo de línea
                    $class = 'log-line';
                    $line_lower = strtolower($line);
                    
                    // Filtrar por invitaciones
                    if (strpos($line_lower, 'invitation') !== false || 
                        strpos($line_lower, 'invitacion') !== false ||
                        strpos($line_lower, 'save invitations') !== false ||
                        strpos($line_lower, 'post recibido en invitations') !== false) {
                        $class .= ' invitation';
                        $invitation_count++;
                    }
                    
                    // Filtrar por errores
                    if (strpos($line_lower, 'error') !== false || 
                        strpos($line_lower, 'fatal') !== false ||
                        strpos($line_lower, 'exception') !== false) {
                        $class .= ' error';
                        $error_count++;
                    } elseif (strpos($line_lower, 'warning') !== false || 
                              strpos($line_lower, 'notice') !== false) {
                        $class .= ' warning';
                    }
                    
                    // Resaltar fecha de hoy
                    if (strpos($line, $today) !== false) {
                        $class .= ' highlight';
                    }
                    
                    // Formatear la línea
                    $formatted_line = htmlspecialchars($line);
                    
                    // Resaltar palabras clave
                    $keywords = [
                        'ERROR' => '#f44336',
                        'error' => '#f44336',
                        'Warning' => '#ff9800',
                        'invitation' => '#4CAF50',
                        'invitacion' => '#4CAF50',
                        'POST' => '#2196F3',
                        'success' => '#4CAF50',
                        'failed' => '#f44336',
                        'Exception' => '#f44336',
                    ];
                    
                    foreach ($keywords as $keyword => $color) {
                        $formatted_line = str_replace(
                            $keyword, 
                            "<strong style='color: {$color};'>{$keyword}</strong>", 
                            $formatted_line
                        );
                    }
                    
                    echo "<div class='{$class}' data-line='{$index}'>";
                    echo "<span class='timestamp'>[" . ($index + 1) . "]</span> ";
                    echo $formatted_line;
                    echo "</div>";
                }
                
                echo "</div>";
                
                echo "<div class='info-box' style='margin-top: 20px;'>";
                echo "<strong>?? Resumen:</strong><br>";
                echo "Total de líneas: {$lines_to_show}<br>";
                echo "Relacionadas con invitaciones: <span style='color: #4CAF50;'>{$invitation_count}</span><br>";
                echo "Errores encontrados: <span style='color: #f44336;'>{$error_count}</span>";
                echo "</div>";
            }
            ?>

        <?php else: ?>
            <div class="no-log">
                <h2>? No se pudo encontrar el archivo de log</h2>
                <p>Ubicaciones buscadas:</p>
                <ul style="list-style: none; padding: 0;">
                    <?php foreach ($possible_logs as $path): ?>
                        <?php if ($path): ?>
                            <li>? <?= htmlspecialchars($path) ?></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
                
                <div class="info-box">
                    <p><strong>Información de PHP:</strong></p>
                    <p>error_log configurado: <?= htmlspecialchars(ini_get('error_log') ?: 'No configurado') ?></p>
                    <p>log_errors: <?= ini_get('log_errors') ? 'Activado' : 'Desactivado' ?></p>
                </div>
            </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php?page=test_invitations_save" class="btn">?? Ir a Diagnóstico</a>
            <a href="index.php?page=invitations" class="btn btn-success">?? Ir a Invitaciones</a>
            <a href="index.php" class="btn btn-secondary">?? Dashboard</a>
        </div>
    </div>

    <script>
        function filterInvitations() {
            const lines = document.querySelectorAll('.log-line');
            let visible = 0;
            lines.forEach(line => {
                if (line.classList.contains('invitation')) {
                    line.style.display = 'block';
                    visible++;
                } else {
                    line.style.display = 'none';
                }
            });
            updateActiveButton(1);
            alert(`Mostrando ${visible} líneas relacionadas con invitaciones`);
        }

        function filterErrors() {
            const lines = document.querySelectorAll('.log-line');
            let visible = 0;
            lines.forEach(line => {
                if (line.classList.contains('error')) {
                    line.style.display = 'block';
                    visible++;
                } else {
                    line.style.display = 'none';
                }
            });
            updateActiveButton(2);
            alert(`Mostrando ${visible} errores`);
        }

        function filterToday() {
            const lines = document.querySelectorAll('.log-line');
            let visible = 0;
            lines.forEach(line => {
                if (line.classList.contains('highlight')) {
                    line.style.display = 'block';
                    visible++;
                } else {
                    line.style.display = 'none';
                }
            });
            updateActiveButton(3);
            alert(`Mostrando ${visible} líneas de hoy`);
        }

        function showAll() {
            const lines = document.querySelectorAll('.log-line');
            lines.forEach(line => {
                line.style.display = 'block';
            });
            updateActiveButton(0);
        }

        function updateActiveButton(index) {
            const buttons = document.querySelectorAll('.controls .btn');
            buttons.forEach((btn, i) => {
                if (i === index) {
                    btn.classList.add('filter-active');
                } else {
                    btn.classList.remove('filter-active');
                }
            });
        }

        function loadLines(num) {
            window.location.href = '?page=view_error_log&lines=' + num;
        }

        // Auto-scroll al final
        window.addEventListener('load', function() {
            const container = document.getElementById('log-container');
            if (container) {
                container.scrollIntoView({ behavior: 'smooth', block: 'end' });
            }
        });
    </script>
</body>
</html>




