# Análisis: Inscripción de equipos de 4 (inscripción en sitio)

## Por qué la inscripción individual funciona y la de equipos fallaba

| Flujo | Entrada | ¿Sesión correcta? |
|-------|--------|---------------------|
| **Individual** | El usuario envía el formulario a `public/tournament_admin_toggle_inscripcion.php`. Ese script **carga primero** `config/session_start_early.php` y luego el API real. | Sí: misma cookie de sesión que la página del formulario. |
| **Equipos de 4** | El formulario hace `fetch()` a `public/api/guardar_equipo.php`. Si ese script **no** cargaba `session_start_early` antes de `bootstrap`, solo ejecutaba `bootstrap`, que usa `session_name(APP_CONFIG['security']['session_name'])`. En muchos entornos ese nombre es distinto al que usa `session_start_early` (p. ej. `mistorneos_session_dev` vs `mistorneos_session`). | No: la API buscaba otra cookie → sesión nueva → `$_SESSION['csrf_token']` vacío → validación CSRF fallaba y el código antiguo hacía `die('CSRF validation failed')` sin devolver JSON ni más logs. |

Por tanto el fallo no estaba en el formulario ni en los datos, sino en que **la API de equipos no compartía la misma sesión** que la página (mismo nombre de sesión y mismo inicio temprano).

---

## Pasos del proceso de inscripción de equipos de 4

1. **Entrada a la pantalla**  
   Usuario entra a Gestión de Torneos → Panel del torneo → “Inscribir equipo en sitio” (acción `inscribir_equipo_sitio`). Se carga `modules/gestion_torneos/inscribir_equipo_sitio.php` con datos: torneo, jugadores disponibles, clubes, equipos ya registrados.

2. **Selector de club**  
   El usuario elige un club en el `<select id="club_id">`. Obligatorio para poder guardar.

3. **Nombre del equipo**  
   El usuario escribe el nombre en `#nombre_equipo`. Obligatorio.

4. **Selección de 4 jugadores**  
   Por cada hueco (1 a 4) el usuario busca por cédula (o elige de la lista), se rellenan cédula, nombre y se guardan en inputs ocultos (`jugador_id_inscrito_*`, `jugador_id_usuario_h_*`, etc.). La validación en JS exige club, nombre y 4 jugadores con cédula y nombre para permitir enviar.

5. **Submit del formulario**  
   Al hacer clic en “Guardar equipo”, el evento `submit` del `#formEquipo` hace `e.preventDefault()`, construye un `FormData` con:
   - `csrf_token`, `equipo_id`, `torneo_id`, `nombre_equipo`, `club_id`
   - Para cada jugador: `jugadores[n][cedula]`, `jugadores[n][nombre]`, `jugadores[n][id_inscrito]`, `jugadores[n][id_usuario]`, `jugadores[n][es_capitan]`  
   y hace `fetch(api_base_path + 'guardar_equipo.php', { method: 'POST', body: formData })`.

6. **API `public/api/guardar_equipo.php`**  
   - **Orden crítico:** primero `session_start_early.php`, después `bootstrap` y el resto. Así la API usa la **misma sesión** (y mismo `csrf_token`) que la página del formulario.
   - Buffer de salida y manejo de errores para responder siempre en JSON.
   - Lee el body (soporta `application/json` y `multipart/form-data`), normaliza `$input`, valida CSRF desde `$input['csrf_token']` vs `$_SESSION['csrf_token']`. Si falla, responde JSON con `error_type: 'CSRF_INVALID'` y mensaje para recargar; no hace `die()`.
   - Extrae `torneo_id`, `equipo_id`, `nombre_equipo`, `club_id`, `jugadores`; si `jugadores` viene como string JSON, lo decodifica.
   - Comprueba usuario autenticado y permisos; en transacción crea o actualiza equipo y procesa jugadores en `inscritos`.

7. **Respuesta en el navegador**  
   El JS parsea la respuesta como JSON. Si `data.success === true`, muestra mensaje de éxito y recarga. Si `data.error_type === 'CSRF_INVALID'`, muestra “Token de seguridad expirado” con opción “Recargar página”. Cualquier otro error se muestra con el mensaje devuelto.

---

## Dónde estaba el problema

- **Síntoma en logs (versión antigua en servidor):** solo aparecía “POST recibido” y “Content-Type recibido”, y **no** “POST/input recibido”, “CSRF validado correctamente” ni “PASO 1”, etc.
- **Causa:** En el servidor seguía desplegada la versión **antigua** de `guardar_equipo.php` que:
  1. **No** cargaba `session_start_early.php` al inicio.
  2. Al fallar la validación CSRF ejecutaba `die('CSRF validation failed')`, por lo que el script terminaba sin escribir más logs ni devolver JSON.

Con la versión antigua, al cargar solo `bootstrap` se usaba `session_name(APP_CONFIG['security']['session_name'])`. Si ese valor no coincide con el nombre usado al cargar la página (p. ej. en index se usa `session_start_early` → `getenv('SESSION_NAME') ?: 'mistorneos_session'`), la cookie que envía el navegador no corresponde a esa sesión, PHP abre una sesión nueva, `$_SESSION['csrf_token']` está vacío y la validación CSRF falla.

---

## Solución aplicada en código

1. **En `public/api/guardar_equipo.php`:**
   - Al **inicio** del script (tras `ob_start()`):  
     `require_once __DIR__ . '/../../config/session_start_early.php';`
   - Así la API inicia/reanuda la sesión con el **mismo nombre y path** que `index.php` y reconoce la cookie del formulario.
   - Validación CSRF **sin** `die()`: si falla, se responde JSON con `error_type: 'CSRF_INVALID'` y mensaje para recargar.
   - Log unificado: “POST/input recibido” y luego “CSRF validado correctamente” o “CSRF inválido...”, “PASO 1”, etc.
   - Soporte de body en JSON y multipart; normalización de `jugadores` y `equipo_id` vacío como 0.

2. **En el formulario** (`inscribir_equipo_sitio.php`):  
   - Botón “Guardar equipo” deshabilitado solo cuando el torneo ha iniciado (`$torneo_iniciado`).  
   - Manejo de `data.error_type === 'CSRF_INVALID'` con SweetAlert y botón “Recargar página” que hace `location.reload()`.

---

## Qué hacer en el servidor (beta/producción)

1. **Desplegar** la versión **actual** de `public/api/guardar_equipo.php` (la que tiene `session_start_early` al inicio y la validación CSRF que devuelve JSON).
2. **Comprobar** que en el log aparezcan, al guardar un equipo:
   - `=== INICIO GUARDAR EQUIPO ===`
   - `POST/input recibido: {...}`
   - `CSRF validado correctamente` (o `CSRF inválido...` si hay problema de token)
   - `PASO 1: Datos extraídos...`
   - y el resto de pasos hasta el éxito o error controlado.
3. Si tras el despliegue el problema continúa, revisar en el servidor:
   - Variable de entorno `SESSION_NAME` (si existe, debe ser la misma que use la aplicación al cargar la página).
   - Que la petición a `guardar_equipo.php` envíe la misma cookie de sesión que la página del formulario (mismo dominio, path y que no se bloquee la cookie en peticiones fetch; en same-origin normalmente se envía; si hay subdominios o rutas distintas, revisar `session_set_cookie_params` en `session_start_early.php`).

---

## Resumen

- **Inscripción individual** funciona porque su punto de entrada (`tournament_admin_toggle_inscripcion.php`) ya carga `session_start_early` antes del API.
- **Inscripción de equipos de 4** fallaba porque `guardar_equipo.php` no cargaba `session_start_early`, la sesión no coincidía y el CSRF fallaba; la versión antigua además cortaba la ejecución con `die()` sin devolver JSON.
- **Solución:** cargar `session_start_early` al inicio de `guardar_equipo.php`, validar CSRF respondiendo en JSON y desplegar esta versión en el servidor. Tras el despliegue, los logs deben mostrar “POST/input recibido” y los pasos siguientes; si no, el servidor sigue con la versión antigua del archivo.
