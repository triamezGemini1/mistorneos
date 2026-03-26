# Procedimiento: Generación de Rondas, QR y Verificación de Actas

Este documento describe el flujo completo desde la generación de una ronda hasta la verificación de actas enviadas por QR.

---

## 1. Generación de la ronda

### 1.1 Cuándo se puede generar una ronda

- **Primera ronda (ronda 1):** Siempre que haya al menos 4 inscritos confirmados en el torneo.
- **Rondas siguientes (2, 3, … N):** Solo cuando **todas las mesas de la ronda anterior tienen resultados registrados**.

Una mesa se considera “con resultados” cuando en `partiresul` los 4 registros de esa mesa tienen `registrado = 1`. No importa si el resultado llegó por **administrador** (registro manual) o por **QR** (envío desde el móvil): en ambos casos se pone `registrado = 1`, y la mesa cuenta como completa para poder generar la siguiente ronda.

### 1.2 Dónde se dispara

- **Panel de torneo** (`panel`, `panel-moderno`, `panel_equipos`): formulario POST con `action=generar_ronda`, `torneo_id` y opcionalmente `estrategia_asignacion` / `estrategia_ronda2`.
- El controlador es `modules/torneo_gestion.php`: caso `generar_ronda` → función `generarRonda()`.

### 1.3 Qué hace `generarRonda()`

1. **Comprobaciones**
   - Permisos del usuario sobre el torneo.
   - Torneo con al menos 4 inscritos confirmados.
   - Si ya existe una ronda generada, que **todas las mesas de la última ronda** tengan resultados (`todasLasMesasCompletas`); si no, devuelve error indicando cuántas mesas faltan.

2. **Actualización previa**
   - Llama a `actualizarEstadisticasInscritos($torneo_id)` para que la clasificación esté al día antes de asignar la nueva ronda.

3. **Asignación de mesas**
   - Según la **modalidad** del torneo:
     - **Individual (no equipos):** `MesaAsignacionService` (ronda 1: dispersión por clubes; ronda 2: separación de líderes; intermedias y final: suizo / intercalado).
     - **Equipos (modalidad 3):** `MesaAsignacionEquiposService` (ronda 1, 2+, estrategia configurable).
   - El servicio escribe en **`partiresul`**:
     - Una fila por jugador por mesa: `id_torneo`, `id_usuario`, `partida` (ronda), `mesa`, `secuencia` (1–4: A, C, B, D), `fecha_partida`, `registrado = 0`.
   - Jugadores que no caben en mesas (resto de dividir entre 4) se asignan a **BYE** (`mesa = 0`) con resultado ya aplicado (partida ganada, efectividad, etc.).

4. **Historial de parejas**
   - Si existe la tabla `historial_parejas`, se guardan las parejas AC y BD de cada mesa para evitar repetir compañeros en rondas siguientes.

5. **Notificaciones**
   - Se preparan notificaciones masivas (Telegram y/o campanita web) con plantilla `nueva_ronda`: mesa, pareja, enlaces a resumen y clasificación.

6. **Redirección**
   - Redirige al panel del torneo con mensaje de éxito (número de mesas, BYE, etc.) o de error.

### 1.4 Tabla `partiresul` tras generar una ronda

Para cada jugador en una mesa (no BYE) queda algo como:

- `id_torneo`, `id_usuario`, `partida` (ronda), `mesa`, `secuencia` (1–4)
- `registrado = 0`
- Sin `resultado1`, `resultado2`, `efectividad` aún (o valores por defecto)
- Columnas opcionales: `origen_dato`, `estatus`, `foto_acta` (si existen en el esquema)

Hasta que alguien registre el resultado (manual o QR), la mesa sigue “incompleta” para la generación de la siguiente ronda.

---

## 2. Flujo QR: desde la hoja de anotación hasta el envío del acta

### 2.1 Origen del QR

- En **Hojas de anotación** (`gestion_torneos/hojas-anotacion.php`) se genera una hoja por mesa y ronda.
- Para cada mesa se construye una URL pública que incluye **token de seguridad**:
  - `QrMesaTokenHelper::generar($torneo_id, $mesa, $ronda)` → token HMAC (torneo + mesa + ronda).
  - URL tipo:  
    `public_mesa_input.php?t={torneo_id}&m={mesa}&r={ronda}&token={token}`
  - Esa URL es la que se codifica en el **código QR** de la hoja. Si se usa otro script (por ejemplo `cargar_acta_mesa.php`), el formato puede ser `torneo_id`, `mesa_id`, `ronda`; el backend de envío debe recibir también el `token` cuando el origen es QR.

### 2.2 Página de carga del acta (formulario móvil)

- El jugador/mesa escanea el QR y abre la URL (ej. `public_mesa_input.php` o `cargar_acta_mesa.php`).
- La página:
  - Valida parámetros (torneo, mesa, ronda y, si aplica, token).
  - Carga de `partiresul` los 4 jugadores de esa mesa/ronda (con nombre, secuencia).
  - Muestra un formulario:
    - Para cada jugador: resultado1, resultado2 (y opcionalmente sanciones/tarjetas si el formulario lo incluye).
    - **Foto del acta** (obligatoria en envíos por QR).
  - El formulario envía por POST a un endpoint de envío (en PROD: `actions/public_score_submit.php`), con `origen=qr` y, si la URL lleva token, el `token` en el POST.

### 2.3 Endpoint de envío: `public_score_submit.php`

- Recibe: `torneo_id`, `mesa_id` (o `mesa`), `ronda`, `jugadores` (array con id_usuario, secuencia, resultado1, resultado2, etc.), `image` (archivo), `origen` (qr | admin), y si aplica `token`.

**Validaciones:**

- Si `origen === 'qr'`:
  - **Token obligatorio:** `QrMesaTokenHelper::validar($torneo_id, $mesa_id, $ronda, $token)`. Si falla, responde 403 (“Enlace inválido o expirado”).
  - **Imagen obligatoria:** sin foto del acta no se acepta el envío (403/400).

- Comprueba que el torneo exista, esté activo y no cerrado (`locked`).
- Comprueba que la mesa exista en `partiresul` para esa ronda.
- Si la tabla `partiresul` tiene columna `estatus` y la mesa ya está en `confirmado`, rechaza (no se puede reenviar por QR).

**Procesamiento:**

1. **Imagen:** se guarda en `upload/actas_torneos/` con nombre tipo `acta_T{id}_R{ronda}_M{mesa}_{uniqid}.jpg` (o .png). Si la tabla tiene `foto_acta`, se guarda la ruta relativa en cada fila de la mesa (o en un UPDATE global por mesa).
2. **Efectividad y sanciones:** se usa la misma lógica que en el admin (puntos del torneo, `SancionesHelper` para tarjetas/sanciones). Se calcula `efectividad` por jugador.
3. **Escritura en `partiresul`:**
   - `resultado1`, `resultado2`, `efectividad`, `ff`, `tarjeta`, `sancion`, `registrado = 1`, `fecha_partida`, etc.
   - Si existe: `origen_dato = 'qr'`.
   - Si existe: **`estatus = 'pendiente_verificacion'`** (solo cuando origen es QR; si es admin puede quedar `confirmado`).

Así, la mesa pasa a tener `registrado = 1` (cuenta para “mesa completa” y para poder generar la siguiente ronda), pero el acta queda en estado **pendiente de verificación** hasta que un administrador la apruebe o rechace.

---

## 3. Verificación de actas (admin)

### 3.1 Listado de torneos con actas pendientes

- **Acción:** `verificar_actas_index`.
- **Vista:** `tournament_admin/verificar_actas_index.php`.
- **Datos:** `obtenerTorneosConActasPendientes($user_id, $is_admin_general)`:
  - Consulta `partiresul` con `estatus = 'pendiente_verificacion'` y, si existe, `origen_dato = 'qr'`, agrupando por torneo.
  - Devuelve torneos con al menos una mesa pendiente y el conteo de actas pendientes por torneo.
- El usuario elige un torneo y pasa a **Verificar resultados** de ese torneo.

### 3.2 Pantalla de verificación por torneo

- **Acción:** `verificar_resultados` con `torneo_id` (y opcionalmente `ronda` y `mesa`).
- **Vista:** `tournament_admin/views/verificar_resultados.php`.

**Datos cargados:**

- `obtenerDatosVerificarActasLista($torneo_id)` → lista de mesas pendientes: pares `(partida, mesa)` con `estatus = 'pendiente_verificacion'` (y si aplica `origen_dato = 'qr'`).
- Si se indica **ronda y mesa**: `obtenerDatosVerificarActa($torneo_id, $ronda, $mesa)` devuelve los 4 jugadores de esa mesa (nombres, resultado1, resultado2, foto_acta, etc.) siempre que el acta siga en `pendiente_verificacion`.

**Interfaz:**

- Sidebar: enlaces a cada mesa pendiente (Ronda X · Mesa Y). Al elegir una, se recarga la página con `ronda` y `mesa` en la URL y se muestra el detalle.
- Área principal:
  - Sin mesa elegida: mensaje para que seleccione una mesa.
  - Con mesa elegida: formulario con parejas A/B, puntos editables, **imagen del acta** (zoom/rotar) y botones **Aprobar** y **Rechazar**.

### 3.3 Aprobar acta (POST `verificar_acta_aprobar`)

- Formulario envía: `torneo_id`, `ronda`, `mesa`, `jugadores[id][resultado1]`, `jugadores[id][resultado2]`, etc., más `redirect_action=verificar_resultados` y CSRF.
- **Función:** `verificarActaAprobar()` en `torneo_gestion.php`.

**Pasos:**

1. Comprueba permisos y que el torneo no esté cerrado (o que el usuario sea admin_general).
2. Comprueba que la tabla `partiresul` tenga columna `estatus`.
3. Lee los puntos del torneo y los 4 registros actuales de la mesa.
4. Usa `SancionesHelper` para tarjetas/sanciones y recalcula efectividad con la misma lógica que el resto del sistema.
5. En una transacción:
   - Actualiza cada fila de `partiresul` de esa mesa: `resultado1`, `resultado2`, `efectividad`, `tarjeta`, `sancion`, **`estatus = 'confirmado'`**.
6. Llama a `actualizarEstadisticasInscritos($torneo_id)` para recalcular posiciones/estadísticas.
7. Opcionalmente envía notificaciones a los jugadores (`enviarNotificacionesResultadosAprobados`).
8. Redirige a `verificar_resultados` (o a `verificar_actas_index`) con mensaje de éxito.

Tras aprobar, esa mesa deja de ser “pendiente” y ya no aparece en el listado de actas a verificar.

### 3.4 Rechazar acta (POST `verificar_acta_rechazar`)

- Formulario envía: `torneo_id`, `ronda`, `mesa`, `redirect_action`, CSRF.
- **Función:** `verificarActaRechazar()`.

**Pasos:**

1. Mismas comprobaciones de permisos y torneo no cerrado (o admin_general).
2. En `partiresul`, para esa mesa (id_torneo, partida, mesa):
   - Se ponen a 0 o NULL: `registrado`, `resultado1`, `resultado2`, `efectividad`, `ff`, `tarjeta`, `sancion`.
   - Si existe: `estatus = 'pendiente_verificacion'` (para que vuelva a aparecer como pendiente).
   - Si existe: `foto_acta = NULL`.
3. Se llama a `actualizarEstadisticasInscritos($torneo_id)`.
4. Redirige a `verificar_resultados` (o índice) con mensaje tipo “Acta rechazada; el jugador puede volver a escanear y enviar el acta”.

Esa mesa vuelve a estar “sin resultado” desde el punto de vista de datos (registrado=0) y, si se usa estatus, queda de nuevo en `pendiente_verificacion` hasta un nuevo envío por QR y una nueva verificación.

---

## 4. Resumen del ciclo de datos

| Paso | Quién | Dónde | partiresul |
|------|--------|------|------------|
| 1. Generar ronda | Admin | Panel → generar_ronda | Filas creadas: partida, mesa, secuencia, registrado=0 |
| 2. Envío por QR | Jugador/mesa | QR → formulario → public_score_submit | registrado=1, origen_dato=qr, estatus=pendiente_verificacion, foto_acta |
| 3. Mesa “completa” | Sistema | contarMesasIncompletas / todasLasMesasCompletas | Cuenta si registrado=1 en los 4 de la mesa → permite generar siguiente ronda |
| 4. Verificar actas | Admin | verificar_actas_index → verificar_resultados | Lista mesas con estatus=pendiente_verificacion (y origen_dato=qr si se filtra) |
| 5a. Aprobar | Admin | verificar_acta_aprobar | estatus=confirmado; estadísticas y notificaciones |
| 5b. Rechazar | Admin | verificar_acta_rechazar | registrado=0, resultados y foto_acta a 0/NULL; estatus=pendiente_verificacion para reenvío |

---

## 5. Archivos clave (referencia)

- **Generación de ronda:** `modules/torneo_gestion.php` (generarRonda, case generer_ronda); `config/MesaAsignacionService.php` o `MesaAsignacionEquiposService.php` (generarAsignacionRonda, guardarAsignacionRonda).
- **QR y token:** `lib/QrMesaTokenHelper.php`; `modules/gestion_torneos/hojas-anotacion.php` (URL del QR).
- **Formulario público:** `public/cargar_acta_mesa.php` o equivalente `public_mesa_input.php`; en PROD, envío en `actions/public_score_submit.php`.
- **Verificación:** `modules/torneo_gestion.php` (verificar_actas_index, verificar_resultados, verificarActaAprobar, verificarActaRechazar; obtenerDatosVerificarActasLista, obtenerDatosVerificarActa, obtenerTorneosConActasPendientes); vistas en `modules/tournament_admin/verificar_actas_index.php` y `views/verificar_resultados.php`.
