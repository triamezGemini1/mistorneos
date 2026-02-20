<?php
/**
 * Configuración para la base de datos externa de personas
 * Base de datos: personas (desarrollo) / laestaci1_fvdadmin (producción)
 * Tabla: dbo_persona (desarrollo) / dbo.persona (producción)
 *
 * Optimizado para reutilizar conexiones y mejorar rendimiento
 */

// Asegurar que Environment esté disponible
if (!class_exists('Environment') && file_exists(__DIR__ . '/environment.php')) {
    require_once __DIR__ . '/environment.php';
}

class PersonaDatabase {
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $port;
    private $tableName;
    private $tableCandidates = [];
    private $tableIndex = 0;
    private $conn;
    private $enabled = true;
    
    // Pool estático de conexiones para reutilización
    private static $connectionPool = [];
    private static $connectionAttempts = [];
    private static $lastErrorTime = null;
    private static $dbUnavailable = false; // Flag para evitar intentos repetidos
    private static $dbUnavailableTime = null; // Timestamp cuando se marcó no disponible
    
    // Timeout para conexiones (en segundos)
    private const CONNECTION_TIMEOUT = 3;
    private const QUERY_TIMEOUT = 1;
    private const UNAVAILABLE_RESET_TIME = 60; // Reintentar después de 60 segundos
    
    public function __construct() {
        // Obtener configuración desde $GLOBALS['APP_CONFIG']['persona_db']
        $config = $GLOBALS['APP_CONFIG']['persona_db'] ?? null;
        $env = class_exists('Environment') ? Environment::get() : 'development';
        
        // Credenciales base por entorno (evitar fallos de base inexistente en dev)
        $defaultHost = 'localhost';
        $defaultDb = Environment::isProduction() ? 'laestaci1_fvdadmin' : 'personas';
        $defaultUser = Environment::isProduction() ? 'laestaci1_admin' : 'root';
        $defaultPass = Environment::isProduction() ? '' : ''; // Usar .env en producción
        // Tabla: dbo_persona (desarrollo y producción)
        $defaultTable = Environment::isProduction() ? 'dbo.persona' : 'dbo_persona';
        $tableRaw = $defaultTable;
        
        if (!$config) {
            // Usar credenciales por defecto (producción)
            $this->host = $defaultHost;
            $this->dbname = $defaultDb;
            $this->username = $defaultUser;
            $this->password = $defaultPass;
            $this->port = 3306;
            $this->tableName = "`{$defaultTable}`";
        } else {
            $this->host = $config['host'] ?? $defaultHost;
            // Usar base de datos según entorno
            if ($env === 'production') {
                $this->dbname = $config['name'] ?? $defaultDb;
            } else {
                // Permitir sobreescritura específica para desarrollo
                $this->dbname = $config['name_dev'] ?? $config['name'] ?? $defaultDb;
            }
            $this->username = $config['user'] ?? $defaultUser;
            $this->password = $config['pass'] ?? $defaultPass;
            $this->port = $config['port'] ?? 3306;
            // Seleccionar tabla según entorno
            if ($env === 'production') {
                $tableRaw = $config['table'] ?? $defaultTable;
            } else {
                $tableRaw = $config['table_dev'] ?? $config['table'] ?? $defaultTable;
            }
            $this->tableName = "`{$tableRaw}`";
        }

        // Preparar lista de tablas candidatas (dbo_personas, dbo.persona, persona, dbo_persona)
        $candidates = [];
        if (!empty($tableRaw)) {
            $candidates[] = $tableRaw;
        }
        $candidates[] = 'dbo_persona';
        $candidates[] = 'dbo.persona';
        $candidates[] = 'persona';
        $candidates[] = 'dbo_persona';
        if ($env !== 'production') {
            $candidates[] = 'dbo_persona';
        }
        $this->tableCandidates = array_values(array_unique(array_filter($candidates)));
        $this->tableIndex = 0;
        if (!empty($this->tableCandidates)) {
            $this->tableName = "`{$this->tableCandidates[0]}`";
        }
    }
    
    /**
     * Verifica si la búsqueda de personas está habilitada
     */
    public function isEnabled(): bool {
        // Resetear flag si ha pasado suficiente tiempo
        if (self::$dbUnavailable && self::$dbUnavailableTime !== null) {
            if (time() - self::$dbUnavailableTime > self::UNAVAILABLE_RESET_TIME) {
                self::$dbUnavailable = false;
                self::$dbUnavailableTime = null;
            }
        }
        return $this->enabled && !self::$dbUnavailable;
    }
    
    /**
     * Fuerza un reinicio del estado de disponibilidad
     */
    public static function resetAvailability() {
        self::$dbUnavailable = false;
        self::$dbUnavailableTime = null;
        self::$connectionPool = [];
    }

    /**
     * Obtiene una conexión reutilizable desde el pool o crea una nueva
     * Implementa timeout para evitar esperas largas
     */
    public function getConnection() {
        $cacheKey = $this->host . '_' . $this->port . '_' . $this->dbname;
        $now = time();
        
        // Verificar si existe una conexión válida en el pool
        if (isset(self::$connectionPool[$cacheKey])) {
            $pooledConn = self::$connectionPool[$cacheKey];
            
            // Verificar que la conexión sigue activa
            try {
                $pooledConn->query('SELECT 1');
                $this->conn = $pooledConn;
                return $this->conn;
            } catch (PDOException $e) {
                // La conexión está muerta, eliminarla del pool
                unset(self::$connectionPool[$cacheKey]);
            }
        }
        
        // Crear nueva conexión con timeout
        try {
            // Establecer timeout a nivel de PHP antes de conectar
            $originalTimeout = ini_get('default_socket_timeout');
            ini_set('default_socket_timeout', self::CONNECTION_TIMEOUT);
            
            // Intentar agregar timeout en el DSN si es posible (MySQL 5.6.6+)
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset=utf8mb4";
            
            // Crear conexión con opciones
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT => false, // No usar conexiones persistentes para mejor control
                PDO::ATTR_EMULATE_PREPARES => false, // Usar prepared statements nativos para mejor rendimiento
            ];
            
            // Crear conexión con timeout
            $startConnect = microtime(true);
            $this->conn = new PDO(
                $dsn,
                $this->username,
                $this->password,
                $options
            );
            
            $connectTime = microtime(true) - $startConnect;
            
            // Si la conexión tardó más de lo esperado, registrar
            if ($connectTime > 1.0) {
                error_log("PersonaDatabase: Conexión lenta - " . round($connectTime, 2) . "s");
            }
            
            // Restaurar timeout original
            ini_set('default_socket_timeout', $originalTimeout);
            
            // Establecer timeout para consultas a nivel de sesión MySQL
            // Nota: wait_timeout e interactive_timeout son para inactividad, no para consultas individuales
            // Para timeout de consultas, MySQL usa max_execution_time (MySQL 5.7.8+)
            try {
                // Intentar establecer max_execution_time (MySQL 5.7.8+)
                $this->conn->exec("SET SESSION max_execution_time = " . (self::QUERY_TIMEOUT * 1000)); // En milisegundos
            } catch (PDOException $e) {
                // Si la versión de MySQL no soporta max_execution_time, intentar con max_statement_time (MySQL 8.0.23+)
                try {
                    $this->conn->exec("SET SESSION max_statement_time = " . (self::QUERY_TIMEOUT * 1000));
                } catch (PDOException $e2) {
                    // Si ninguna funciona, continuar sin timeout a nivel de MySQL
                    // El timeout de PHP seguirá funcionando
                }
            }
            
            // Guardar en el pool para reutilización
            self::$connectionPool[$cacheKey] = $this->conn;
            
            // Si la conexión es exitosa, limpiar el cache de errores
            if (isset(self::$connectionAttempts[$cacheKey])) {
                unset(self::$connectionAttempts[$cacheKey]);
            }
            
        } catch(PDOException $exception) {
            $errorMsg = $exception->getMessage();
            
            // Si la base de datos no existe, marcar como no disponible temporalmente
            if (strpos($errorMsg, 'Unknown database') !== false || 
                strpos($errorMsg, "doesn't exist") !== false ||
                strpos($errorMsg, 'Access denied') !== false) {
                self::$dbUnavailable = true;
                self::$dbUnavailableTime = time();
            }
            
            // Solo registrar error si no se ha registrado en los últimos 5 minutos
            if (!isset(self::$connectionAttempts[$cacheKey]) || 
                ($now - self::$connectionAttempts[$cacheKey]) > 300) {
                error_log("PersonaDatabase: Error de conexión - " . $errorMsg);
                self::$connectionAttempts[$cacheKey] = $now;
            }
            $this->conn = null;
        }
        
        return $this->conn;
    }
    
    /**
     * Busca una persona por nacionalidad y cédula
     * Tabla: dbo.persona en base de datos fvdadmin
     */
    public function searchPersonaById($nacionalidad, $cedula) {
        // Verificar si debemos reintentar (después del timeout)
        if (self::$dbUnavailable && self::$dbUnavailableTime !== null) {
            if (time() - self::$dbUnavailableTime > self::UNAVAILABLE_RESET_TIME) {
                self::$dbUnavailable = false;
                self::$dbUnavailableTime = null;
            }
        }
        
        // Si la BD no está disponible, retornar inmediatamente
        if (self::$dbUnavailable) {
            return [
                'encontrado' => false,
                'error' => 'Búsqueda externa deshabilitada temporalmente'
            ];
        }
        
        $startTime = microtime(true);
        
        try {
            $conn = $this->getConnection();
            
            if (!$conn) {
                return [
                    'encontrado' => false,
                    'error' => 'Base de datos externa no disponible'
                ];
            }
            
            // Validar y limpiar parámetros
            $cedula = trim($cedula);
            $nacionalidad = strtoupper(trim($nacionalidad));
            
            if (empty($cedula) || empty($nacionalidad)) {
                return [
                    'encontrado' => false,
                    'error' => 'Parámetros inválidos'
                ];
            }
            
            $persona = null;
            $tableErrorMsg = null;
            $tables = !empty($this->tableCandidates) ? $this->tableCandidates : [trim($this->tableName, '`')];

            foreach ($tables as $idx => $rawTable) {
                $this->tableIndex = $idx;
                $table_name = "`{$rawTable}`";
                $this->tableName = $table_name;

                try {
                    // Búsqueda por cédula y nacionalidad (incluir Nac para devolver nacionalidad desde la BD)
                    $query = "SELECT Nombre1, Nombre2, Apellido1, Apellido2, FNac, Sexo, Nac
                              FROM {$table_name}
                              WHERE IDUsuario = :cedula AND Nac = :nacionalidad 
                              LIMIT 1";
                    
                    $stmt = $conn->prepare($query);
                    $stmt->bindValue(':cedula', $cedula, PDO::PARAM_STR);
                    $stmt->bindValue(':nacionalidad', $nacionalidad, PDO::PARAM_STR);
                    $stmt->execute();
                    
                    $persona = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Si no se encuentra, intentar solo por cédula
                    if (!$persona) {
                        $query2 = "SELECT Nombre1, Nombre2, Apellido1, Apellido2, FNac, Sexo, Nac
                                  FROM {$table_name}
                                  WHERE IDUsuario = :cedula 
                                  LIMIT 1";
                        
                        $stmt2 = $conn->prepare($query2);
                        $stmt2->bindValue(':cedula', $cedula, PDO::PARAM_STR);
                        $stmt2->execute();
                        
                        $persona = $stmt2->fetch(PDO::FETCH_ASSOC);
                    }

                    // Consulta exitosa (sin excepciones), romper el loop
                    break;
                } catch (PDOException $tableError) {
                    $tableErrorMsg = $tableError->getMessage();
                    // Si la tabla no existe, probar siguiente candidato
                    if (strpos($tableErrorMsg, "doesn't exist") !== false ||
                        strpos($tableErrorMsg, 'Base table or view not found') !== false ||
                        strpos($tableErrorMsg, '42S02') !== false) {
                        continue;
                    }
                    // Otro error: propagar
                    throw $tableError;
                }
            }

            if ($persona === null && $tableErrorMsg) {
                error_log("PersonaDatabase: Error en tablas candidatas (" . implode(',', $tables) . "): " . $tableErrorMsg);
            }
            
            $elapsedTime = microtime(true) - $startTime;
            
            if ($persona) {
                // Concatenar nombres y apellidos
                $parts = array_filter([
                    $persona['Nombre1'] ?? '',
                    $persona['Nombre2'] ?? '',
                    $persona['Apellido1'] ?? '',
                    $persona['Apellido2'] ?? ''
                ]);
                $nombreCompleto = trim(implode(' ', $parts));
                
                // Formatear fecha de nacimiento
                $fechaNacimiento = null;
                if (!empty($persona['FNac'])) {
                    try {
                        $fechaNacimiento = date('Y-m-d', strtotime($persona['FNac']));
                    } catch (Exception $e) {
                        $fechaNacimiento = null;
                    }
                }
                
                // Cédula solo numérica (IDUsuario en BD externa); nacionalidad en su propio campo
                $cedulaNumerica = preg_replace('/\D/', '', $cedula);
                $nac = isset($persona['Nac']) ? trim($persona['Nac']) : $nacionalidad;
                if (!in_array(strtoupper($nac), ['V', 'E', 'J', 'P'])) {
                    $nac = $nacionalidad;
                }
                // Sexo: leer columna (Sexo o sexo según BD) y normalizar a M/F/O para usuarios
                $sexoRaw = $persona['Sexo'] ?? $persona['sexo'] ?? '';
                $sexoNormalizado = '';
                if ($sexoRaw !== '' && $sexoRaw !== null) {
                    $s = strtoupper(trim((string) $sexoRaw));
                    if (in_array($s, ['M', '1', 'MASCULINO', 'MALE'], true)) {
                        $sexoNormalizado = 'M';
                    } elseif (in_array($s, ['F', '2', 'FEMENINO', 'FEMALE'], true)) {
                        $sexoNormalizado = 'F';
                    } else {
                        $sexoNormalizado = 'O';
                    }
                }
                return [
                    'encontrado' => true,
                    'fuente' => 'externa',
                    'persona' => [
                        'cedula' => $cedulaNumerica,
                        'nacionalidad' => strtoupper($nac),
                        'nombre' => $nombreCompleto,
                        'sexo' => $sexoNormalizado,
                        'fechnac' => $fechaNacimiento,
                        'celular' => ''
                    ]
                ];
            } else {
                return [
                    'encontrado' => false,
                    'error' => 'No se encontró persona con esa cédula'
                ];
            }
            
        } catch (PDOException $e) {
            $errorMsg = $e->getMessage();
            
            // Si la BD no existe, marcarla como no disponible
            if (strpos($errorMsg, 'Unknown database') !== false) {
                self::$dbUnavailable = true;
            }
            
            // No mostrar errores al usuario, solo registrar
            error_log("PersonaDatabase ERROR: " . $errorMsg);
            
            return [
                'encontrado' => false,
                'error' => 'Búsqueda no disponible'
            ];
        } catch (Exception $e) {
            error_log("PersonaDatabase ERROR: " . $e->getMessage());
            return [
                'encontrado' => false,
                'error' => 'Error en la búsqueda'
            ];
        }
    }
    
    /**
     * Obtiene N personas aleatorias de la base de datos externa
     * Útil para scripts de migración o generación de usuarios de prueba
     * 
     * Optimizado para tablas grandes usando OFFSET aleatorio en lugar de ORDER BY RAND()
     * 
     * @param int $limit Cantidad de personas a obtener
     * @return array Lista de personas con: id_usuario, nac, nombre, sexo, fechnac
     */
    public function getRandomPersonas(int $limit = 100): array {
        if (self::$dbUnavailable) {
            return [];
        }
        try {
            $conn = $this->getConnection();
            if (!$conn) {
                return [];
            }
            $tables = !empty($this->tableCandidates) ? $this->tableCandidates : [trim($this->tableName, '`')];
            foreach ($tables as $idx => $rawTable) {
                $table_name = "`{$rawTable}`";
                try {
                    // Estrategia optimizada para tablas grandes: usar WHERE con condición aleatoria
                    // Seleccionar registros donde MOD(IDUsuario, N) = valor_aleatorio
                    // Esto es mucho más rápido que OFFSET en tablas de millones de registros
                    $modulo = 100; // Dividir en 100 grupos
                    $randomGroup = mt_rand(0, $modulo - 1);
                    
                    $query = "SELECT IDUsuario, Nac, Nombre1, Nombre2, Apellido1, Apellido2, FNac, Sexo
                              FROM {$table_name}
                              WHERE CAST(IDUsuario AS UNSIGNED) % {$modulo} = {$randomGroup}
                              LIMIT " . (int)$limit;
                    
                    $stmt = $conn->query($query);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $personas = [];
                    foreach ($rows as $r) {
                        $parts = array_filter([
                            $r['Nombre1'] ?? '',
                            $r['Nombre2'] ?? '',
                            $r['Apellido1'] ?? '',
                            $r['Apellido2'] ?? ''
                        ]);
                        $nombreCompleto = trim(implode(' ', $parts));
                        $fechnac = null;
                        if (!empty($r['FNac'])) {
                            try {
                                $fechnac = date('Y-m-d', strtotime($r['FNac']));
                            } catch (Exception $e) {
                                $fechnac = null;
                            }
                        }
                        $sexo = strtoupper(trim($r['Sexo'] ?? 'M'));
                        if (!in_array($sexo, ['M', 'F'])) {
                            $sexo = ($sexo === 'F' || $sexo === '2' || $sexo === 'FEMENINO') ? 'F' : 'M';
                        }
                        $personas[] = [
                            'id_usuario' => $r['IDUsuario'] ?? '',
                            'nac' => strtoupper(trim($r['Nac'] ?? 'V')),
                            'nombre' => $nombreCompleto,
                            'sexo' => $sexo,
                            'fechnac' => $fechnac
                        ];
                    }
                    return $personas;
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), "doesn't exist") !== false) {
                        continue;
                    }
                    throw $e;
                }
            }
        } catch (Exception $e) {
            error_log("PersonaDatabase::getRandomPersonas ERROR: " . $e->getMessage());
        }
        return [];
    }

    /**
     * Obtiene N personas aleatorias usando ORDER BY RAND() con nombres y apellidos válidos.
     * Pensado para scripts de seed (usuarios de prueba): garantiza Nombre1 y Apellido1
     * con al menos 2 caracteres para poder generar usuario tipo "JuPe".
     *
     * @param int $limit Cantidad de personas a obtener
     * @return array Lista de personas: id_usuario, nac, nombre, nombre1, apellido1, sexo, fechnac
     */
    public function getRandomPersonasForSeed(int $limit = 100): array {
        if (self::$dbUnavailable) {
            return [];
        }
        try {
            $conn = $this->getConnection();
            if (!$conn) {
                return [];
            }
            // Priorizar dbo_persona (singular) por ser el nombre habitual en BD personas
            $seedTables = ['dbo_persona', 'dbo_personas'];
            $tables = array_values(array_unique(array_merge($seedTables, $this->tableCandidates ?? [])));
            foreach ($tables as $idx => $rawTable) {
                $table_name = "`{$rawTable}`";
                try {
                    $limit = max(1, (int)$limit);
                    // Intentar con mayúsculas (Nombre1, Apellido1) y si falla con minúsculas (nombre1, apellido1)
                    $columnSets = [
                        ['Nombre1', 'Nombre2', 'Apellido1', 'Apellido2', 'IDUsuario', 'Nac', 'FNac', 'Sexo'],
                        ['nombre1', 'nombre2', 'apellido1', 'apellido2', 'id_usuario', 'nac', 'fnac', 'sexo'],
                    ];
                    $rows = [];
                    foreach ($columnSets as $cols) {
                        $n1 = $cols[0];
                        $a1 = $cols[2];
                        foreach (['strict' => true, 'relaxed' => false] as $mode => $strict) {
                            $lengthCond = $strict
                                ? " AND LENGTH(TRIM({$n1})) >= 2 AND LENGTH(TRIM({$a1})) >= 2"
                                : '';
                            $query = "SELECT {$cols[4]} AS IDUsuario, {$cols[5]} AS Nac, {$cols[0]} AS Nombre1, {$cols[1]} AS Nombre2, {$cols[2]} AS Apellido1, {$cols[3]} AS Apellido2, {$cols[6]} AS FNac, {$cols[7]} AS Sexo
                                      FROM {$table_name}
                                      WHERE TRIM(COALESCE({$n1},'')) != '' AND TRIM(COALESCE({$a1},'')) != ''{$lengthCond}
                                      ORDER BY RAND()
                                      LIMIT " . ($strict ? $limit : min($limit * 3, 500));
                            try {
                                $stmt = $conn->query($query);
                                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                if (!empty($rows)) {
                                    break 2;
                                }
                            } catch (PDOException $e) {
                                if (strpos($e->getMessage(), 'Unknown column') !== false) {
                                    break;
                                }
                                throw $e;
                            }
                        }
                    }
                    if (empty($rows)) {
                        continue;
                    }
                    $personas = [];
                    foreach ($rows as $r) {
                        $nombre1 = trim($r['Nombre1'] ?? '');
                        $apellido1 = trim($r['Apellido1'] ?? '');
                        if (strlen($nombre1) < 2 || strlen($apellido1) < 2) {
                            continue;
                        }
                        $parts = array_filter([
                            $r['Nombre1'] ?? '',
                            $r['Nombre2'] ?? '',
                            $r['Apellido1'] ?? '',
                            $r['Apellido2'] ?? ''
                        ]);
                        $nombreCompleto = trim(implode(' ', $parts));
                        $fechnac = null;
                        if (!empty($r['FNac'])) {
                            try {
                                $fechnac = date('Y-m-d', strtotime($r['FNac']));
                            } catch (Exception $e) {
                                $fechnac = null;
                            }
                        }
                        $sexo = strtoupper(trim($r['Sexo'] ?? 'M'));
                        if (!in_array($sexo, ['M', 'F'])) {
                            $sexo = ($sexo === 'F' || $sexo === '2' || $sexo === 'FEMENINO') ? 'F' : 'M';
                        }
                        $personas[] = [
                            'id_usuario' => $r['IDUsuario'] ?? '',
                            'nac' => strtoupper(trim($r['Nac'] ?? 'V')),
                            'nombre' => $nombreCompleto,
                            'nombre1' => $nombre1,
                            'apellido1' => $apellido1,
                            'sexo' => $sexo,
                            'fechnac' => $fechnac
                        ];
                    }
                    return array_slice($personas, 0, $limit);
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), "doesn't exist") !== false) {
                        continue;
                    }
                    throw $e;
                }
            }
        } catch (Exception $e) {
            error_log("PersonaDatabase::getRandomPersonasForSeed ERROR: " . $e->getMessage());
        }
        return [];
    }

    /**
     * Limpia el pool de conexiones y resetea el flag de disponibilidad
     */
    public static function clearConnectionPool() {
        self::$connectionPool = [];
        self::$dbUnavailable = false;
    }
}
