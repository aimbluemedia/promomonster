<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Fetches a visitor-supplied URL, defensively.
 *
 * A server that fetches whatever URL a stranger types is a server-side request
 * forgery hole: point it at http://169.254.169.254/ and it will happily read
 * the host's cloud metadata — credentials included — and hand the result back.
 * Shared hosting is less exposed than a cloud VM, but "less" is not a defence.
 *
 * So this does five things, and all five matter:
 *   1. http and https only, on ports 80 and 443. No file://, gopher://, no
 *      redirect to a shell out of a scheme we never intended to speak.
 *   2. No credentials in the URL, which otherwise leak into logs.
 *   3. Resolves the hostname itself and refuses private, loopback, link-local
 *      and reserved addresses — in both IPv4 and IPv6.
 *   4. Pins the connection to the address it just checked (CURLOPT_RESOLVE).
 *      Without this a hostname can answer once with a public address for the
 *      check and again with 127.0.0.1 for the fetch: DNS rebinding.
 *   5. Follows redirects by hand, re-running every check on each hop, because
 *      the safe URL you validated is allowed to redirect to an unsafe one.
 *
 * Plus the boring limits: a timeout, a response cap, and no cookies kept.
 */
final class SafeFetch
{
    private const MAX_BYTES = 1_500_000;
    private const TIMEOUT = 12;
    private const MAX_REDIRECTS = 3;
    private const USER_AGENT = 'PromoMonsterBot/1.0 (+https://promomonster.com/about-our-bot)';

    /**
     * @return array{url:string,status:int,html:string}
     * @throws RuntimeException with a message safe to show a visitor.
     */
    public static function get(string $url): array
    {
        $url = self::normalise($url);

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            [$host, $ip, $port, $scheme] = self::validate($url);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,   // We follow by hand, re-checking each hop.
                CURLOPT_HEADER         => true,
                CURLOPT_TIMEOUT        => self::TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_USERAGENT      => self::USER_AGENT,
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_ACCEPT_ENCODING => '',     // Let curl handle gzip.
                // Pin to the address we just vetted, closing the rebinding window.
                CURLOPT_RESOLVE        => ["{$host}:{$port}:{$ip}"],
                CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml'],
                // Stop reading once we have enough; a 4 GB "homepage" is not
                // going to be analysed anyway.
                CURLOPT_BUFFERSIZE     => 16384,
                CURLOPT_NOPROGRESS     => false,
                CURLOPT_PROGRESSFUNCTION => static fn ($res, $dlTotal, $dlNow) => $dlNow > self::MAX_BYTES ? 1 : 0,
            ]);

            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $errno = curl_errno($ch);
            curl_close($ch);

            if ($raw === false) {
                if ($errno === CURLE_ABORTED_BY_CALLBACK) {
                    throw new RuntimeException('That page is too large for us to read.');
                }
                throw new RuntimeException('We could not load that page. Check the address and try again.');
            }

            $headers = substr((string) $raw, 0, $headerSize);
            $body    = substr((string) $raw, $headerSize);

            if ($status >= 300 && $status < 400) {
                $location = self::headerValue($headers, 'location');
                if ($location === null) {
                    throw new RuntimeException('That page redirected somewhere we could not follow.');
                }
                $url = self::normalise(self::absolutise($location, $url));
                continue;
            }

            if ($status >= 400) {
                throw new RuntimeException("That address returned an error ({$status}).");
            }

            return ['url' => $url, 'status' => $status, 'html' => substr($body, 0, self::MAX_BYTES)];
        }

        throw new RuntimeException('That address redirected too many times.');
    }

    /** Adds a scheme if the visitor typed a bare domain, and trims stray text. */
    public static function normalise(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            throw new RuntimeException('Enter your website address.');
        }
        if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = 'https://' . $url;
        }

        return $url;
    }

    /**
     * @return array{0:string,1:string,2:int,3:string}  host, ip, port, scheme
     */
    private static function validate(string $url): array
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'])) {
            throw new RuntimeException('That does not look like a web address.');
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException('Only http and https addresses can be checked.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('Remove the username and password from the address.');
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (!in_array($port, [80, 443], true)) {
            throw new RuntimeException('Only the standard web ports can be checked.');
        }

        $host = $parts['host'];
        $ip = self::resolve($host);

        return [$host, $ip, $port, $scheme];
    }

    /** Resolves to one public address, or refuses. */
    private static function resolve(string $host): string
    {
        // A bare IP is allowed only if it is public.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            self::assertPublic($host);
            return $host;
        }

        if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/i', $host)) {
            throw new RuntimeException('That does not look like a web address.');
        }

        $addresses = [];
        $v4 = @gethostbynamel($host);
        if (is_array($v4)) {
            $addresses = $v4;
        }
        $v6 = @dns_get_record($host, DNS_AAAA);
        if (is_array($v6)) {
            foreach ($v6 as $record) {
                if (isset($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        if ($addresses === []) {
            throw new RuntimeException('We could not find that domain. Check the spelling.');
        }

        // EVERY address must be public. If a name resolves to both a public and
        // a private address, pinning to the public one is not enough of a
        // guarantee to be worth the risk.
        foreach ($addresses as $address) {
            self::assertPublic($address);
        }

        return $addresses[0];
    }

    private static function assertPublic(string $ip): void
    {
        $public = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );

        // NO_RES_RANGE misses a few that matter, so name them.
        $blocked = $ip === '0.0.0.0' || $ip === '::' || str_starts_with($ip, '0.');

        if ($public === false || $blocked) {
            throw new RuntimeException('That address points somewhere we will not fetch.');
        }
    }

    private static function headerValue(string $headers, string $name): ?string
    {
        // Redirect chains mean several header blocks; the last Location wins.
        $found = null;
        foreach (preg_split('/\r?\n/', $headers) ?: [] as $line) {
            if (stripos($line, $name . ':') === 0) {
                $found = trim(substr($line, strlen($name) + 1));
            }
        }

        return $found;
    }

    private static function absolutise(string $location, string $base): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $location)) {
            return $location;
        }

        $parts = parse_url($base);
        $root = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '')
              . (isset($parts['port']) ? ':' . $parts['port'] : '');

        return $root . '/' . ltrim($location, '/');
    }
}
