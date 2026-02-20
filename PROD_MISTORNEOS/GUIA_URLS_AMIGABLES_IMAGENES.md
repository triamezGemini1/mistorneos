# Guía: URLs Amigables e Optimización de Imágenes

## 📋 Resumen

Este documento explica cómo usar las nuevas funcionalidades implementadas:
- **URLs Amigables** para torneos
- **Optimización de Imágenes** (compresión y WebP)

---

## 🔗 URLs Amigables

### ¿Qué son?

Las URLs amigables convierten direcciones como:
```
/public/torneo_detalle.php?torneo_id=123
```

En URLs más legibles y SEO-friendly:
```
/public/torneo/123/torneo-de-domino-2025
```

### Cómo Funciona

1. **`.htaccess`** reescribe las URLs amigables a las rutas PHP tradicionales
2. **`UrlHelper`** genera slugs a partir de nombres de torneos
3. Los archivos PHP aceptan tanto URLs amigables como tradicionales (compatibilidad)

### Uso en Código

#### Generar URL Amigable

```php
require_once __DIR__ . '/../lib/UrlHelper.php';

// Para un torneo
$url = UrlHelper::torneoUrl(123, "Torneo de Dominó 2025");
// Resultado: /public/torneo/123/torneo-de-domino-2025

// Para resultados
$url = UrlHelper::resultadosUrl(123, "Torneo de Dominó 2025");
// Resultado: /public/resultados/123/torneo-de-domino-2025
```

#### Generar Slug

```php
$slug = UrlHelper::slugify("Torneo de Dominó 2025");
// Resultado: "torneo-de-domino-2025"
```

### Migración de Slugs (Opcional)

Si quieres almacenar slugs en la base de datos para mejor rendimiento:

```bash
# Ejecutar script de migración
php scripts/migrate_tournament_slugs.php
```

Este script:
- Agrega columna `slug` a la tabla `tournaments` (si no existe)
- Genera slugs para todos los torneos existentes
- Maneja duplicados agregando el ID al final

### Reglas de Reescritura (.htaccess)

Las reglas ya están configuradas en `.htaccess`:

```apache
# Torneos
RewriteRule ^public/torneo/([0-9]+)/([a-z0-9-]+)/?$ public/torneo_detalle.php?torneo_id=$1 [L,QSA]

# Resultados
RewriteRule ^public/resultados/([0-9]+)/([a-z0-9-]+)/?$ public/resultados_detalle.php?torneo_id=$1 [L,QSA]
```

### Compatibilidad

✅ **Las URLs antiguas siguen funcionando** - No hay breaking changes
- `/public/torneo_detalle.php?torneo_id=123` → Funciona
- `/public/torneo/123/torneo-de-domino-2025` → Funciona (nuevo)

---

## 🖼️ Optimización de Imágenes

### ¿Qué hace?

1. **Comprime imágenes** (JPEG, PNG, GIF) reduciendo tamaño sin perder calidad visible
2. **Redimensiona** imágenes grandes a tamaños razonables (1920x1080 por defecto)
3. **Genera versiones WebP** automáticamente (formato moderno con mejor compresión)

### Uso en Código

#### Optimizar una Imagen

```php
require_once __DIR__ . '/../lib/ImageOptimizer.php';

$result = ImageOptimizer::optimize(
    'upload/tournaments/afiche.jpg',
    null, // Sobrescribir original
    [
        'quality' => 85,        // Calidad JPEG (0-100)
        'max_width' => 1920,     // Ancho máximo
        'max_height' => 1080,    // Alto máximo
        'create_webp' => true,   // Crear versión WebP
        'webp_quality' => 80     // Calidad WebP
    ]
);

if ($result['success']) {
    echo "Original: " . round($result['original_size'] / 1024, 2) . " KB\n";
    echo "Optimizado: " . round($result['optimized_size'] / 1024, 2) . " KB\n";
    echo "Ahorro: " . $result['savings_percent'] . "%\n";
    if ($result['webp_path']) {
        echo "WebP creado: " . $result['webp_path'] . "\n";
    }
}
```

#### Optimizar un Directorio Completo

```php
$stats = ImageOptimizer::optimizeDirectory(
    'upload/tournaments',
    ['quality' => 85, 'create_webp' => true],
    true // Recursivo
);

echo "Procesados: " . $stats['processed'] . "\n";
echo "Optimizados: " . $stats['optimized'] . "\n";
echo "Ahorro total: " . $stats['total_savings_mb'] . " MB\n";
```

#### Generar HTML con Soporte WebP

```php
// Genera <picture> con fallback automático
echo ImageOptimizer::imageTag(
    'upload/tournaments/afiche.jpg',
    'Afiche del torneo',
    ['class' => 'w-full rounded-lg']
);

// Resultado:
// <picture>
//   <source srcset="...afiche.webp" type="image/webp">
//   <img src="...afiche.jpg" alt="Afiche del torneo" class="w-full rounded-lg" loading="lazy" decoding="async">
// </picture>
```

#### Obtener Mejor Versión Disponible

```php
// Devuelve WebP si existe y el navegador lo soporta, sino la original
$best_image = ImageOptimizer::getBestVersion('upload/tournaments/afiche.jpg');
```

### Script CLI para Optimizar Imágenes Existentes

```bash
# Optimizar todas las imágenes en upload/tournaments
php scripts/optimize_images.php upload/tournaments --recursive --create-webp

# Optimizar con calidad personalizada
php scripts/optimize_images.php upload/logos --quality=90 --webp-quality=85

# Optimizar sin crear WebP
php scripts/optimize_images.php upload/clubs --no-webp

# Opciones disponibles:
# --recursive          Procesar subdirectorios
# --quality=85        Calidad JPEG (0-100)
# --webp-quality=80   Calidad WebP (0-100)
# --max-width=1920    Ancho máximo
# --max-height=1080   Alto máximo
# --no-webp           No crear versiones WebP
```

### Ejemplo de Salida del Script

```
🚀 Iniciando optimización de imágenes...
📁 Directorio: upload/tournaments
🔄 Recursivo: Sí
⚙️  Calidad JPEG: 85
⚙️  Calidad WebP: 80
⚙️  Crear WebP: Sí
📏 Tamaño máximo: 1920x1080

✅ Optimización completada!
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📊 Estadísticas:
   • Archivos procesados: 15
   • Archivos optimizados: 15
   • Archivos fallidos: 0
   • Espacio ahorrado: 2.45 MB
   • Tiempo transcurrido: 3.21 segundos

📝 Detalles por archivo:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📄 afiche-torneo-2025.jpg
   Original: 1.2 MB → Optimizado: 0.45 MB
   Ahorro: 0.75 MB (62.5%)
   ✅ Versión WebP creada
```

### Soporte WebP Automático en .htaccess

El `.htaccess` ya está configurado para servir WebP automáticamente cuando:
- El navegador acepta WebP (`Accept: image/webp`)
- Existe una versión `.webp` del archivo

**No necesitas cambiar tu código HTML** - El servidor maneja esto automáticamente.

---

## 📝 Mejores Prácticas

### URLs Amigables

1. **Usa `UrlHelper`** para generar todas las URLs de torneos
2. **No hardcodees URLs** - Siempre usa los helpers
3. **Mantén compatibilidad** - Las URLs antiguas siguen funcionando

### Optimización de Imágenes

1. **Optimiza antes de subir** - Usa el script CLI para imágenes existentes
2. **Sube imágenes razonables** - No subas imágenes de 10MB
3. **Usa WebP cuando sea posible** - Mejor compresión (30-50% más pequeño)
4. **Mantén calidad 80-85** - Balance entre tamaño y calidad

### Flujo Recomendado

1. **Al subir nueva imagen:**
   ```php
   // Después de subir
   $result = ImageOptimizer::optimize($uploaded_path, null, [
       'quality' => 85,
       'create_webp' => true
   ]);
   ```

2. **En HTML:**
   ```php
   // Usar ImageOptimizer::imageTag() para soporte WebP automático
   echo ImageOptimizer::imageTag($image_path, $alt_text);
   ```

3. **Para imágenes existentes:**
   ```bash
   php scripts/optimize_images.php upload/tournaments --recursive
   ```

---

## 🔧 Troubleshooting

### URLs Amigables No Funcionan

1. **Verifica que mod_rewrite esté habilitado:**
   ```bash
   # En Apache
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   ```

2. **Verifica permisos de .htaccess:**
   - Debe ser legible por Apache
   - No debe tener restricciones en httpd.conf

3. **Verifica RewriteBase:**
   - Si el sitio está en subdirectorio, ajusta `RewriteBase` en `.htaccess`

### Optimización de Imágenes Falla

1. **Verifica extensión GD:**
   ```php
   if (!function_exists('imagejpeg')) {
       echo "GD extension no está instalada";
   }
   ```

2. **Verifica permisos de escritura:**
   - El directorio debe ser escribible por PHP

3. **Verifica memoria:**
   - Imágenes muy grandes pueden requerir más memoria PHP

---

## 📊 Beneficios

### URLs Amigables

- ✅ **Mejor SEO** - Google prefiere URLs descriptivas
- ✅ **Más fáciles de compartir** - URLs legibles
- ✅ **Mejor UX** - Los usuarios entienden qué están viendo
- ✅ **Compatibilidad** - URLs antiguas siguen funcionando

### Optimización de Imágenes

- ✅ **30-50% menos tamaño** - Páginas cargan más rápido
- ✅ **Mejor experiencia móvil** - Menos datos consumidos
- ✅ **WebP moderno** - Mejor compresión que JPEG/PNG
- ✅ **Automático** - El servidor sirve la mejor versión

---

**Fecha de implementación:** 2025-01-27
**Versión:** 1.0












