# Propuesta: Restaurar funcionalidad QR y Verificación de Actas

## Diagnóstico del estado actual

Tras auditar el código principal (`modules/`, `public/`, `actions/`, `config/`), se identificaron las siguientes carencias frente a lo que requiere el flujo completo.

---

## 1. Archivos faltantes en el proyecto principal

### 1.1 `actions/public_score_submit.php`

| Situación | Detalle |
|-----------|---------|
| **Estado** | No existe en el proyecto principal |
| **Ubicación en PROD** | `PROD_MISTORNEOS/actions/public_score_submit.php` |
| **Uso** | `config/routes.php` incluye este archivo para la ruta POST `/actions/public-score-submit` |
| **Impacto** | Al enviar un acta por QR o desde `cargar_acta_mesa.php`, el envío falla (archivo no encontrado) |

**Propuesta:** Copiar `PROD_MISTORNEOS/actions/public_score_submit.php` a `actions/public_score_submit.php` en la raíz del proyecto. Ajustar rutas de `require_once` si difieren (por ejemplo `__DIR__ . '/../config/bootstrap.php'`).

---

### 1.2 `public/public_mesa_input.php`

| Situación | Detalle |
|-----------|---------|
| **Estado** | No existe en el proyecto principal |
| **Ubicación en PROD** | `PROD_MISTORNEOS/public/public_mesa_input.php` |
| **Uso** | URLs de los QR en hojas de anotación: `public_mesa_input.php?t={torneo}&m={mesa}&r={ronda}&token={token}` |
| **Impacto** | Los QR de las hojas llevan a 404. El flujo de envío de actas por móvil no funciona |

**Propuesta:** Copiar `PROD_MISTORNEOS/public/public_mesa_input.php` a `public/public_mesa_input.php`. Comprobar que el formulario envíe a la URL correcta (p. ej. `/actions/public-score-submit` según el routing).

---

## 2. Funciones PHP faltantes en `modules/torneo_gestion.php`

### 2.1 Funciones requeridas y no definidas

| Función | Estado | Uso |
|---------|--------|-----|
| `verificarActaAprobar($user_id, $is_admin_general)` | No existe | POST `verificar_acta_aprobar` al aprobar un acta |
| `verificarActaRechazar($user_id, $is_admin_general)` | No existe | POST `verificar_acta_rechazar` al rechazar un acta |
| `enviarNotificacionesResultadosAprobados($pdo, $torneo_id, $ronda, $mesa)` | No existe | Llamada desde `verificarActaAprobar` tras aprobar un acta |

**Impacto:** Al hacer clic en "Aprobar" o "Rechazar" en la pantalla de verificación de actas aparece un error tipo "Call to undefined function verificarActaAprobar".

**Propuesta:** Incorporar estas tres funciones desde `PROD_MISTORNEOS/modules/torneo_gestion.php` (o desde la documentación de flujo) dentro de la sección de funciones auxiliares. Mantener la misma firma y lógica para no romper el flujo actual.

---

## 3. Esquema de base de datos

### 3.1 Columnas necesarias en `partiresul`

| Columna | Tipo esperado por el código | Uso |
|---------|-----------------------------|-----|
| `estatus` | `VARCHAR` o `ENUM` con valores `'pendiente_verificacion'`, `'confirmado'` | Estado del acta QR |
| `origen_dato` | `ENUM('admin','qr')` | Origen del registro (QR vs admin) |
| `foto_acta` | `VARCHAR(255)` | Ruta de la foto del acta subida por QR |

**Posible conflicto:** Existe `scripts/add_partiresul_estatus_boolean.php`, que añade `estatus` como `TINYINT(1)` (0/1). El código de verificación y `public_score_submit` esperan cadenas `'pendiente_verificacion'` y `'confirmado'`.

**Propuesta:**  
- Si ya se ejecutó `add_partiresul_estatus_boolean.php`, adaptar el código para trabajar con 0/1 o crear una migración que convierta a `VARCHAR(50)` con los valores requeridos.  
- Si aún no se ejecutó, usar `sql/add_foto_acta_origen_partiresul.sql` (o equivalente) para añadir `estatus` como `VARCHAR(50)` con valores `'confirmado'` y `'pendiente_verificacion'`, además de `origen_dato` y `foto_acta`.

---

## 4. Flujo de URLs y parámetros

### 4.1 Hojas de anotación → QR

- En `modules/gestion_torneos/hojas-anotacion.php` la base de la URL es `$url_dominio_public . '/public_mesa_input.php'`.
- Parámetros generados: `t`, `m`, `r`, `token` (p. ej. `?t=1&m=2&r=3&token=xxx`).

**Comprobación:**  
- `public_mesa_input.php` debe existir en `public/`.  
- Debe aceptar `t`, `m`, `r` y `token`.  
- El formulario debe incluir `token` en el POST si el origen es QR.

### 4.2 Formulario público → envío

- `cargar_acta_mesa.php` usa `torneo_id`, `mesa_id`, `ronda` (sin token).
- `public_mesa_input.php` usa `t`, `m`, `r`, `token`.
- Ambos envían a `actions/public-score-submit` (o ruta equivalente).
- `public_score_submit.php` exige `token` cuando `origen=qr`.

**Propuesta:**  
- Usar `public_mesa_input.php` como entrada principal para actas vía QR (con token).  
- Mantener `cargar_acta_mesa.php` solo si se define un flujo alternativo (p. ej. con token opcional o con `origen=admin`). Para reactivar el flujo mínimo, priorizar `public_mesa_input.php` + `public_score_submit.php`.

---

## 5. Dependencias adicionales

### 5.1 Clases / helpers utilizados por el flujo QR y verificación

| Dependencia | Archivo típico | Verificar existencia |
|-------------|----------------|----------------------|
| `QrMesaTokenHelper` | `lib/QrMesaTokenHelper.php` | ✓ Existe |
| `SancionesHelper` | `lib/SancionesHelper.php` | ✓ Existe |
| `ImageOptimizer` | `lib/ImageOptimizer.php` | Revisar si existe |
| `NotificationManager` | `lib/NotificationManager.php` | Revisar si existe |
| `AppHelpers` | `lib/app_helpers.php` | Revisar si existe |

Si alguna falta, hay que copiarla desde PROD o implementarla antes de activar el flujo completo.

---

## 6. Plan de implementación sugerido

### Fase 1 – Restaurar archivos esenciales (prioridad alta)

1. Crear `actions/` en la raíz si no existe.
2. Copiar `PROD_MISTORNEOS/actions/public_score_submit.php` → `actions/public_score_submit.php`.
3. Copiar `PROD_MISTORNEOS/public/public_mesa_input.php` → `public/public_mesa_input.php`.
4. Ajustar rutas de include en los archivos copiados según la estructura del proyecto principal.

### Fase 2 – Restaurar lógica de verificación de actas

5. Añadir en `modules/torneo_gestion.php` las funciones:
   - `verificarActaAprobar()`
   - `verificarActaRechazar()`
   - `enviarNotificacionesResultadosAprobados()`

### Fase 3 – Esquema de base de datos

6. Ejecutar la migración adecuada para `partiresul`:
   - Si se usa esquema PROD: `sql/add_foto_acta_origen_partiresul.sql` (o equivalente).
   - Si ya existe `estatus` como `TINYINT(1)`, decidir entre adaptar el código o migrar a `VARCHAR`/`ENUM` con los valores usados en el código.

### Fase 4 – Comprobaciones finales

7. Comprobar que existe el directorio `upload/actas_torneos/` con permisos de escritura.
8. Probar el flujo completo:
   - Generar ronda → imprimir hojas con QR → escanear QR → cargar acta y foto → enviar.
   - Verificar que la acta aparece en "Verificar actas" y que Aprobar/Rechazar funcionan sin errores.

---

## 7. Viabilidad y esfuerzo estimado

| Tarea | Riesgo | Esfuerzo | Viabilidad |
|-------|--------|----------|------------|
| Copiar `public_score_submit.php` | Bajo | ~5 min | Alta |
| Copiar `public_mesa_input.php` | Bajo | ~5 min | Alta |
| Añadir 3 funciones en `torneo_gestion.php` | Medio | ~15–20 min | Alta |
| Migración de BD (si hace falta) | Medio | ~10 min | Alta |
| Pruebas end-to-end | Bajo | ~20–30 min | Alta |

En conjunto, la restauración es viable con un esfuerzo acotado, siempre que:
- Se disponga de acceso al código de PROD para copiar los archivos.
- Se respeten las dependencias (SancionesHelper, QrMesaTokenHelper, etc.).
- Se alinee el esquema de `partiresul` con lo que espera el código (valores de `estatus` y columnas `origen_dato`, `foto_acta`).

---

## 8. Checklist de validación

Después de aplicar los cambios, verificar:

- [ ] `actions/public_score_submit.php` existe y la ruta POST responde correctamente.
- [ ] `public/public_mesa_input.php` existe y carga los datos de la mesa con `t`, `m`, `r`, `token`.
- [ ] Los QR de las hojas abren `public_mesa_input.php` sin error 404.
- [ ] Al enviar un acta desde el móvil (resultados + foto), se guarda en `partiresul` con `origen_dato='qr'` y `estatus='pendiente_verificacion'`.
- [ ] La acción "Verificar actas" lista torneos con actas pendientes.
- [ ] Al seleccionar una mesa pendiente se cargan jugadores, puntos y foto.
- [ ] "Aprobar" actualiza `estatus` a `'confirmado'` y recalcula estadísticas.
- [ ] "Rechazar" limpia resultados/foto y deja la acta lista para reenvío.
- [ ] El directorio `upload/actas_torneos/` existe y es escribible por el servidor web.
