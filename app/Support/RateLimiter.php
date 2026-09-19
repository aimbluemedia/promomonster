<?php

declare(strict_types=1);

namespace App\Support;

final class RateLimiter
{
    /**
     * Fixed-window limiter backed by MySQL, so it holds across requests and
     * across the multiple PHP processes shared hosting runs.
     *
     * Checks AND records in one call. Right for a cheap form post, where the
     * attempt itself is the thing being limited. Wrong where the attempt can
     * fail before it costs anything — see atLimit()/record() below.
     */
    public static function tooManyAttempts(string $key, int $limit, int $windowSeconds): bool
    {
        $hash = hash('sha256', $key);
        $now = time();

        Database::run(
            'DELETE FROM rate_limits WHERE window_started_at < :cutoff',
            ['cutoff' => date('Y-m-d H:i:s', $now - $windowSeconds)],
        );

        $row = Database::first(
            'SELECT attempts FROM rate_limits WHERE bucket_key = :key',
            ['key' => $hash],
        );

        if ($row === null) {
            Database::run(
                'INSERT INTO rate_limits (bucket_key, attempts, window_started_at)
                 VALUES (:key, 1, :now)
                 ON DUPLICATE KEY UPDATE attempts = attempts + 1',
                ['key' => $hash, 'now' => date('Y-m-d H:i:s', $now)],
            );
            return false;
        }

        if ((int) $row['attempts'] >= $limit) {
            return true;
        }

        Database::run(
            'UPDATE rate_limits SET attempts = attempts + 1 WHERE bucket_key = :key',
            ['key' => $hash],
        );
        return false;
    }

    /**
     * Read-only: is this key already at its limit?
     *
     * Split from recording because counting an attempt that then fails charges
     * someone for work never done. A rejected URL, an unreachable site, a crash
     * — none of those cost an API call, but each one used to consume a day's
     * allowance, and the owner could be locked out with nothing to show for it
     * and nothing to delete. Check with this, and call record() only once the
     * expensive thing has actually happened.
     */
    public static function atLimit(string $key, int $limit, int $windowSeconds): bool
    {
        self::expire($windowSeconds);

        $row = Database::first(
            'SELECT attempts FROM rate_limits WHERE bucket_key = :key',
            ['key' => hash('sha256', $key)],
        );

        return $row !== null && (int) $row['attempts'] >= $limit;
    }

    /** Records one use of a key. Call after the work succeeded. */
    public static function record(string $key): void
    {
        Database::run(
            'INSERT INTO rate_limits (bucket_key, attempts, window_started_at)
             VALUES (:key, 1, :now)
             ON DUPLICATE KEY UPDATE attempts = attempts + 1',
            ['key' => hash('sha256', $key), 'now' => date('Y-m-d H:i:s')],
        );
    }

    /** Drops buckets whose window has passed. */
    private static function expire(int $windowSeconds): void
    {
        Database::run(
            'DELETE FROM rate_limits WHERE window_started_at < :cutoff',
            ['cutoff' => date('Y-m-d H:i:s', time() - $windowSeconds)],
        );
    }

    /**
     * Forgets one bucket.
     *
     * Needed because deleting the record of something is not the same as
     * undoing it: remove a score row to retest and the limiter still remembers
     * the attempt, so the retest is refused and the delete button looks broken.
     * The caller passes the same key it limited on.
     */
    public static function forget(string $key): void
    {
        Database::run(
            'DELETE FROM rate_limits WHERE bucket_key = :key',
            ['key' => hash('sha256', $key)],
        );
    }

    /**
     * Clears every bucket. The keys are hashed, so a bucket cannot be found by
     * the address that filled it — which means that when someone is locked out
     * and there is no row to trace them by, clearing the lot is the only way
     * back. Operator-only.
     */
    public static function forgetAll(): int
    {
        $statement = Database::run('DELETE FROM rate_limits');

        return $statement->rowCount();
    }
}
