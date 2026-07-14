<?php

class TestDatabase
{
    private static ?PDO $instance = null;
    private static string $dbName = 'mova_test';

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $host = getenv('DB_HOST') ?: 'localhost';
            $port = getenv('DB_PORT') ?: '3306';
            $user = getenv('DB_USER') ?: 'root';
            $pass = getenv('DB_PASS') ?: '';
            $dbName = getenv('DB_NAME') ?: self::$dbName;

            $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);

            try {
                $conn = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);

                $conn->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $conn->exec("USE `$dbName`");

                self::$instance = $conn;
            } catch (PDOException $e) {
                throw new RuntimeException(
                    "Cannot connect to test database: " . $e->getMessage() . "\n" .
                    "Ensure MySQL is running and accessible via DB_HOST/DB_PORT/DB_USER/DB_PASS env vars."
                );
            }
        }

        return self::$instance;
    }

    public static function migrate(): void
    {
        $db = self::getConnection();

        $migrations = [
            __DIR__ . '/../migrations/test_schema.sql',
        ];

        foreach ($migrations as $file) {
            if (!file_exists($file)) {
                throw new RuntimeException("Migration file not found: $file");
            }
            $sql = file_get_contents($file);
            if (!empty(trim($sql))) {
                $statements = explode(';', $sql);
                foreach ($statements as $stmt) {
                    $stmt = trim($stmt);
                    if (!empty($stmt)) {
                        $db->exec($stmt);
                    }
                }
            }
        }
    }

    public static function seed(): void
    {
        $db = self::getConnection();
        $db->exec("SET FOREIGN_KEY_CHECKS = 0");
        $db->exec("TRUNCATE TABLE mova_notifications");
        $db->exec("TRUNCATE TABLE mova_maintenance_logs");
        $db->exec("TRUNCATE TABLE mova_maintenance_schedules");
        $db->exec("TRUNCATE TABLE mova_trip_photos");
        $db->exec("TRUNCATE TABLE mova_trip_checklists");
        $db->exec("TRUNCATE TABLE mova_fuel_reports");
        $db->exec("TRUNCATE TABLE mova_expense_reports");
        $db->exec("TRUNCATE TABLE mova_trips");
        $db->exec("TRUNCATE TABLE mova_vehicle_requests");
        $db->exec("TRUNCATE TABLE mova_issue_reports");
        $db->exec("TRUNCATE TABLE mova_user_branch_access");
        $db->exec("TRUNCATE TABLE mova_vehicles");
        $db->exec("TRUNCATE TABLE mova_customer_configs");
        $db->exec("TRUNCATE TABLE mova_users");
        $db->exec("TRUNCATE TABLE mova_customers");
        $db->exec("TRUNCATE TABLE mova_branches");
        $db->exec("TRUNCATE TABLE mova_regions");
        $db->exec("TRUNCATE TABLE mova_role_modules");
        $db->exec("TRUNCATE TABLE mova_config_options");
        $db->exec("TRUNCATE TABLE mova_login_attempts");
        $db->exec("TRUNCATE TABLE mova_audit_logs");
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");

        $seedFile = __DIR__ . '/../migrations/test_seed.sql';
        if (file_exists($seedFile)) {
            $sql = file_get_contents($seedFile);
            if (!empty(trim($sql))) {
                $statements = explode(';', $sql);
                foreach ($statements as $stmt) {
                    $stmt = trim($stmt);
                    if (!empty($stmt)) {
                        $db->exec($stmt);
                    }
                }
            }
        }
    }

    public static function reset(): void
    {
        $db = self::getConnection();
        $db->exec("SET FOREIGN_KEY_CHECKS = 0");
        $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $db->exec("TRUNCATE TABLE `$table`");
        }
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    }

    public static function dropDatabase(): void
    {
        if (self::$instance !== null) {
            $dbName = getenv('DB_NAME') ?: self::$dbName;
            self::$instance->exec("DROP DATABASE IF EXISTS `$dbName`");
            self::$instance = null;
        }
    }
}
