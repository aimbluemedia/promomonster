<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Whether the thing that just fetched a tracked link was a person.
 *
 * Corporate mail scanners follow every link in every message before a human
 * sees it -- Microsoft's Safe Links, Mimecast, Barracuda, Proofpoint, and a
 * dozen others. The fetch is indistinguishable from a click unless somebody
 * looks, and the cost of not looking is specific: the request reads "opened the
 * link" when nobody opened it, and the reminder button disappears, so the one
 * customer who most needs chasing is the one the business can no longer chase.
 *
 * The unsubscribe flow has defended against this since it was written -- GET
 * shows a confirmation page rather than acting, because scanners would
 * otherwise unsubscribe half a list. The click tracker had no equivalent.
 *
 * Nothing here is certain and nothing here needs to be. A fetch is recorded
 * either way; this only decides whether it counts as a human opening the link.
 * Wrongly calling a person a scanner costs one unnecessary reminder offered.
 * Wrongly calling a scanner a person costs the business a customer. So the
 * tests are deliberately asymmetric: an ordinary browser must always pass, and
 * anything with a positive sign of automation on it does not.
 *
 * Kept out of ReviewRequests because it reads the request, and a data class
 * that reaches into $_SERVER cannot be tested without pretending to be a web
 * server. Here it is one function over an array.
 */
final class ClickSource
{
    /**
     * How soon after sending a fetch is certainly not a person.
     *
     * A scanner fetches within seconds of delivery, usually before the message
     * is in the inbox at all. A person has to receive it, notice it, open it,
     * read enough to decide, and find the link. Forty-five seconds is long
     * enough that no scanner is still thinking about it and short enough that
     * it cannot swallow a real reader -- and a real reader who was that fast
     * loses nothing but the offer of a reminder they do not need.
     */
    public const SETTLE_SECONDS = 45;

    /**
     * Agents that say what they are. Checked case-insensitively.
     *
     * Short on purpose. Scanners that want to look like browsers do, so this
     * catches only the honest ones and the signals below do the real work. A
     * long list of vendor strings would go stale and would read as protection
     * it is not providing.
     */
    private const AGENTS = [
        'bot', 'crawler', 'spider', 'slurp', 'preview', 'scanner', 'fetcher',
        'curl/', 'wget', 'python-requests', 'okhttp', 'java/', 'go-http-client',
        'headlesschrome', 'phantomjs', 'libwww', 'apache-httpclient',
        'proofpoint', 'barracuda', 'mimecast', 'symantec', 'forcepoint',
        'microsoft office', 'ms-office', 'safelinks', 'bitdefender',
    ];

    /**
     * Why this fetch looks automated, or null if it looks like a person.
     *
     * @param array<string,mixed>|null $server defaults to the live request
     */
    public static function reason(?array $server = null): ?string
    {
        $server = $server ?? $_SERVER;

        $method = strtoupper(trim((string) ($server['REQUEST_METHOD'] ?? 'GET')));
        $agent  = mb_strtolower(trim((string) ($server['HTTP_USER_AGENT'] ?? '')));
        $accept = mb_strtolower(trim((string) ($server['HTTP_ACCEPT'] ?? '')));

        // A browser following a link issues GET. HEAD is a machine asking
        // whether the URL resolves, which is exactly what a link checker does.
        if ($method !== 'GET') {
            return 'the request was ' . $method . ', not GET';
        }

        if ($agent === '') {
            return 'no user agent was sent';
        }

        foreach (self::AGENTS as $needle) {
            if (str_contains($agent, $needle)) {
                return 'the user agent says "' . $needle . '"';
            }
        }

        // Sec-Fetch-* is set by the browser, not by the page, so a client that
        // did not come from a browser engine cannot fake it by accident. When
        // it is present it is decisive: a person following a link from their
        // mail client is a top-level navigation to a document.
        //
        // Only read when present. Older browsers and some in-app webviews send
        // none of these, and treating absence as suspicion would quietly stop
        // counting real people on older phones.
        $mode = mb_strtolower(trim((string) ($server['HTTP_SEC_FETCH_MODE'] ?? '')));
        $dest = mb_strtolower(trim((string) ($server['HTTP_SEC_FETCH_DEST'] ?? '')));

        if ($mode !== '' && $mode !== 'navigate') {
            return 'Sec-Fetch-Mode was "' . $mode . '", not a navigation';
        }
        if ($dest !== '' && $dest !== 'document') {
            return 'Sec-Fetch-Dest was "' . $dest . '", not a document';
        }

        // Every browser asks for HTML when loading a page. A client that will
        // take anything, or that asked for nothing, is not rendering a page.
        if ($accept !== '' && !str_contains($accept, 'text/html') && !str_contains($accept, '*/*')) {
            return 'it did not ask for HTML';
        }

        return null;
    }
}
