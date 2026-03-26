# Solución: Error de Permisos en Base de Datos Externa

## Error
```
Access denied for user 'laestaci1_soloyo'@'localhost' to database 'laestaci1_fvdadmin'
```

## Causa
El usuario de MySQL `laestaci1_soloyo` no tiene permisos para acceder a la base de datos `laestaci1_fvdadmin`.

## Soluciones

### Opción 1: Otorgar Permisos al Usuario (Recomendado)

Conectarse a MySQL como usuario administrador (root o el usuario principal) y ejecutar:

```sql
-- Otorgar todos los permisos sobre la base de datos
GRANT ALL PRIVILEGES ON laestaci1_fvdadmin.* TO 'laestaci1_soloyo'@'localhost';

-- O solo permisos de lectura (más seguro)
GRANT SELECT ON laestaci1_fvdadmin.* TO 'laestaci1_soloyo'@'localhost';

-- Aplicar los cambios
FLUSH PRIVILEGES;
```

### Opción 2: Verificar Nombre Correcto de la Base de Datos

Es posible que la base de datos tenga un nombre diferente. Verificar:

```sql
-- Listar todas las bases de datos disponibles
SHOW DATABASES;

-- Verificar permisos del usuario
SHOW GRANTS FOR 'laestaci1_soloyo'@'localhost';
```

### Opción 3: Usar Usuario Diferente

Si el usuario `laestaci1_soloyo` no puede tener permisos, usar otro usuario que sí tenga acceso:

1. Editar `config/config.production.php`:
```php
'persona_db' => [
    'host' => 'localhost',
    'port' => 3306,
    'name' => 'laestaci1_fvdadmin',
    'user' => 'OTRO_USUARIO_CON_PERMISOS',  // ← Cambiar aquí
    'pass' => 'CONTRASEÑA_DEL_USUARIO',     // ← Cambiar aquí
    'charset' => 'utf8mb4'
],
```

### Opción 4: Verificar que la Base de Datos Exista

```sql
-- Verificar si la base de datos existe
SHOW DATABASES LIKE 'laestaci1_fvdadmin';

-- Si no existe, puede que tenga otro nombre
SHOW DATABASES LIKE '%fvdadmin%';
SHOW DATABASES LIKE '%persona%';
```

## Verificación Post-Corrección

Después de aplicar los permisos, probar la conexión:

```sql
-- Conectarse con el usuario
mysql -u laestaci1_soloyo -p laestaci1_fvdadmin

-- Probar una consulta
SELECT COUNT(*) FROM persona;
-- o
SELECT COUNT(*) FROM `dbo.persona`;
```

## Script de Verificación

Crear un archivo temporal `public/test_db_connection.php`:

```php
<?php
require_once __DIR__ . '/../config/bootstrap.php';

$config = $GLOBALS['APP_CONFIG']['persona_db'] ?? null;

if ($config) {
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['user'], $config['pass']);
        echo "✓ Conexión exitosa a {$config['name']}";
        
        // Probar consulta
        $stmt = $pdo->query("SHOW TABLES LIKE '%persona%'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<br>Tablas encontradas: " . implode(', ', $tables);
    } catch (PDOException $e) {
        echo "✗ Error: " . $e->getMessage();
    }
}
?>
```

## Notas Importantes

1. **Seguridad**: Si solo se necesita leer datos, otorgar solo permisos `SELECT` en lugar de `ALL PRIVILEGES`
2. **Host**: Verificar si el usuario debe ser `'localhost'` o `'%'` (cualquier host)
3. **Base de Datos Principal**: Verificar que el usuario tenga acceso también a la base de datos principal si es la misma

## Contacto con el Proveedor de Hosting

Si no tienes acceso de administrador a MySQL, contactar al proveedor de hosting para:
- Otorgar permisos al usuario `laestaci1_soloyo` sobre `laestaci1_fvdadmin`
- O proporcionar las credenciales de un usuario que sí tenga acceso
- O verificar el nombre correcto de la base de datos




