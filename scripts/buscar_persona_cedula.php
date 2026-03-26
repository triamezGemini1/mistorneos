<?php
/**
 * Consulta una persona en la base de datos externa (dbo_persona / dbo_personas)
 * por cédula (y opcionalmente nacionalidad).
 *
 * Uso: php scripts/buscar_persona_cedula.php <cedula> [nacionalidad]
 * Ejemplo: php scripts/buscar_persona_cedula.php 5608138
 *          php scripts/buscar_persona_cedula.php 5608138 V
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

$cedula = isset($argv[1]) ? trim($argv[1]) : '';
$nacionalidad = isset($argv[2]) ? strtoupper(trim($argv[2])) : 'V';

if ($cedula === '') {
    echo "Uso: php scripts/buscar_persona_cedula.php <cedula> [nacionalidad]\n";
    echo "Ejemplo: php scripts/buscar_persona_cedula.php 5608138 V\n";
    exit(1);
}

if (!in_array($nacionalidad, ['V', 'E', 'J', 'P'], true)) {
    $nacionalidad = 'V';
}

// Solo dígitos para la búsqueda externa
$cedulaNum = preg_replace('/\D/', '', $cedula);
if ($cedulaNum === '') {
    echo "Error: la cédula debe contener al menos un dígito.\n";
    exit(1);
}

echo "=== Búsqueda en BD externa (dbo_persona) ===\n\n";
echo "Cédula (numérica): {$cedulaNum}\n";
echo "Nacionalidad: {$nacionalidad}\n\n";

if (!file_exists(__DIR__ . '/../config/persona_database.php')) {
    echo "Error: No existe config/persona_database.php\n";
    exit(1);
}

require_once __DIR__ . '/../config/persona_database.php';

try {
    $database = new PersonaDatabase();
    if (!$database->isEnabled()) {
        echo "La búsqueda externa está deshabilitada o la BD no está disponible.\n";
        exit(1);
    }
    $result = $database->searchPersonaById($nacionalidad, $cedulaNum);
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "--- Resultado ---\n";
if (isset($result['encontrado']) && $result['encontrado'] && isset($result['persona'])) {
    $p = $result['persona'];
    echo "Encontrado: SÍ\n";
    echo "Cédula (guardada en usuarios): " . ($p['cedula'] ?? '') . "\n";
    echo "Nacionalidad (guardada en usuarios): " . ($p['nacionalidad'] ?? '') . "\n";
    echo "Nombre completo: " . ($p['nombre'] ?? '') . "\n";
    echo "Sexo (normalizado M/F/O): " . ($p['sexo'] ?? '') . "\n";
    echo "Fecha nacimiento: " . ($p['fechnac'] ?? '') . "\n";
    echo "Celular: " . ($p['celular'] ?? '') . "\n";
    echo "\nEstos son los valores que se usarían al guardar en la tabla usuarios.\n";
} else {
    echo "Encontrado: NO\n";
    echo "Mensaje: " . ($result['error'] ?? 'No se encontró persona con esa cédula') . "\n";
}

echo "\n";
