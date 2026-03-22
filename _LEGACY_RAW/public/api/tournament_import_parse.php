<?php
declare(strict_types=1);

/**
 * API: Parsea archivo de importación (.xls Excel 97-2003 o .csv).
 * Devuelve { headers: [...], rows: [[...], ...] } para previsualización y mapeo.
 * Lectura celda a celda en .xls para evitar errores de encoding.
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

Auth::requireRole(['admin_general', 'admin_torneo', 'admin_club']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
$session_token = $_SESSION['csrf_token'] ?? '';
if (!$csrf_token || !$session_token || !hash_equals($session_token, $csrf_token)) {
    echo json_encode(['success' => false, 'error' => 'Token CSRF inválido']);
    exit;
}

if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No se recibió el archivo o hubo error en la subida']);
    exit;
}

$file = $_FILES['archivo'];
$tmpPath = $file['tmp_name'];
$name = $file['name'];
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

if (!in_array($ext, ['xls', 'xlsx', 'csv'], true)) {
    echo json_encode(['success' => false, 'error' => 'Formato no soportado. Use .xls, .xlsx o .csv']);
    exit;
}

$asegurarUtf8 = static function ($v): string {
    if ($v === null || $v === '') {
        return '';
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

try {
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
            $cells = array_map(function ($c) use ($asegurarUtf8) {
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
    } elseif ($ext === 'xls') {
        require_once __DIR__ . '/../../libs/SimpleXLS.php';
        $xls = SimpleXLS::parse($tmpPath);
        if (!$xls) {
            $err = SimpleXLS::parseError();
            throw new RuntimeException($err ?: 'Error al leer el archivo .xls');
        }
        $xls->setOutputEncoding('UTF-8');
        $allRows = $xls->rows(0);
        if (empty($allRows)) {
            throw new RuntimeException('El archivo no contiene filas');
        }
        $headerRowIndex = null;
        $headerKeywords = ['nacionalidad', 'cedula', 'cédula', 'nombre', 'club', 'organizacion', 'organización', 'sexo', 'telefono', 'email'];
        for ($r = 0, $max = min(4, count($allRows)); $r < $max; $r++) {
            $row = $allRows[$r];
            $cells = array_map(function ($c) {
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
        for ($i = $headerRowIndex + 1, $n = count($allRows); $i < $n; $i++) {
            $rowCells = $allRows[$i];
            $rowCells = array_map(function ($v) use ($asegurarUtf8) {
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
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Formato .xlsx no disponible sin Composer. Use .xls (Excel 97-2003) o CSV.',
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'headers' => $headers,
        'rows' => $rows,
    ]);
} catch (Throwable $e) {
    error_log('tournament_import_parse: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al leer el archivo: ' . $e->getMessage(),
    ]);
}
