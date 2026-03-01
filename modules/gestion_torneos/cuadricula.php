<?php
/**
 * Vista: Cuadrícula de Asignaciones
 * Estructura: 22 filas x 9 segmentos (3 columnas cada uno)
 * - Columna 1 (verde): ID Usuario ordenado ASC
 * - Columna 2 (azul): Mesa y Letra asignada
 * - Columna 3 (naranja): Separador 2px mínimo
 */
if (!isset($base_url) || !isset($use_standalone)) {
    $script_actual = basename($_SERVER['PHP_SELF'] ?? '');
    $use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php']);
    $base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';
}

// Mapear secuencia a letra: Pareja AC (sec 1,2) → A,C | Pareja BD (sec 3,4) → B,D
$letras = [1 => 'A', 2 => 'C', 3 => 'B', 4 => 'D'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuadrícula - Ronda <?php echo $numRonda ?? 0; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html {
            font-size: 16px; /* Base para rem */
        }
        
        body {
            font-family: Verdana, sans-serif;
            background: #f5f5f5;
            font-size: 1rem;
        }
        
        @media print {
            .no-print {
                display: none;
            }
            body {
                margin: 0;
                padding: 5mm;
                background: white;
            }
            .cuadricula-container {
                page-break-inside: avoid;
            }
        }
        
        .no-print {
            padding: 1.25rem;
            background: white;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.1);
            margin-bottom: 1.25rem;
        }
        
        .cuadricula-container {
            background: white;
            padding: 1.25rem 0;
            margin: 0 auto;
            max-width: 95%;
            width: 95%;
            text-align: left;
        }
        
        .cuadricula-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.4rem 1%;
            background: white;
            margin-bottom: 0.35rem;
            font-size: clamp(0.875rem, 2.5vw, 1rem);
            font-weight: 300;
            color: #1a365d;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .cuadricula-header-left {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 1;
            flex-wrap: wrap;
        }
        
        .cuadricula-header-right {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .cuadricula-header-torneo {
            color: #1a365d;
            text-align: center;
            text-transform: uppercase;
        }
        
        @media print {
            .cuadricula-header-right {
                display: none;
            }
        }
        
        .cuadricula-table-wrapper {
            width: 95%;
            max-width: 95vw;
            margin: 0 auto;
            overflow-x: auto;
            padding: 0;
            -webkit-overflow-scrolling: touch;
        }
        
        .cuadricula-table {
            border-collapse: collapse;
            width: 100%;
            margin: 0;
            font-size: 0.75rem;
            table-layout: fixed;
            font-family: Calibri, 'Lato', sans-serif !important;
            font-weight: 700 !important;
        }
        
        .cuadricula-table th,
        .cuadricula-table td {
            border: 1px solid #000;
            padding: 0.222rem 0.171rem;
            text-align: center;
            vertical-align: middle;
            height: auto;
            min-height: 1.458vh;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            font-family: Calibri, 'Lato', sans-serif !important;
            font-weight: 700 !important;
            font-size: 0.75rem !important;
        }
        
        .cuadricula-table thead th {
            font-weight: 700 !important;
            font-size: 0.85em;
        }
        
        /* Columna 1: ID Usuario (Verde) - 5 dígitos 99999 */
        .col-id-usuario {
            background-color: #4ade80 !important; /* Verde */
            font-weight: 700 !important;
            color: #000;
            width: 10%;
            min-width: 2.8rem;
        }
        
        /* Columna 2: Mesa y Letra (Azul) - 5 dígitos + letra "99999 A" */
        .col-mesa-letra {
            background-color: #60a5fa !important; /* Azul */
            font-weight: 700 !important;
            color: #000;
            width: 10%;
            min-width: 3.5rem;
        }
        
        /* Columna 3: Separador (Naranja) */
        .col-separador {
            background-color: #fb923c !important; /* Naranja */
            width: 1%;
            min-width: 0.25rem;
            padding: 0;
            border: none;
        }
        
        /* Celda vacía */
        .celda-vacia {
            background-color: #f0f0f0;
            color: #999;
        }
        
        /* Jugador BYE (mesa 0) */
        .celda-bye {
            background-color: #fef08a !important;
            font-style: italic;
            font-weight: 700 !important;
        }
        
        /* Responsive para tablets */
        @media screen and (max-width: 1024px) and (min-width: 769px) {
            .cuadricula-table {
                font-size: clamp(0.7rem, 1.8vw, 1.1rem);
            }
            .cuadricula-table th,
            .cuadricula-table td {
                padding: 0.176rem 0.133rem;
                min-height: 1.395vh;
            }
        }
        
        /* Responsive para móviles */
        @media screen and (max-width: 768px) {
            .cuadricula-table {
                font-size: clamp(0.65rem, 1.5vw, 0.9rem);
            }
            .cuadricula-table th,
            .cuadricula-table td {
                padding: 0.147rem 0.097rem;
                min-height: 1.17vh;
            }
            .col-id-usuario {
                width: 10%;
                min-width: 2rem;
            }
            .col-mesa-letra {
                width: 10%;
                min-width: 2rem;
            }
            .cuadricula-header {
                font-size: clamp(0.75rem, 2vw, 0.9rem);
                padding: 0.4rem 2%;
                margin-bottom: 0.3rem;
                flex-direction: column;
                align-items: center;
            }
            
            .cuadricula-header-left {
                width: 100%;
                justify-content: center;
                order: 1;
            }
            
            .cuadricula-header-right {
                width: 100%;
                justify-content: flex-start;
                order: 2;
            }
        }
        
        /* Responsive para móviles pequeños */
        @media screen and (max-width: 480px) {
            .cuadricula-table {
                font-size: clamp(0.6rem, 1.2vw, 0.8rem);
            }
            .cuadricula-table th,
            .cuadricula-table td {
                padding: 0.122rem 0.071rem;
                min-height: 1.089vh;
            }
            .col-id-usuario {
                width: 10%;
                min-width: 1.5rem;
            }
            .col-mesa-letra {
                width: 10%;
                min-width: 1.5rem;
            }
        }
        
        /* Orientación horizontal en móviles */
        @media screen and (max-width: 768px) and (orientation: landscape) {
            .cuadricula-table {
                font-size: clamp(0.7rem, 1.4vw, 0.95rem);
            }
            .cuadricula-table th,
            .cuadricula-table td {
                min-height: 0.909vh;
            }
        }
    </style>
</head>
<body>
    <div class="cuadricula-container">
        <div class="cuadricula-header">
            <div class="cuadricula-header-left">
                <span class="cuadricula-header-torneo">
                    <?php echo htmlspecialchars(strtoupper($torneo['nombre'] ?? 'Torneo')); ?> - RONDA <?php echo $numRonda ?? 0; ?>
                </span>
            </div>
            <div class="cuadricula-header-right">
                <button onclick="window.print()" class="btn btn-primary btn-sm">
                    <i class="fas fa-print mr-2"></i> Imprimir
                </button>
                <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=panel&torneo_id=<?php echo $torneo['id']; ?>" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left mr-2"></i> Volver al Panel
                </a>
            </div>
        </div>
        <div class="cuadricula-table-wrapper">
        <table class="cuadricula-table">
            <thead>
                <tr>
                    <?php $totalSegmentos = 9; for ($segmento = 0; $segmento < $totalSegmentos; $segmento++): ?>
                        <th class="col-id-usuario">ID</th>
                        <th class="col-mesa-letra">MESA</th>
                        <?php if ($segmento < $totalSegmentos - 1): ?>
                            <td class="col-separador"></td>
                        <?php endif; ?>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
            <?php
            $totalFilas = 22;
            $totalSegmentos = 9;
            $jugadoresPorSegmento = $totalFilas; // Máximo 22 jugadores por segmento
            
            // Organizar asignaciones por segmento (llenado vertical: segmento por segmento)
            $segmentos = [];
            
            // Inicializar segmentos vacíos
            for ($s = 0; $s < $totalSegmentos; $s++) {
                $segmentos[$s] = [];
            }
            
            // Distribuir asignaciones por segmentos (llenado vertical)
            // Llenar el primer segmento completamente antes de pasar al siguiente
            if (!empty($asignaciones)) {
                $indice = 0;
                foreach ($asignaciones as $asignacion) {
                    $segmento = floor($indice / $jugadoresPorSegmento);
                    if ($segmento >= $totalSegmentos) break;
                    
                    $segmentos[$segmento][] = $asignacion;
                    $indice++;
                }
            }
            
            // Llenar filas
            for ($fila = 0; $fila < $totalFilas; $fila++):
            ?>
                <tr>
                    <?php for ($segmento = 0; $segmento < $totalSegmentos; $segmento++): ?>
                        <?php
                        // Obtener asignación para esta fila y segmento
                        $asignacion = isset($segmentos[$segmento][$fila]) ? $segmentos[$segmento][$fila] : null;
                        
                        if ($asignacion):
                            $idUsuario = $asignacion['id_usuario'] ?? '';
                            $mesaRaw = $asignacion['mesa'] ?? 0;
                            $mesa = (int)$mesaRaw;
                            $secuencia = (int)($asignacion['secuencia'] ?? 0);
                            $letra = $letras[$secuencia] ?? '';
                            // BYE: mesa 0 (puede venir como int 0 o string "0" según driver)
                            $esBye = ($mesa === 0 || $mesaRaw === '0' || $mesaRaw === 0);
                        ?>
                            <!-- Columna 1: ID Usuario (Verde) -->
                            <td class="col-id-usuario<?php echo $esBye ? ' celda-bye' : ''; ?>">
                                <?php echo htmlspecialchars($idUsuario); ?>
                            </td>
                            
                            <!-- Columna 2: Mesa y Letra (Azul); BYE si mesa 0 -->
                            <td class="col-mesa-letra<?php echo $esBye ? ' celda-bye' : ''; ?>">
                                <?php if ($esBye): ?>BYE<?php else: ?><?php echo $mesa; ?><?php echo htmlspecialchars($letra); ?><?php endif; ?>
                            </td>
                        <?php else: ?>
                            <!-- Celda vacía para ID -->
                            <td class="celda-vacia col-id-usuario"></td>
                            <!-- Celda vacía para Mesa-Letra -->
                            <td class="celda-vacia col-mesa-letra"></td>
                        <?php endif; ?>
                        
                        <!-- Columna 3: Separador (Naranja) - Solo entre segmentos -->
                        <?php if ($segmento < $totalSegmentos - 1): ?>
                            <td class="col-separador"></td>
                        <?php endif; ?>
                        
                    <?php endfor; ?>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>
        </div>
    </div>
</body>
</html>
