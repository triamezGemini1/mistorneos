# Plan de rendimiento LVD — Landing SPA (`rediseño-interfaz-2026`)

Documento de auditoría y pasos técnicos para reducir **LCP** (Largest Contentful Paint) y **CLS** (Cumulative Layout Shift) en `public/landing-spa.php` y el consumo de `public/api/landing_data.php`.

**Fecha de referencia:** auditoría sobre el código actual de la rama de rediseño.

---

## 1. Hallazgos de la auditoría rápida

### 1.1 ¿El Hero retrasa el LCP por Vue?

**No de forma directa en el sentido “el H1 solo existe dentro de Vue”.** El Hero y la barra superior ya están **duplicados en HTML estático** dentro del primer bloque `v-if="loading"` en `landing-spa.php` (líneas ~261–302: `#static-landing-shell`, `#hero`, logo con `fetchpriority="high"`).

**Pero el LCP puede seguir siendo malo por la cadena crítica:**

| Etapa | Qué ocurre |
|--------|------------|
| 1 | El HTML incluye `#app` con plantilla Vue en línea (`v-if` / `v-else-if` / `<landing-content>`). **Hasta que Vue no monta**, el navegador no interpreta `v-if`: pueden mostrarse varios bloques hermanos a la vez o texto crudo (`{{ error }}`), generando **CLS** y un candidato a LCP distinto al Hero final. |
| 2 | Scripts al final del `<body>`: `vue.global.prod.js` (unpkg) y luego `assets/landing-spa.js` — **ejecución secuencial y bloqueante** respecto al orden de carga al final del documento. |
| 3 | Tras `createApp(...).mount('#app')`, `onMounted` llama a `fetch(APP_CONFIG.apiUrl)` → `landing_data.php`. Mientras `loading === true`, el usuario ve el shell estático; al terminar el JSON, Vue pasa a `<landing-content>` y **reemplaza** el Hero por otro casi idéntico (plantilla `#landing-template`) → **riesgo alto de CLS** (salto de layout / parpadeo). |

**Conclusión:** Un LCP del orden de **varios segundos** (p. ej. ~16 s en redes lentas o TTFB alto) suele deberse a la **suma** de:

- Descarga + parse de **Vue desde unpkg** (CDN externo, sin `defer` explícito en el mismo orden que el bundle local).
- Descarga de **`output.css`** (cargado con truco `media="print" onload` → el estilo “real” puede aplicarse tarde; el LCP puede quedar esperando tipografía/layout).
- **`landing_data.php` en frío** (MISS de caché, consultas + JSON grande) después del mount.
- **Fuentes** (Google Fonts, mismo patrón `print`/`onload`).

No es solo “Vue en el Hero”, sino **hidratar un árbol grande con Vue antes de que el usuario vea un estado estable**, más **CSS y API en paralelo**.

### 1.2 `public/api/landing_data.php`

- **Rutas de activos:** Construye `$baseUrl` con `AppHelpers::getPublicUrl()` (o `app_base_url()` + `/public`), coherente con subcarpetas (`/pruebas/public`, etc.). Los logos usan `AppHelpers::imageUrl()` cuando existe.
- **No hay URLs hardcodeadas** a un dominio antiguo tipo `mistorneos` en este archivo.
- **Caché:** APCu o `storage/cache/*.json`, TTL 90 s — ayuda al TTFB en caliente; el primer MISS puede ser costoso.

### 1.3 `public/landing-spa.php`

- Comentario de ejemplo menciona `mistorneos_beta` como **ruta de despliegue**, no como origen de assets.
- CSS principal: `assets/dist/output.css` vía `$base_url` (correcto para el rediseño).
- Vue: `https://unpkg.com/vue@3/dist/vue.global.prod.js` (tercero; conviene versionar y/o alojar en propio dominio para **preconnect** y caché).

---

## 2. Plan “Static Shell” (LVD al instante)

Objetivo: que el usuario vea **nav + Hero + tipografía base** en la **primera pintura**, sin depender de Vue ni del `fetch` de la API.

### Opción recomendada (mayor impacto en LCP/CLS)

1. **Sacar el Hero + nav del árbol Vue**  
   - Renderizar en PHP un bloque único (p. ej. `public/includes/landing_lvd_static_shell.php`) **antes** de `<div id="app">`.  
   - Contenido: mismo markup que hoy tiene `#static-landing-shell` (sin `v-if`).  
   - El LCP (típicamente **H1** o **logo**) queda en HTML puro, visible **antes** de cualquier script.

2. **Reducir `#app` al contenido dinámico**  
   - Montar Vue solo en `#app` debajo del Hero, o usar un contenedor `#app-dynamic` sin duplicar Hero en la plantilla `landing-template`.  
   - Elimina el **salto** entre “Hero de loading” y “Hero de landing-content”.

3. **`v-cloak` solo donde haga falta**  
   - Si quedan restos de plantilla con `{{ }}`, usar `[v-cloak] { display: none !important; }` **solo** en nodos hijos de `#app`, no en el shell estático.

### Opción incremental (menos invasiva)

1. Añadir **`defer`** a `vue.global.prod.js` y a `landing-spa.js` (mantener orden: primero Vue, luego app).  
2. **`<link rel="modulepreload">` o `preload`** para el JS crítico local (`landing-spa.js`) y, si se autoaloja Vue, para ese archivo.  
3. **Preconnect:** `link rel="preconnect"` a `unpkg.com` (mientras se use) y al origen de la propia app si el API está en el mismo host.  
4. **CSS crítico del Hero** (unas pocas reglas) en `<style>` inline mínimo en `<head>`; el `output.css` completo puede seguir asíncrono o cargarse después del primer paint.

### Opción datos “antes de pintar” (LCP estable con contenido rico)

- **SSR parcial en PHP:** la primera respuesta HTML incluye ya un JSON embebido `<script type="application/json" id="landing-initial-data">` generado llamando a la misma lógica que `landing_data.php` (o un `include` del servicio).  
- Vue en `onMounted` usa esos datos si existen y **no muestra** estado loading para el fold superior, o hace `fetch` solo para refresco.

---

## 3. Verificación de rutas (rediseño vs antiguas)

| Recurso | Estado |
|---------|--------|
| `output.css` | Ruta bajo `$base_url . 'assets/dist/output.css'` — alineada al pipeline Tailwind del proyecto. |
| `landing-spa.js` | `$base_url . 'assets/landing-spa.js'`. |
| Logo | `AppHelpers::getAppLogo()` / `view_image.php?path=...` — dinámico, no atado al nombre “mistorneos”. |
| API | `$base_url . 'api/landing_data.php'` — correcto para cualquier subcarpeta. |
| Enlaces internos | `landing-spa.php`, `login.php`, etc. con `$base_url` — consistente. |

**Recomendación:** en búsquedas globales, evitar paths absolutos a `/mistorneos/...`; mantener **siempre** `AppHelpers::getPublicUrl()` o `$base_url` generado en PHP.

---

## 4. Pasos técnicos priorizados (LCP + CLS)

### Fase A — Rápida (bajo riesgo)

1. **Preconnect** a `https://unpkg.com` (y `https://cdnjs.cloudflare.com` si Font Awesome sigue en CDN).  
2. **`defer`** en ambos scripts finales; verificar que `mount` sigue ocurriendo tras el DOM listo.  
3. **Reservar espacio** para el logo (`width`/`height` ya presentes) y para el bloque Hero (`min-height` suave) para reducir CLS.  
4. Revisar si el truco `media="print" onload` en `output.css` es necesario; valorar **una hoja crítica inline** + carga diferida del bundle Tailwind completo.

### Fase B — Media (arquitectura SPA)

5. **Static shell fuera de `#app`** y **un solo Hero** en el árbol (ver sección 2).  
6. **Self-host de Vue** (misma versión fija) en `public/assets/vendor/vue.global.prod.js` con **Cache-Control** largo + **SRI** opcional.  
7. **Prefetch** de `landing_data.php` con `<link rel="prefetch">` (opcional; no sustituye inline JSON si se busca LCP &lt; 2.5s).

### Fase C — Avanzada (LCP + TTFB)

8. **Payload inicial en HTML** (JSON embebido) para evitar esperar el round-trip del `fetch` para el contenido above-the-fold.  
9. **HTTP cache** en el borde (CDN) para `landing-spa.php` estático donde aplique, o página estática generada + hidratación.  
10. **Imágenes:** `logo` ya con `fetchpriority="high"`; para cards de eventos, mantener `loading="lazy"` y dimensiones explícitas donde sea posible.

### CLS concretamente

- Eliminar **duplicación Hero** (loading vs template).  
- No mostrar bloques `v-else-if="error"` en el DOM hasta que Vue esté activo, o ocultarlos con CSS hasta `mounted`.  
- Evitar insertar **marquee / grids** sin altura mínima antes de que lleguen los datos.

---

## 5. Cómo medir en esta rama

1. **Chrome DevTools → Lighthouse** (Mobile, throttling 4G).  
2. **Performance → Web Vitals** en campo (si hay RUM).  
3. En **Performance** panel: marcar **LCP**, revisar **Main thread** en el momento del LCP (Vue mount vs primera pintura).  
4. Cabecera **`X-Landing-Cache: HIT/MISS`** en `landing_data.php` para correlacionar TTFB de la API.

---

## 6. Referencias de archivos

- `public/landing-spa.php` — shell, estilos inline extensos, montaje `#app`, carga Vue + `landing-spa.js`.  
- `public/assets/landing-spa.js` — `loading`, `fetch(apiUrl)`, `LandingContent`, plantilla `#landing-template`.  
- `public/api/landing_data.php` — `LandingDataService`, caché, `base_url` en JSON.

---

*Fin del plan. Ajustar prioridades según métricas reales tras un baseline Lighthouse en `rediseño-interfaz-2026`.*
