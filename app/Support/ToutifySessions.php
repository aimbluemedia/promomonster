<?php

declare(strict_types=1);

namespace App\Support;

use PDOException;

/**
 * Which PHP session belongs to which Toutify login.
 *
 * Back-channel logout is the reason this table exists. Toutify posts a token
 * saying "this login has ended" and PromoMonster has to end the matching
 * session HERE -- possibly for a member who is not making a request at the
 * time, from a different browser, on a different device. Without a record
 * tying a PHP session id to Toutify's sid and sub, there is nothing to end.
 *
 * Ending one means deleting its session file, because that is the only way to
 * reach into a session that is not the current request's. PHP's default
 * handler stores one file per session; anything else (Redis, a database
 * handler) would need its own branch here, and a comment saying so is more
 * use than silently failing.
 */
final class ToutifySessions
{
    /** Remembers this request's session as belonging to a Toutify login. */
    public static function record(string $sessionId, string $sid, string $sub): void
    {
        if ($sessionId === '' || $sub === '') {
            return;
        }

        Database::run(
            'INSERT INTO toutify_sessions (session_id, sid, sub) VALUES (:id, :sid, :sub)
             ON DUPLICATE KEY UPDATE sid = VALUES(sid), sub = VALUES(sub)',
            ['id' => $sessionId, 'sid' => $sid, 'sub' => $sub],
        );
    }

    public static function forget(string $sessionId): void
    {
        Database::run('DELETE FROM toutify_sessions WHERE session_id = :id', ['id' => $sessionId]);
    }

    /**
     * Ends the sessions for one Toutify login.
     *
     * With a sid, only that login. Without one, every session the member has
     * anywhere -- which is what a logout token with no sid means.
     *
     * @return int how many were ended
     */
    public static function endFor(string $sub, ?string $sid = null): int
    {
        if ($sub === '') {
            return 0;
        }

        $rows = $sid === null || $sid === ''
            ? Database::all('SELECT session_id FROM toutify_sessions WHERE sub = :sub', ['sub' => $sub])
            : Database::all(
                'SELECT session_id FROM toutify_sessions WHERE sub = :sub AND sid = :sid',
                ['sub' => $sub, 'sid' => $sid],
            );

        foreach ($rows as $row) {
            self::destroy((string) $row['session_id']);
        }

        if ($rows !== []) {
            $ids    = array_map(static fn (array $r): string => (string) $r['session_id'], $rows);
            $marks  = implode(',', array_fill(0, count($ids), '?'));
            $stmt   = Database::connection()->prepare(
                "DELETE FROM toutify_sessions WHERE session_id IN ({$marks})"
            );
            $stmt->execute($ids);
        }

        return count($rows);
    }

    /**
     * Deletes one session's storage.
     *
     * Only the default file handler is handled, and only inside the configured
     * save path: the id comes from our own table, but building a filesystem
     * path from a stored value without checking where it lands is how a path
     * traversal starts.
     */
    private static function destroy(string $sessionId): void
    {
        if (preg_match('/^[A-Za-z0-9,\-]{1,128}$/', $sessionId) !== 1) {
            return;
        }

        $path = session_save_path();
        // The setting can carry "N;/path" or "N;MODE;/path".
        if (str_contains($path, ';')) {
            $path = substr($path, strrpos($path, ';') + 1);
        }
        if ($path === '' || !is_dir($path)) {
            $path = sys_get_temp_dir();
        }

        $file = rtrim($path, '/') . '/sess_' . $sessionId;
        if (is_file($file)) {
            @unlink($file);
        }
    }
}
