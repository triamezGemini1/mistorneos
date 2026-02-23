<?php
/**
 * Clase DB - Gestión de conexiones a base de datos
 * 
 * Soporta dos conexiones:
 * - Principal (mistorneos): Torneos, usuarios, inscripciones, resultados
 * - Secundaria (fvdadmin): Datos de apoyo para búsquedas
 */
if (!defined('APP_BOOTSTRAPPED')) { require __DIR__ . '/bootstrap.php'; }

class DB {
  private static $pdo = null;           // Conexión principal (mistorneos)
  private static $pdoSecondary = null;  // Conexión secundaria (fvdadmin)

  /**
   * Obtiene la conexión principal (mistorneos)
   */
  public static function pdo(): PDO {
    if (self::$pdo === null) {
      self::$pdo = self::createConnection('primary');
    }
    return self::$pdo;
  }

  /**
   * Obtiene la conexión secundaria (fvdadmin)
   */
  public static function pdoSecondary(): PDO {
    if (self::$pdoSecondary === null) {
      self::$pdoSecondary = self::createConnection('secondary');
    }
    return self::$pdoSecondary;
  }

  /**
   * Alias para la conexión principal
   */
  public static function mistorneos(): PDO {
    return self::pdo();
  }

  /**
   * Alias para la conexión secundaria
   */
  public static function fvdadmin(): PDO {
    return self::pdoSecondary();
  }

  /**
   * Crea una conexión PDO
   */
  private static function createConnection(string $type = 'primary'): PDO {
    $cfg = $GLOBALS['APP_CONFIG']['db'] ?? [];
    
    if ($type === 'secondary') {
      // Conexión secundaria (fvdadmin) — según ámbito APP_ENV
      $host = Env::getDbSecondary('HOST') ?: ($cfg['secondary_host'] ?? 'localhost');
      $port = Env::getDbSecondary('PORT') ?: ($cfg['secondary_port'] ?? '3306');
      $name = Env::getDbSecondary('DATABASE') ?: ($cfg['secondary_name'] ?? 'fvdadmin');
      $user = Env::getDbSecondary('USERNAME') ?: ($cfg['secondary_user'] ?? 'root');
      $pass = Env::getDbSecondary('PASSWORD') ?: ($cfg['secondary_pass'] ?? '');
      $charset = $cfg['secondary_charset'] ?? 'utf8mb4';
      $dbLabel = 'fvdadmin (secundaria)';
    } else {
      // Conexión principal (mistorneos) — según ámbito APP_ENV (DB_DEV_* o DB_PROD_*)
      $host = Env::getDb('HOST') ?: ($cfg['host'] ?? 'localhost');
      $port = Env::getDb('PORT') ?: ($cfg['port'] ?? '3306');
      $name = Env::getDb('DATABASE') ?: ($cfg['name'] ?? 'mistorneos');
      $user = Env::getDb('USERNAME') ?: ($cfg['user'] ?? 'root');
      $pass = Env::getDb('PASSWORD') ?: ($cfg['pass'] ?? '');
      $charset = $cfg['charset'] ?? 'utf8mb4';
      $dbLabel = 'mistorneos (principal)';
    }
    
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
    $opt = [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
      PDO::ATTR_TIMEOUT => 5,
    ];
    
    try {
      $pdo = new PDO($dsn, $user, $pass, $opt);
      $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
      return $pdo;
    } catch (PDOException $e) {
      self::handleConnectionError($e, $dbLabel);
      throw $e;
    }
  }

  /**
   * Maneja errores de conexión
   */
  private static function handleConnectionError(PDOException $e, string $dbLabel): void {
    if (strpos($e->getMessage(), '2002') !== false || strpos($e->getMessage(), 'denegó') !== false) {
      $error_msg = "No se puede conectar a MySQL ({$dbLabel}).";
      if (php_sapi_name() !== 'cli') {
        http_response_code(503);
        die("
          <html>
            <head><title>Error de Conexión</title></head>
            <body style='font-family: Arial; padding: 40px; text-align: center;'>
              <h1>⚠️ Error de Conexión a la Base de Datos</h1>
              <p style='font-size: 18px; color: #666;'>Base de datos: <strong>{$dbLabel}</strong></p>
              <p style='color: #999;'>MySQL no está disponible o las credenciales son incorrectas.</p>
            </body>
          </html>
        ");
      }
      throw new PDOException($error_msg, (int)$e->getCode(), $e);
    }
  }

  /**
   * Verifica si la conexión principal está disponible
   */
  public static function isConnected(): bool {
    try {
      self::pdo();
      return true;
    } catch (PDOException $e) {
      return false;
    }
  }

  /**
   * Verifica si la conexión secundaria está disponible
   */
  public static function isSecondaryConnected(): bool {
    try {
      self::pdoSecondary();
      return true;
    } catch (PDOException $e) {
      return false;
    }
  }

  /**
   * Ejecuta una consulta en ambas bases de datos
   * Útil para búsquedas combinadas
   */
  public static function queryBoth(string $sql, array $params = []): array {
    $results = [
      'primary' => [],
      'secondary' => []
    ];
    
    try {
      $stmt = self::pdo()->prepare($sql);
      $stmt->execute($params);
      $results['primary'] = $stmt->fetchAll();
    } catch (PDOException $e) {
      $results['primary_error'] = $e->getMessage();
    }
    
    try {
      $stmt = self::pdoSecondary()->prepare($sql);
      $stmt->execute($params);
      $results['secondary'] = $stmt->fetchAll();
    } catch (PDOException $e) {
      $results['secondary_error'] = $e->getMessage();
    }
    
    return $results;
  }
}

// Nombre de la tabla de invitaciones (por defecto 'invitaciones'; en .env puede definirse TABLE_INVITATIONS=invitations)
if (!defined('TABLE_INVITATIONS')) {
  define('TABLE_INVITATIONS', (class_exists('Env') && Env::has('TABLE_INVITATIONS')) ? (string) Env::get('TABLE_INVITATIONS') : 'invitaciones');
}
