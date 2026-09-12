<?php

declare(strict_types=1);

namespace App\Support;

final class RateLimiter
{
    /**
     * Fixed-window limiter backed by MySQL, so it holds across requests and
     * across the multiple PHP processes shared hosting runs.
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
}
