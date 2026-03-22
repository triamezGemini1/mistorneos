<?php
/**
 * API: Estadísticas de torneo
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/Api/JsonResponse.php';

use Lib\Api\JsonResponse;

$torneo_id = (int)($_GET['torneo_id'] ?? 0);

if (!$torneo_id) {
    JsonResponse::validationError(['torneo_id' => 'ID de torneo requerido']);
}

try {
    $pdo = DB::pdo();

    // Total inscritos
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscripciones WHERE torneo_id = ?");
    $stmt->execute([$torneo_id]);
    $total_inscritos = (int) $stmt->fetchColumn();

    // Por sexo
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscripciones WHERE torneo_id = ? AND (sexo = 'M' OR sexo = 1)");
    $stmt->execute([$torneo_id]);
    $hombres = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscripciones WHERE torneo_id = ? AND (sexo = 'F' OR sexo = 2)");
    $stmt->execute([$torneo_id]);
    $mujeres = (int) $stmt->fetchColumn();

    // Siguiente identificador
    $stmt = $pdo->prepare("SELECT MAX(identificador) FROM inscripciones WHERE torneo_id = ?");
    $stmt->execute([$torneo_id]);
    $max_id = $stmt->fetchColumn();
    $siguiente_id = $max_id ? ((int)$max_id + 1) : 1;

    // Clubes participantes
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT club_id) FROM inscripciones WHERE torneo_id = ? AND club_id IS NOT NULL");
    $stmt->execute([$torneo_id]);
    $clubes = (int) $stmt->fetchColumn();

    JsonResponse::success([
        'total' => $total_inscritos,
        'hombres' => $hombres,
        'mujeres' => $mujeres,
        'clubes' => $clubes,
        'siguiente_id' => $siguiente_id
    ]);

} catch (Exception $e) {
    if (Env::bool('APP_DEBUG')) {
        JsonResponse::serverError('Error: ' . $e->getMessage());
    } else {
        JsonResponse::serverError();
    }
}
