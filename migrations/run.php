<?php
/**
 * Migration Runner
 * Usage: php migrations/run.php
 */

require_once __DIR__ . '/../config/database.php';

$migrations = glob(__DIR__ . '/*.sql');
sort($migrations);

echo "MOVA Migration Runner\n";
echo str_repeat('=', 50) . "\n";

$db = Database::getConnection();

// Create migration tracking table
$db->exec("
    CREATE TABLE IF NOT EXISTS `mova_migrations` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `filename` VARCHAR(255) NOT NULL,
        `executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_filename` (`filename`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$executed = $db->query("SELECT filename FROM mova_migrations")->fetchAll(PDO::FETCH_COLUMN);

foreach ($migrations as $file) {
    $filename = basename($file);

    if (in_array($filename, $executed)) {
        echo "[SKIP] $filename (already executed)\n";
        continue;
    }

    if ($filename === basename(__FILE__)) {
        continue;
    }

    // Buang komentar baris (-- ...) DULU, baru split per statement.
    // (Bug lama: chunk yang diawali komentar ikut terbuang, SQL-nya tak jalan.)
    $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents($file));
    $statements = array_filter(array_map('trim', explode(';', $sql)), fn($s) => $s !== '');

    try {
        $db->beginTransaction();
        foreach ($statements as $statement) {
            $db->exec($statement);
        }
        // DDL (ALTER/CREATE) memicu implicit commit di MySQL → transaksi bisa
        // sudah tertutup. Commit hanya bila memang masih ada transaksi aktif.
        if ($db->inTransaction()) {
            $db->commit();
        }
        $db->prepare("INSERT INTO mova_migrations (filename) VALUES (?)")->execute([$filename]);
        echo "[OK]   $filename\n";
    } catch (\PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        echo "[FAIL] $filename: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo str_repeat('=', 50) . "\n";
echo "Migration selesai.\n";
