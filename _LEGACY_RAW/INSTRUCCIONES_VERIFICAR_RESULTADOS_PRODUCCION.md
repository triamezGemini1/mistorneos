# Cómo ver la nueva vista "Verificar resultados" en producción

## Qué debe verse

- **Barra arriba:** A la izquierda: Pareja A (nombres) y su input de puntos; debajo Pareja B (nombres) y su input. A la derecha de esa barra: los 3 botones (Aprobar, Rechazar, Volver).
- **Abajo:** Foto del acta a todo el ancho; controles de zoom/giro en una esquina sobre la imagen.

---

## Pasos para que funcione en producción

### 1. Saber dónde está la app en el servidor

En producción, la aplicación está en una ruta como:

- `public_html/mistorneos/`  
- o `www/`  
- o `htdocs/mistorneos/`  

Anota esa ruta (raíz del proyecto). Ahí dentro debe existir la carpeta **`modules`**.

### 2. Ruta exacta del archivo en el servidor

El archivo que carga la vista debe estar en:

```
[RAÍZ_DE_TU_APP]/modules/tournament_admin/views/verificar_resultados.php
```

Ejemplo: si la raíz es `public_html/mistorneos`, el archivo es:

`public_html/mistorneos/modules/tournament_admin/views/verificar_resultados.php`

### 3. Qué archivo subir desde tu PC

Usa **uno** de estos (tienen el mismo contenido):

- `c:\wamp64\www\mistorneos\modules\tournament_admin\views\verificar_resultados.php`
- o `c:\wamp64\www\mistorneos\PROD_MISTORNEOS\modules\tournament_admin\views\verificar_resultados.php`

Súbelo **sustituyendo** el archivo que esté en el servidor en la ruta del paso 2.

### 4. Cómo subir

- **FTP / administrador de archivos:** Navega en el servidor hasta `modules/tournament_admin/views/` y sube/reemplaza solo `verificar_resultados.php`.
- **Si despliegas con ZIP:** Incluye en el ZIP la carpeta `modules` con ese archivo actualizado y descomprime/reemplaza en el servidor para que `modules/tournament_admin/views/verificar_resultados.php` quede actualizado.

### 5. Comprobar que el servidor usa el archivo nuevo

1. En producción, entra a **Verificar resultados (QR)** y elige una mesa.
2. Clic derecho en la página → **Ver código fuente** (o "View Page Source").
3. Busca (Ctrl+F): **`verificar_resultados v2`**
   - Si aparece el comentario `<!-- Vista verificar_resultados v2: barra Pareja A/B ... -->` → el servidor está usando el archivo nuevo. Deberías ver la barra con las dos parejas y los botones a la derecha.
   - Si no aparece → en el servidor se sigue usando otro archivo o otra ruta. Revisa que hayas subido el archivo a `modules/tournament_admin/views/verificar_resultados.php` (respecto a la raíz de la app).

### 6. Caché

- En el navegador: **Ctrl + F5** (recarga forzada) al probar.
- Si en el servidor usas **PHP OPcache**: reinicia PHP o limpia OPcache para que cargue el `.php` nuevo.

---

## Resumen en una frase

Sube el archivo `verificar_resultados.php` desde tu PC a la ruta **`modules/tournament_admin/views/verificar_resultados.php`** en el servidor (respecto a la raíz de la aplicación) y reemplaza el que haya; luego recarga con Ctrl+F5 y comprueba con "Ver código fuente" que aparece el texto "verificar_resultados v2".
