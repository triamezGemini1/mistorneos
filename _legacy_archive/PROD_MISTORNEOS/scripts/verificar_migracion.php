<?php
/**
 * Script para verificar que la migración a producción se ejecutó correctamente
 * Ejecutar después de subir los archivos y ejecutar el SQL
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

echo "=== VERIFICACIÓN DE MIGRACIÓN A PRODUCCIÓN ===\n\n";

$errores = [];
$exitos = [];

try {
    $pdo = DB::pdo();
    
    // 1. Verificar columna es_evento_masivo
    echo "1. Verificando columna es_evento_masivo en tournaments...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM tournaments LIKE 'es_evento_masivo'");
    if ($stmt->rowCount() > 0) {
        echo "   ✓ Columna es_evento_masivo existe\n";
        $exitos[] = "Columna es_evento_masivo";
    } else {
        echo "   ✗ Columna es_evento_masivo NO existe\n";
        $errores[] = "Columna es_evento_masivo no encontrada";
    }
    
    // 2. Verificar columna cuenta_id
    echo "2. Verificando columna cuenta_id en tournaments...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM tournaments LIKE 'cuenta_id'");
    if ($stmt->rowCount() > 0) {
        echo "   ✓ Columna cuenta_id existe\n";
        $exitos[] = "Columna cuenta_id";
    } else {
        echo "   ✗ Columna cuenta_id NO existe\n";
        $errores[] = "Columna cuenta_id no encontrada";
    }
    
    // 3. Verificar tabla cuentas_bancarias
    echo "3. Verificando tabla cuentas_bancarias...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'cuentas_bancarias'");
    if ($stmt->rowCount() > 0) {
        echo "   ✓ Tabla cuentas_bancarias existe\n";
        $exitos[] = "Tabla cuentas_bancarias";
        
        // Verificar estructura
        $stmt = $pdo->query("DESCRIBE cuentas_bancarias");
        $columnas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $columnas_requeridas = ['id', 'cedula_propietario', 'nombre_propietario', 'banco', 'estatus'];
        foreach ($columnas_requeridas as $col) {
            if (in_array($col, $columnas)) {
                echo "   ✓ Columna $col existe\n";
            } else {
                echo "   ✗ Columna $col NO existe\n";
                $errores[] = "Columna $col faltante en cuentas_bancarias";
            }
        }
    } else {
        echo "   ✗ Tabla cuentas_bancarias NO existe\n";
        $errores[] = "Tabla cuentas_bancarias no encontrada";
    }
    
    // 4. Verificar tabla reportes_pago_usuarios
    echo "4. Verificando tabla reportes_pago_usuarios...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'reportes_pago_usuarios'");
    if ($stmt->rowCount() > 0) {
        echo "   ✓ Tabla reportes_pago_usuarios existe\n";
        $exitos[] = "Tabla reportes_pago_usuarios";
        
        // Verificar estructura
        $stmt = $pdo->query("DESCRIBE reportes_pago_usuarios");
        $columnas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $columnas_requeridas = ['id', 'id_usuario', 'torneo_id', 'cuenta_id', 'fecha', 'hora', 'tipo_pago', 'monto', 'estatus'];
        foreach ($columnas_requeridas as $col) {
            if (in_array($col, $columnas)) {
                echo "   ✓ Columna $col existe\n";
            } else {
                echo "   ✗ Columna $col NO existe\n";
                $errores[] = "Columna $col faltante en reportes_pago_usuarios";
            }
        }
    } else {
        echo "   ✗ Tabla reportes_pago_usuarios NO existe\n";
        $errores[] = "Tabla reportes_pago_usuarios no encontrada";
    }
    
    // 5. Verificar índices
    echo "5. Verificando índices...\n";
    $stmt = $pdo->query("SHOW INDEX FROM tournaments WHERE Key_name = 'idx_es_evento_masivo'");
    if ($stmt->rowCount() > 0) {
        echo "   ✓ Índice idx_es_evento_masivo existe\n";
        $exitos[] = "Índice idx_es_evento_masivo";
    } else {
        echo "   ✗ Índice idx_es_evento_masivo NO existe\n";
        $errores[] = "Índice idx_es_evento_masivo no encontrado";
    }
    
    $stmt = $pdo->query("SHOW INDEX FROM tournaments WHERE Key_name = 'idx_cuenta_id'");
    if ($stmt->rowCount() > 0) {
        echo "   ✓ Índice idx_cuenta_id existe\n";
        $exitos[] = "Índice idx_cuenta_id";
    } else {
        echo "   ✗ Índice idx_cuenta_id NO existe\n";
        $errores[] = "Índice idx_cuenta_id no encontrado";
    }
    
    // 6. Verificar foreign keys
    echo "6. Verificando foreign keys...\n";
    $stmt = $pdo->query("
        SELECT CONSTRAINT_NAME 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'tournaments' 
        AND CONSTRAINT_NAME = 'fk_tournaments_cuenta'
    ");
    if ($stmt->rowCount() > 0) {
        echo "   ✓ Foreign key fk_tournaments_cuenta existe\n";
        $exitos[] = "Foreign key fk_tournaments_cuenta";
    } else {
        echo "   ✗ Foreign key fk_tournaments_cuenta NO existe\n";
        $errores[] = "Foreign key fk_tournaments_cuenta no encontrada";
    }
    
    // 7. Verificar archivos importantes
    echo "\n7. Verificando archivos importantes...\n";
    $archivos = [
        'public/inscribir_evento_masivo.php',
        'public/reportar_pago_evento_masivo.php',
        'public/ver_recibo_pago.php',
        'modules/cuentas_bancarias.php',
        'modules/reportes_pago_usuarios.php',
        'manuales_web/manual_usuario.php',
        'manuales_web/admin_club_resumido.html',
        'lib/BankValidator.php'
    ];
    
    foreach ($archivos as $archivo) {
        $ruta = __DIR__ . '/../' . $archivo;
        if (file_exists($ruta)) {
            echo "   ✓ $archivo existe\n";
            $exitos[] = "Archivo $archivo";
        } else {
            echo "   ✗ $archivo NO existe\n";
            $errores[] = "Archivo $archivo no encontrado";
        }
    }
    
    // Resumen
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "RESUMEN DE VERIFICACIÓN\n";
    echo str_repeat("=", 60) . "\n";
    echo "✓ Exitosos: " . count($exitos) . "\n";
    echo "✗ Errores: " . count($errores) . "\n\n";
    
    if (count($errores) > 0) {
        echo "ERRORES ENCONTRADOS:\n";
        foreach ($errores as $error) {
            echo "  - $error\n";
        }
        echo "\n⚠️  Por favor, corrige estos errores antes de continuar.\n";
        exit(1);
    } else {
        echo "✅ ¡Todas las verificaciones pasaron exitosamente!\n";
        echo "La migración se completó correctamente.\n";
        exit(0);
    }
    
} catch (Exception $e) {
    echo "\n✗ ERROR CRÍTICO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

