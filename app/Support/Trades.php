<?php

declare(strict_types=1);

namespace App\Support;

/**
 * What kind of business this is.
 *
 * One list, because three things key off it and they have to agree: the public
 * lead form asks for it, the member can now change it, and the playbook is
 * looked up by it (`playbooks.vertical`). It was typed inline in the lead form
 * and nowhere else, so a member-facing picker written separately would have
 * drifted the first time a trade was added -- and a trade that exists in one
 * list and not the other is an account whose playbook silently never matches.
 *
 * The keys are what goes in the database and must never change: they are
 * already in `locations.vertical` and `audits.vertical` on live rows, and
 * renaming one orphans every account on it. The labels are free to be reworded.
 *
 * "Something else" is deliberately a real option rather than an empty value. A
 * business that does not fit wants to say so once, not be asked again on every
 * screen that notices the field is blank.
 */
final class Trades
{
    /** @var array<string,string> */
    public const ALL = [
        'landscaping' => 'Landscaping / lawn care',
        'hvac'        => 'HVAC / plumbing / electrical',
        'roofing'     => 'Roofing / exteriors',
        'dental'      => 'Dental / medical',
        'veterinary'  => 'Veterinary',
        'restaurant'  => 'Restaurant / café',
        'salon'       => 'Salon / barber / spa',
        'auto'        => 'Auto repair',
        'legal'       => 'Law firm',
        'realestate'  => 'Real estate',
        'other'       => 'Something else',
    ];

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::ALL);
    }

    /**
     * What to show for a stored value.
     *
     * Falls back to the raw value rather than to "unknown". Rows predating
     * this list, and anything typed by staff during onboarding, still read as
     * something rather than disappearing from the member's own dashboard.
     */
    public static function label(string $key): string
    {
        return self::ALL[$key] ?? $key;
    }

    /**
     * The value to store, or null if it is not one we know.
     *
     * Validation lives here so every caller gets the same answer. An unknown
     * trade is refused rather than written: `playbooks.vertical` is a lookup
     * key, and a free-typed value would match no playbook and look like a bug
     * in the playbook rather than a typo in a form.
     */
    public static function clean(string $raw): ?string
    {
        $raw = strtolower(trim($raw));

        return self::has($raw) ? $raw : null;
    }
}
