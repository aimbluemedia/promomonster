<?php

declare(strict_types=1);

namespace App\Support;

use PDOException;

/**
 * Webhook de-duplication.
 *
 * Toutify retries an event for 24 hours until it gets a 2xx, so the same
 * delivery arrives many times and more than one can be in flight at once.
 * The INSERT is the check: a SELECT-then-INSERT leaves a window two retries
 * fit through, and the duplicate key is what closes it.
 */
final class ToutifyEvents
{
    /** Rows older than this are pruned; the spec asks for 30 days. */
    private const KEEP_DAYS = 30;

    /** True the first time this event id is seen, false afterwards. */
    public static function firstSighting(string $eventId, string $type): bool
    {
        if ($eventId === '') {
            // Nothing to deduplicate on. Handling it is the safer failure: the
            // handlers are all newest-wins, so doing it twice changes nothing.
            return true;
        }

        try {
            Database::run(
                'INSERT INTO toutify_events (event_id, type) VALUES (:id, :type)',
                ['id' => $eventId, 'type' => $type],
            );

            return true;
        } catch (PDOException $e) {
            if (($e->errorInfo[0] ?? '') === '23000') {
                return false;
            }

            throw $e;
        }
    }

    /**
     * Lets an event be delivered again.
     *
     * Called when handling THREW: the row was written before the work, so
     * without this the retry Toutify is about to send would be dropped as a
     * duplicate and the change would be lost for good.
     */
    public static function forget(string $eventId): void
    {
        if ($eventId === '') {
            return;
        }

        try {
            Database::run('DELETE FROM toutify_events WHERE event_id = :id', ['id' => $eventId]);
        } catch (\Throwable) {
            // Best effort. A failure here costs a lost retry, not a wrong write.
        }
    }

    /** Housekeeping, cheap enough to run on the way past. */
    public static function prune(): void
    {
        try {
            Database::run(
                'DELETE FROM toutify_events WHERE received_at < (NOW() - INTERVAL :days DAY)',
                ['days' => self::KEEP_DAYS],
            );
        } catch (\Throwable) {
            // Never let tidying up fail a webhook.
        }
    }

    /**
     * Checks a Toutify-Signature header against the raw body.
     *
     * Header is "t=<unix>,v1=<hex HMAC-SHA256 of '<t>.<raw body>'>". The
     * timestamp is inside the signed material, which is what stops a captured
     * body being replayed forever.
     *
     * Returns null when genuine, or a short reason. The reason goes to the
     * log, never to the response: telling a caller which part of its forgery
     * failed is free help.
     */
    public static function verify(string $raw, ?string $header, string $secret, ?int $now = null): ?string
    {
        if ($secret === '') {
            return 'no webhook secret is configured';
        }
        if ($header === null || trim($header) === '') {
            return 'no signature header';
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) {
                continue;
            }
            [$name, $value] = $pair;
            if ($name === 't') {
                $timestamp = ctype_digit($value) ? (int) $value : null;
            } elseif ($name === 'v1') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null) {
            return 'no timestamp in signature';
        }
        if ($signatures === []) {
            return 'no v1 signature';
        }

        $now ??= time();
        if (abs($now - $timestamp) > 300) {
            return 'timestamp is more than five minutes out';
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $raw, $secret);

        foreach ($signatures as $candidate) {
            // hash_equals, not ===. Comparing an HMAC in variable time is the
            // textbook way to let somebody forge one a byte at a time.
            if (hash_equals($expected, $candidate)) {
                return null;
            }
        }

        return 'signature does not match';
    }
}
