<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
$pdo = Board\Infrastructure\Database::connect()->pdo;
$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (name text PRIMARY KEY, checksum char(64) NOT NULL, applied_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP)");
// Un seul migrateur à la fois, y compris si deux conteneurs démarrent ensemble.
$pdo->query('SELECT pg_advisory_lock(4782301)');
try {
    $files = glob(dirname(__DIR__) . '/database/migrations/*.sql'); sort($files);
    foreach ($files as $file) {
        $name = basename($file); $sum = hash_file('sha256', $file);
        $query = $pdo->prepare('SELECT checksum FROM schema_migrations WHERE name = :name');
        $query->execute(['name' => $name]); $previous = $query->fetchColumn();
        if ($previous !== false) {
            if (!hash_equals($previous, $sum)) { throw new RuntimeException("Migration déjà appliquée modifiée : $name. Créez une nouvelle migration."); }
            continue;
        }
        $pdo->beginTransaction();
        try {
            $pdo->exec(file_get_contents($file));
            $query = $pdo->prepare('INSERT INTO schema_migrations(name, checksum) VALUES (:name, :checksum)');
            $query->execute(['name' => $name, 'checksum' => $sum]);
            $pdo->commit(); echo "Appliquée : $name\n";
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    }
} finally { $pdo->query('SELECT pg_advisory_unlock(4782301)'); }
