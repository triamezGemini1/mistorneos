<?php



/**
 * Bootstrap para Testing
 * 
 * Inicializa entorno de testing y carga autoloader
 */

// Definir que estamos en testing
define('TESTING', true);
define('APP_BOOTSTRAPPED', true);

// Cargar Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Cargar configuraci�n de testing
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_DATABASE'] = 'mistorneos_test';

// Iniciar sesi�n para tests que la requieran
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper para tests
function test_db_connection(): PDO
{
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = sprintf(
            "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
            $_ENV['DB_HOST'] ?? 'localhost',
            $_ENV['DB_PORT'] ?? '3306',
            $_ENV['DB_DATABASE'] ?? 'mistorneos_test'
        );
        
        $pdo = new PDO(
            $dsn,
            $_ENV['DB_USERNAME'] ?? 'root',
            $_ENV['DB_PASSWORD'] ?? '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
    
    return $pdo;
}

// Limpiar base de datos de testing
function clean_test_database(): void
{
    $pdo = test_db_connection();
    
    // Obtener todas las tablas
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    // Deshabilitar foreign key checks temporalmente
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    foreach ($tables as $table) {
        $pdo->exec("TRUNCATE TABLE `$table`");
    }
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
}

// Crear datos de prueba
function seed_test_data(): void
{
    $pdo = test_db_connection();
    
    // Insertar usuarios de prueba
    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password, role, status, created_at) 
        VALUES (:username, :email, :password, :role, 1, NOW())
    ");
    
    $testUsers = [
        ['username' => 'admin_test', 'email' => 'admin@test.com', 'password' => password_hash('admin123', PASSWORD_DEFAULT), 'role' => 'admin_general'],
        ['username' => 'user_test', 'email' => 'user@test.com', 'password' => password_hash('user123', PASSWORD_DEFAULT), 'role' => 'usuario'],
    ];
    
    foreach ($testUsers as $user) {
        $stmt->execute($user);
    }
}

echo "\n? Test bootstrap loaded successfully\n\n";







