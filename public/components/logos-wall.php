<?php
/**
 * Logos Wall - Banner multilínea con ~40 logos en 3 filas.
 * Infinite loop con parallax sutil, mask-image en bordes, lazy loading y GPU.
 * Lee: upload/clientes (prioridad) o upload/logos. Distribución automática en 3 filas.
 * Requiere: AppHelpers::imageUrl(), app_base_url()
 */

$base_dir = dirname(__DIR__, 2);
$allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

// Intentar carpeta clientes, luego logos
$folders = ['upload/clientes', 'upload/logos'];
$all_paths = [];
foreach ($folders as $folder) {
    $full_path = $base_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $folder);
    if (!is_dir($full_path)) {
        continue;
    }
    $files = @scandir($full_path);
    if ($files === false) {
        continue;
    }
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed_ext)) {
            $all_paths[] = $folder . '/' . $file;
        }
    }
    if (count($all_paths) > 0) {
        break;
    }
}

// Repartir en 3 filas de forma equitativa
$total = count($all_paths);
$rows = [[], [], []];
if ($total > 0) {
    $per_row = (int) ceil($total / 3);
    $rows[0] = array_slice($all_paths, 0, $per_row);
    $rows[1] = array_slice($all_paths, $per_row, $per_row);
    $rows[2] = array_slice($all_paths, 2 * $per_row);
}

$has_any = !empty($rows[0]) || !empty($rows[1]) || !empty($rows[2]);
if (!$has_any) {
    return;
}
?>
    <!-- Logos Wall: 3 filas, infinite loop, mask, lazy, GPU. Contenedor al 80% del ancho. -->
    <section class="logos-wall-section py-12 md:py-16 bg-white border-y border-gray-100" aria-label="Logos de clientes y partners">
        <div class="logos-wall-outer w-4/5 max-w-full mx-auto overflow-hidden">
        <div class="logos-wall-container relative w-full overflow-hidden" style="
            -webkit-mask-image: linear-gradient(to right, transparent 0%, black 12%, black 88%, transparent 100%);
            mask-image: linear-gradient(to right, transparent 0%, black 12%, black 88%, transparent 100%);
        ">
            <?php
            $durations = ['45s', '55s', '50s'];
            $directions = [1, -1, 1];
            foreach ($rows as $i => $row_paths):
                if (empty($row_paths)) {
                    continue;
                }
                $duration = $durations[$i];
                $dir = $directions[$i];
            ?>
            <div class="logos-wall-row-wrap overflow-hidden py-4" style="height: 220px;">
                <div class="logos-wall-track h-full flex items-center gap-10 md:gap-14 shrink-0" style="
                    width: max-content;
                    animation: logos-wall-<?= (int) $i ?> <?= $duration ?> linear infinite;
                    animation-direction: <?= $dir === -1 ? 'reverse' : 'normal' ?>;
                    will-change: transform;
                ">
                    <?php
                    foreach ([0, 1] as $dup):
                        foreach ($row_paths as $path):
                            if (class_exists('AppHelpers')) {
                                $base = rtrim(AppHelpers::getPublicUrl(), '/');
                                $url = $base . '/view_image.php?path=' . rawurlencode($path);
                            } else {
                                $url = 'view_image.php?path=' . rawurlencode($path);
                            }
                            $alt = pathinfo($path, PATHINFO_FILENAME);
                    ?>
                    <div class="logos-wall-item flex items-center justify-center shrink-0" style="height: 200px; min-width: 200px;">
                        <img src="<?= htmlspecialchars($url) ?>" alt="<?= htmlspecialchars($alt) ?>" loading="lazy" decoding="async" class="w-full h-full object-contain" style="max-width: 200px; max-height: 200px;">
                    </div>
                    <?php
                        endforeach;
                    endforeach;
                    ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        </div>
        <style>
            .logos-wall-row-wrap { contain: layout style paint; }
            .logos-wall-track { backface-visibility: hidden; transform: translateZ(0); }
            @keyframes logos-wall-0 {
                0% { transform: translateX(0); }
                100% { transform: translateX(-50%); }
            }
            @keyframes logos-wall-1 {
                0% { transform: translateX(0); }
                100% { transform: translateX(-50%); }
            }
            @keyframes logos-wall-2 {
                0% { transform: translateX(0); }
                100% { transform: translateX(-50%); }
            }
        </style>
    </section>
