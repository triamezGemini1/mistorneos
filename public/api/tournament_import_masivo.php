<?php
declare(strict_types=1);

/**
 * API Importación Masiva para Torneos.
 * action=validar: devuelve estado por fila (semáforo).
 * action=importar: procesa en lotes, devuelve estadísticas y CSV de errores (base64) si aplica.
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../lib/ImportacionMasivaService.php';

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

$action = $_POST['action'] ?? '';
$torneo_id = (int) ($_POST['torneo_id'] ?? 0);
$filas_raw = $_POST['filas'] ?? '';

if ($torneo_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Torneo inválido']);
    exit;
}

if (!Auth::canAccessTournament($torneo_id)) {
    echo json_encode(['success' => false, 'error' => 'Sin permiso para este torneo']);
    exit;
}

$filas = [];
if ($filas_raw !== '') {
    $decoded = json_decode($filas_raw, true);
    if (is_array($decoded)) {
        $filas = $decoded;
    }
}

try {
    $pdo = DB::pdo();

    if ($action === 'validar') {
        $resultado = ImportacionMasivaService::validarFilas($pdo, $torneo_id, $filas);
        echo json_encode([
            'success' => true,
            'validacion' => $resultado,
        ]);
        exit;
    }

    if ($action === 'importar') {
        $userId = (int) (Auth::id() ?? 0);
        $result = ImportacionMasivaService::procesarImportacion($pdo, $torneo_id, $filas, $userId);
        $payload = [
            'success' => true,
            'procesados' => $result['procesados'],
            'nuevos' => $result['nuevos'],
            'omitidos' => $result['omitidos'],
            'errores' => $result['errores'],
        ];
        if ($result['csv_errores'] !== '') {
            $payload['archivo_errores_base64'] = base64_encode($result['csv_errores']);
        }
        echo json_encode($payload);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Acción no válida']);
} catch (Throwable $e) {
    error_log('tournament_import_masivo: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error interno: ' . $e->getMessage(),
    ]);
}
