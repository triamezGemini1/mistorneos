# Instrucciones para Debuggear el Error de Guardado de Equipos

## Estado Actual

✅ **Logs de PHP borrados** - Están listos para capturar nuevos errores
✅ **Código mejorado** - Ahora captura todos los errores y los convierte a JSON
✅ **JavaScript mejorado** - Muestra en consola exactamente qué recibe del servidor

## Pasos para Debuggear

### Paso 1: Abrir Consola del Navegador

1. Abre tu navegador
2. Presiona **F12** para abrir las herramientas de desarrollador
3. Ve a la pestaña **Console**
4. Mantén la consola abierta

### Paso 2: Intentar Guardar un Equipo

1. Ve al formulario de inscripción de equipos
2. Llena el formulario:
   - Selecciona un **Club**
   - Ingresa el **Nombre del Equipo**
   - Selecciona jugadores de la lista disponible
3. Haz clic en **"Guardar Equipo"**

### Paso 3: Revisar la Consola del Navegador

La consola debería mostrar:

#### Si todo funciona correctamente:
```
=== INICIO GUARDAR EQUIPO (JavaScript) ===
Datos del equipo: {...}
Jugador 1 (posición 1): {...}
Total de jugadores a enviar: X
Enviando datos al servidor...
Respuesta recibida, status: 200
Content-Type: application/json
Respuesta completa (primeros 500 caracteres): {"success":true,"message":"..."}
Datos de respuesta (JSON parseado): {...}
=== ÉXITO: Equipo guardado correctamente ===
```

#### Si hay un error:
```
=== INICIO GUARDAR EQUIPO (JavaScript) ===
...
Respuesta recibida, status: 500
Content-Type: text/html  <-- IMPORTANTE: Si es text/html, hay un error de PHP
Respuesta completa (primeros 500 caracteres): <!DOCTYPE html>...  <-- Esto muestra el HTML del error
```

### Paso 4: Revisar Logs de PHP

Los logs estarán en: **`C:\wamp64\logs\php_error.log`**

#### Forma 1: Usar PowerShell
```powershell
Get-Content C:\wamp64\logs\php_error.log -Tail 50
```

#### Forma 2: Abrir el archivo directamente
Abre el archivo: `C:\wamp64\logs\php_error.log` con un editor de texto y ve al final del archivo.

#### Forma 3: Monitorear en tiempo real
```powershell
Get-Content C:\wamp64\logs\php_error.log -Wait -Tail 20
```

### Paso 5: Buscar los Logs del Guardado

Busca en los logs líneas que empiecen con:
```
=== INICIO GUARDAR EQUIPO ===
PASO 1: Datos extraídos...
PASO 2: Usuario autenticado...
PASO 3: Transacción iniciada...
```

O errores que empiecen con:
```
ERROR:
API Error [XXX]:
=== ERROR FINAL:
```

## Qué Buscar

### Error Común 1: Archivo no encontrado
```
API Error [FILE_NOT_FOUND]: Archivo requerido no encontrado: XXX
```
**Solución**: Verificar que todos los archivos existen en las rutas correctas

### Error Común 2: Error SQL
```
ERROR en PASO 5.X al crear/actualizar inscrito: SQLSTATE[42S22]: Column not found...
SQL Error Info: {...}
```
**Solución**: Verificar la estructura de la tabla `inscritos` o `equipos`

### Error Común 3: Error al crear equipo
```
EquiposHelper::crearEquipo - ERROR: ...
```
**Solución**: Revisar los logs detallados de `EquiposHelper::crearEquipo`

### Error Común 4: HTML en lugar de JSON
Si en la consola ves HTML en lugar de JSON, significa que:
- Hay un error de PHP que está generando una página de error
- Los logs de PHP tendrán el error exacto

## Script de Prueba

Para verificar que los logs funcionan, puedes ejecutar:

**URL**: `http://localhost/mistorneos/public/api/test_logs.php`

Este script:
- Verifica que los logs funcionen
- Muestra la ruta del archivo de logs
- Verifica que el archivo sea escribible
- Escribe algunos logs de prueba

## Próximos Pasos

1. **Intenta guardar un equipo nuevamente**
2. **Copia TODOS los logs de la consola del navegador** (especialmente si ves HTML)
3. **Copia los últimos 50-100 líneas del archivo de logs de PHP**
4. **Compártelos** para identificar exactamente dónde está fallando

## Archivos Importantes

- **API**: `public/api/guardar_equipo.php`
- **Helper**: `lib/EquiposHelper.php`
- **Formulario**: `modules/gestion_torneos/inscribir_equipo_sitio.php`
- **Logs**: `C:\wamp64\logs\php_error.log`








