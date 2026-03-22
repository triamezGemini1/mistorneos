# Plan de refactor: `panel-moderno.php` — división de archivos y responsabilidades

Documento generado para alinear implementación con **vista limpia**, **inyección vía `$view_data`** y **modularización**. Referencia de líneas aproximadas respecto a `modules/gestion_torneos/panel-moderno.php` (revisar tras ediciones).

---

## 1. Objetivo

- Reducir `panel-moderno.php` a un **orquestador** (includes de partials + carga de assets).
- Centralizar **cálculos, consultas PDO y flags de negocio** fuera de la vista (servicio + `case 'panel'`).
- Mantener **sin cambios** la lógica de dominó en **Mano Nula / Chancletas / Zapatos** (no vive en este panel; está en `lib/ParejasResultadosService.php`, `registrar-resultados-v2.php`, etc.).

---

## 2. Capa de datos (backend): qué crear y qué función cumple

| Archivo / símbolo | Función |
|-------------------|--------|
| **`lib/PanelTorneoViewData.php`** (clase recomendada) | Fábrica única: recibe `torneo_id`, usuario y contexto (`standalone` o no), devuelve **un array asociativo** listo para `extract()` en la vista. Evita inflar más `torneo_gestion.php`. |
| **`modules/torneo_gestion.php` → `case 'panel'`** | Solo **delegar**: `$view_data = PanelTorneoViewData::build($torneo_id, …);` (o función global thin wrapper). Sin lógica nueva larga aquí. |
| **`obtenerDatosPanel($torneo_id)`** (existente) | Base de datos del panel: torneo, rondas agregadas, conteos, `estadisticas`, flags `puede_generar_ronda`, etc. **Opción A:** el servicio lo llama y enriquece. **Opción B:** mover su contenido gradualmente al servicio. |

### 2.1 Lógica / consultas a **extraer de la vista** (hoy en `panel-moderno.php`)

| Origen (aprox.) | Qué hace | Destino propuesto |
|-----------------|----------|-------------------|
| Líneas 14–35: fallback `SELECT` torneo si falta `$torneo` | Consulta defensiva; no debería duplicar `obtenerTorneo` del case | Servicio: confiar en datos del controlador; si hace falta, una sola consulta en el servicio. |
| Líneas 37–77: modalidad, `torneo_bloqueado_inscripciones`, `puedeCerrar`, `mostrar_aviso_20min`, countdown | Reglas de UI + negocio mezcladas | Servicio: `panel_flags` o claves sueltas en `$view_data`. |
| Líneas 92–104: `primera_mesa` con `MIN(mesa)` | PDO preparado | Servicio / helper `obtenerPrimeraMesaRonda($torneoId, $ronda)`. |
| Defaults `actas_pendientes_count`, `mesas_verificadas_count`, `mesas_digitadas_count`, `ultima_ronda_tiene_resultados` | Hoy la vista asume `isset`; **no están en `obtenerDatosPanel`** | Servicio: consultas o llamadas a helpers ya existentes en `torneo_gestion.php` (buscar actas / auditoría) y rellenar siempre. |

### 2.2 Contrato sugerido: claves de `$view_data` (para la vista)

Documentar en PHPDoc de la clase. Ejemplo (no exhaustivo):

- `torneo`, `torneo_id`
- `rondas` / `rondas_generadas`
- `ultima_ronda`, `proxima_ronda`, `totalRondas` (desde `torneo['rondas']`)
- `estadisticas` (array)
- `puede_generar_ronda`, `mesas_incompletas`, `is_locked` (locked)
- `inscritos_confirmados`, `total_inscritos`, `total_equipos`, …
- `primera_mesa` (nullable int)
- `ultima_ronda_tiene_resultados` (bool)
- `actas_pendientes_count`, `mesas_verificadas_count`, `mesas_digitadas_count`
- `correcciones_cierre_at`, `countdown_fin_timestamp`, `mostrar_aviso_20min`, `puede_cerrar_torneo`
- `torneo_bloqueado_inscripciones`
- Flags modalidad: `es_modalidad_equipos`, `es_modalidad_parejas`, `es_modalidad_parejas_fijas`, `es_modalidad_equipos_o_parejas`
- `label_modalidad` (string ya resuelto: Individual / Parejas / …) — evita `if` en la vista
- `podios_action` (`podios` vs `podios_equipos`) y `url_podios` ya construida
- `base_url`, `use_standalone`, `tiempo_ronda_minutos` (int saneado)
- `page_title` (opcional)

La vista solo hace `extract($view_data)` y renderiza.

---

## 3. Partials PHP (`modules/gestion_torneos/partials/`)

Carpeta nueva recomendada: **`modules/gestion_torneos/partials/panel/`** para no mezclar con otros módulos.

### 3.1 Tres archivos del prompt original (mapeo a contenido real)

| Archivo | Función / contenido tomado del panel actual |
|---------|-----------------------------------------------|
| **`_header_stats.php`** | Breadcrumb + cabecera del torneo (nombre, fecha, modalidad, rondas, ID) + **mensajes flash** (success/error sesión) + alertas **actas pendientes** + bloque **auditoría** verificadas/digitadas + **banner “Ronda actual”** (stats, mesas, inscritos/equipos) + avisos **cronómetro cierre** (countdown / “puede finalizar”) según líneas ~134–277. |
| **`_rondas_list.php`** | En el código **no hay** una tabla HTML iterando todas las rondas; hay un **resumen de ronda actual** y datos en `$rondas_generadas`. Este partial debe: (1) renderizar el estado actual y pendientes de mesas; (2) **opcionalmente** listar filas `rondas_generadas` (num_ronda, mesas, jugadores, fecha) en tabla responsive **mobile-first**. |
| **`_acciones_torneo.php`** | Las **tres columnas** de acciones: Gestión de mesas / Operaciones / Resultados (aprox. líneas 455–742), incluyendo formularios POST y enlaces. |

### 3.2 Partials adicionales recomendados (el archivo supera lo que cabe en 3 trozos)

| Archivo | Función |
|---------|--------|
| **`_assets_panel.php`** | `<link>` a `design-system.css`, `modern-panel.css`; condicional Tailwind standalone (`output.css` + `tailwind.config` inline si aplica). |
| **`_cronometro_ronda.php`** | Botón activar cronómetro + overlay HTML + **script inline** del cronómetro (líneas ~279–443) o, mejor, `require` de partial que solo enlaza `public/assets/js/panel-cronometro-ronda.js` (extracción JS). |
| **`_alerta_torneo_cerrado.php`** | Bloque “Torneo cerrado” (líneas ~445–453). |
| **`_modal_importacion_masiva.php`** | Modal Bootstrap importación CSV/Excel (líneas ~748–781). |
| **`_scripts_panel.php`** | Scripts: `DOMContentLoaded` generar ronda, countdown cierre, `actualizarEstadisticasConfirmar`, `eliminarRondaConfirmar`, `confirmarCierreTorneo`, IIFE importación masiva (líneas ~783–1208) — idealmente **extraer a JS** (`public/assets/js/panel-*.js`) y dejar aquí solo `<script src="...">`. |

---

## 4. Assets front (JS/CSS)

| Archivo propuesto | Función |
|-------------------|--------|
| `public/assets/js/panel-cronometro-ronda.js` | Lógica del overlay cronómetro (audio, drag, timers). |
| `public/assets/js/panel-cierre-countdown.js` | Cuenta regresiva `.countdown-tiempo-restante` + reload. |
| `public/assets/js/panel-confirmaciones.js` | Swal: actualizar estadísticas, eliminar ronda, cerrar torneo. |
| `public/assets/js/panel-importacion-masiva.js` | CSV/Excel mapping, fetch a `api/tournament_import_*.php`. |
| `public/assets/css/panel-institutional.css` o ampliar `modern-panel.css` | Paleta **Navy / Gold / Maroon**, cards, breakpoints mobile-first. Si usas Tailwind compilado, actualizar **fuente** (`tailwind.config` / `input.css`) y **rebuild** de `assets/dist/output.css`. |

---

## 5. `panel-moderno.php` final (orquestador)

**Responsabilidad:** `require` de `partials` en orden; **sin** consultas SQL; **sin** bloques grandes de lógica; opcionalmente `extract($view_data)` ya hecho en el controlador.

Ejemplo de orden:

1. `_assets_panel.php`
2. `div.tw-panel` contenedor
3. `_header_stats.php`
4. `_cronometro_ronda.php` (o script externo)
5. `_alerta_torneo_cerrado.php`
6. `div` grid 3 columnas → `_columna_gestion_mesas.php` / `_columna_operaciones.php` / `_columna_resultados.php` **o** un solo `_acciones_torneo.php` que incluya las tres internamente
7. `_modal_importacion_masiva.php`
8. `_scripts_panel.php`

**Meta de líneas &lt; 100:** solo alcanzable si **JS inline** se mueve a archivos `.js` (las líneas de `<script>` cuentan igual).

---

## 6. Integración con otros entrypoints

- **`admin_torneo.php` / `panel_torneo.php`**: siguen usando la misma vista; `$view_data` debe incluir `use_standalone` y `base_url` coherentes (ya contemplado en líneas 10–12 del panel actual).

---

## 7. Seguridad y reglas de negocio

- **PDO:** Toda nueva consulta en el servicio con **sentencias preparadas** (como en el fallback de `primera_mesa`).
- **XSS:** Partials siguen usando `htmlspecialchars` en textos y URLs construidas con `AppHelpers`/`htmlspecialchars` donde corresponda.
- **Mano Nula / Chancletas / Zapatos:** **No** forman parte de este refactor; no editar `ParejasResultadosService` ni vistas de resultados salvo bugfix explícito.

---

## 8. Resumen: archivos nuevos a crear (checklist)

- [ ] `lib/PanelTorneoViewData.php` (o nombre acordado)
- [ ] `modules/gestion_torneos/partials/panel/_header_stats.php`
- [ ] `modules/gestion_torneos/partials/panel/_rondas_list.php`
- [ ] `modules/gestion_torneos/partials/panel/_acciones_torneo.php` (y/o sub-partials `_columna_*.php`)
- [ ] Partials opcionales: `_assets_panel.php`, `_cronometro_ronda.php`, `_alerta_torneo_cerrado.php`, `_modal_importacion_masiva.php`, `_scripts_panel.php`
- [ ] JS en `public/assets/js/panel-*.js` (recomendado)
- [ ] Ajustes CSS / Tailwind para paleta institucional

---

## 9. Verificación manual sugerida

- Panel con torneo **sin rondas** / **con rondas** / **cerrado (locked)**.
- Modalidades **individual, parejas, equipos, parejas fijas**.
- Importación masiva + `#importacion-masiva`.
- Cronómetro de ronda + countdown de cierre.
- Eliminar última ronda (con y sin resultados en mesas).

---

*Fin del documento de mapeo.*
