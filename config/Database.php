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
                $env = self::loadEnv(__DIR__ . '/.env');

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

    /**
     * Baca file .env dengan aman. parse_ini_file bisa GAGAL bila nilai
     * (mis. password DB) mengandung karakter khusus INI (# ; " { } | & dll),
     * mengembalikan false -> koneksi jatuh ke 'localhost' (error [2002]).
     * Karena itu ada fallback parser manual: split pada '=' pertama.
     */
    private static function loadEnv(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }
        $env = @parse_ini_file($file, false, INI_SCANNER_RAW);
        if (is_array($env) && $env) {
            return $env;
        }
        $out = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));
            $len = strlen($val);
            if ($len >= 2 && ($val[0] === '"' || $val[0] === "'") && $val[$len - 1] === $val[0]) {
                $val = substr($val, 1, -1);
            }
            $out[$key] = $val;
        }
        return $out;
    }
}
