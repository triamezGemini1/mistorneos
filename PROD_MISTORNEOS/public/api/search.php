<?php

session_start();

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

// Verificar autenticaci�n
$user = Auth::user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Obtener t�rmino de b�squeda
$search_term = $_GET['q'] ?? '';
$search_term = trim($search_term);

if (empty($search_term) || strlen($search_term) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$results = [];

try {
    // Buscar en clubs
    $stmt = DB::pdo()->prepare("
        SELECT 'club' as type, id, nombre as title, delegado as subtitle, 'index.php?page=clubs&action=edit&id=' as url
        FROM clubes 
        WHERE nombre LIKE ? OR delegado LIKE ? OR telefono LIKE ?
        LIMIT 5
    ");
    $search_param = "%$search_term%";
    $stmt->execute([$search_param, $search_param, $search_param]);
    $club_results = $stmt->fetchAll();
    
    foreach ($club_results as $result) {
        $results[] = [
            'type' => 'club',
            'icon' => 'fas fa-building',
            'title' => $result['title'],
            'subtitle' => $result['subtitle'],
            'url' => $result['url'] . $result['id'],
            'badge' => 'Club'
        ];
    }
    
    // Buscar en torneos
    $stmt = DB::pdo()->prepare("
        SELECT 'tournament' as type, t.id, t.nombre as title, c.nombre as subtitle, 'index.php?page=tournaments&action=edit&id=' as url
        FROM tournaments t
        LEFT JOIN clubes c ON t.club_responsable = c.id
        WHERE t.nombre LIKE ?
        LIMIT 5
    ");
    $stmt->execute([$search_param]);
    $tournament_results = $stmt->fetchAll();
    
    foreach ($tournament_results as $result) {
        $results[] = [
            'type' => 'tournament',
            'icon' => 'fas fa-trophy',
            'title' => $result['title'],
            'subtitle' => 'Club: ' . ($result['subtitle'] ?? 'N/A'),
            'url' => $result['url'] . $result['id'],
            'badge' => 'Torneo'
        ];
    }
    
    // Buscar en inscritos
    $stmt = DB::pdo()->prepare("
        SELECT 'registrant' as type, r.id, r.nombre as title, t.nombre as subtitle, 'index.php?page=registrants&action=edit&id=' as url
        FROM inscripciones r
        LEFT JOIN tournaments t ON r.torneo_id = t.id
        WHERE r.nombre LIKE ? OR r.cedula LIKE ?
        LIMIT 5
    ");
    $stmt->execute([$search_param, $search_param]);
    $registrant_results = $stmt->fetchAll();
    
    foreach ($registrant_results as $result) {
        $results[] = [
            'type' => 'registrant',
            'icon' => 'fas fa-user',
            'title' => $result['title'],
            'subtitle' => 'Torneo: ' . ($result['subtitle'] ?? 'N/A'),
            'url' => $result['url'] . $result['id'],
            'badge' => 'Inscrito'
        ];
    }
    
    // Limitar resultados totales
    $results = array_slice($results, 0, 10);
    
} catch (Exception $e) {
    error_log("Error en b�squeda: " . $e->getMessage());
    $results = [];
}

header('Content-Type: application/json');
echo json_encode([
    'results' => $results,
    'total' => count($results),
    'query' => $search_term
]);
