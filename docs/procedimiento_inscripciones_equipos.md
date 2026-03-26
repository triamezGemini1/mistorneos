# Procedimiento: Inscripciones por Equipos

## Flujo Completo del Proceso

### 1. Punto de Entrada
- **Archivo**: `modules/gestion_torneos/inscripciones_equipos.php`
- **Controlador**: `modules/torneo_gestion.php` → `case 'inscripciones_equipos'`
- **Función que obtiene los datos**: `obtenerDatosInscripcionesEquipos($torneo_id)`

---

## 2. Función `obtenerDatosInscripcionesEquipos($torneo_id)` - Paso a Paso

### PASO 1: Obtener información del usuario actual
```php
$current_user = Auth::user();
$user_club_id_raw = Auth::getUserClubId();
$user_club_id = ($user_club_id_raw !== null && (int)$user_club_id_raw > 0) ? (int)$user_club_id_raw : null;
$is_admin_general = Auth::isAdminGeneral();
$is_admin_club = Auth::isAdminClub();
```

**Qué hace:**
- Obtiene el usuario actual de la sesión
- Extrae el `club_id` del usuario (debe ser > 0 para ser válido)
- Determina si es administrador general
- Determina si es administrador de club

**Logs de depuración:**
```
DEBUG obtenerDatosInscripcionesEquipos - Usuario: [id], Role: [role], 
user_club_id_raw: [valor], user_club_id: [valor], 
is_admin_general: [true/false], is_admin_club: [true/false]
```

---

### PASO 2: Obtener información del torneo
```php
$stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
$stmt->execute([$torneo_id]);
$torneo = $stmt->fetch(PDO::FETCH_ASSOC);
```

**Qué hace:**
- Busca el torneo por su ID
- Si no existe, retorna arrays vacíos

**Logs de depuración:**
```
DEBUG obtenerDatosInscripcionesEquipos - Torneo encontrado: [nombre]
```

---

### PASO 3: Obtener jugadores según el rol del administrador

#### 3A. Si es ADMINISTRADOR GENERAL:
```php
SELECT ins.id as id_inscrito, u.id as id_usuario, u.nombre, u.cedula, u.sexo,
       COALESCE(ins.id_club, u.club_id) as club_id, c.nombre as club_nombre
FROM inscritos ins
INNER JOIN usuarios u ON ins.id_usuario = u.id
LEFT JOIN clubes c ON COALESCE(ins.id_club, u.club_id) = c.id
WHERE ins.torneo_id = ? AND ins.estatus != 4
```

**Qué hace:**
- Obtiene TODOS los jugadores que están inscritos en el torneo (tabla `inscritos`)
- Solo muestra jugadores que ya están inscritos en el torneo

**Logs:**
```
DEBUG obtenerDatosInscripcionesEquipos - Modo: ADMIN GENERAL
DEBUG obtenerDatosInscripcionesEquipos - ADMIN GENERAL: [N] jugadores encontrados
```

#### 3B. Si es ADMINISTRADOR DE CLUB o USUARIO:
```php
SELECT ins.id as id_inscrito, u.id as id_usuario, u.nombre, u.cedula, u.sexo,
       u.club_id as club_id, c.nombre as club_nombre
FROM usuarios u
LEFT JOIN clubes c ON u.club_id = c.id
LEFT JOIN inscritos ins ON ins.id_usuario = u.id AND ins.torneo_id = ? AND ins.estatus != 4
WHERE u.role = 'usuario' 
  AND (u.status IN ('approved', 'active', 'activo') OR u.status = 1)
  AND u.club_id IN ([clubes_ids])
```

**Qué hace:**
- Obtiene TODOS los afiliados (tabla `usuarios`) del territorio del administrador
- Si el administrador es `admin_club`, incluye su club + clubes supervisados
- Si es usuario regular, solo su club
- Hace LEFT JOIN con `inscritos` para obtener `id_inscrito` si el afiliado ya está inscrito
- Filtra por: `role = 'usuario'` y `status` activo

**Logs:**
```
DEBUG obtenerDatosInscripcionesEquipos - Modo: ADMIN CLUB/USUARIO, club_id = [id]
DEBUG obtenerDatosInscripcionesEquipos - ADMIN CLUB: clubes supervisados = [lista]
DEBUG obtenerDatosInscripcionesEquipos - SQL jugadores: [query]
DEBUG obtenerDatosInscripcionesEquipos - Parámetros: torneo_id=[id], clubes_ids=[lista]
DEBUG obtenerDatosInscripcionesEquipos - Jugadores encontrados: [N]
```

---

### PASO 4: Filtrar jugadores que ya están en equipos
```php
SELECT DISTINCT u.cedula
FROM inscritos i
INNER JOIN usuarios u ON i.id_usuario = u.id
WHERE i.torneo_id = ? AND i.codigo_equipo IS NOT NULL AND i.estatus != 4
```

**Qué hace:**
- Obtiene las cédulas de jugadores que ya tienen un `codigo_equipo` asignado
- Estos jugadores NO aparecerán en la lista de disponibles

**Logs:**
```
DEBUG obtenerDatosInscripcionesEquipos - Jugadores en equipos: [N] cédulas
```

Luego filtra:
```php
foreach ($todos_jugadores as $jugador) {
    if (!in_array($jugador['cedula'], $cedulas_en_equipos)) {
        $jugadores_disponibles[] = $jugador;
    }
}
```

**Logs:**
```
DEBUG obtenerDatosInscripcionesEquipos - Jugadores disponibles (después de filtrar): [N]
```

---

### PASO 5: Obtener equipos del torneo
```php
SELECT e.*, c.nombre as nombre_club
FROM equipos e
LEFT JOIN clubes c ON e.id_club = c.id
WHERE e.id_torneo = ?
```

**Qué hace:**
- Obtiene todos los equipos del torneo
- Para cada equipo, obtiene sus jugadores usando `EquiposHelper::getJugadoresEquipo()`
- Agrupa equipos por club

---

### PASO 6: Obtener clubes disponibles para el formulario

#### 6A. Si es ADMINISTRADOR GENERAL:
```php
SELECT id, nombre FROM clubes 
WHERE (estatus = 1 OR estatus = '1' OR estatus = 'activo') 
ORDER BY nombre ASC
```

**Qué hace:**
- Muestra TODOS los clubes activos del sistema

**Logs:**
```
DEBUG obtenerDatosInscripcionesEquipos - Obteniendo clubes: ADMIN GENERAL
DEBUG obtenerDatosInscripcionesEquipos - Clubes encontrados (ADMIN GENERAL): [N]
```

#### 6B. Si es ADMINISTRADOR DE CLUB:
```php
$clubes_disponibles = ClubHelper::getClubesSupervisedWithData($user_club_id);
// + agregar el club del usuario si no está en la lista
```

**Qué hace:**
- Obtiene los clubes supervisados por el administrador
- Agrega su propio club si no está en la lista
- Verifica que el club tenga `estatus` activo

**Logs:**
```
DEBUG obtenerDatosInscripcionesEquipos - Obteniendo clubes: ADMIN CLUB, club_id = [id]
DEBUG obtenerDatosInscripcionesEquipos - Clubes supervisados obtenidos: [N]
DEBUG obtenerDatosInscripcionesEquipos - Club del usuario agregado: [nombre]
```

#### 6C. Si es USUARIO REGULAR:
```php
SELECT id, nombre FROM clubes 
WHERE id = ? AND (estatus = 1 OR estatus = '1' OR estatus = 'activo')
```

**Qué hace:**
- Solo muestra su propio club (si está activo)

**Logs:**
```
DEBUG obtenerDatosInscripcionesEquipos - Obteniendo clubes: USUARIO, club_id = [id]
DEBUG obtenerDatosInscripcionesEquipos - Club encontrado: [nombre]
```

---

### PASO 7: Retornar datos
```php
return [
    'torneo' => $torneo,
    'jugadores_disponibles' => $jugadores_disponibles,
    'equipos' => $equipos,
    'equipos_por_club' => $equipos_por_club,
    'clubes_disponibles' => $clubes_disponibles,
    'total_jugadores_disponibles' => count($jugadores_disponibles),
    'total_equipos' => count($equipos),
    'jugadores_por_equipo' => max(2, (int)($torneo['pareclub'] ?? 4))
];
```

**Logs finales:**
```
DEBUG obtenerDatosInscripcionesEquipos - RESULTADO FINAL: [N] clubes, [N] jugadores disponibles
```

---

## 3. Posibles Problemas y Soluciones

### Problema: "No hay clubes disponibles"

**Causas posibles:**
1. El usuario no tiene `club_id` válido (> 0)
2. El club del usuario tiene `estatus` inactivo (no es 1, '1', ni 'activo')
3. `Auth::getUserClubId()` retorna `null` o `0`
4. El usuario no tiene rol correcto (`admin_general`, `admin_club`, o `usuario`)

**Verificar en logs:**
- `user_club_id_raw` y `user_club_id`
- Si el club existe y está activo en la BD
- El rol del usuario

---

### Problema: "No hay jugadores disponibles"

**Causas posibles:**
1. No hay afiliados (`usuarios`) con `role = 'usuario'` y `status` activo en el territorio
2. Todos los jugadores ya están asignados a equipos (tienen `codigo_equipo`)
3. El `club_id` del usuario no coincide con ningún afiliado
4. Los afiliados tienen `status` diferente a 'approved', 'active', 'activo', o 1

**Verificar en logs:**
- Cuántos jugadores se encontraron antes de filtrar
- Cuántos jugadores están en equipos
- El SQL ejecutado y sus parámetros

---

## 4. Cómo Revisar los Logs

Los logs se guardan en el archivo de error de PHP (normalmente en `error_log` o según configuración de PHP).

Buscar líneas que empiecen con:
```
DEBUG obtenerDatosInscripcionesEquipos
```

Estas líneas mostrarán:
- El usuario y su rol
- El club_id del usuario
- Cuántos jugadores se encontraron
- Cuántos clubes se encontraron
- El SQL ejecutado (para admin_club/usuario)
- El resultado final

---

## 5. Próximos Pasos para Diagnosticar

1. **Revisar los logs** después de cargar la página de inscripciones por equipos
2. **Verificar en la BD:**
   - `SELECT * FROM usuarios WHERE id = [user_id]` - verificar `club_id` y `status`
   - `SELECT * FROM clubes WHERE id = [club_id]` - verificar `estatus`
   - `SELECT COUNT(*) FROM usuarios WHERE club_id = [club_id] AND role = 'usuario' AND status IN ('approved', 'active', 'activo', 1)`
3. **Comparar con el formulario de inscripción individual** (`inscribir_sitio`) que funciona correctamente








