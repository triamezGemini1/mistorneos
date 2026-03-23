# Verificar despliegue de guardar_equipo.php

## Problema
El log muestra **"POST recibido"** y se corta después de **"Content-Type recibido"**. Eso indica que en el servidor está la **versión antigua** del archivo.

## Qué desplegar (importante: v2 evita OPcache obsoleto)

1. **`public/api/guardar_equipo_v2.php`** — implementación real (nombre nuevo = PHP no tiene bytecode antiguo).
2. **`public/api/guardar_equipo.php`** — solo hace `require` de `guardar_equipo_v2.php` (compatibilidad).
3. **`modules/gestion_torneos/inscribir_equipo_sitio.php`** — el formulario hace `fetch` a **`guardar_equipo_v2.php`** (obligatorio para que el navegador no siga pegando al script cacheado).

Sin subir **v2** y sin actualizar la vista, seguirás viendo en el log "POST recibido" aunque el disco tenga el PHP nuevo.

## Comprobar que el archivo desplegado es el correcto

En el archivo del servidor debe aparecer **exactamente** lo siguiente:

1. **Líneas 9-10:** justo después de `ob_start();` debe estar:
   ```php
   // Iniciar sesión igual que index.php...
   require_once __DIR__ . '/../../config/session_start_early.php';
   ```

2. **Línea de log:** debe decir **"POST/input recibido"** (con la palabra **input**), no solo "POST recibido":
   ```php
   error_log("POST/input recibido: " . json_encode($input, JSON_UNESCAPED_UNICODE));
   ```

3. **CSRF:** cuando falla el token no debe haber `die('CSRF...')`; debe haber un `echo json_encode([...'error_type' => 'CSRF_INVALID'...]);` y luego `exit;`.

## Forzar recarga sin reiniciar el servidor (OPcache)

Aunque el archivo en disco cambie, PHP puede seguir sirviendo la versión compilada en memoria. Para forzar la actualización:

1. Sube el archivo **`public/api/clear_cache.php`** (temporal) al servidor.
2. Abre en el navegador: `https://tu-dominio.com/.../public/api/clear_cache.php`
3. Debe mostrar "OPcache reseteado con éxito" (o "OPcache no está activo..." si el servidor no usa OPcache).
4. **Elimina `clear_cache.php`** del servidor después de usarlo (es temporal y no debe quedar en producción).

## Si el archivo ya es el nuevo pero no ves "CSRF validado correctamente"

Entonces el problema es la **sesión**: el script necesita que la sesión se inicie **antes** de cargar bootstrap, con el mismo nombre que la página del formulario.

- `guardar_equipo.php` debe cargar **`session_start_early.php`** en las primeras líneas (antes de `bootstrap.php`). Así `$_SESSION['csrf_token']` estará disponible y la validación CSRF podrá pasar.
- Si la sesión no se inicia correctamente, `$_SESSION['csrf_token']` queda vacío y el script responde con JSON `error_type: 'CSRF_INVALID'` (o en la versión antigua terminaba con `die()`).
- Comprueba en el servidor que existe `config/session_start_early.php` y que el nombre de sesión (variable de entorno `SESSION_NAME` o valor por defecto) coincida con el que usa la aplicación al cargar la página (p. ej. `index.php` también carga `session_start_early` primero).

## Después de desplegar

Al guardar un equipo desde "Inscribir equipo en sitio", en el log deberían aparecer **en este orden**:

1. `=== INICIO GUARDAR EQUIPO ===`
2. `REQUEST_METHOD: POST`
3. `Content-Type recibido: ...`
4. **`POST/input recibido: {...}`**  ← si ves esto, el archivo nuevo está activo
5. **`CSRF validado correctamente`** (o `CSRF inválido...` si hay que recargar la página)
6. `PASO 1: Datos extraídos - torneo_id=...`
7. Y el resto del flujo hasta éxito o error en JSON.

Si tras desplegar sigues viendo solo **"POST recibido"** (sin "input") y el log se corta después de "Content-Type recibido", el servidor sigue usando el archivo antiguo (caché, ruta equivocada o no se sobrescribió el archivo).
