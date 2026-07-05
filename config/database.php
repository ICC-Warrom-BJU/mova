<?php

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $url = getenv('MYSQL_URL') ?: getenv('DATABASE_URL');
            if ($url) {
                $params = parse_url($url);
                $host = $params['host'] ?? 'localhost';
                $port = $params['port'] ?? '3306';
                $dbname = ltrim($params['path'] ?? '', '/');
                $user = $params['user'] ?? 'root';
                $pass = $params['pass'] ?? '';
            } else {
                $envFile = __DIR__ . '/.env';
                $env = file_exists($envFile) ? parse_ini_file($envFile) : [];

                $host = getenv('MYSQL_HOST') ?: ($env['DB_HOST'] ?? 'localhost');
                $port = getenv('MYSQL_PORT') ?: ($env['DB_PORT'] ?? '3306');
                $dbname = getenv('MYSQL_DATABASE') ?: ($env['DB_NAME'] ?? 'mova');
                $user = getenv('MYSQL_USER') ?: ($env['DB_USER'] ?? 'root');
                $pass = getenv('MYSQL_PASSWORD') ?: ($env['DB_PASS'] ?? '');
            }

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $host, $port, $dbname
            );

            self::$instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }

        return self::$instance;
    }
}
