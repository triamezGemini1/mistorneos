<?php
declare(strict_types=1);

/**
 * API: Parsea archivo de importación (.xls Excel 97-2003 o .csv).
 * Devuelve { headers: [...], rows: [[...], ...] } para previsualización y mapeo.
 * Lectura celda a celda en .xls para evitar errores de encoding.
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
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
    } else {
        require_once __DIR__ . '/../../vendor/autoload.php';
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($ext === 'xls' ? 'Xls' : 'Xlsx');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($tmpPath);
        $sheet = $spreadsheet->getActiveSheet();
        $maxRow = $sheet->getHighestRow();
        $maxCol = $sheet->getHighestColumn();
        $maxColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($maxCol);

        for ($r = 1; $r <= $maxRow; $r++) {
            $rowCells = [];
            for ($c = 1; $c <= $maxColIndex; $c++) {
                $cell = $sheet->getCellByColumnAndRow($c, $r);
                $val = $cell->getValue();
                if ($val instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                    $val = $val->getPlainText();
                }
                if ($val !== null && is_float($val) && (int) $val == $val) {
                    $val = (int) $val;
                }
                $rowCells[] = $asegurarUtf8($val);
            }
            if ($r === 1) {
                $headers = $rowCells;
            } else {
                $rows[] = $rowCells;
            }
        }
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $reader);
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
