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

    /** @var array<string,mixed>|false|null  false means "looked, found none" */
    private static array|false|null $account = null;

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
            'SELECT id, email, first_name, last_name, is_admin, status, must_change_password
               FROM users WHERE id = :id LIMIT 1',
            ['id' => (int) $id],
        );

        // Suspending an account ends the session on the next request rather
        // than whenever the cookie happens to expire.
        if ($user === null || $user['status'] !== 'active') {
            self::logout();
            return null;
        }

        return self::$cached = $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isStaff(): bool
    {
        $user = self::user();
        return $user !== null && (int) $user['is_admin'] === 1;
    }

    public static function mustChangePassword(): bool
    {
        $user = self::user();
        return $user !== null && (int) $user['must_change_password'] === 1;
    }

    /**
     * A temporary password gets you exactly one place: the change form. Called
     * from the area guards, so every route is covered rather than each
     * controller having to remember.
     */
    private static function enforcePasswordChange(string $area): void
    {
        if (!self::mustChangePassword()) {
            return;
        }
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        if ($path !== "/{$area}/password") {
            Request::redirect("/{$area}/password");
        }
    }

    /** PromoMonster staff only. */
    public static function requireStaff(): void
    {
        if (!self::isStaff()) {
            Request::redirect('/superadmin/login');
        }
        self::enforcePasswordChange('superadmin');
    }

    /**
     * The account this user belongs to, or null. A customer is a user with a
     * row in account_users; staff have none, which is why the two guards are
     * separate rather than one role column.
     *
     * @return array<string,mixed>|null
     */
    public static function account(): ?array
    {
        $user = self::user();
        if ($user === null) {
            return null;
        }
        if (self::$account !== null) {
            return self::$account ?: null;
        }

        $row = Database::first(
            'SELECT a.*, au.role AS member_role
               FROM account_users au
               JOIN accounts a ON a.id = au.account_id
              WHERE au.user_id = :uid
           ORDER BY au.created_at LIMIT 1',
            ['uid' => (int) $user['id']],
        );

        self::$account = $row ?? false;
        return $row;
    }

    /** Customers only. */
    public static function requireMember(): void
    {
        if (self::account() === null) {
            Request::redirect('/members/login');
        }
        self::enforcePasswordChange('members');
    }

    /** True if this password is the one already on the account. */
    public static function attemptPasswordOnly(int $userId, string $password): bool
    {
        $row = Database::first('SELECT password_hash FROM users WHERE id = :id', ['id' => $userId]);
        return $row !== null && password_verify($password, (string) $row['password_hash']);
    }

    /** Replaces the password and clears the temporary flag. */
    public static function setPassword(int $userId, string $password): void
    {
        Database::run(
            'UPDATE users
                SET password_hash = :hash, must_change_password = 0, password_changed_at = NOW()
              WHERE id = :id',
            ['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $userId],
        );
        self::$cached = null;
        // A password change is a good moment to cut any other live session.
        session_regenerate_id(true);
        Audit::log('auth.password_changed', 'user', $userId);
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
            'SELECT id, password_hash, status FROM users WHERE email = :email LIMIT 1',
            ['email' => $email],
        );

        // Credentials only. Whether this user may reach a given area is decided
        // by requireStaff() / requireMember() after the session exists.
        $ok = $user !== null
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
        self::$account = null;

        Database::run('UPDATE users SET last_login_at = NOW() WHERE id = :id', ['id' => $user['id']]);
        Audit::log('auth.login', 'user', (int) $user['id']);

        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        self::$cached = null;
        self::$account = null;
        session_regenerate_id(true);
    }
}
