<?php
/**
 * Script para actualizar el campo sexo de los usuarios desde la base de datos externa persona
 * 
 * Uso: php scripts/update_users_sexo_from_persona.php
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/persona_database.php';

echo "=== ACTUALIZACIÓN DE SEXO DE USUARIOS DESDE BD PERSONA ===\n\n";

try {
    // 1. Conectar a la base de datos principal
    $pdo = DB::pdo();
    echo "✓ Conectado a la base de datos principal\n";
    
    // 2. Verificar que la columna sexo existe
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'sexo'");
    if (!$stmt->fetch()) {
        die("❌ ERROR: La columna 'sexo' no existe en la tabla users.\nPor favor ejecuta primero: sql/add_sexo_to_users.sql\n");
    }
    echo "✓ Columna 'sexo' existe en la tabla users\n";
    
    // 3. Conectar a la base de datos persona
    $personaDb = new PersonaDatabase();
    $personaConn = $personaDb->getConnection();
    
    if (!$personaConn) {
        die("❌ ERROR: No se pudo conectar a la base de datos persona\n");
    }
    echo "✓ Conectado a la base de datos persona\n\n";
    
    // 4. Obtener todos los usuarios que no tienen sexo asignado o tienen NULL
    $stmt = $pdo->query("
        SELECT id, cedula, nombre 
        FROM users 
        WHERE (sexo IS NULL OR sexo = '') 
        AND cedula IS NOT NULL 
        AND cedula != '' 
        AND cedula != '0'
        ORDER BY id
    ");
    
    $usuarios_sin_sexo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Usuarios sin sexo asignado: " . count($usuarios_sin_sexo) . "\n\n";
    
    if (empty($usuarios_sin_sexo)) {
        echo "✓ Todos los usuarios ya tienen sexo asignado.\n";
        exit(0);
    }
    
    // 5. Actualizar usuarios
    $actualizados = 0;
    $no_encontrados = 0;
    $errores = 0;
    
    echo "Actualizando usuarios...\n";
    echo str_repeat("-", 80) . "\n";
    
    $pdo->beginTransaction();
    
    try {
        foreach ($usuarios_sin_sexo as $index => $usuario) {
            $cedula_completa = $usuario['cedula'];
            
            // Extraer nacionalidad y número de cédula
            // La cédula puede estar en formato "V12345678" o solo "12345678"
            $nacionalidad = 'V'; // Por defecto
            $cedula_numero = $cedula_completa;
            
            if (preg_match('/^([VEJP])(\d+)$/i', $cedula_completa, $matches)) {
                $nacionalidad = strtoupper($matches[1]);
                $cedula_numero = $matches[2];
            }
            
            // Buscar en la base de datos persona
            $query = "SELECT Sexo FROM dpersona WHERE IDUsuario = ? AND Nac = ? LIMIT 1";
            $stmt = $personaConn->prepare($query);
            $stmt->execute([$cedula_numero, $nacionalidad]);
            $persona = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($persona && !empty($persona['Sexo'])) {
                // Normalizar el sexo
                $sexo = strtoupper(trim($persona['Sexo']));
                
                // Convertir a formato estándar (M, F, O)
                if ($sexo === 'M' || $sexo === '1' || $sexo === 'MASCULINO' || $sexo === 'MALE') {
                    $sexo_final = 'M';
                } elseif ($sexo === 'F' || $sexo === '2' || $sexo === 'FEMENINO' || $sexo === 'FEMALE') {
                    $sexo_final = 'F';
                } else {
                    $sexo_final = 'O';
                }
                
                // Actualizar usuario
                $update_stmt = $pdo->prepare("UPDATE users SET sexo = ? WHERE id = ?");
                $result = $update_stmt->execute([$sexo_final, $usuario['id']]);
                
                if ($result) {
                    $actualizados++;
                    echo sprintf(
                        "[%3d/%3d] ✓ Usuario actualizado: %s (%s) - Sexo: %s\n",
                        $actualizados,
                        count($usuarios_sin_sexo),
                        $usuario['nombre'],
                        $cedula_completa,
                        $sexo_final
                    );
                } else {
                    $errores++;
                    echo sprintf(
                        "[%3d/%3d] ✗ Error al actualizar: %s (%s)\n",
                        $index + 1,
                        count($usuarios_sin_sexo),
                        $usuario['nombre'],
                        $cedula_completa
                    );
                }
            } else {
                $no_encontrados++;
                echo sprintf(
                    "[%3d/%3d] ⚠ No encontrado en BD persona: %s (%s)\n",
                    $index + 1,
                    count($usuarios_sin_sexo),
                    $usuario['nombre'],
                    $cedula_completa
                );
            }
        }
        
        $pdo->commit();
        
        echo str_repeat("-", 80) . "\n";
        echo "\n=== RESUMEN ===\n";
        echo "✓ Usuarios actualizados: $actualizados\n";
        echo "⚠ Usuarios no encontrados en BD persona: $no_encontrados\n";
        if ($errores > 0) {
            echo "✗ Errores: $errores\n";
        }
        echo "\n✓ Proceso completado exitosamente!\n";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}


