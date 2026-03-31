# Solución: "No encontrado. Complete los datos manualmente" en Producción

## Problema
En producción, al solicitar afiliación, aparece el mensaje "No encontrado. Complete los datos manualmente." aunque la persona existe en la base de datos externa.

## Causa
La búsqueda en la base de datos externa `laestaci1_fvdadmin` (tabla `dbo.persona`) no está funcionando correctamente en producción debido a:

1. **Nombre de tabla incorrecto**: El código estaba usando `dbo_persona_staging` (desarrollo) en lugar de `dbo.persona` (producción)
2. **Referencia de tabla**: En MySQL, si la tabla tiene un punto en el nombre, necesita backticks: `` `dbo.persona` ``
3. **Detección de entorno**: Necesita detectar correctamente si está en producción

## Solución Aplicada

### 1. Detección Automática de Entorno
El código ahora detecta automáticamente si está en producción y usa la tabla correcta:

```php
$is_production = class_exists('Environment') ? Environment::isProduction() : false;
$table_name = $is_production ? '`dbo.persona`' : 'dbo_persona_staging';
```

### 2. Fallback para Nombre de Tabla
Si no encuentra con `` `dbo.persona` ``, intenta con `persona` (sin el prefijo `dbo.`):

```php
if (!$persona && $is_production && $table_name === '`dbo.persona`') {
    // Intentar con 'persona' sin backticks
    $query_fallback = "SELECT ... FROM persona WHERE ...";
}
```

### 3. Logging para Debugging
Se agregó logging para facilitar el diagnóstico:

```php
error_log("PersonaDatabase: Buscando en tabla {$table_name} - Nac={$nacionalidad}, Cedula={$cedula}");
```

## Verificación

### 1. Verificar Configuración de Base de Datos
En `config/config.production.php`, verificar que esté configurado:

```php
'persona_db' => [
    'host' => 'localhost',
    'port' => 3306,
    'name' => 'laestaci1_fvdadmin',
    'user' => 'laestaci1_user',  // ← Usuario real
    'pass' => 'CONTRASEÑA_REAL', // ← Contraseña real
    'charset' => 'utf8mb4'
],
```

### 2. Verificar Nombre Real de la Tabla
En el servidor de producción, verificar el nombre exacto de la tabla:

```sql
USE laestaci1_fvdadmin;
SHOW TABLES LIKE '%persona%';
```

Posibles nombres:
- `` `dbo.persona` `` (con punto y backticks)
- `persona` (sin prefijo)
- `dbo_persona` (con guión bajo)

### 3. Verificar Estructura de la Tabla
Verificar que la tabla tenga los campos correctos:

```sql
DESCRIBE `dbo.persona`;
-- o
DESCRIBE persona;
```

Campos requeridos:
- `IDUsuario` (cédula)
- `Nac` (nacionalidad: V, E, J, P)
- `Nombre1`, `Nombre2`, `Apellido1`, `Apellido2`
- `FNac` (fecha de nacimiento)
- `Sexo` (género)

### 4. Probar Búsqueda Manual
Probar directamente en MySQL:

```sql
SELECT Nombre1, Nombre2, Apellido1, Apellido2, FNac, Sexo
FROM `dbo.persona`
WHERE IDUsuario = '12345678' AND Nac = 'V'
LIMIT 1;
```

Si funciona con backticks, el código debería funcionar.
Si no funciona, probar sin backticks:

```sql
SELECT Nombre1, Nombre2, Apellido1, Apellido2, FNac, Sexo
FROM persona
WHERE IDUsuario = '12345678' AND Nac = 'V'
LIMIT 1;
```

## Debugging

### Habilitar Logs
En producción, los logs se guardan automáticamente. Revisar:

1. **Logs de PHP**: `storage/logs/` o logs del servidor
2. **Logs de error**: Buscar mensajes que contengan "PersonaDatabase"

### Mensajes de Log Esperados
```
PersonaDatabase: Buscando en tabla `dbo.persona` - Nac=V, Cedula=12345678
PersonaDatabase: No encontrado en `dbo.persona`, intentando con 'persona'
```

### Si Aún No Funciona

1. **Verificar conexión a base de datos**:
   - Probar conexión manual con las credenciales
   - Verificar que el usuario tenga permisos de SELECT

2. **Verificar formato de cédula**:
   - La cédula debe ser solo números (sin nacionalidad)
   - La nacionalidad se envía por separado

3. **Verificar tipo de datos**:
   - `IDUsuario` puede ser VARCHAR o INT
   - El código usa `PDO::PARAM_STR` para mayor compatibilidad

4. **Probar endpoint directamente**:
   ```
   https://laestaciondeldominohoy.com/mistorneos/public/api/search_user_persona.php?cedula=12345678&nacionalidad=V
   ```

## Corrección Manual (Si es Necesario)

Si el nombre de la tabla es diferente, editar `config/persona_database.php` línea 173:

```php
// Cambiar según el nombre real de la tabla
$table_name = 'nombre_real_de_la_tabla';
```

O crear un archivo de configuración específico para producción que sobrescriba el nombre de la tabla.

## Contacto

Si el problema persiste después de verificar estos puntos:
1. Revisar logs detallados del servidor
2. Verificar nombre exacto de la tabla en producción
3. Probar consulta SQL manual con las credenciales de producción




