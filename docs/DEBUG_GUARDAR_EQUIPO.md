# Cómo Debuggear el Guardado de Equipos

## Logs Agregados

He agregado logs detallados en cada paso del proceso de guardado. Los logs aparecerán en:

1. **Consola del navegador (JavaScript)**: F12 → Console
2. **Archivo de logs de PHP**: `C:\wamp64\logs\php_error.log` (o el configurado en php.ini)

## Pasos para Identificar el Error

### 1. Abrir Consola del Navegador
- Presiona **F12** en tu navegador
- Ve a la pestaña **Console**

### 2. Intentar Guardar un Equipo
- Llena el formulario
- Haz clic en "Guardar Equipo"
- Observa los logs en la consola

### 3. Revisar Logs de PHP
Abre el archivo de logs de PHP para ver los logs del servidor:
```
C:\wamp64\logs\php_error.log
```

O verifica dónde está configurado en `php.ini`:
```ini
error_log = C:\wamp64\logs\php_error.log
```

## Qué Buscar en los Logs

### JavaScript (Consola del Navegador)
```
=== INICIO GUARDAR EQUIPO (JavaScript) ===
Datos del equipo: {equipo_id: "...", torneo_id: "...", nombre_equipo: "...", club_id: "..."}
Jugador 1 (posición 1): {cedula: "...", nombre: "...", id_inscrito: "...", id_usuario: "..."}
Total de jugadores a enviar: X
Enviando datos al servidor...
Respuesta recibida, status: 200
Datos de respuesta: {success: true/false, message: "..."}
```

### PHP (Archivo de Logs)
```
=== INICIO GUARDAR EQUIPO ===
POST recibido: {...}
PASO 1: Datos extraídos - torneo_id=X, equipo_id=X, nombre_equipo=..., club_id=X
PASO 1: Jugadores recibidos: X jugadores
PASO 2: Usuario autenticado - user_id=X
PASO 3: Transacción iniciada
PASO 4: Verificando si es equipo nuevo o existente
PASO 4B: Creando nuevo equipo
EquiposHelper::crearEquipo - INICIO
...
PASO 5: Iniciando procesamiento de jugadores
PASO 5.1: Procesando jugador - {...}
PASO 5.2: Actualizando inscrito...
PASO 6: Commit de transacción
=== ÉXITO: Equipo guardado correctamente ===
```

## Puntos de Falla Comunes

### 1. Validación de Datos (PASO 1)
```
ERROR: Datos incompletos - torneo_id=X, nombre_equipo='...', club_id=X
```
**Solución**: Verificar que todos los campos requeridos estén llenos

### 2. Error en Crear Equipo (PASO 4B / EquiposHelper)
```
EquiposHelper::crearEquipo - ERROR: ...
```
**Posibles causas**:
- Torneo no encontrado
- Modalidad incorrecta
- Equipo duplicado
- Error SQL en inserción

### 3. Error al Buscar Usuario (PASO 5.X)
```
ERROR: No se pudo determinar el ID de usuario para la cédula X
```
**Solución**: Verificar que el jugador exista en la tabla `usuarios`

### 4. Error al Insertar/Actualizar Inscrito (PASO 5.X)
```
ERROR en PASO 5.X al crear/actualizar inscrito: ...
SQL Error Info: {...}
```
**Posibles causas**:
- Campo faltante en tabla `inscritos`
- Tipo de dato incorrecto
- Restricción de clave foránea
- Valor NULL en campo NOT NULL

### 5. Error de Transacción
```
ERROR en transacción: ...
=== ROLLBACK: Transacción revertida ===
```
**Causa**: Cualquier error hace rollback de todos los cambios

## Ejemplo de Error Común

Si ves esto:
```
EquiposHelper::crearEquipo - ERROR en inserción directa: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'campo_x' in 'field list'
```

Significa que falta un campo en la tabla `equipos`. Solución: Verificar estructura de la tabla.

## Verificar Estructura de Tablas

Ejecuta estas consultas para verificar que las tablas tengan todos los campos necesarios:

```sql
DESCRIBE equipos;
DESCRIBE inscritos;
DESCRIBE usuarios;
```

## Próximos Pasos

1. **Ejecuta el guardado** y copia todos los logs (tanto JavaScript como PHP)
2. **Comparte los logs** para identificar exactamente dónde falla
3. **Revisa la estructura de las tablas** si hay errores SQL








