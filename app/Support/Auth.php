<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Admin authentication.
 *
 * Real auth, not the development placeholder this replaces: the site is live,
 * so the admin area is reachable from the internet and has to hold up.
 *
 *  - passwords hashed with password_hash(), verified in constant time
 *  - session id regenerated on login to close session fixation
 *  - failed attempts throttled per email AND per IP, recorded in the database
 *    so a lockout survives a process restart
 *  - the same generic message for unknown email and wrong password, so the
 *    form cannot be used to enumerate accounts
 */
final class Auth
{
    private const SESSION_KEY = 'admin_user_id';
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_SECONDS = 900;

    /** @var array<string,mixed>|null */
    private static ?array $cached = null;

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        if (self::$cached !== null) {
            return self::$cached;
        }
        $id = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_int($id) && !is_string($id)) {
            return null;
        }

        $user = Database::first(
            'SELECT id, email, first_name, last_name, is_admin, status
               FROM users WHERE id = :id LIMIT 1',
            ['id' => (int) $id],
        );

        // Revoking admin or suspending the account ends the session on the next
        // request rather than whenever the cookie happens to expire.
        if ($user === null || (int) $user['is_admin'] !== 1 || $user['status'] !== 'active') {
            self::logout();
            return null;
        }

        return self::$cached = $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** Redirects to the login form unless a live admin session exists. */
    public static function requireAdmin(): void
    {
        if (!self::check()) {
            Request::redirect('/admin/login');
        }
    }

    public static function lockedOut(string $email): bool
    {
        $since = date('Y-m-d H:i:s', time() - self::LOCKOUT_SECONDS);
        $row = Database::first(
            'SELECT COUNT(*) AS failures FROM login_attempts
              WHERE successful = 0 AND created_at > :since
                AND (email = :email OR ip = :ip)',
            ['since' => $since, 'email' => mb_strtolower($email), 'ip' => Request::ip()],
        );
        return (int) ($row['failures'] ?? 0) >= self::MAX_ATTEMPTS;
    }

    public static function attempt(string $email, string $password): bool
    {
        $email = mb_strtolower(trim($email));

        $user = Database::first(
            'SELECT id, password_hash, is_admin, status FROM users WHERE email = :email LIMIT 1',
            ['email' => $email],
        );

        $ok = $user !== null
            && (int) $user['is_admin'] === 1
            && $user['status'] === 'active'
            && password_verify($password, (string) $user['password_hash']);

        // Spend comparable time on a missing user so response timing does not
        // reveal whether the address exists.
        if ($user === null) {
            password_verify($password, '$2y$12$usesomesillystringforsalt0000000000000000000000000000000000');
        }

        Database::run(
            'INSERT INTO login_attempts (email, ip, successful) VALUES (:email, :ip, :ok)',
            ['email' => $email, 'ip' => Request::ip(), 'ok' => $ok ? 1 : 0],
        );

        if (!$ok) {
            return false;
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            Database::run(
                'UPDATE users SET password_hash = :hash WHERE id = :id',
                ['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']],
            );
        }

        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = (int) $user['id'];
        self::$cached = null;

        Database::run('UPDATE users SET last_login_at = NOW() WHERE id = :id', ['id' => $user['id']]);
        Audit::log('admin.login', 'user', (int) $user['id']);

        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        self::$cached = null;
        session_regenerate_id(true);
    }
}
