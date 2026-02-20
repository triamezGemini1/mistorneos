<?php
/**
 * API: Buscar persona por cédula
 * Usa la tabla dbo.persona en la base de datos fvdadmin
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/bootstrap.php';

$cedula = trim($_GET['cedula'] ?? '');
$nacionalidad = strtoupper(trim($_GET['nacionalidad'] ?? 'V'));

if (empty($cedula)) {
    echo json_encode(['success' => false, 'error' => 'Cédula requerida']);
    exit;
}

try {
    // Usar PersonaDatabase para consistencia
    require_once __DIR__ . '/../config/persona_database.php';
    
    $database = new PersonaDatabase();
    $result = $database->searchPersonaById($nacionalidad, $cedula);
    
    if (isset($result['encontrado']) && $result['encontrado']) {
        echo json_encode([
            'success' => true,
            'persona' => $result['persona']
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'error' => $result['error'] ?? 'Persona no encontrada'
        ]);
    }

} catch (Exception $e) {
    error_log("api/search_person.php - Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error en la búsqueda']);
}
?>
