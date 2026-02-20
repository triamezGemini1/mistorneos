# Resumen del chat — Desktop, panel y ciclo 8 pasos

Documento generado para revisión posterior. Recoge los temas tratados en esta sesión.

---

## 1. Panel de control: condiciones de activación/desactivación

- **Inscripciones (ambos botones):**
  - **Torneo individual:** se bloquean cuando ya existe la **segunda ronda** (hay registros en `partiresul` con `partida >= 2`).
  - **Torneo por equipos:** se bloquean **al iniciar el torneo** (cuando hay al menos una ronda generada).
- **Generar Ronda:** se bloquea si el torneo tiene al menos una ronda y queda **alguna mesa sin cargar** en esa última ronda (`registrado = 0`). Se desbloquea cuando todas las mesas de la ronda actual están cargadas.
- En la tabla de torneos se añadió la columna **Modalidad** (Individual / Equipos), usando `modalidad = 3` para equipos.
- **Resultados / Reportes:** según el torneo seleccionado se muestran enlaces a reporte general (individual) o a equipos resumido, equipos detallado y reporte general.

---

## 2. Ciclo de 8 pasos (Desktop)

Se verificó y completó el flujo en `admin_panel.php` y archivos asociados:

| Paso | Acción | Archivo / Notas |
|------|--------|------------------|
| 1 | Crear Torneo | `public/desktop/crear_torneo.php` — guarda en SQLite local |
| 2 | Inscripción | `public/desktop/inscribir.php` — guarda en `inscritos` (SQLite) |
| 3 | Generar Ronda | Botón en panel → `generar_ronda.php` usa `core/logica_torneo.php` y **MesaAsignacionService** (o MesaAsignacionEquiposService); lee `modalidad` de `tournaments` local |
| 4 | Cuadrícula por ID | `public/desktop/cuadricula.php` — SELECT con **ORDER BY id_usuario ASC** para localizar jugadores rápido |
| 5 | Imprimir Hojas | `public/desktop/imprimir_hojas.php` — vista imprimible desde `partiresul` (Imprimir / Guardar como PDF en el navegador) |
| 6 | Ingresar Resultados | `public/desktop/resultados.php` → envía a `save_resultados.php` |
| 7 | Clasificación | Se ejecuta **automáticamente** en `save_resultados.php` vía `actualizarEstadisticasInscritos($torneo_id)` antes de permitir el paso 8 |
| 8 | Generar Ronda X+1 | Bloqueado si hay mesas pendientes en la ronda actual |

- **Base de datos:** todo el ciclo usa la SQLite local (p. ej. `public/desktop/data/mistorneos_local.db` cuando se sirve desde `public`).
- En el panel se añadió una tarjeta **“Ciclo de 8 pasos”** con enlaces a cada paso.

---

## 3. Diagnóstico de rutas — Servidor PHP (puerto 8000)

- **Problema:** al ejecutar `php -S localhost:8000` desde la raíz del proyecto, la URL `http://localhost:8000/desktop/` daba **Not Found**.
- **Causa:** la raíz documental era `mistorneos`. La carpeta **`mistorneos/desktop/`** (junto a `public/`) es solo core/lógica y **no contiene `index.php`**. El `index.php` del escritorio está en **`public/desktop/`**.
- **Comandos ejecutados:**
  - `Get-ChildItem -Recurse -Name` (equivalente a `ls -R`).
  - `Get-ChildItem -Filter index.php -Recurse` para localizar todos los `index.php`.
- **Resultado:** en **`desktop/`** (raíz del proyecto) **no existe ningún `index.php`**. Sí existe en **`public/desktop/index.php`**.
- **Solución:**
  - Comando: `cd c:\wamp64\www\mistorneos` y luego `php -S localhost:8000 -t public`
  - URL del escritorio: **`http://localhost:8000/desktop/`**
  - Alternativa (solo desktop en la raíz): `php -S localhost:8000 -t public/desktop` → URL **`http://localhost:8000/`**

Se creó `public/desktop/SERVIDOR_PHP_DESKTOP.md` con este diagnóstico.

---

## 4. Usuario administrador en la base local

- **Problema:** el usuario no existía en la base de datos local y no se podía hacer login en la interfaz del puerto 8000.
- **Base de datos:** la usada por la interfaz es **`public/desktop/data/mistorneos_local.db`** (no `mistorneos/db_local.sqlite`).
- **Acciones realizadas:**
  - Creación del script **`public/desktop/seed_admin.php`** que:
    - Se conecta a la SQLite anterior.
    - Asegura que la tabla `usuarios` tenga columnas necesarias (`username`, `password_hash`, `role`).
    - Inserta o actualiza el usuario **Trinoamez** con:
      - Contraseña: `password_hash('npi$2025', PASSWORD_BCRYPT)` (compatible con el login).
      - Rol: **`admin_general`** (el login solo permite `admin_general`, `admin_torneo`, `admin_club`, `operador`).
  - Ejecución: `php seed_admin.php` desde `public/desktop`.
- **Confirmación:** usuario insertado correctamente; se puede iniciar sesión en **http://localhost:8000/desktop/login_local.php** con:
  - Usuario: **Trinoamez**
  - Contraseña: **npi$2025**

Para repetir o actualizar el usuario más adelante:

```bash
cd c:\wamp64\www\mistorneos\public\desktop
php seed_admin.php
```

---

## 5. Archivos creados o modificados (referencia)

- **Panel y ciclo:** `public/desktop/admin_panel.php`, `public/desktop/crear_torneo.php`, `public/desktop/inscribir.php`, `public/desktop/cuadricula.php`, `public/desktop/imprimir_hojas.php`, `public/desktop/generar_ronda.php`, `public/desktop/save_resultados.php`, reportes (stubs).
- **Core:** `desktop/core/` (logica_torneo, MesaAsignacionService, db_bridge, config, etc.).
- **Documentación / utilidades:** `public/desktop/SERVIDOR_PHP_DESKTOP.md`, `public/desktop/seed_admin.php`.
- **Esquema local:** `public/desktop/db_local.php` (tabla `usuarios` con `username`, `password_hash`, `role`; migración de `modalidad` en `tournaments`).

---

*Generado para revisión posterior. Fecha aproximada: febrero 2026.*
