# Secuencia del proceso de inscripción por invitación (Formulario de invitación de clubes)

## Resumen

Este documento describe **cada paso** del flujo desde que el usuario accede al formulario de invitación hasta que se guarda (o se rechaza) la inscripción. La búsqueda en backend se ejecuta **en este orden fijo**:

- **PASO 1:** Buscar en tabla **inscritos** (solo si `torneo_id` > 0). Si existe → respuesta `ya_inscrito`, STOP.
- **PASO 2:** Buscar en tabla **usuarios**. Si existe → devolver datos, STOP.
- **PASO 3:** Buscar en **base personas** (externa). Si existe → devolver datos, STOP.
- **PASO 4:** No encontrado → respuesta `no_encontrado` (registro manual).

En el log del servidor verás líneas como:
- `search_persona.php - ENTRADA: nacionalidad=..., cedula=..., torneo_id=...`
- `search_persona.php - PASO 1: Buscando en INSCRITOS (...)` o `PASO 1 OMITIDO: torneo_id=0`
- `search_persona.php - PASO 2: Buscando en USUARIOS` y resultado
- `search_persona.php - PASO 3: Buscando en BASE PERSONAS` y resultado
- `search_persona.php - PASO 4: NO_ENCONTRADO` si no se encontró en ninguno

---

## 1. Acceso al formulario

| Paso | Qué hace | Qué se espera |
|------|----------|----------------|
| 1.1 | El usuario abre el enlace de invitación: **`/invitation/register?token=XXX`** o **`/invitation/register?torneo=T&club=C`** (o ambos: token + torneo + club). | URL con al menos `token` o la pareja `torneo` + `club`. |
| 1.2 | El router (`config/routes.php`) atiende **GET /invitation/register** e incluye **`modules/invitation_register.php`**. | Se carga la vista del formulario de invitación. |
| 1.3 | PHP lee **`$token`**, **`$torneo_id`** y **`$club_id`** de `$_GET`. Si viene solo `token` (≥32 caracteres) y faltan torneo/club, se consulta la tabla **`invitaciones`** con ese token y se rellenan **`torneo_id`** y **`club_id`**. | En memoria quedan `$torneo_id` y `$club_id` no vacíos para una invitación válida. |
| 1.4 | Se valida la invitación: consulta a **invitaciones** con `torneo_id` y `club_id` (y opcionalmente token). Si no hay fila → "Invitación no válida". | **`$invitation_data`** poblado; **`$torneo_id`** y **`$club_id`** disponibles para toda la página. |
| 1.5 | Se renderiza el HTML del formulario con **`<input type="hidden" id="torneo_id" name="torneo_id" value="...">`** y el bloque **`<script> window.INVITATION_REGISTER_CONFIG = { apiBase: '...', torneoId: <?= (int)$torneo_id ?> }; </script>`**. | El navegador tiene en el DOM el `torneo_id` y en config el **torneoId** para el JS. |
| 1.6 | Se carga **`invitation-register.js`**. El script lee **`INVITATION_REGISTER_CONFIG.apiBase`** y **`config.torneoId`** y define la función **`getTorneoId()`** (que usa config o el input `#torneo_id` como respaldo). | En cada búsqueda se podrá obtener el `torneo_id` para enviarlo al backend. |

---

## 2. Búsqueda al salir del campo Cédula (onblur)

| Paso | Qué hace | Qué se espera |
|------|----------|----------------|
| 2.1 | El usuario escribe la cédula y sale del campo. El input tiene **`onblur="if(typeof searchPersona==='function')searchPersona();"`**. | Se ejecuta **`searchPersona()`** (definida en `invitation-register.js`). |
| 2.2 | **searchPersona()** lee **cedula** y **nacionalidad** del formulario, normaliza la cédula (solo números) y, si es muy corta, no hace nada. | Solo se hace la petición si hay nacionalidad y cédula con longitud válida. |
| 2.3 | Se muestra indicador de carga (toast o mensaje "Buscando..."). | UI no bloqueante. |
| 2.4 | Se construye la URL de la API:  
 **`{apiBase}/search_persona.php?cedula=...&nacionalidad=...&torneo_id={torneoId}`**  
 donde **torneoId = getTorneoId()** (config o input `#torneo_id`). | **Es crítico:** la URL debe incluir **`torneo_id`** para que el backend ejecute el NIVEL 1 (inscritos). |
| 2.5 | **fetch(searchUrl)** envía la petición GET a **`search_persona.php`**. | Backend recibe **cedula**, **nacionalidad** y **torneo_id**. |

---

## 3. Backend: `public/api/search_persona.php` (secuencia por pasos)

El backend ejecuta **siempre** en este orden. Cada paso escribe una línea en el log para poder seguir la secuencia.

| Paso | Qué hace | Log esperado | Qué se espera |
|------|----------|--------------|----------------|
| 3.0 | Lee **cedula**, **nacionalidad** y **torneo_id** de GET/POST. Normaliza cédula y nacionalidad. | `ENTRADA: nacionalidad=V, cedula=4978399, torneo_id=5` (si torneo_id=0 el PASO 1 se omite). | **torneo_id** debe ser > 0 para que se ejecute PASO 1. |
| 3.1 | **PASO 1 – Inscritos:** Si **torneo_id > 0**, ejecuta `SELECT id FROM inscritos WHERE torneo_id=? AND nacionalidad=? AND cedula=?`. Si hay fila → responde y **termina**. | `PASO 1: Buscando en INSCRITOS (torneo_id=5, nac=V, cedula=4978399)` y luego `PASO 1 resultado: YA_INSCRITO` o `no encontrado en inscritos, continuar a PASO 2`. Si torneo_id=0: `PASO 1 OMITIDO: torneo_id=0`. | Si ya inscrito: `{ "status": "ya_inscrito", "mensaje": "..." }`. Front muestra mensaje y limpia formulario. |
| 3.2 | **PASO 2 – Usuarios:** Busca en **tabla usuarios** por cédula (variantes). Si hay fila → responde y termina. | `PASO 2: Buscando en USUARIOS` y luego `PASO 2 resultado: ENCONTRADO en usuarios (Nombre)` o `no encontrado en usuarios, continuar a PASO 3`. | `{ "status": "encontrado", "existe_en_usuarios": true, "persona": { ... } }`. Front rellena formulario. |
| 3.3 | **PASO 3 – Base personas:** Consulta **base externa** (PersonaDatabase). Si hay resultado → responde y termina. | `PASO 3: Buscando en BASE PERSONAS (externa)` y luego `PASO 3 resultado: ENCONTRADO en base externa` o `no encontrado en base externa`. | `{ "status": "encontrado", "fuente": "externa", "persona": { ... } }`. Front rellena formulario. |
| 3.4 | **PASO 4 – No encontrado:** No se encontró en ninguno. | `PASO 4: NO_ENCONTRADO, devolver registro manual`. | `{ "status": "no_encontrado", "mensaje": "..." }`. Front habilita registro manual. |

---

## 4. Frontend tras la respuesta de búsqueda

| Paso | Qué hace | Qué se espera |
|------|----------|----------------|
| 4.1 | **Si `result.status === 'ya_inscrito'`:** Muestra toast con **result.mensaje**, limpia campos y pone foco en **nacionalidad**. | No se rellena el formulario; el usuario puede introducir otra cédula. |
| 4.2 | **Si `result.status === 'no_encontrado'`:** Muestra toast con mensaje y foco en nacionalidad. | Formulario listo para completar manualmente. |
| 4.3 | **Si `result.encontrado && result.persona`:** Llama a **fillFormFromPersona(result.persona)** y rellena nombre, sexo, fechnac, teléfono, email. | Campos listos para revisar y enviar. |
| 4.4 | Se oculta el indicador de carga. | UI actualizada. |

---

## 5. Envío del formulario (Inscribir)

| Paso | Qué hace | Qué se espera |
|------|----------|----------------|
| 5.1 | El usuario pulsa **"Inscribir"**. El formulario se envía por **POST** a la misma URL (invitation/register) con **action=register_player**, **torneo_id**, **club_id**, nacionalidad, cedula, nombre, sexo, fechnac, telefono, email. | PHP en **modules/invitation_register.php** procesa **$_POST**. |
| 5.2 | Backend valida sesión/permisos, torneo_id, club_id, cédula y nombre. Comprueba si el usuario ya existe en **usuarios** (por cédula); si no existe, puede crear usuario y luego insertar en **inscritos** (según lógica del módulo). | Inscripción guardada en **inscritos** (y usuario creado si aplica). |
| 5.3 | Respuesta de éxito: recarga o mensaje y listado de "Jugadores Inscritos" actualizado. | El nuevo jugador aparece en la tabla de inscritos. |

---

## 6. Por qué los logs muestran solo "Paso 1: usuarios" y no NIVEL 1

Posibles causas:

1. **No se envía `torneo_id`**  
   - Los logs muestran **GET params: cedula, nacionalidad** (sin torneo_id).  
   - Entonces en backend **torneo_id = 0**, se **omite el NIVEL 1** y se va directo a usuarios.  
   - **Solución:** Asegurar que el frontend envíe siempre **torneo_id** en la URL de búsqueda (en este proyecto: **invitation-register.js** usa **getTorneoId()** y lo añade a la query; el formulario tiene **id="torneo_id"** en el hidden para respaldo).

2. **Se está ejecutando otra versión del backend**  
   - Si los logs siguen mostrando textos como **"Paso 1: Buscando en tabla usuarios"** y **"Buscando: Nacionalidad=..."**, esa es la **versión antigua** de **search_persona.php** (por ejemplo en **PROD_MISTORNEOS** o otra ruta).  
   - La versión actual en **mistorneos/public/api/search_persona.php** no escribe esos mensajes; usa comentarios "NIVEL 1", "NIVEL 2", etc.  
   - **Solución:** Confirmar qué archivo sirve realmente la ruta **/api/search_persona.php** (p. ej. en **config/routes.php** se incluye **public/api/search_persona.php**) y desplegar/actualizar ese mismo archivo en el entorno donde se ven los logs.

3. **Caché de JS**  
   - Si el navegador sigue usando un **invitation-register.js** antiguo que no enviaba **torneo_id**, los GET seguirán sin torneo_id.  
   - **Solución:** Cargar el script con **?v=<?php echo time(); ?>** o similar y comprobar en DevTools (pestaña Network) que la URL de **search_persona.php** incluya **torneo_id=...**. En la consola (F12) el script escribe **search_persona: URL=... torneo_id=...** en cada búsqueda para verificar el valor enviado.

---

## 7. Checklist rápido

- [ ] En la pestaña Network, la petición a **search_persona.php** lleva **torneo_id** en la query.
- [ ] El backend que corre es **mistorneos/public/api/search_persona.php** (o la copia actualizada con NIVEL 1–4).
- [ ] Tras "ya inscrito", el front muestra el mensaje y no rellena el formulario.
- [ ] El formulario de invitación tiene **`<input id="torneo_id" name="torneo_id" value="...">`** y **INVITATION_REGISTER_CONFIG.torneoId** con valor numérico.
- [ ] **getTorneoId()** en **invitation-register.js** usa config o el input **#torneo_id** y se usa en la URL de **search_persona.php**.
- [ ] En el log del servidor aparecen **PASO 1 / PASO 2 / PASO 3 / PASO 4** según la secuencia real; si solo ves "PASO 2" sin "PASO 1", comprobar **torneo_id** en ENTRADA.

Con esto, la secuencia queda unificada y el PASO 1 (inscritos) se ejecuta siempre que el front envíe **torneo_id**.
