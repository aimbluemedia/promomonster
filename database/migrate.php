<?php

declare(strict_types=1);

/**
 * Applies pending .sql migrations and records each one, so re-running only
 * applies what is new.
 *
 * Run from the project root:   php database/migrate.php
 *
 * No shell access? Use migrate-web.php in the browser instead, or import
 * database/full-schema.sql through phpMyAdmin on a fresh database.
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Support\Database;
use App\Support\Migrator;

$pdo = Database::connection();

// SKIP LOCKED is used by the request queue. Without it two workers can be handed
// the same row, so say plainly whether this server has it.
$version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
$isMaria = stripos($version, 'mariadb') !== false;
[$major, $minor] = array_map('intval', array_pad(explode('.', $version, 3), 2, '0'));
$supportsSkipLocked = $isMaria
    ? ($major > 10 || ($major === 10 && $minor >= 6))
    : ($major >= 8);

echo "Server: {$version}\n\n";
if (!$supportsSkipLocked) {
    echo "WARNING: this server does not support SELECT ... FOR UPDATE SKIP LOCKED\n"
       . "         (needs MySQL 8.0+ or MariaDB 10.6+). Migrations will still\n"
       . "         apply, but concurrent queue claims cannot run safely here.\n\n";
}

try {
    $log = Migrator::run($pdo);
} catch (Throwable $e) {
    echo "STOPPED: {$e->getMessage()}\n";
    exit(1);
}

$failed = false;
foreach ($log as $entry) {
    printf("  %-9s %s\n", $entry['state'], $entry['file']);
    if ($entry['state'] === 'fail') {
        $failed = true;
        echo "\n" . $entry['detail'] . "\n";
    }
}

if ($failed) {
    exit(1);
}

$counts = array_count_values(array_column($log, 'state'));
printf(
    "\n%d applied, %d baselined (already present), %d already recorded.\n",
    $counts['applied'] ?? 0,
    $counts['baseline'] ?? 0,
    $counts['skip'] ?? 0,
);
