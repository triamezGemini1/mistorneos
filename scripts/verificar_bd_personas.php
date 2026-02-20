<?php
/**
 * Script para verificar la conexión a la base de datos personas y la tabla dbo_persona.
 * Ejecutar: php scripts/verificar_bd_personas.php
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/persona_database.php';

echo "═══════════════════════════════════════════════════════════════\n";
echo "  Verificación: BD personas / tabla dbo_persona\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$personaDb = new PersonaDatabase();
$conn = $personaDb->getConnection();

if (!$conn) {
    echo "❌ ERROR: No se pudo conectar a la base de datos de personas.\n";
    echo "\nVerifique:\n";
    echo "  - Que la BD 'personas' exista en MySQL\n";
    echo "  - Credenciales en config/config.development.php (persona_db)\n";
    echo "  - Que el servicio MySQL esté activo\n";
    exit(1);
}

echo "✅ Conexión establecida\n\n";

// Detectar nombre de BD y tabla en uso (usando reflexión o prueba directa)
$config = $GLOBALS['APP_CONFIG']['persona_db'] ?? null;
$env = class_exists('Environment') ? Environment::get() : 'development';
$db_name = $env === 'production' ? ($config['name'] ?? 'laestaci1_fvdadmin') : ($config['name_dev'] ?? $config['name'] ?? 'personas');
$table_candidates = ['dbo_persona', 'dbo.persona', 'persona'];

echo "  Base de datos esperada: $db_name\n";
echo "  Tablas candidatas: " . implode(', ', $table_candidates) . "\n\n";

$tabla_encontrada = null;

foreach ($table_candidates as $table) {
    try {
        $stmt = $conn->query("SELECT COUNT(*) FROM `$table` LIMIT 1");
        $count = $stmt->fetchColumn();
        $tabla_encontrada = $table;
        echo "✅ Tabla '$table' encontrada. Registros: " . number_format($count) . "\n";
        break;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), '42S02') !== false) {
            echo "  - Tabla '$table': no existe\n";
        } else {
            echo "  - Tabla '$table': " . $e->getMessage() . "\n";
        }
    }
}

if (!$tabla_encontrada) {
    echo "\n❌ Ninguna tabla de personas encontrada.\n";
    echo "\nCree la BD y tabla, o importe datos. Ejemplo:\n";
    echo "  CREATE DATABASE IF NOT EXISTS personas;\n";
    echo "  USE personas;\n";
    echo "  CREATE TABLE dbo_persona (\n";
    echo "    IDUsuario VARCHAR(20),\n";
    echo "    Nac VARCHAR(2),\n";
    echo "    Nombre1 VARCHAR(50),\n";
    echo "    Nombre2 VARCHAR(50),\n";
    echo "    Apellido1 VARCHAR(50),\n";
    echo "    Apellido2 VARCHAR(50),\n";
    echo "    FNac DATE,\n";
    echo "    Sexo VARCHAR(1)\n";
    echo "  );\n";
    exit(1);
}

// Mostrar columnas de la tabla
echo "\n  Columnas de la tabla:\n";
try {
    $stmt = $conn->query("SHOW COLUMNS FROM `$tabla_encontrada`");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $required = ['IDUsuario', 'Nac', 'Nombre1', 'Apellido1', 'FNac', 'Sexo'];
    foreach ($cols as $c) {
        $nombre = $c['Field'] ?? $c['field'] ?? '';
        $req = in_array($nombre, $required) ? ' ✓' : '';
        echo "    - $nombre ({$c['Type']})$req\n";
    }
    $existing = array_column($cols, 'Field');
    $missing = array_diff($required, $existing);
    if (!empty($missing)) {
        echo "\n  ⚠️  Columnas esperadas faltantes: " . implode(', ', $missing) . "\n";
    }
} catch (PDOException $e) {
    echo "  No se pudieron listar columnas: " . $e->getMessage() . "\n";
}

// Prueba de getRandomPersonasForSeed
echo "\n  Prueba getRandomPersonasForSeed(3):\n";
PersonaDatabase::clearConnectionPool();
PersonaDatabase::resetAvailability();
$personaDb2 = new PersonaDatabase();
$personas = $personaDb2->getRandomPersonasForSeed(3);
if (empty($personas)) {
    echo "  ❌ No se obtuvieron personas (getRandomPersonasForSeed)\n";
} else {
    foreach ($personas as $i => $p) {
        $cedula = ($p['nac'] ?? '') . ($p['id_usuario'] ?? '');
        echo "    " . ($i + 1) . ". " . ($p['nombre'] ?? 'N/A') . " | Cédula: $cedula\n";
    }
    echo "  ✅ Método getRandomPersonasForSeed OK\n";
}

echo "\n═══════════════════════════════════════════════════════════════\n";
echo "  Verificación completada\n";
echo "═══════════════════════════════════════════════════════════════\n";
