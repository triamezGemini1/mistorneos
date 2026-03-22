# Por qué sigue el log aunque borraste guardar_equipo.php

## Qué ocurre

Con **OPcache** activo, PHP guarda en **RAM** el bytecode compilado de cada script **por ruta de archivo**. Eso incluye el código que hace:

```text
=== INICIO GUARDAR EQUIPO ===
POST recibido: ...
```

**Borrar el `.php` en disco no vacía OPcache de inmediato.** Los workers de PHP-FPM pueden **seguir ejecutando** esa copia en memoria hasta que:

- reinicies **PHP-FPM** (o Apache con mod_php), **o**
- se vacíe la caché de ese worker (poco fiable si hay muchos workers).

Por eso puedes ver en el log el mismo mensaje **después** de eliminar el archivo.

## Qué hacer

1. **Reiniciar PHP-FPM** (lo más fiable):
   ```bash
   sudo systemctl restart php-fpm
   # o php8.1-fpm / php8.2-fpm
   ```

2. **No depender del nombre `guardar_equipo.php`** para el flujo nuevo: subir **`guardar_equipo_v2.php`** y que el formulario haga POST solo a **v2** (nunca cacheado con el código viejo en esa ruta).

3. **Volver a poner** un `guardar_equipo.php` mínimo en disco (opcional) que solo haga `require 'guardar_equipo_v2.php'`, para quien aún llame al nombre antiguo **después** de reiniciar PHP (así no dan 404).

4. **Evitar volver a subir** paquetes que traigan el `guardar_equipo.php` **largo antiguo** (por ejemplo carpeta `PROD_MISTORNEOS` en el repo tenía esa versión; ya debe estar alineada con v2 + wrapper).

## Cómo saber si ya es código nuevo

En el log debe aparecer:

```text
=== INICIO GUARDAR EQUIPO V2 (opcache-safe) ===
POST/input recibido: ...
```

Si sigues viendo `POST recibido` sin "input", sigue activo el bytecode viejo o hay **otra copia** del script en otro directorio que atiende la misma URL.
