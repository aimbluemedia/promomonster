<?php

declare(strict_types=1);

/**
 * Applies every .sql file in database/migrations in filename order.
 *
 * Run from the project root:   php database/migrate.php
 * On shared hosting without shell access, paste the .sql files into
 * phpMyAdmin instead -- they are plain SQL with no placeholders.
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Support\Database;

$applied = 0;
$files = glob(__DIR__ . '/migrations/*.sql') ?: [];
sort($files);

foreach ($files as $file) {
    $sql = file_get_contents($file);
    if ($sql === false || trim($sql) === '') {
        continue;
    }

    echo 'Applying ' . basename($file) . ' ... ';
    try {
        Database::connection()->exec($sql);
        echo "ok\n";
        $applied++;
    } catch (PDOException $e) {
        echo "FAILED\n  " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\n{$applied} migration(s) applied.\n";
