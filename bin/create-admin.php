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

/**
 * Generates a readable one-time password. Ambiguous characters are left out so
 * it survives being read aloud or copied by hand.
 */
function temporaryPassword(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $groups = [];
    for ($g = 0; $g < 4; $g++) {
        $chunk = '';
        for ($i = 0; $i < 5; $i++) {
            $chunk .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $groups[] = $chunk;
    }
    return implode('-', $groups);
}

$password = getenv('PM_ADMIN_PASSWORD') ?: null;
$generated = false;

if ($password === null || $password === '') {
    $password = temporaryPassword();
    $generated = true;
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
                must_change_password = :force,
                email_verified_at = COALESCE(email_verified_at, NOW())
          WHERE id = :id",
        ['hash' => $hash, 'force' => $generated ? 1 : 0, 'id' => $existing['id']],
    );
    echo "Updated {$email} and granted admin.\n";
} else {
    Database::run(
        "INSERT INTO users (email, password_hash, first_name, last_name, is_admin, status,
                            must_change_password, email_verified_at)
         VALUES (:email, :hash, :first, :last, 1, 'active', :force, NOW())",
        ['email' => $email, 'hash' => $hash, 'first' => $first, 'last' => $last,
         'force' => $generated ? 1 : 0],
    );
    echo "Created admin {$email}.\n";
}

if ($generated) {
    echo "\n  Temporary password:  {$password}\n";
    echo "  Sign in at /superadmin/login — you will be asked to choose your own.\n";
    echo "  This is shown once and is not stored anywhere in plain text.\n\n";
}
