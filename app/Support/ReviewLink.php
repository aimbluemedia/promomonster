<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Checking a Google review link before we let a business save it.
 *
 * Two reasons this is strict rather than "does it look like a URL".
 *
 * The first is the business's own. A link that opens their listing instead of
 * the review box sends every customer they email to a page with no obvious way
 * to leave a review, and they will blame us rather than the link. Catching it
 * here costs one sentence on screen; catching it later costs a month of asks.
 *
 * The second is ours. /r/{token} answers with a Location header built from this
 * value, so whatever is stored here is somewhere we will send other people's
 * customers. Restricted to Google's own hosts, that is a redirect to Google.
 * Unrestricted, it is an open redirect on our domain, which is a thing phishing
 * campaigns look for and a thing that gets a domain flagged.
 */
final class ReviewLink
{
    /**
     * Hosts allowed to appear in a review link.
     *
     * Every one of these is Google. A business with a review link on some other
     * domain does not have a Google review link, whatever they were told.
     */
    private const HOSTS = [
        'g.page',
        'search.google.com',
        'maps.app.goo.gl',
        'goo.gl',
        'www.google.com',
        'google.com',
        'maps.google.com',
    ];

    /** Longer than any real one, short enough to stay a sane column. */
    private const MAX_LENGTH = 500;

    /**
     * @return array{ok:bool, url:?string, error:?string}
     */
    public static function check(string $raw): array
    {
        $url = trim($raw);

        if ($url === '') {
            return self::no('Paste your Google review link to save it.');
        }

        // Control characters first, before anything tries to interpret this as
        // a URL. A newline in a value that reaches a Location header is header
        // injection; PHP refuses it, but refusing it here is where it belongs.
        if (preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return self::no('That link has something odd in it. Copy it again from Google.');
        }

        if (mb_strlen($url) > self::MAX_LENGTH) {
            return self::no('That link is too long to be a Google review link.');
        }

        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return self::no('That does not look like a web address. It should start with https://');
        }

        if (strtolower($parts['scheme']) !== 'https') {
            return self::no('The link needs to start with https://');
        }

        $host = strtolower($parts['host']);
        if (!in_array($host, self::HOSTS, true)) {
            return self::no(
                'That is not a Google link. A review link comes from your Google Business '
                . 'Profile and lives on google.com or g.page.',
            );
        }

        // A listing URL is a Google URL and still the wrong one: it opens the
        // business's page, where "write a review" is a scroll and a click away.
        // This is the mistake almost everybody makes, so it gets its own answer
        // rather than a generic refusal.
        $path = strtolower($parts['path'] ?? '');
        if (str_contains($path, '/maps/place') || str_contains($path, '/maps/search')) {
            return self::no(
                'That link opens your listing rather than the review box. In your Google '
                . 'Business Profile choose "Ask for reviews" and copy the link it gives you.',
            );
        }

        return ['ok' => true, 'url' => $url, 'error' => null];
    }

    /**
     * A shortened form for showing a saved link back on screen.
     *
     * These are long and meaningless to read; the point of showing it is to
     * confirm something is saved, not to be read character by character.
     */
    public static function short(string $url, int $keep = 44): string
    {
        $url = preg_replace('~^https://~', '', trim($url)) ?? $url;

        return mb_strlen($url) <= $keep ? $url : mb_substr($url, 0, $keep - 1) . '…';
    }

    /** @return array{ok:false, url:null, error:string} */
    private static function no(string $error): array
    {
        return ['ok' => false, 'url' => null, 'error' => $error];
    }
}
