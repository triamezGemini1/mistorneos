# Optimización Core Web Vitals - TBT y FCP

## Diagnóstico del problema

| Métrica | Actual | Objetivo | Causa principal |
|---------|--------|----------|-----------------|
| **TBT** | 4,330ms | <200ms | JS bloqueante, scripts inline grandes, Tailwind CDN (landing) |
| **LCP** | 6.1s | <2.5s | CSS render-blocking, cascada de recursos |
| **FCP** | 4.2s | <1.5s | Bootstrap + dashboard.css bloqueantes |

**TTFB 21ms** → El backend es rápido. El problema es 100% frontend.

---

## Estrategia implementada

1. **CSS crítico inline** – Layout above-the-fold sin esperar CDN (~2KB)
2. **Carga asíncrona de CSS** – Bootstrap, dashboard.css, Font Awesome, Google Fonts con `media="print" onload="this.media='all'"`
3. **Preconnect** – Conexiones tempranas a CDNs (cdn.jsdelivr.net, cdnjs.cloudflare.com, fonts.googleapis.com)
4. **JS diferido** – Script inline extraído a `dashboard-init.js` con `defer`, usa `requestIdleCallback` para init
5. **Sin preload duplicado** – Eliminado preload que causaba doble descarga

---

## Archivos modificados

- `public/includes/layout.php` – Head y footer optimizados
- `public/includes/admin_torneo_layout.php` – Head optimizado
- `public/assets/dashboard-init.js` – Script de init extraído (nuevo)
- `public/assets/critical.css` – Referencia (el crítico está inline en layout.php)

---

## Carga condicional en PHP (plantilla para futuras mejoras)

Para Font Awesome y scripts de tablas/gráficos solo cuando sean estrictamente necesarios:

```php
<?php
// Al inicio del layout, antes del <head>
$needs_font_awesome = true; // layout.php siempre lo usa en sidebar
$needs_datatables = in_array($current_page, ['users', 'registrants', 'invitations']);
$needs_sweetalert = in_array($current_page, ['inscribir_sitio', 'galeria_fotos']);
?>
```

En el `<head>`:
```php
<?php if ($needs_font_awesome): ?>
<link rel="stylesheet" href="..." media="print" onload="this.media='all'">
<?php endif; ?>
```

---

## Landing.php – CRÍTICO (si es la página con Performance 36)

La landing usa **Tailwind CDN** (`cdn.tailwindcss.com`) que compila CSS en tiempo real en el navegador. Esto genera **TBT extremo** (~2-3 segundos solo de Tailwind).

**Recomendación:** Compilar Tailwind en build y servir CSS estático:
```bash
npm init -y && npm install -D tailwindcss
npx tailwindcss -i ./input.css -o ./public/assets/tailwind.min.css --minify
```

Reemplazar en `landing.php`:
```html
<!-- Antes (bloquea TBT) -->
<script src="https://cdn.tailwindcss.com" defer></script>

<!-- Después -->
<link rel="stylesheet" href="assets/tailwind.min.css" media="print" onload="this.media='all'">
```

---

## Resultados esperados

- **FCP:** de ~4.2s a <1.5s (CSS crítico inline + async)
- **TBT:** reducción significativa al mover ~170 líneas de JS inline a archivo con defer
- **LCP:** mejora por cascada más rápida de recursos
