# Diagnóstico: invitaciones caídas en producción

## 1. Dónde ver el error exacto (EL "POR QUÉ")

Este proyecto es **PHP (no Laravel)**. Los errores no están en `storage/logs/laravel.log`.

- **Apache:** revisar `error_log` del servidor (ruta típica: `/var/log/apache2/error.log` o la que defina `ErrorLog` en el vhost).
- **PHP:** si está habilitado `log_errors`, los Fatal/QueryException van al archivo indicado por `error_log` en `php.ini` (o al error_log de Apache si no está definido).
- **Tras el fix:** si falla la creación de invitaciones, el mensaje también se escribe con `error_log('Invitacion_clubes: ...')` en ese mismo log.

**Qué buscar en el log:**  
`Fatal error`, `QueryException`, `SQLSTATE`, `Column not found`, `integrity constraint`, `NULL`, `invitaciones`, `directorio_clubes`.

---

## 2. Verificación de base de datos (posible discrepancia)

Comparar en **producción** vs **desarrollo** la estructura de las tablas.

### Tabla `invitaciones`

El flujo en `modules/invitacion_clubes.php` inserta solo columnas que existan en la tabla (usa `SHOW COLUMNS` y hace intersección con los datos). Si en producción falta una columna **NOT NULL** sin default que sí existe en desarrollo, el INSERT fallará.

Columnas que el código intenta usar al crear una invitación:

- `torneo_id`, `club_id`, `invitado_delegado`, `invitado_email`, `acceso1`, `acceso2`, `usuario`, `club_email`, `club_telefono`, `club_delegado`, `token`, `estado`
- Si existe: `id_directorio_club`

Comprobar en producción:

- Que existan esas columnas (o que las que falten acepten NULL o tengan default).
- Que **`token`** y las que uséis como obligatorias no sean NOT NULL sin valor por defecto si a veces no se envían.

Migraciones aplicadas en desarrollo que deben estar (o ser equivalentes) en producción:

- `sql/add_id_usuario_vinculado_invitaciones.sql` → columna `id_usuario_vinculado` (NULL permitido).

### Tabla `directorio_clubes`

El código hace `SELECT id, nombre, direccion, delegado, telefono, email FROM directorio_clubes`.  
Solo necesita que existan esas columnas.

Migración opcional en producción:

- `sql/add_id_usuario_directorio_clubes.sql` → columna `id_usuario` (NULL). Si no está aplicada, no debería romper el envío de invitaciones, pero conviene tenerla si el resto del sistema la usa.

### Comandos útiles para comparar

En desarrollo y en producción:

```sql
SHOW COLUMNS FROM invitaciones;
SHOW CREATE TABLE invitaciones;

SHOW COLUMNS FROM directorio_clubes;
SHOW CREATE TABLE directorio_clubes;
```

Comparar salidas: mismas columnas, mismos tipos y mismos NULL/NOT NULL/DEFAULT.

---

## 3. Formulario y redirección (evitar “sin formato” en producción)

- **Formulario:** `<form method="post" action="index.php?page=invitacion_clubes&torneo_id=<?= $torneo_id ?>">`. La acción es **relativa** para que el POST vaya siempre al mismo entry point que la página actual (también en producción con subcarpeta o proxy).
- **Redirect:** Tras guardar o error se usa redirección **relativa**: `header('Location: index.php?' . http_build_query($params))`. Así el navegador vuelve al mismo `index.php` (con layout y CSS) y no a otra ruta que pueda servirse sin formato.
- **Inclusión del módulo:** En **GET**, `index.php` incluye `layout.php` y el layout incluye el módulo **dentro de `<main>`**, es decir, después de cargar `<head>` y CSS. Por eso la página se ve con formato. En **POST**, `index.php` incluye solo el módulo (sin layout); el módulo no debe imprimir nada y debe hacer siempre redirect.

## 4. Cambios aplicados en el código (reparación del flujo)

- **Transacción:** la creación de invitaciones (inserciones en `clubes` e `invitaciones`) se ejecuta dentro de `beginTransaction()` / `commit()`. Si algo falla, se hace `rollBack()` y no se dejan datos a medias.
- **Redirect siempre:** tanto en éxito como en error se hace `header('Location: index.php?...')` (relativo) y `exit`. En error ya no se sigue al HTML, por lo que no se rompe el formato de la app.
- **Buffer de salida:** al entrar al POST se hace `ob_start()` y antes de cada `header('Location')` se llama a `ob_end_clean()`, para que ningún `echo`/salida accidental impida el redirect.
- **Error en log:** en el `catch` se hace `error_log('Invitacion_clubes: ' . $e->getMessage() ...)` para que el error exacto quede en el log del servidor (Apache/PHP).

La redirección tras éxito o error vuelve a la misma pantalla de invitación de clubes (mismo path, con `page=invitacion_clubes&torneo_id=...`) para que se cargue el layout correctamente.

---

## 5. Regla de oro

No debe haber `echo`, `print_r`, `var_dump` ni ninguna salida antes de `header('Location')` en el flujo de invitaciones. El uso de `ob_start()` y `ob_end_clean()` mitiga salidas accidentales de dependencias.

---

**Resumen:** Revisar el `error_log` de Apache/PHP en producción para el mensaje exacto; comparar `invitaciones` y `directorio_clubes` con desarrollo; con el fix aplicado, cualquier fallo debería verse en el log y redirigir a la misma página con mensaje de error en lugar de romper el formato.
