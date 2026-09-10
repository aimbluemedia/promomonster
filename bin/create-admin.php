<?php

declare(strict_types=1);

/**
 * Creates or promotes an admin user. Deliberately CLI-only — no web route can
 * mint an administrator.
 *
 *   php bin/create-admin.php you@example.com "First" "Last"
 *
 * Prompts for the password so it never reaches shell history. Set
 * PM_ADMIN_PASSWORD to script it.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../app/bootstrap.php';

use App\Support\Database;

$email = $argv[1] ?? null;
$first = $argv[2] ?? 'Admin';
$last  = $argv[3] ?? 'User';

if ($email === null || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php bin/create-admin.php you@example.com \"First\" \"Last\"\n");
    exit(1);
}
$email = mb_strtolower($email);

$password = getenv('PM_ADMIN_PASSWORD') ?: null;
if ($password === null || $password === '') {
    echo 'Password: ';
    @shell_exec('stty -echo 2>/dev/null');
    $password = trim((string) fgets(STDIN));
    @shell_exec('stty echo 2>/dev/null');
    echo "\n";
}

if (strlen($password) < 12) {
    fwrite(STDERR, "Password must be at least 12 characters.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$existing = Database::first('SELECT id FROM users WHERE email = :email', ['email' => $email]);

if ($existing !== null) {
    Database::run(
        "UPDATE users
            SET password_hash = :hash, is_admin = 1, status = 'active',
                email_verified_at = COALESCE(email_verified_at, NOW())
          WHERE id = :id",
        ['hash' => $hash, 'id' => $existing['id']],
    );
    echo "Updated {$email} and granted admin.\n";
} else {
    Database::run(
        "INSERT INTO users (email, password_hash, first_name, last_name, is_admin, status, email_verified_at)
         VALUES (:email, :hash, :first, :last, 1, 'active', NOW())",
        ['email' => $email, 'hash' => $hash, 'first' => $first, 'last' => $last],
    );
    echo "Created admin {$email}.\n";
}
