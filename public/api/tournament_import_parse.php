<?php
declare(strict_types=1);

/**
 * API: Parsea archivo de importación (.xlsx, .xls, .xlsm, .csv).
 * Excel: PhpSpreadsheet (composer). CSV: nativo.
 *
 * El archivo subido se copia a storage/tmp/ con ruta absoluta (__DIR__) para evitar
 * fallos con tmp de PHP en algunos hostings.
 */

// 1. Definimos la raíz del proyecto subiendo 2 niveles desde public/api
$baseDir = dirname(__DIR__, 2);

// 2. Cargamos el autoload usando la ruta real del sistema
$autoloadPath = $baseDir . '/vendor/autoload.php';

if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
} else {
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode([
        'error' => 'Error Crítico: No se encuentra el cargador de librerías.',
        'ruta_buscada' => $autoloadPath,
    ], JSON_UNESCAPED_UNICODE));
}

use PhpOffice\PhpSpreadsheet\IOFactory;

require_once __DIR__ . '/../../config/session_start_early.php';
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/auth.php';

// Respuesta JSON ante error fatal (evita pantalla en blanco si hay fallo antes del try/catch)
register_shutdown_function(static function (): void {
    $e = error_get_last();
    if ($e === null || !in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    if (headers_sent()) {
        return;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => $e['message'],
        'php_file' => $e['file'],
        'php_line' => $e['line'],
        'fatal' => true,
    ], JSON_UNESCAPED_UNICODE);
});

header('Content-Type: application/json; charset=utf-8');

Auth::requireRoleJson(['admin_general', 'admin_torneo', 'admin_club']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
$session_token = $_SESSION['csrf_token'] ?? '';
if (!$csrf_token || !$session_token || !hash_equals($session_token, $csrf_token)) {
    echo json_encode(['success' => false, 'error' => 'Token CSRF inválido o sesión desincronizada. Recargue la página (F5) e intente de nuevo.']);
    exit;
}

if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    $err = (int) ($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE);
    $msg = 'No se recibió el archivo o hubo error en la subida';
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        $msg = 'El archivo supera el tamaño máximo permitido por el servidor (php.ini: upload_max_filesize / post_max_size).';
    }
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

$file = $_FILES['archivo'];
$uploadTmp = $file['tmp_name'];
$name = $file['name'];
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

if (!in_array($ext, ['xls', 'xlsx', 'xlsm', 'csv'], true)) {
    echo json_encode(['success' => false, 'error' => 'Formato no soportado. Use Excel (.xls, .xlsx, .xlsm) o CSV.']);
    exit;
}

/** Ruta absoluta bajo public/api → ../../storage/tmp (no depende del cwd del servidor) */
$storageTmp = __DIR__ . '/../../storage/tmp';
if (!is_dir($storageTmp) && !@mkdir($storageTmp, 0755, true)) {
    echo json_encode(['success' => false, 'error' => 'No se pudo crear el directorio temporal: ' . $storageTmp]);
    exit;
}
$safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($name));
if ($safeBase === '' || $safeBase === '_') {
    $safeBase = 'upload.' . $ext;
}
$targetPath = $storageTmp . '/import_' . bin2hex(random_bytes(8)) . '_' . $safeBase;
if (!is_uploaded_file($uploadTmp)) {
    echo json_encode(['success' => false, 'error' => 'Archivo temporal de subida inválido.']);
    exit;
}
if (!@move_uploaded_file($uploadTmp, $targetPath)) {
    if (!@copy($uploadTmp, $targetPath)) {
        echo json_encode(['success' => false, 'error' => 'No se pudo copiar el archivo a ' . $targetPath]);
        exit;
    }
}
$tmpPath = realpath($targetPath) ?: $targetPath;

$asegurarUtf8 = static function ($v): string {
    if ($v === null || $v === '') {
        return '';
    }
    if (is_object($v) && method_exists($v, '__toString')) {
        $v = (string) $v;
    }
    $s = trim((string) $v);
    $s = str_replace("\xEF\xBB\xBF", '', $s);
    $s = trim($s);
    if ($s === '') {
        return '';
    }
    $enc = mb_detect_encoding($s, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($enc && $enc !== 'UTF-8') {
        $s = mb_convert_encoding($s, 'UTF-8', $enc);
    }
    return $s;
};

/**
 * @param array<int, array<int, mixed>> $allRows
 * @return array{0: array<int, string>, 1: array<int, array<int, string>>}
 */
function extract_headers_and_data_rows(array $allRows, callable $asegurarUtf8): array {
    $allRows = array_values(array_filter($allRows, static function ($row) {
        if (!is_array($row)) {
            return false;
        }
        foreach ($row as $c) {
            if ($c !== null && $c !== '') {
                return true;
            }
        }
        return false;
    }));
    if (empty($allRows)) {
        throw new RuntimeException('El archivo no contiene filas');
    }
    $headerRowIndex = null;
    $headerKeywords = [
        'nacionalidad', 'cedula', 'cédula', 'nombre', 'nombres', 'club', 'organizacion', 'organización',
        'sexo', 'telefono', 'teléfono', 'email', 'correo', 'pareja', 'compañero', 'compañera', 'nombre pareja',
        'jugador', 'integrante', 'apellido', 'apellidos',
    ];
    for ($r = 0, $max = min(4, count($allRows)); $r < $max; $r++) {
        $row = $allRows[$r];
        $cells = array_map(static function ($c) {
            return trim(mb_strtolower((string) $c));
        }, $row);
        $match = 0;
        foreach ($cells as $cell) {
            foreach ($headerKeywords as $kw) {
                if ($cell === $kw || ($cell !== '' && strpos($cell, $kw) !== false)) {
                    $match++;
                    break;
                }
            }
        }
        if ($match >= 2) {
            $headerRowIndex = $r;
            break;
        }
    }
    if ($headerRowIndex === null) {
        $headerRowIndex = 3;
    }
    if (count($allRows) < $headerRowIndex + 1) {
        throw new RuntimeException('El archivo debe tener cabecera (nacionalidad, CEDULA, nombre, etc.) y al menos una fila de datos');
    }
    $headers = array_map($asegurarUtf8, $allRows[$headerRowIndex]);
    $numCols = count($headers);
    $rows = [];
    for ($i = $headerRowIndex + 1, $n = count($allRows); $i < $n; $i++) {
        $rowCells = $allRows[$i];
        $rowCells = array_map(static function ($v) use ($asegurarUtf8) {
            if ($v !== null && is_float($v) && (int) $v == $v) {
                $v = (int) $v;
            }
            return $asegurarUtf8($v);
        }, $rowCells);
        while (count($rowCells) < $numCols) {
            $rowCells[] = '';
        }
        $rows[] = array_slice($rowCells, 0, $numCols);
    }
    return [$headers, $rows];
}

try {
    @ini_set('memory_limit', '256M');
    @set_time_limit(180);

    $headers = [];
    $rows = [];

    if ($ext === 'csv') {
        $content = file_get_contents($tmpPath);
        $content = $asegurarUtf8($content) ?: $content;
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $numHeaders = 0;
        foreach ($lines as $i => $line) {
            $cells = str_getcsv($line, ';');
            if (strpos($line, ',') !== false && (count($cells) === 1 || count($cells) < 3)) {
                $cells = str_getcsv($line, ',');
            }
            $cells = array_map(static function ($c) use ($asegurarUtf8) {
                return $asegurarUtf8($c);
            }, $cells);
            if ($i === 0) {
                $headers = $cells;
                $numHeaders = count($headers);
            } else {
                while (count($cells) < $numHeaders) {
                    $cells[] = '';
                }
                $rows[] = array_slice($cells, 0, $numHeaders);
            }
        }
    } elseif (in_array($ext, ['xlsx', 'xlsm', 'xls'], true)) {
        // Importante: class_exists(..., true) o omitir el 2.º arg para permitir el autoload de Composer.
        // Con false, PHP no invoca el autoloader y la clase parece "inexistente" aunque vendor esté bien.
        $phpspreadsheetReady = class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class);

        if (in_array($ext, ['xlsx', 'xlsm'], true)) {
            if (!$phpspreadsheetReady) {
                throw new RuntimeException(
                    'PhpSpreadsheet no disponible. Ejecute composer install en: ' . $baseDir
                );
            }
            $spreadsheet = IOFactory::load($tmpPath);
            $sheet = $spreadsheet->getActiveSheet();
            $allRows = $sheet->toArray(null, true, true, false);
            if (!is_array($allRows)) {
                $allRows = [];
            }
            [$headers, $rows] = extract_headers_and_data_rows($allRows, $asegurarUtf8);
        } elseif ($ext === 'xls') {
            if ($phpspreadsheetReady) {
                $spreadsheet = IOFactory::load($tmpPath);
                $sheet = $spreadsheet->getActiveSheet();
                $allRows = $sheet->toArray(null, true, true, false);
                if (!is_array($allRows)) {
                    $allRows = [];
                }
                [$headers, $rows] = extract_headers_and_data_rows($allRows, $asegurarUtf8);
            } else {
                require_once __DIR__ . '/../../libs/SimpleXLS.php';
                $xls = SimpleXLS::parse($tmpPath);
                if (!$xls) {
                    $err = SimpleXLS::parseError();
                    throw new RuntimeException($err ?: 'Error al leer el archivo .xls');
                }
                $xls->setOutputEncoding('UTF-8');
                $allRows = $xls->rows(0);
                if (empty($allRows) || !is_array($allRows)) {
                    throw new RuntimeException('El archivo no contiene filas');
                }
                [$headers, $rows] = extract_headers_and_data_rows($allRows, $asegurarUtf8);
            }
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Formato no reconocido']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'headers' => $headers,
        'rows' => $rows,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('tournament_import_parse: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!class_exists('Env', false) && is_readable(__DIR__ . '/../../lib/Env.php')) {
        require_once __DIR__ . '/../../lib/Env.php';
    }
    $exposeTrace = class_exists('Env', false)
        && (Env::bool('APP_DEBUG', false) || Env::bool('IMPORT_PARSE_EXPOSE_ERRORS', true));
    $payload = [
        'success' => false,
        'error' => $e->getMessage(),
        'php_file' => $e->getFile(),
        'php_line' => $e->getLine(),
        'error_class' => get_class($e),
    ];
    if ($exposeTrace) {
        $payload['trace'] = $e->getTraceAsString();
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($targetPath) && is_string($targetPath) && $targetPath !== '' && is_file($targetPath)) {
        @unlink($targetPath);
    }
}
