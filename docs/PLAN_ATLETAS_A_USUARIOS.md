# Plan de acción: generar usuarios desde la tabla `atletas`

**Objetivo:** Usar la tabla `atletas` como fuente para crear registros en la tabla `usuarios`, cubriendo todos los campos requeridos por `usuarios` sin ejecutar nada hasta que apruebes este plan.

---

## 1. Verificaciones previas

### 1.1 Estructura de la tabla `atletas` (confirmada)

Columnas disponibles en `atletas`:

**Id**, **cedula**, **sexo**, numfvd, **asociación**, **estatus**, afiliación, anualidad, carnet, traspaso, inscripción, **categ**, **nombre**, profesión, dirección, **celular**, **email**, **fechnac**, fechfvd, fechact, **foto**, cedula_img, created_at, updated_at.

Las que están en **negrita** se usan en el mapeo a `usuarios`; el resto no se mapea. La tabla no tiene campo `nacionalidad`; en usuarios se usará `'V'` por defecto.

### 1.2 Estructura y requisitos de la tabla `usuarios`

Según `schema/schema.sql` y `lib/security.php` (Security::createUser):

| Campo           | En `usuarios`      | Uso en createUser |
|----------------|--------------------|--------------------|
| id             | AUTO_INCREMENT     | —                  |
| nombre         | VARCHAR(62) NOT NULL| Opcional en data; si falta se rellena con default |
| cedula         | VARCHAR(20) NOT NULL| Opcional en data; validado si se envía |
| nacionalidad   | CHAR(1) NOT NULL DEFAULT 'V' | Opcional; valores V,E,J,P |
| sexo           | ENUM('M','F','O') NOT NULL DEFAULT 'M' | Opcional |
| fechnac        | DATE NULL          | Opcional |
| email          | VARCHAR(100) NOT NULL | Opcional en data; si falta se genera |
| username       | VARCHAR(60) NOT NULL UNIQUE | **Requerido** |
| password_hash  | VARCHAR(255) NOT NULL | **Requerido** (se genera desde `password`) |
| role           | ENUM(...) NOT NULL DEFAULT 'usuario' | **Requerido**; para atletas: `usuario` |
| status         | TINYINT NOT NULL DEFAULT 0 | Opcional; 0 = activo |
| club_id        | INT NULL DEFAULT 0 | Opcional |
| entidad        | INT NOT NULL DEFAULT 0 | Opcional |
| uuid           | VARCHAR(36) NULL   | Opcional; se puede generar |
| celular        | Si existe columna   | Opcional |
| photo_path     | VARCHAR(200) NULL  | Opcional |
| categ          | INT NOT NULL DEFAULT 0 | Rellenado por createUser si es NOT NULL |

**Resumen:** Para cada usuario hace falta definir de forma inequívoca: **username**, **password** (luego se hashea), **role**. El resto pueden ser opcionales o generados/por defecto; pero para cumplir NOT NULL de `nombre`, `cedula`, `email` hay que asegurar que siempre se asignen (directamente desde atletas o por reglas de generación).

---

## 2. Mapeo exacto: `atletas` → `usuarios`

Asumiendo una tabla `atletas` con columnas “típicas” (a ajustar con tu estructura real):

| Campo en `usuarios` | Origen en `atletas` | Regla si falta |
|---------------------|---------------------|----------------|
| nombre              | nombre              | Obligatorio; omitir fila si falta |
| cedula              | cedula              | Obligatorio; omitir fila si falta |
| nacionalidad        | —                   | Fijo 'V' (atletas no tiene este campo) |
| sexo                | sexo                | Normalizar M/F/O; default 'M' |
| fechnac             | fechnac             | Directo; puede ser NULL |
| email               | email               | Si falta: generar ej. username + '@atletas.local' |
| celular             | celular             | Directo; opcional |
| username            | **Único:** "user00" + numfvd (solo dígitos) si existe; si no, "user00" + id atleta. Sufijo _2, _3 si hay conflicto. | Mantiene UNIQUE en BD (seguridad). |
| password            | **Cédula** (solo dígitos; se hashea) | Obligatorio; rellenar a 6 caracteres si hace falta |
| role                | Fijo 'usuario'      | — |
| status              | estatus             | Mapear: valor activo → 0, otro → 1 |
| club_id             | asociación          | Si es ID de club; si no, 0 o parámetro --club_id |
| entidad             | —                   | 0 si no aplica |
| photo_path          | foto                | Si foto es ruta o nombre de archivo |
| categ               | categ               | Directo si existe en usuarios |

### 2.1 Acceso al sistema (atletas) y seguridad

- **Username:** único por atleta. Formato **user00** + numfvd (solo dígitos) si existe en atletas; si no hay numfvd, **user00** + id del atleta. Si hay conflicto de unicidad se añade sufijo _2, _3...
- **Contraseña:** cédula del atleta (solo dígitos). Se almacena solo el hash.
- Se mantiene la restricción **UNIQUE** en `usuarios.username` (recomendación de seguridad: ver `docs/CONSEJOS_SEGURIDAD.md`).
- No se usa username repetido "usuario" para evitar ambigüedad y facilitar auditoría.

### 2.2 Contraseña inicial

- Definir una política clara, por ejemplo:
  - **Opción 1:** Contraseña por defecto única configurable (ej. `--password=Cambiar123`) para todos los atletas generados.
  - **Opción 2:** Contraseña = cédula (como en flujo de invitación).
  - **Opción 3:** Contraseña aleatoria por usuario y guardarla en un reporte (CSV/Excel) para entregar a cada atleta.

El script no debe dejar `password` vacío; siempre pasar un valor a Security::createUser.

### 2.3 Evitar duplicados

- **Clave de unicidad en origen:** Por ejemplo `(cedula, nacionalidad)` en atletas.
- **Antes de crear usuario:** Comprobar si ya existe en `usuarios` un registro con la misma `cedula` (y opcionalmente `nacionalidad`). Si existe:
  - **Opción A:** Omitir (solo log) y continuar con el siguiente.
  - **Opción B:** Actualizar datos del usuario existente (nombre, email, etc.) desde atletas (requiere definir bien la política).

---

## 3. Herramienta a implementar (script)

### 3.1 Tipo de script

- **Recomendación:** Un script PHP por línea de comandos (ej. `scripts/crear_usuarios_desde_atletas.php`), siguiendo el patrón de `crear_usuarios_desde_personas.php`.

### 3.2 Conexión a datos

- Si `atletas` está en la **misma base** que `usuarios`: usar `DB::pdo()` (config/db.php).
- Si `atletas` está en **otra base**: definir configuración (ej. en `config/` o .env) y una clase o función que devuelva un PDO para esa base (similar a PersonaDatabase para `dbo_persona`).

### 3.3 Parámetros del script (propuesta)

- `--dry-run`: solo leer `atletas`, mostrar mapeo a usuarios y contar cuántos se crearían/omitirían; no escribir en `usuarios`.
- `--limit=N`: procesar como máximo N registros de atletas (útil para pruebas).
- `--password=xxx`: contraseña por defecto para nuevos usuarios.
- `--club_id=K`: asignar `club_id = K` a todos los usuarios generados (si no viene de atletas).
- `--desde-id=X` / `--hasta-id=Y`: filtrar atletas por rango de id (si aplica).
- Opcional: `--actualizar`: si el usuario ya existe por cedula, actualizar nombre/email/sexo/fechnac/celular desde atletas (solo si apruebas esta política).

### 3.4 Flujo del script (pasos)

1. Cargar bootstrap, DB y `Security` (y si aplica, conexión a BD de atletas).
2. **Obtener estructura** de `atletas` (SHOW COLUMNS) para validar o detectar nombres de columnas.
3. **Leer atletas** con SELECT (con límite y filtros según parámetros).
4. Para cada fila:
   - Extraer/normalizar: nombre, cedula (solo dígitos), nacionalidad, sexo, fechnac, email, celular/telefono, club_id, entidad.
   - Si falta cedula o nombre (y no se puede generar), **omitir** y registrar en log.
   - Generar **username** único (según regla elegida).
   - Comprobar si ya existe usuario con misma **cedula** (y nacionalidad si aplica); si existe, omitir o actualizar según opción.
   - Armar array `$data` para Security::createUser (username, password, role, nombre, cedula, nacionalidad, sexo, fechnac, email, celular, club_id, entidad, status).
   - Llamar a **Security::createUser($data)** (salvo en dry-run).
5. Mostrar resumen: creados, omitidos por duplicado, errores.

### 3.5 Uso de Security::createUser

- Usar **siempre** Security::createUser para crear usuarios (no INSERT directo), para:
  - Validar username, password, role.
  - Generar password_hash con el mismo algoritmo del resto del sistema.
  - Rellenar columnas NOT NULL que falten (según lógica actual del método).
- Pasar en `$data` todos los campos que ya tengas de atletas; el método ya considera opcionales y rellena defaults donde haga falta.

---

## 4. Checklist antes de implementar

- [x] Tienes la estructura real de la tabla `atletas` (nombres de columnas y tipos).
- [ ] Decidido: contraseña por defecto (única, cédula o aleatoria con reporte).
- [ ] Decidido: si `atletas` está en la misma BD que `usuarios` o en otra (y cómo se configura).
- [ ] Decidido: qué hacer con atletas ya existentes como usuario (omitir vs actualizar).
- [ ] Decidido: si se asigna `club_id`/`entidad` desde atletas o solo por parámetro del script.
- [ ] Ajustar el mapeo de la sección 2 a los nombres reales de las columnas de `atletas`.

---

## 5. Entregables del script (cuando se implemente)

1. **Script:** `scripts/crear_usuarios_desde_atletas.php` (o nombre acordado).
2. **Documentación en cabecera del script:** uso, parámetros y ejemplo de invocación.
3. **Opcional:** archivo de configuración o ejemplo (ej. `config/atletas_db.example.php`) si la fuente es otra BD.
4. **Opcional:** script SQL o migración que cree la tabla `atletas` con la estructura esperada por el mapeo, si tú vas a crear esa tabla.

---

## 6. Resumen

- **Fuente:** tabla `atletas` (estructura confirmada: Id, cedula, sexo, numfvd, asociación, estatus, afiliación, anualidad, carnet, traspaso, inscripción, categ, nombre, profesión, dirección, celular, email, fechnac, fechfvd, fechact, foto, cedula_img, created_at, updated_at).
- **Destino:** tabla `usuarios` con todos los campos requeridos cubiertos por mapeo + valores por defecto.
- **Herramienta:** script PHP CLI que lee atletas, normaliza datos, genera username y opcionalmente email/password, evita duplicados por cedula y usa Security::createUser.
- **No se ejecuta nada** hasta que confirmes la estructura de `atletas`, las decisiones de contraseña, duplicados y ubicación de la tabla; entonces se puede implementar el script según este plan y ajustar el mapeo a tus columnas reales.

Si compartes el resultado de `SHOW COLUMNS FROM atletas` (o el listado de columnas de atletas), se puede bajar este plan a un mapeo campo a campo exacto y al código del script sin ejecutarlo.

**Actualización:** La estructura de `atletas` ya está confirmada y el mapeo de la sección 2 es campo a campo exacto (nombre, cedula, sexo, estatus, asociación, categ, celular, email, fechnac, foto, etc.). Pendiente: decidir contraseña, duplicados y ubicación de la tabla; luego implementar el script.
