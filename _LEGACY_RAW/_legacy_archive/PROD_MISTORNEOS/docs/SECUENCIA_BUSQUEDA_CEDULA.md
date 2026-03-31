# Secuencia de Funciones al Buscar una Cédula

## Flujo Completo de Búsqueda

### 1. **FRONTEND - Formulario (JavaScript)**

**Archivo:** `public/affiliate_request.php` (líneas 388-431)

```javascript
function buscarPersona() {
    // 1.1. Obtener valores del formulario
    const cedula = document.getElementById('cedula').value.trim();
    const nacionalidad = document.getElementById('nacionalidad').value;
    
    // 1.2. Validar que haya cédula
    if (!cedula) return;
    
    // 1.3. Mostrar indicador de carga
    resultadoDiv.innerHTML = '<span>Buscando...</span>';
    
    // 1.4. Hacer petición AJAX al endpoint
    fetch(`${base_url}/api/search_user_persona.php?cedula=${cedula}&nacionalidad=${nacionalidad}`)
        .then(response => response.json())
        .then(data => {
            // 1.5. Procesar respuesta y llenar formulario
            if (data.success && data.data.encontrado) {
                // Llenar campos: nombre, celular, email, fechnac
            }
        });
}
```

**Evento:** Se ejecuta cuando el usuario escribe en el campo de cédula o hace clic en buscar.

---

### 2. **BACKEND - Endpoint API**

**Archivo:** `public/api/search_user_persona.php` (líneas 1-104)

#### 2.1. Inicialización
```php
// 2.1.1. Cargar bootstrap (configuración, helpers)
require_once __DIR__ . '/../../config/bootstrap.php';

// 2.1.2. Cargar conexión a base de datos principal
require_once __DIR__ . '/../../config/db.php';
```

**Secuencia en `bootstrap.php`:**
- Carga `lib/Env.php` → Lee variables de entorno
- Carga `config/environment.php` → Detecta entorno (producción/desarrollo)
- Carga `config/config.production.php` o `config/config.development.php`
- Carga `lib/app_helpers.php` → Helpers de URL
- Carga `lib/Log.php` → Sistema de logging
- Inicia sesión PHP

#### 2.2. Validación de Parámetros
```php
// 2.2.1. Obtener parámetros GET
$cedula = trim($_GET['cedula'] ?? '');
$nacionalidad = trim($_GET['nacionalidad'] ?? 'V');

// 2.2.2. Validar que haya cédula
if (empty($cedula)) {
    return error JSON;
}

// 2.2.3. Validar nacionalidad (V, E, J, P)
if (!in_array($nacionalidad, ['V', 'E', 'J', 'P'])) {
    $nacionalidad = 'V';
}
```

#### 2.3. Búsqueda en Base de Datos Principal
```php
// 2.3.1. Buscar en tabla 'usuarios' (BD principal)
$stmt = DB::pdo()->prepare("SELECT id, username, nombre FROM usuarios WHERE cedula = ?");
$stmt->execute([$cedula]);
$existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

// 2.3.2. Si existe usuario, retornar error
if ($existingUser) {
    return JSON: "Ya existe un usuario con esta cédula";
}
```

**Función:** `DB::pdo()` → Retorna conexión PDO a base de datos principal (`mistorneos` o `laestaci1_fvdadmin`)

#### 2.4. Búsqueda en Base de Datos Externa
```php
// 2.4.1. Verificar que existe archivo de configuración
if (file_exists(__DIR__ . '/../../config/persona_database.php')) {
    
    // 2.4.2. Cargar clase PersonaDatabase
    require_once __DIR__ . '/../../config/persona_database.php';
    
    // 2.4.3. Instanciar clase
    $database = new PersonaDatabase();
    
    // 2.4.4. Buscar persona
    $result = $database->searchPersonaById($nacionalidad, $cedula);
    
    // 2.4.5. Si encuentra, retornar datos
    if ($result['encontrado'] && isset($result['persona'])) {
        return JSON con datos de la persona;
    }
}
```

#### 2.5. Respuesta Final
```php
// Si no encontró en ningún lado
return JSON: "Persona no encontrada. Puede ingresar los datos manualmente."
```

---

### 3. **BACKEND - Clase PersonaDatabase**

**Archivo:** `config/persona_database.php`

#### 3.1. Constructor
```php
public function __construct() {
    // 3.1.1. Obtener configuración desde $GLOBALS['APP_CONFIG']['persona_db']
    $config = $GLOBALS['APP_CONFIG']['persona_db'] ?? null;
    
    // 3.1.2. Si no hay configuración, usar valores por defecto
    if (!$config) {
        $this->host = 'localhost';
        $this->dbname = 'personas';
        $this->username = 'root';
        $this->password = '';
    } else {
        // 3.1.3. Usar configuración de producción/desarrollo
        $this->host = $config['host'] ?? 'localhost';
        $this->dbname = $config['name'] ?? 'personas';
        $this->username = $config['user'] ?? 'root';
        $this->password = $config['pass'] ?? '';
    }
}
```

#### 3.2. Método: `searchPersonaById($nacionalidad, $cedula)`

**Línea 150:** Inicio del método

```php
public function searchPersonaById($nacionalidad, $cedula) {
    // 3.2.1. Iniciar timer para medir rendimiento
    $startTime = microtime(true);
    
    // 3.2.2. Obtener conexión a BD externa
    $conn = $this->getConnection();
    
    // 3.2.3. Si no hay conexión, retornar error
    if (!$conn) {
        return ['encontrado' => false, 'error' => 'BD externa no disponible'];
    }
    
    // 3.2.4. Validar y limpiar parámetros
    $cedula = trim($cedula);
    $nacionalidad = trim($nacionalidad);
    
    // 3.2.5. Detectar si está en producción
    $is_production = class_exists('Environment') ? Environment::isProduction() : false;
    
    // 3.2.6. Determinar tabla según entorno
    if ($is_production) {
        // PRODUCCIÓN: Intentar con 'persona' y luego 'dbo.persona'
        $tables_to_try = ['persona', '`dbo.persona`'];
    } else {
        // DESARROLLO: Usar 'dbo_persona_staging'
        $table_name = 'dbo_persona_staging';
    }
    
    // 3.2.7. Ejecutar búsqueda
    foreach ($tables_to_try as $table_name) {
        // Preparar query
        $query = "SELECT Nombre1, Nombre2, Apellido1, Apellido2, FNac, Sexo
                  FROM {$table_name}
                  WHERE IDUsuario = :cedula AND Nac = :nacionalidad 
                  LIMIT 1";
        
        // Preparar statement
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':cedula', $cedula, PDO::PARAM_STR);
        $stmt->bindValue(':nacionalidad', $nacionalidad, PDO::PARAM_STR);
        
        // Ejecutar
        $stmt->execute();
        
        // Obtener resultado
        $persona = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Si encuentra, salir del loop
        if ($persona) break;
    }
    
    // 3.2.8. Procesar resultado
    if ($persona) {
        // Concatenar nombres
        $nombreCompleto = trim(implode(' ', [
            $persona['Nombre1'],
            $persona['Nombre2'],
            $persona['Apellido1'],
            $persona['Apellido2']
        ]));
        
        // Formatear fecha
        $fechaNacimiento = date('Y-m-d', strtotime($persona['FNac']));
        
        // Retornar datos
        return [
            'encontrado' => true,
            'fuente' => 'externa',
            'persona' => [
                'nombre' => $nombreCompleto,
                'sexo' => $persona['Sexo'],
                'fechnac' => $fechaNacimiento,
                'celular' => ''
            ]
        ];
    }
    
    // 3.2.9. Si no encuentra, retornar error
    return ['encontrado' => false, 'error' => 'No encontrado'];
}
```

#### 3.3. Método: `getConnection()`

**Línea 48:** Método para obtener conexión

```php
public function getConnection() {
    // 3.3.1. Crear clave de cache para pool de conexiones
    $cacheKey = $this->host . '_' . $this->port . '_' . $this->dbname;
    
    // 3.3.2. Verificar si existe conexión en pool
    if (isset(self::$connectionPool[$cacheKey])) {
        $pooledConn = self::$connectionPool[$cacheKey];
        
        // 3.3.3. Verificar que la conexión sigue activa
        try {
            $pooledConn->query('SELECT 1');
            return $pooledConn; // Reutilizar conexión existente
        } catch (PDOException $e) {
            // Conexión muerta, eliminarla del pool
            unset(self::$connectionPool[$cacheKey]);
        }
    }
    
    // 3.3.4. Crear nueva conexión
    try {
        // Establecer timeout
        ini_set('default_socket_timeout', self::CONNECTION_TIMEOUT);
        
        // Crear DSN
        $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset=utf8mb4";
        
        // Crear conexión PDO
        $this->conn = new PDO($dsn, $this->username, $this->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        
        // Guardar en pool para reutilización
        self::$connectionPool[$cacheKey] = $this->conn;
        
        return $this->conn;
    } catch (PDOException $e) {
        error_log("PersonaDatabase: Error de conexión - " . $e->getMessage());
        return null;
    }
}
```

---

## Diagrama de Flujo

```
┌─────────────────────────────────────────────────────────────┐
│ 1. FRONTEND (JavaScript)                                     │
│    affiliate_request.php → buscarPersona()                   │
│    ↓                                                          │
│    fetch('/api/search_user_persona.php?cedula=X&nacionalidad=V')│
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 2. BACKEND - API Endpoint                                    │
│    public/api/search_user_persona.php                        │
│    ↓                                                          │
│    2.1. bootstrap.php → Carga configuración                  │
│    2.2. Validar parámetros                                   │
│    2.3. DB::pdo() → Buscar en tabla 'usuarios' (BD principal)│
│         ↓ Si existe usuario → Retornar error                 │
│    2.4. new PersonaDatabase() → Buscar en BD externa         │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 3. BACKEND - PersonaDatabase                                │
│    config/persona_database.php                              │
│    ↓                                                          │
│    3.1. __construct() → Leer configuración                  │
│    3.2. searchPersonaById() → Buscar persona                │
│         ↓                                                     │
│         3.2.1. getConnection() → Obtener conexión PDO        │
│         ↓                                                     │
│         3.2.2. Detectar entorno (producción/desarrollo)      │
│         ↓                                                     │
│         3.2.3. Determinar tabla:                             │
│                - Producción: ['persona', '`dbo.persona`']   │
│                - Desarrollo: 'dbo_persona_staging'           │
│         ↓                                                     │
│         3.2.4. Ejecutar query SQL                            │
│         ↓                                                     │
│         3.2.5. Procesar resultado                            │
│         ↓                                                     │
│         3.2.6. Retornar array con datos                      │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 4. BASE DE DATOS EXTERNA                                     │
│    laestaci1_fvdadmin                                        │
│    Tabla: persona o dbo.persona                              │
│    Query: SELECT ... WHERE IDUsuario = :cedula AND Nac = :nacionalidad│
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 5. RESPUESTA JSON                                            │
│    Retorna a JavaScript → Llenar formulario                  │
└─────────────────────────────────────────────────────────────┘
```

---

## Archivos Involucrados (en orden de ejecución)

1. **`public/affiliate_request.php`** (línea 388)
   - Función JavaScript `buscarPersona()`
   - Hace petición AJAX

2. **`public/api/search_user_persona.php`** (línea 1)
   - Endpoint API que recibe la petición
   - Valida parámetros
   - Busca en BD principal
   - Llama a PersonaDatabase

3. **`config/bootstrap.php`** (línea 1)
   - Carga configuración del sistema
   - Inicializa entorno

4. **`config/environment.php`** (línea 1)
   - Detecta si está en producción o desarrollo
   - Carga archivo de configuración correspondiente

5. **`config/config.production.php`** o **`config/config.development.php`**
   - Contiene credenciales de base de datos
   - Configuración de `persona_db`

6. **`config/persona_database.php`** (línea 1)
   - Clase `PersonaDatabase`
   - Método `getConnection()` → Obtiene conexión PDO
   - Método `searchPersonaById()` → Busca persona

7. **Base de Datos Externa: `laestaci1_fvdadmin`**
   - Tabla: `persona` o `dbo.persona`
   - Ejecuta query SQL

---

## Puntos Clave

1. **Detección de Entorno:** `Environment::isProduction()` determina qué tabla usar
2. **Pool de Conexiones:** Las conexiones se reutilizan para mejor rendimiento
3. **Fallback de Tablas:** En producción intenta primero `persona`, luego `dbo.persona`
4. **Timeout:** Conexiones tienen timeout de 3 segundos, queries de 1 segundo
5. **Logging:** Todos los pasos se registran en logs para debugging

---

## Logs Esperados

En producción, deberías ver en los logs:

```
PersonaDatabase PRODUCCIÓN: Buscando - Nac=V, Cedula=12345678
PersonaDatabase: Intentando tabla persona
PersonaDatabase: ✓ Encontrado en tabla persona
```

O si la primera tabla no funciona:

```
PersonaDatabase: ✗ No encontrado en tabla persona
PersonaDatabase: Intentando tabla `dbo.persona`
PersonaDatabase: ✓ Encontrado en tabla `dbo.persona`
```




