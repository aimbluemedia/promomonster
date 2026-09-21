<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Signed identifiers for links that arrive with no session behind them.
 *
 * An unsubscribe link has to work months later, from an archived email, on a
 * device that has never seen this site. There is no login to check, so the
 * link itself carries the authority — which means it has to be unguessable and
 * it has to be verifiable without a database lookup to find out whether it is
 * even well-formed.
 *
 * So: the id in the clear, plus an HMAC of it under a key only the server has.
 * Deriving it rather than storing a column means every email a contact has ever
 * received unsubscribes them, including ones sent before the column would have
 * existed.
 *
 * Note what is NOT signed here: the click token on a review request. That one
 * is a random UUID stored on its own row, because it identifies a single send
 * and we want it to stop meaning anything if the row goes away.
 */
final class Tokens
{
    /** Long enough that guessing is hopeless, short enough for a tidy URL. */
    private const SIGNATURE_BYTES = 16;

    private const UNSUBSCRIBE = 'unsubscribe';

    public static function unsubscribe(int $contactId): string
    {
        return self::sign(self::UNSUBSCRIBE, (string) $contactId);
    }

    /** The contact id, or null if the token is missing, malformed or forged. */
    public static function readUnsubscribe(string $token): ?int
    {
        $value = self::verify(self::UNSUBSCRIBE, $token);
        if ($value === null || !ctype_digit($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    /** True when a key is configured, so callers can report the cause. */
    public static function configured(): bool
    {
        return self::rawKey() !== '';
    }

    /**
     * How a person's address is recorded on the suppression list.
     *
     * Hashed, not stored in the clear: once someone has said "never again",
     * holding their address in a table forever is the opposite of honouring it.
     * The hash is enough to check a future send against, and useless for
     * anything else. Lowercased and trimmed first so the same address written
     * two ways lands on the same row.
     */
    public static function addressHash(string $address): string
    {
        return hash('sha256', mb_strtolower(trim($address)));
    }

    // -- Internals ---------------------------------------------------------

    private static function sign(string $purpose, string $value): string
    {
        $signature = substr(
            hash_hmac('sha256', $purpose . ':' . $value, self::key(), true),
            0,
            self::SIGNATURE_BYTES,
        );

        return $value . '-' . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    private static function verify(string $purpose, string $token): ?string
    {
        $cut = strrpos($token, '-');
        if ($cut === false || $cut === 0) {
            return null;
        }

        $value = substr($token, 0, $cut);

        // Constant-time, and on the whole token: comparing the signature with
        // === would leak how much of a guess was right, one character at a time.
        return hash_equals(self::sign($purpose, $value), $token) ? $value : null;
    }

    private static function key(): string
    {
        $key = self::rawKey();
        if ($key === '') {
            throw new RuntimeException(
                'No app_key is configured. Unsubscribe links cannot be signed without one — '
                . 'add a long random string as app_key in app/config.php.',
            );
        }

        return $key;
    }

    private static function rawKey(): string
    {
        return trim((string) Config::get('app_key', ''));
    }
}
