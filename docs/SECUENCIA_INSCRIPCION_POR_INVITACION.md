# Secuencia del proceso de inscripción por invitación (Formulario de invitación de clubes)

## Resumen

La búsqueda está dividida en **cuatro bloques separados**. Cada bloque genera **una sola acción** en el frontend:

| Bloque | Dónde busca | Si encuentra | Acción en frontend |
|--------|-------------|-------------|--------------------|
| **1. INSCRITO** | Tabla `inscritos` (solo si `torneo_id` > 0) | Ya inscrito en el torneo | Mensaje, **limpiar formulario**, **foco en nacionalidad**. |
| **2. USUARIO** | Tabla `usuarios` | Persona en la plataforma | **Rellenar formulario** (con `id_usuario`), permitir inscribir. |
| **3. PERSONAS** | Base externa (personas) | Persona en base externa | **Rellenar formulario** (sin id); al pulsar Inscribir se crea usuario e inscribe. |
| **4. NUEVO** | — | No encontrado en ninguno | Mantener nacionalidad y cédula, **limpiar resto**, **foco en nombre**; al pulsar Inscribir se **crea usuario** en `usuarios` y se **inscribe** en el torneo. |

El backend (`search_persona.php`) devuelve en cada respuesta el campo **`accion`**: `ya_inscrito` | `encontrado_usuario` | `encontrado_persona` | `nuevo` | `error`.

En el log del servidor verás:
- `search_persona.php - ENTRADA: nacionalidad=..., cedula=..., torneo_id=...`
- `search_persona.php - BLOQUE INSCRITO: Buscando en inscritos (...)` o `BLOQUE INSCRITO omitido (torneo_id=0)`
- `search_persona.php - BLOQUE USUARIO: Buscando en usuarios` y resultado
- `search_persona.php - BLOQUE PERSONAS: Buscando en base externa` y resultado
- `search_persona.php - BLOQUE NUEVO: no encontrado en inscritos, usuarios ni personas`

**Antipeticiones duplicadas:** Tanto `invitation-register.js` como `inscripciones.js` usan una variable de bloqueo (`isSearching` / `busquedaEnCurso`). Si una búsqueda está en curso, no se dispara otra hasta que termine.

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

## 3. Backend: `public/api/search_persona.php` (cuatro bloques, una acción por bloque)

El backend ejecuta **siempre** en este orden. Cada bloque devuelve **una sola acción** (`accion` en el JSON).

| Bloque | Qué hace | Log esperado | Respuesta (accion + datos) |
|--------|----------|--------------|----------------------------|
| 3.0 | Lee **cedula**, **nacionalidad** y **torneo_id** de GET/POST. Normaliza cédula y nacionalidad. | `ENTRADA: nacionalidad=V, cedula=..., torneo_id=5` | — |
| **BLOQUE 1 – INSCRITO** | Si **torneo_id > 0**, busca en **inscritos**. Si hay fila → responde y **termina**. | `BLOQUE INSCRITO: Buscando en inscritos (...)` → `YA_INSCRITO` o `no encontrado, continuar a BLOQUE USUARIO`. | `accion: "ya_inscrito"`, mensaje. Front: mensaje, limpiar formulario, foco nacionalidad. |
| **BLOQUE 2 – USUARIO** | Busca en **usuarios** por cédula (variantes). Si hay fila → responde y termina. | `BLOQUE USUARIO: Buscando en usuarios` → `ENCONTRADO` o `no encontrado, continuar a BLOQUE PERSONAS`. | `accion: "encontrado_usuario"`, persona (con id). Front: rellenar formulario, permitir inscribir. |
| **BLOQUE 3 – PERSONAS** | Consulta **base externa** (PersonaDatabase). Si hay resultado → responde y termina. | `BLOQUE PERSONAS: Buscando en base externa` → resultado. | `accion: "encontrado_persona"`, persona (sin id). Front: rellenar; al enviar se crea usuario e inscribe. |
| **BLOQUE 4 – NUEVO** | No encontrado en ninguno. | `BLOQUE NUEVO: no encontrado en inscritos, usuarios ni personas`. | `accion: "nuevo"`, mensaje. Front: mantener nac/cedula, limpiar resto, foco nombre; al enviar se crea usuario e inscribe. |

---

## 4. Frontend tras la respuesta de búsqueda (una acción por respuesta)

El frontend usa **`result.accion`** (o `result.status` en respuestas antiguas) y ejecuta **solo la acción** correspondiente:

| Acción | Qué hace el frontend | Resultado |
|--------|----------------------|-----------|
| **ya_inscrito** | Toast con **result.mensaje**, **clearFormFields()**, foco en **nacionalidad**. | Formulario limpio; usuario puede ingresar otra cédula. |
| **encontrado_usuario** o **encontrado_persona** | **fillFormFromPersona(result.persona)**, toast con mensaje, foco en nombre. | Campos rellenados; permitir inscribir (con id_usuario si viene de usuarios). |
| **nuevo** (o no_encontrado) | Toast con mensaje, **clearFormFieldsExceptSearch()** (mantiene nacionalidad y cédula), foco en **nombre**. | Formulario listo para completar; al pulsar Inscribir se crea usuario e inscribe. |
| **error** | Toast con mensaje de error. | Sin cambio en formulario. |
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
- [ ] En el log del servidor aparecen **BLOQUE INSCRITO / BLOQUE USUARIO / BLOQUE PERSONAS / BLOQUE NUEVO** según la secuencia; si solo ves "BLOQUE USUARIO" sin "BLOQUE INSCRITO", comprobar **torneo_id** en ENTRADA.
- [ ] Cada respuesta de búsqueda incluye **accion** (ya_inscrito | encontrado_usuario | encontrado_persona | nuevo | error) y el front ejecuta una sola acción por respuesta.

Con esto, cada búsqueda genera una sola acción: inscrito → mensaje y limpiar; usuario/personas → rellenar y permitir inscribir; nuevo → formulario manual y al enviar se crea usuario e inscribe.
