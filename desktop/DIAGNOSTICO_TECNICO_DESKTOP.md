# Diagnóstico técnico integral — mistorneos/desktop

**Alcance:** Carpeta `mistorneos/desktop` únicamente. Sin modificaciones; análisis exclusivamente descriptivo.

**Fecha:** Febrero 2026.

---

## 1. Stack tecnológico

| Componente | Tecnología | Versión / detalle |
|------------|------------|-------------------|
| **Backend / runtime** | PHP | Sin `composer.json` ni `package.json` en desktop; se asume PHP 7.4+ (strict_types, typed properties en DB). |
| **Framework** | Ninguno | PHP plano; sin framework MVC. Clases y funciones en `core/`. |
| **Base de datos** | SQLite (PDO) | Dos puntos de acceso: `db_bridge.php` (core) y `db_local.php` (scripts). Archivo por defecto: `desktop/db_local.sqlite` (bridge) o `desktop/data/mistorneos_local.db` (db_local). |
| **Gestión de estado** | Ninguna | Sin librería de estado. Sesiones y contexto en la capa de presentación (fuera de esta carpeta, en `public/desktop/`). |
| **Estilado / frontend** | N/A en desktop | La carpeta `desktop` no contiene CSS/JS ni assets; la UI está en `public/desktop/` (Bootstrap 5, Font Awesome). |
| **Sincronización** | cURL / stream | `import_from_web.php` y `test_connection.php` usan cURL o `file_get_contents` para APIs HTTP. Configuración en `config_sync.php`. |

**Conclusión:** Stack minimalista: PHP + SQLite + configuración manual. Sin gestor de dependencias ni framework en esta carpeta.

---

## 2. Arquitectura de carpetas

```
mistorneos/desktop/
├── core/                    # Lógica de negocio y conexión a BD
│   ├── config.php           # Constantes (DESKTOP_VERSION, APP_NAME, RELOAD_INTERVAL)
│   ├── db_bridge.php        # Conexión PDO SQLite para el core (DB::pdo())
│   ├── logica_torneo.php    # generarRonda(), actualizarEstadisticasInscritos() y dependencias
│   ├── MesaAsignacionService.php      # Asignación de mesas (torneos individuales)
│   ├── MesaAsignacionEquiposService.php # Asignación de mesas (torneos por equipos)
│   └── InscritosHelper.php  # Constantes y helpers de estatus de inscritos
├── data/                    # Directorio para BD SQLite (por defecto mistorneos_local.db)
│   └── .gitkeep
├── config_sync.php          # SYNC_WEB_URL, SYNC_API_KEY, SYNC_SSL_VERIFY (no versionado en ejemplo)
├── config_sync.example.php  # Plantilla de configuración de sync
├── db_local.php             # Clase DB_Local + ensureLocalSchema (usuarios, tournaments, inscritos, payments, maestros)
├── import_from_web.php      # Pull de jugadores desde API web o MySQL local → tabla usuarios
├── sync_api.php             # Endpoint POST: recibe JSON de jugadores y escribe en tabla usuarios_local
├── test_logic.php           # Prueba de autonomía: db_bridge + MesaAsignacionService + lectura inscritos
├── test_connection.php      # Prueba de conexión al API remoto (fetch_jugadores)
├── reset_desktop_db.php     # Borra desktop/data/mistorneos_local.db (y WAL/SHM)
├── debug_db.php             # Página HTML que lista tabla usuarios de la BD local
```

**Dónde reside la lógica de negocio**

- **Core:** `core/logica_torneo.php` (generación de rondas y actualización de estadísticas), `core/MesaAsignacionService.php` y `core/MesaAsignacionEquiposService.php` (asignación de mesas y BYE).
- **Persistencia:** Toda la lógica usa `DB::pdo()` de `core/db_bridge.php`. La ruta del SQLite puede sobrescribirse con la constante `DESKTOP_APP_DB_PATH` (usada desde `public/desktop/` para apuntar a la misma BD que la UI).
- **Scripts de soporte:** `db_local.php` define esquema y `DB_Local`; `import_from_web.php` y `sync_api.php` usan `DB_Local` y escriben en la BD local (rutas según `__DIR__` cuando se ejecutan desde `desktop/`).

---

## 3. Análisis funcional por módulo

### 3.1 Conexión a BD y configuración

| Aspecto | Detalle |
|--------|---------|
| **Qué hace** | Expone dos formas de conectar a SQLite: **DB** (core) y **DB_Local** (scripts). Carga constantes de versión y nombre de app. |
| **Conexión** | **db_bridge:** por defecto `desktop/db_local.sqlite`; si existe `DESKTOP_APP_DB_PATH`, la usa (p. ej. `public/desktop/data/mistorneos_local.db`). **db_local:** `desktop/data/mistorneos_local.db` (o `DESKTOP_DB_BASE` + `/data/mistorneos_local.db`). |
| **Completitud** | Operativo. Riesgo: dos rutas de BD distintas según quién invoque el core (desktop vs public/desktop). |

### 3.2 Generación de rondas (individual y equipos)

| Aspecto | Detalle |
|--------|---------|
| **Qué hace** | `generarRonda($torneo_id, ...)` lee torneo (modalidad), comprueba que la última ronda esté completada, actualiza estadísticas, llama a `MesaAsignacionService` (individual) o `MesaAsignacionEquiposService` (equipos) e inserta en `partiresul`. |
| **Conexión** | Frontend (en `public/desktop/`) envía POST a `generar_ronda.php` → incluye core y llama `generarRonda()`; la BD usada es la que define `DESKTOP_APP_DB_PATH` (típicamente la de la UI). |
| **Completitud** | Lógica completa y extraída del proyecto principal. **Depende de que la BD tenga tabla `partiresul`** (y para equipos, `equipos` e `inscritos.codigo_equipo`). En `desktop/db_local.php` **no se crea `partiresul`** ni `equipos`. |

### 3.3 Actualización de estadísticas de inscritos

| Aspecto | Detalle |
|--------|---------|
| **Qué hace** | `actualizarEstadisticasInscritos($torneo_id)` recorre inscritos, lee `partiresul` (resultados por mesa), calcula ganados/perdidos/efectividad/puntos/sanciones/tarjetas y actualiza `inscritos`. Luego llama a recalcular posiciones y, si es torneo de equipos, a `actualizarEstadisticasEquipos` y `recalcularPosicionesEquipos`. |
| **Conexión** | Invocada desde la UI al guardar resultados o al generar ronda; usa `DB::pdo()` (misma BD que el resto del core). |
| **Completitud** | Implementación completa. Requiere `partiresul` y, para equipos, tabla `equipos` y `inscritos.codigo_equipo`. |

### 3.4 Asignación de mesas (MesaAsignacionService / MesaAsignacionEquiposService)

| Aspecto | Detalle |
|--------|---------|
| **Qué hace** | Ronda 1: dispersión por clubes (individual) o distribución secuencial por equipos. Rondas 2..N: estrategias Suizo, BYE, evitar repetir parejas. Escriben en `partiresul`. |
| **Conexión** | Solo backend: llamados desde `logica_torneo.php` con `DB::pdo()`. |
| **Completitud** | Código extenso y completo. Dependen de tablas `inscritos`, `partiresul`, y en equipos de `equipos` y estructura de inscritos por equipo. |

### 3.5 InscritosHelper

| Aspecto | Detalle |
|--------|---------|
| **Qué hace** | Constantes y condiciones SQL para estatus (pendiente, confirmado, retirado) compatibles con columna INT o texto. |
| **Conexión** | Usado por MesaAsignacionService y por lógica que filtra inscritos activos. |
| **Completitud** | Completo; sin dependencias de UI. |

### 3.6 Importación desde la web (import_from_web.php)

| Aspecto | Detalle |
|--------|---------|
| **Qué hace** | Obtiene jugadores desde API remota (fetch_jugadores) o desde MySQL local (`--local`), e inserta/actualiza en la tabla **usuarios** de la SQLite local (por UUID y last_updated). |
| **Conexión** | CLI o navegador. Usa `desktop/db_local.php` → `desktop/data/mistorneos_local.db`. Configuración en `config_sync.php`. |
| **Completitud** | Funcional si la API responde y la BD local tiene el esquema de `db_local` (usuarios). No crea `partiresul` ni torneos/inscritos de prueba. |

### 3.7 Sync API (sync_api.php)

| Aspecto | Detalle |
|--------|---------|
| **Qué hace** | Recibe POST JSON con `jugadores[]`, compara por UUID con la BD local e inserta/actualiza en la tabla **usuarios_local** (no `usuarios`). |
| **Conexión** | Pensado como endpoint al que llama el servidor web para “empujar” datos al cliente desktop. Crea la tabla `usuarios_local` si no existe. |
| **Completitud** | Implementado. **Inconsistencia:** usa `usuarios_local` mientras `import_from_web` y el resto del flujo usan `usuarios`. No hay unificación de modelo. |

### 3.8 Pruebas y utilidades

| Archivo | Función | Completitud |
|---------|---------|-------------|
| **test_logic.php** | Carga db_bridge y MesaAsignacionService, cuenta `inscritos`. | Falla si la BD por defecto no tiene `partiresul` (db_bridge por defecto usa `desktop/db_local.sqlite`, y `desktop/db_local.php` no crea `partiresul`). |
| **test_connection.php** | Comprueba URL del API y API key (cURL). | Completo. |
| **reset_desktop_db.php** | Borra solo `desktop/data/mistorneos_local.db` (y WAL/SHM). No toca `public/desktop/data/` ni `desktop/db_local.sqlite`. | Parcial: deja otras posibles BD intactas. |
| **debug_db.php** | Lista tabla `usuarios` vía `DB_Local`. Enlace “Volver” apunta a `registro_jugador.php` (que en esta carpeta no existe; está en `public/desktop/`). | Pequeña deuda: enlace roto si se abre desde `desktop/`. |

---

## 4. Estado de la base de datos

### 4.1 Esquema definido en desktop/db_local.php (ensureLocalSchema)

- **usuarios:** id, nombre, cedula, nacionalidad, sexo, fechnac, email, categ, photo_path, uuid, recovery_token, username, password_hash, role, club_id, entidad, status, requested_at, approved_at, approved_by, rejection_reason, last_updated, sync_status, created_at.
- **tournaments:** id, clase, modalidad, tiempo, puntos, rondas, estatus, costo, ranking, pareclub, fechator, nombre, invitacion, normas, afiche, club_responsable, organizacion_id, owner_user_id, entidad, created_at, updated_at, uuid, last_updated, sync_status.
- **inscritos:** id, id_usuario, torneo_id, id_club, posicion, ganados, perdidos, efectividad, puntos, ptosrnk, sancion, chancletas, zapatos, tarjeta, fecha_inscripcion, inscrito_por, notas, estatus, uuid, last_updated, sync_status.
- **payments:** id, torneo_id, club_id, amount, method, reference, status, created_at, updated_at, uuid, last_updated, sync_status.
- **entidad, organizaciones, clubes:** maestros mínimos.

**No definido en desktop/db_local.php:**

- **partiresul** (resultados por mesa y ronda).
- **equipos** (torneos por equipos).
- **inscritos.codigo_equipo** (no se añade en este schema).

### 4.2 Esquema que espera el core (logica_torneo + MesaAsignacion*)

- Todo lo anterior más:
  - **partiresul** (id_torneo, partida, mesa, secuencia, id_usuario, resultado1/2, efectividad, ff, tarjeta, sancion, chancleta, zapato, fecha_partida, registrado, etc.).
  - **equipos** (id_torneo, codigo_equipo, puntos, ganados, perdidos, efectividad, sancion, posicion, estatus, fecha_actualizacion, …).
  - **inscritos.codigo_equipo** para torneos por equipos.

Ese esquema completo está creado en **public/desktop/db_local.php** (capa de presentación), no en `mistorneos/desktop`.

### 4.3 Resumen

- **desktop/:** esquema “corto” (usuarios, tournaments, inscritos, payments, maestros). Sirve para importar jugadores y depurar, pero **no** para ejecutar generación de rondas ni actualización de estadísticas contra esta misma BD.
- **Core:** pensado para ejecutarse contra una BD que ya tenga `partiresul` (y equipos). En la práctica eso es la BD de **public/desktop** cuando se define `DESKTOP_APP_DB_PATH`.

---

## 5. Puntos críticos y deuda técnica

### 5.1 Críticos

1. **Dos bases de datos y dos “db_local”:**  
   - `desktop/db_bridge.php` por defecto usa `desktop/db_local.sqlite`.  
   - `desktop/db_local.php` usa `desktop/data/mistorneos_local.db`.  
   - No hay un único “origen de verdad” para el archivo SQLite si se ejecutan scripts solo desde `desktop/`.

2. **Schema incompleto en desktop:**  
   En `desktop/db_local.php` no se crean `partiresul` ni `equipos`. Si se corre el core contra la BD que crea este `db_local`, fallará en cualquier flujo que use partiresul o equipos.

3. **test_logic.php:**  
   Usa `db_bridge` sin definir `DESKTOP_APP_DB_PATH`. Por defecto conecta a `desktop/db_local.sqlite`, que **no** es creado por `db_local.php` (que crea `data/mistorneos_local.db`). Además, en ninguna parte del desktop se crea la tabla `inscritos` en ese `db_local.sqlite` si el archivo es nuevo. Resultado: puede fallar por tabla inexistente o por archivo inexistente.

4. **sync_api.php vs resto:**  
   Escribe en **usuarios_local** mientras el resto del flujo (import_from_web, login, core) usa **usuarios**. Datos duplicados o flujos que no se encuentran.

### 5.2 Deuda técnica / mejoras

5. **config_sync.php:**  
   Contiene API key en claro y `SYNC_SSL_VERIFY = false`. Debería ser plantilla (`.example`) y no subirse con claves.

6. **debug_db.php:**  
   Enlace a `registro_jugador.php` asume que se sirve desde la misma raíz que la UI (p. ej. `public/desktop/`); desde `desktop/` el enlace es incorrecto.

7. **reset_desktop_db.php:**  
   Solo borra `desktop/data/mistorneos_local.db`. No borra `desktop/db_local.sqlite` ni la BD de `public/desktop/data/`, lo que puede generar confusión.

8. **Sin composer/autoload:**  
   Todo con `require_once` y rutas relativas. Si se moviera o reutilizara el core en otro contexto, habría que revisar rutas y posibles conflictos de clases (p. ej. `DB` en core vs proyecto principal).

9. **Dependencia de $_POST en logica_torneo:**  
   `generarRonda` usa `$_POST['estrategia_asignacion']` y `$_POST['estrategia_ronda2']`. Acopla la lógica al contexto web; en CLI o otro entorno esos valores no existirían.

### 5.3 Archivos vacíos o casi vacíos

- **data/.gitkeep:** Solo mantiene el directorio; correcto.
- No hay archivos PHP vacíos en la carpeta revisada.

---

## 6. Resumen: qué falta para un MVP (desde la óptica de mistorneos/desktop)

- **Unificar BD y esquema:**  
  Un solo archivo SQLite y un solo `ensureLocalSchema` que cree **partiresul**, **equipos** y columnas necesarias en **inscritos** (p. ej. codigo_equipo), o documentar de forma explícita que el core solo se usa con la BD de `public/desktop` y que `desktop/` es solo “core + scripts de importación”.

- **Unificar tablas de usuarios:**  
  Decidir si el flujo de sync usa **usuarios** o **usuarios_local** y unificar; si se mantiene `usuarios_local`, que la UI/import sepan leer de ahí o migrar datos a **usuarios**.

- **test_logic.php fiable:**  
  Que use la misma BD que la app (p. ej. definiendo `DESKTOP_APP_DB_PATH` en un bootstrap de pruebas) o que el schema por defecto del desktop incluya `partiresul` (y si aplica `equipos`) para que el test no falle por tablas faltantes.

- **Configuración y seguridad:**  
  Mover claves y URLs a `config_sync.example.php` (o .env) y no versionar `config_sync.php` con valores reales; endurecer SSL en producción.

- **Desacoplar logica_torneo de $_POST:**  
  Pasar estrategias como argumentos de `generarRonda()` en lugar de leer `$_POST`, para poder usar el core desde CLI o desde otra capa sin dependencia del request web.

- **Documentar relación desktop vs public/desktop:**  
  Dejar claro que la “app” que corre en el puerto 8000 es `public/desktop`, que usa su propia BD y que invoca el core de `desktop/core` inyectando `DESKTOP_APP_DB_PATH`; y que `desktop/` por sí solo es un conjunto de lógica + scripts de import/sync que pueden usar otra BD (y un schema actualmente incompleto para el core).

Con eso, el módulo **desktop** quedaría en estado MVP claro: misma BD, mismo modelo de usuarios, tests que pasan y core reutilizable sin depender del request web.

---

*Informe generado sin modificar ningún archivo del proyecto.*
