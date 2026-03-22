# Flujo de Guardado de Equipos y Jugadores - Paso a Paso

## 1. FORMULARIO (JavaScript - inscribir_equipo_sitio.php)

**Ubicación**: `modules/gestion_torneos/inscribir_equipo_sitio.php` (líneas 668-724)

### Paso 1.1: Validación Inicial
```javascript
// Verifica que Club y Nombre del Equipo estén llenos
if (!puedeSeleccionarJugadores()) {
    alert('Primero seleccione el Club y el Nombre del Equipo.');
    return;
}
```

### Paso 1.2: Preparación de Datos
```javascript
formData.append('csrf_token', ...);
formData.append('equipo_id', ...);        // Vacío si es nuevo equipo
formData.append('torneo_id', ...);
formData.append('nombre_equipo', ...);
formData.append('club_id', ...);

// Por cada jugador con cédula y nombre:
formData.append('jugadores[${posicionJugador}][cedula]', ...);
formData.append('jugadores[${posicionJugador}][nombre]', ...);
formData.append('jugadores[${posicionJugador}][id_inscrito]', ...);  // Puede estar vacío
formData.append('jugadores[${posicionJugador}][id_usuario]', ...);   // Puede estar vacío
formData.append('jugadores[${posicionJugador}][es_capitan]', ...);   // 1 o 0
```

### Paso 1.3: Envío al API
```javascript
fetch('../api/guardar_equipo.php', {
    method: 'POST',
    body: formData
})
```

---

## 2. API RECEPTOR (guardar_equipo.php)

**Ubicación**: `public/api/guardar_equipo.php`

### Paso 2.1: Validación CSRF
```php
CSRF::validate(); // Puede fallar silenciosamente en desarrollo
```

### Paso 2.2: Extracción de Datos
```php
$torneo_id = (int)($_POST['torneo_id'] ?? 0);
$equipo_id = (int)($_POST['equipo_id'] ?? 0);        // 0 si es nuevo
$nombre_equipo = trim($_POST['nombre_equipo'] ?? '');
$club_id = (int)($_POST['club_id'] ?? 0);
$jugadores = $_POST['jugadores'] ?? [];              // Array de jugadores
```

### Paso 2.3: Validación de Datos Requeridos
```php
if ($torneo_id <= 0 || empty($nombre_equipo) || $club_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}
```

### Paso 2.4: Inicio de Transacción
```php
$pdo = DB::pdo();
$pdo->beginTransaction();
```

---

## 3. CREAR/ACTUALIZAR EQUIPO

### Paso 3.1A: Si es EQUIPO EXISTENTE (equipo_id > 0)
```php
// Actualizar equipo existente
UPDATE equipos 
SET nombre_equipo = UPPER(?), id_club = ?
WHERE id = ? AND id_torneo = ?

// Obtener código de equipo existente
SELECT codigo_equipo FROM equipos WHERE id = ?

// Limpiar código_equipo de todos los jugadores anteriores
UPDATE inscritos 
SET codigo_equipo = NULL 
WHERE torneo_id = ? AND codigo_equipo = ?
```

### Paso 3.1B: Si es EQUIPO NUEVO (equipo_id = 0)
```php
// Llama a EquiposHelper::crearEquipo()
$result = EquiposHelper::crearEquipo($torneo_id, $club_id, $nombre_equipo, $creado_por);

// Retorna: ['success' => bool, 'id' => int, 'codigo' => string, 'message' => string]

// Si falla:
if (!$result['success']) {
    throw new Exception($result['message']);
}

// Obtener equipo_id y codigo_equipo
$equipo_id = $result['id'];
$codigo_equipo = $result['codigo'];
```

---

## 4. EQUIPOSHELPER::CREAREQUIPO

**Ubicación**: `lib/EquiposHelper.php` (líneas 38-117)

### Paso 4.1: Validar Torneo
```php
SELECT modalidad FROM tournaments WHERE id = ?
// Verificar que modalidad = 3 (equipos)
```

### Paso 4.2: Verificar Duplicado
```php
SELECT id FROM equipos 
WHERE id_torneo = ? AND id_club = ? AND UPPER(nombre_equipo) = UPPER(?)
// Si existe, retorna error
```

### Paso 4.3: Crear Equipo (dos métodos)
```php
// Método 1: Procedimiento almacenado (si existe)
CALL sp_crear_equipo(?, ?, ?, ?, @id_equipo, @codigo, @msg)
SELECT @id_equipo as id, @codigo as codigo, @msg as mensaje

// Método 2: Inserción directa (si falla el SP)
INSERT INTO equipos (id_torneo, id_club, nombre_equipo, creado_por)
VALUES (?, ?, UPPER(?), ?)
$equipoId = lastInsertId()
```

### Paso 4.4: Generar Código de Equipo
```php
// Formato: "000-000" donde:
// - Primero 3 dígitos: club_id (con ceros a la izquierda)
// - Últimos 3 dígitos: consecutivo_club (secuencial por club en el torneo)

// Obtener consecutivo
SELECT COALESCE(MAX(consecutivo_club),0)+1 
FROM equipos 
WHERE id_torneo = ? AND id_club = ?

// Generar código
$codigo = str_pad($clubId, 3, '0', STR_PAD_LEFT) . '-' . str_pad($consecutivo, 3, '0', STR_PAD_LEFT)
// Ejemplo: "005-001"

// Actualizar equipo con código
UPDATE equipos 
SET consecutivo_club = ?, codigo_equipo = ? 
WHERE id = ?
```

---

## 5. PROCESAR JUGADORES

**Ubicación**: `public/api/guardar_equipo.php` (líneas 80-142)

### Paso 5.1: Por cada jugador en el array
```php
foreach ($jugadores as $jugador_data) {
    // Validar que tenga cédula y nombre
    if (empty($jugador_data['cedula']) || empty($jugador_data['nombre'])) {
        continue; // Saltar si está incompleto
    }
    
    $cedula = trim($jugador_data['cedula']);
    $nombre = trim($jugador_data['nombre']);
    $id_usuario = (int)($jugador_data['id_usuario'] ?? 0);
    $id_inscrito = (int)($jugador_data['id_inscrito'] ?? 0);
}
```

### Paso 5.2: Obtener id_usuario (si no viene)
```php
if ($id_usuario <= 0) {
    SELECT id FROM usuarios WHERE cedula = ? LIMIT 1
    // Si no encuentra, lanza excepción
}

if ($id_usuario <= 0) {
    throw new Exception("No se pudo determinar el ID de usuario para la cédula $cedula");
}
```

### Paso 5.3: Buscar/Crear Registro en inscritos

#### Opción A: Si viene id_inscrito > 0
```php
// Verificar que el id_inscrito corresponde al id_usuario y torneo_id
SELECT id FROM inscritos 
WHERE id = ? AND id_usuario = ? AND torneo_id = ? LIMIT 1

// Si no coincide, invalidar (id_inscrito = 0)
if (!$stmt->fetch()) {
    $id_inscrito = 0;
}
```

#### Opción B: Si id_inscrito = 0 o inválido
```php
// Buscar si ya existe un registro en inscritos
SELECT id FROM inscritos 
WHERE id_usuario = ? AND torneo_id = ? LIMIT 1

if ($rowInscrito existe) {
    $id_inscrito = (int)$rowInscrito['id'];
} else {
    // Crear nuevo registro en inscritos
    INSERT INTO inscritos 
    (id_usuario, torneo_id, id_club, codigo_equipo, estatus, posicion, ganados, perdidos, efectividad, puntos, ptosrnk, sancion, chancletas, zapatos, tarjeta, fecha_inscripcion, inscrito_por)
    VALUES (?, ?, ?, ?, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NOW(), ?)
    
    $id_inscrito = lastInsertId();
}
```

### Paso 5.4: Actualizar inscritos con código_equipo
```php
UPDATE inscritos 
SET id_club = ?, codigo_equipo = ?, estatus = 1
WHERE id = ?
```

---

## 6. COMMIT TRANSACCIÓN

```php
$pdo->commit();

echo json_encode([
    'success' => true,
    'message' => 'Equipo creado/actualizado exitosamente',
    'equipo_id' => $equipo_id
]);
```

---

## 7. MANEJO DE ERRORES

```php
catch (Exception $e) {
    $pdo->rollBack(); // Revierte todos los cambios
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
```

---

## POSIBLES PUNTOS DE FALLA

1. **Validación de datos**: `torneo_id`, `nombre_equipo`, `club_id` vacíos
2. **EquiposHelper::crearEquipo**: Error al crear equipo o generar código
3. **id_usuario no encontrado**: La cédula no existe en tabla `usuarios`
4. **Error en INSERT/UPDATE**: Campos faltantes o tipos incorrectos
5. **Transacción fallida**: Rollback si cualquier paso falla
6. **Campos de tabla inscritos**: Verificar que todos los campos existan








