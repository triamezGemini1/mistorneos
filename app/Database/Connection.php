<?php

declare(strict_types=1);

final class Connection
{
    private static ?PDO $pdo = null;

    /** @var PDO|null BD auxiliar de personas (solo validación en registro; no es la tabla maestra). */
    private static ?PDO $pdoSecondary = null;

    /** @var bool|null null = aún no probado, false = deshabilitada o fallo */
    private static ?bool $secondaryAvailable = null;

    public static function get(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_DATABASE') ?: 'mistorneos';
        $user = getenv('DB_USERNAME') ?: 'root';
        $pass = getenv('DB_PASSWORD') ?: '';
        $charset = getenv('DB_CHARSET') ?: 'utf8mb4';

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $host,
            $port,
            $name,
            $charset
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
            $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
            self::$pdo = $pdo;

            return self::$pdo;
        } catch (PDOException $e) {
            error_log('mistorneos DB: conexión fallida — ' . $e->getMessage());
            throw new ConnectionException(
                'No se pudo conectar con la base de datos. Intente más tarde o contacte al administrador.'
            );
        }
    }

    /**
     * BD auxiliar de personas (p. ej. dbo_persona). Solo para verificar identidad al registrar usuarios.
     * La tabla maestra de la app es `usuarios` en la BD principal; login y buscador del landing usan esa.
     */
    public static function getSecondaryOptional(): ?PDO
    {
        if (self::$secondaryAvailable === false) {
            return null;
        }
        if (self::$pdoSecondary instanceof PDO) {
            return self::$pdoSecondary;
        }

        $name = getenv('DB_SECONDARY_DATABASE');
        if (!is_string($name) || trim($name) === '') {
            self::$secondaryAvailable = false;

            return null;
        }

        $host = getenv('DB_SECONDARY_HOST') ?: (getenv('DB_HOST') ?: 'localhost');
        $port = getenv('DB_SECONDARY_PORT') ?: (getenv('DB_PORT') ?: '3306');
        $user = getenv('DB_SECONDARY_USERNAME') ?: (getenv('DB_USERNAME') ?: 'root');
        $pass = getenv('DB_SECONDARY_PASSWORD');
        if ($pass === false) {
            $pass = getenv('DB_PASSWORD') ?: '';
        }
        $charset = getenv('DB_CHARSET') ?: 'utf8mb4';

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $host,
            $port,
            trim($name),
            $charset
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
            $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
            self::$pdoSecondary = $pdo;
            self::$secondaryAvailable = true;

            return self::$pdoSecondary;
        } catch (PDOException $e) {
            error_log('mistorneos DB auxiliar (personas): conexión no disponible — ' . $e->getMessage());
            self::$secondaryAvailable = false;

            return null;
        }
    }
}
