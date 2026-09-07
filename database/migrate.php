<?php

declare(strict_types=1);

/**
 * Applies pending .sql migrations in filename order and records each one, so
 * re-running only applies what is new.
 *
 * Run from the project root:   php database/migrate.php
 *
 * No shell access? Import database/full-schema.sql through phpMyAdmin instead.
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Support\Database;

$pdo = Database::connection();

// SKIP LOCKED is required by the task-slot claim in migration 003. Without it
// two panelists can be handed the same slot, so refuse to proceed quietly.
$version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
$isMaria = stripos($version, 'mariadb') !== false;
[$major, $minor] = array_map('intval', array_pad(explode('.', $version, 3), 2, '0'));
$supportsSkipLocked = $isMaria
    ? ($major > 10 || ($major === 10 && $minor >= 6))
    : ($major >= 8);

echo "Server: {$version}\n";
if (!$supportsSkipLocked) {
    echo "\nWARNING: this server does not support SELECT ... FOR UPDATE SKIP LOCKED\n"
       . "         (needs MySQL 8.0+ or MariaDB 10.6+). Migrations will still\n"
       . "         apply, but the Phase 1 task-slot claim cannot run safely here.\n\n";
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migrations (
        filename   VARCHAR(191) NOT NULL,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (filename)
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$done = $pdo->query('SELECT filename FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
$files = glob(__DIR__ . '/migrations/*.sql') ?: [];
sort($files);

$applied = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $done, true)) {
        echo "  skip  {$name} (already applied)\n";
        continue;
    }

    $sql = (string) file_get_contents($file);
    if (trim($sql) === '') {
        continue;
    }

    echo "  apply {$name} ... ";
    try {
        $pdo->exec($sql);
        $stmt = $pdo->prepare('INSERT INTO migrations (filename) VALUES (?)');
        $stmt->execute([$name]);
        echo "ok\n";
        $applied++;
    } catch (PDOException $e) {
        echo "FAILED\n    " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\n{$applied} migration(s) applied, " . count($files) . " total.\n";
