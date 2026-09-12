<?php

declare(strict_types=1);

/**
 * Creates a customer account with an owner login. CLI-only, same as the admin
 * script — customers are onboarded by hand during early access.
 *
 *   php bin/create-member.php you@business.com "Business Name" "First" "Last"
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../app/bootstrap.php';

use App\Support\Database;

$email    = $argv[1] ?? null;
$business = $argv[2] ?? null;
$first    = $argv[3] ?? 'Owner';
$last     = $argv[4] ?? '';

if ($email === null || !filter_var($email, FILTER_VALIDATE_EMAIL) || $business === null) {
    fwrite(STDERR, "Usage: php bin/create-member.php you@business.com \"Business Name\" \"First\" \"Last\"\n");
    exit(1);
}
$email = mb_strtolower($email);

$password = getenv('PM_MEMBER_PASSWORD') ?: null;
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

$pdo = Database::connection();
$pdo->beginTransaction();
try {
    $user = Database::first('SELECT id FROM users WHERE email = :email', ['email' => $email]);
    if ($user === null) {
        Database::run(
            "INSERT INTO users (email, password_hash, first_name, last_name, is_admin, status, email_verified_at)
             VALUES (:email, :hash, :first, :last, 0, 'active', NOW())",
            ['email' => $email, 'hash' => password_hash($password, PASSWORD_DEFAULT),
             'first' => $first, 'last' => $last],
        );
        $userId = (int) $pdo->lastInsertId();
    } else {
        $userId = (int) $user['id'];
        Database::run('UPDATE users SET password_hash = :hash WHERE id = :id',
            ['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $userId]);
    }

    Database::run('INSERT INTO accounts (name) VALUES (:name)', ['name' => $business]);
    $accountId = (int) $pdo->lastInsertId();

    Database::run(
        "INSERT INTO account_users (account_id, user_id, role) VALUES (:a, :u, 'owner')",
        ['a' => $accountId, 'u' => $userId],
    );
    Database::run(
        'INSERT INTO locations (account_id, name) VALUES (:a, :name)',
        ['a' => $accountId, 'name' => $business],
    );

    $pdo->commit();
    echo "Created account #{$accountId} ({$business}) with owner {$email}.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Failed: ' . $e->getMessage() . "\n");
    exit(1);
}
