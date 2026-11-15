<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The OpenID Connect client for Toutify.
 *
 * Toutify holds every member's account and their password. PromoMonster never
 * sees a password, never stores one, and never asks for one: a member arrives
 * carrying a "sub", a permanent 26-character id, and that is the whole link.
 *
 * Hand-written because this project has no Composer and no build step -- the
 * host has no shell -- so jumbojett/openid-connect-php cannot be installed.
 * What it would have done is here: discovery, PKCE, the code exchange, ID
 * token verification (ToutifyJwt) and UserInfo.
 *
 * Settings come from the environment first and app/config.php second, so the
 * secret can live in either and never in the code. Nothing here is logged.
 */
final class ToutifyAuth
{
    /** Everything Toutify is asked for. "tier" at member level is ignored. */
    public const SCOPE = 'openid profile email phone toutify websites';

    /** How long a cached discovery document or key set is reused. */
    private const CACHE_SECONDS = 3600;

    /**
     * How long the state, nonce and PKCE verifier stay valid.
     *
     * Thirty minutes at least, because a sign-up can take a while: the member
     * leaves to read an email, chooses a password on Toutify, and only then
     * comes back. A five-minute window would fail most first sign-ups.
     */
    public const PENDING_SECONDS = 1800;

    /* ------------------------------------------------------------- settings */

    public static function issuer(): string
    {
        return rtrim(self::setting('TOUTIFY_ISSUER', 'toutify.issuer', 'https://toutify.com'), '/');
    }

    public static function clientId(): string
    {
        return self::setting('TOUTIFY_CLIENT_ID', 'toutify.client_id', '');
    }

    public static function clientSecret(): string
    {
        return self::setting('TOUTIFY_CLIENT_SECRET', 'toutify.client_secret', '');
    }

    public static function webhookSecret(): string
    {
        return self::setting('TOUTIFY_WEBHOOK_SECRET', 'toutify.webhook_secret', '');
    }

    /** True when sign-in can actually be attempted. */
    public static function configured(): bool
    {
        return self::clientId() !== '' && self::clientSecret() !== '';
    }

    private static function setting(string $env, string $key, string $fallback): string
    {
        $value = getenv($env);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return trim((string) Config::get($key, $fallback));
    }

    /** Where Toutify sends members back to. Must match Toutify exactly. */
    public static function redirectUri(): string
    {
        return rtrim((string) Config::get('app_url', 'https://promomonster.com'), '/')
            . '/auth/toutify/callback';
    }

    /* ------------------------------------------------------------ discovery */

    /**
     * The discovery document, cached on disk.
     *
     * @return array<string,mixed>
     */
    public static function discover(): array
    {
        return self::cached('discovery', self::issuer() . '/.well-known/openid-configuration');
    }

    /**
     * Where a member goes to see their Toutify account.
     *
     * Not from discovery -- this is a page on Toutify's site, not an OIDC
     * endpoint, so there is nothing to discover it from. The path is a setting
     * rather than a literal because the last version of this linked at the
     * issuer's bare origin precisely to avoid guessing a path, and the right
     * answer came from the person who runs Toutify rather than from us.
     *
     * Built onto the issuer's own origin, and the path is scrubbed of anything
     * that could leave it. A setting that could point this link at another
     * site is a setting that could be used to send a signed-in member
     * somewhere that looks like Toutify.
     */
    public static function membersUrl(): string
    {
        $raw = trim(self::setting('TOUTIFY_MEMBERS_PATH', 'toutify.members_path', '/members'));

        // Validated as given, BEFORE any tidying. The first version normalised
        // first and then checked, which let two things through: an empty
        // setting became the bare origin instead of falling back, and
        // "//evil.test/members" had its leading slashes collapsed into a
        // toutify.com path called evil.test. Neither left the issuer, but both
        // silently produced a link nobody asked for.
        //
        // One leading slash, then at least one segment of safe characters.
        // No scheme, no host, no query, no empty segment, no traversal.
        $ok = preg_match('~^/[A-Za-z0-9._-]+(?:/[A-Za-z0-9._-]+)*/?$~', $raw) === 1
            && !in_array('..', explode('/', $raw), true);

        return self::issuer() . '/' . trim($ok ? $raw : '/members', '/');
    }

    public static function endpoint(string $name): string
    {
        $found = (string) (self::discover()[$name] ?? '');
        if ($found === '') {
            throw new \RuntimeException('Toutify discovery has no ' . $name . '.');
        }

        // Discovery is fetched over TLS from the issuer, but an endpoint that
        // pointed somewhere else would send the code, or the secret, to that
        // somewhere else. Pin them to the issuer's own origin.
        if (!str_starts_with($found, self::issuer() . '/')) {
            throw new \RuntimeException('Toutify ' . $name . ' is not on the issuer.');
        }

        return $found;
    }

    /** @return array<int,array<string,mixed>> */
    public static function keys(): array
    {
        $jwks = self::cached('jwks', self::endpoint('jwks_uri'));
        $keys = $jwks['keys'] ?? null;

        return is_array($keys) ? $keys : [];
    }

    /**
     * Fetch-and-cache, with the cache deliberately allowed to be stale.
     *
     * If Toutify is briefly unreachable, a slightly old key set still verifies
     * tokens signed by the key it describes -- far better than refusing every
     * sign-in for the length of an outage. A key ROLLOVER during an outage is
     * the one case this gets wrong, and it self-corrects on the next fetch.
     *
     * @return array<string,mixed>
     */
    private static function cached(string $name, string $url): array
    {
        $file = BASE_PATH . '/storage/cache/toutify-' . $name . '.json';
        $fresh = is_file($file) && (time() - (int) filemtime($file)) < self::CACHE_SECONDS;

        if ($fresh) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data)) {
                return $data;
            }
        }

        try {
            $data = self::getJson($url);
            if (!is_dir(dirname($file))) {
                @mkdir(dirname($file), 0775, true);
            }
            @file_put_contents($file, json_encode($data), LOCK_EX);

            return $data;
        } catch (\Throwable $e) {
            if (is_file($file)) {
                $data = json_decode((string) file_get_contents($file), true);
                if (is_array($data)) {
                    return $data;
                }
            }
            throw $e;
        }
    }

    /* --------------------------------------------------------- signing in */

    /**
     * Everything that has to be remembered between the redirect out and the
     * return. Kept in the session, never in a cookie of its own.
     *
     * @param  array<string,string> $hints
     * @return array{url:string, state:string, nonce:string, verifier:string}
     */
    public static function begin(bool $signup = false, array $hints = []): array
    {
        $verifier = self::randomString(64);
        $state    = self::randomString(32);
        $nonce    = self::randomString(32);

        $params = [
            'response_type'         => 'code',
            'client_id'             => self::clientId(),
            'redirect_uri'          => self::redirectUri(),
            'scope'                 => self::SCOPE,
            'state'                 => $state,
            'nonce'                 => $nonce,
            // S256, never "plain". Toutify requires it and so do we.
            'code_challenge'        => ToutifyJwt::base64Encode(hash('sha256', $verifier, true)),
            'code_challenge_method' => 'S256',
        ];

        if ($signup) {
            $params['prompt'] = 'create';
        }

        // Toutify pre-fills its form from these. Only the four it documents,
        // so a crafted query string cannot push arbitrary parameters into the
        // authorize URL.
        foreach (['login_hint', 'name_hint', 'business_name_hint', 'phone_hint'] as $hint) {
            if (isset($hints[$hint]) && $hints[$hint] !== '') {
                $params[$hint] = $hints[$hint];
            }
        }

        return [
            'url'      => self::endpoint('authorization_endpoint') . '?' . http_build_query($params),
            'state'    => $state,
            'nonce'    => $nonce,
            'verifier' => $verifier,
        ];
    }

    /**
     * Swaps the code for tokens. Returns the raw token response.
     *
     * @return array<string,mixed>
     */
    public static function exchange(string $code, string $verifier): array
    {
        $body = http_build_query([
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => self::redirectUri(),
            'code_verifier' => $verifier,
        ]);

        // client_secret_basic. Both halves are form-urlencoded BEFORE base64,
        // per RFC 6749 2.3.1 -- without it a secret containing any of + / : or
        // a space authenticates on some servers and not others, which is a
        // bug that only shows up when the secret is rotated.
        $auth = base64_encode(rawurlencode(self::clientId()) . ':' . rawurlencode(self::clientSecret()));

        [$status, $json] = self::http('POST', self::endpoint('token_endpoint'), $body, [
            'Authorization: Basic ' . $auth,
            'Content-Type: application/x-www-form-urlencoded',
        ]);

        if ($status !== 200 || !isset($json['id_token'])) {
            throw new \RuntimeException('Toutify refused the code exchange (' . $status . ').');
        }

        return $json;
    }

    /**
     * Verifies an ID token and returns its claims.
     *
     * @return array<string,mixed>
     */
    public static function claims(string $idToken, string $nonce): array
    {
        $claims = ToutifyJwt::decode($idToken, self::keys());
        ToutifyJwt::checkTimes($claims);

        if ((string) ($claims['iss'] ?? '') !== self::issuer()) {
            throw new \RuntimeException('ID token is from the wrong issuer.');
        }

        // aud may be a string or a list.
        $aud = $claims['aud'] ?? '';
        $aud = is_array($aud) ? $aud : [$aud];
        if (!in_array(self::clientId(), array_map('strval', $aud), true)) {
            throw new \RuntimeException('ID token is for another client.');
        }

        // The nonce is what ties this token to the request WE started. Without
        // it a token obtained elsewhere could be replayed into our callback.
        if (!hash_equals($nonce, (string) ($claims['nonce'] ?? ''))) {
            throw new \RuntimeException('ID token nonce does not match.');
        }

        if (($claims['sub'] ?? '') === '') {
            throw new \RuntimeException('ID token has no subject.');
        }

        return $claims;
    }

    /**
     * UserInfo, which carries the profile and the websites list.
     *
     * @return array<string,mixed>
     */
    public static function userInfo(string $accessToken, string $expectedSub): array
    {
        [$status, $json] = self::http('GET', self::endpoint('userinfo_endpoint'), null, [
            'Authorization: Bearer ' . $accessToken,
        ]);

        if ($status !== 200) {
            throw new \RuntimeException('Toutify UserInfo refused the token (' . $status . ').');
        }

        // The subject must be the same person the ID token named, or a token
        // for someone else would sign that someone else in.
        if (!hash_equals($expectedSub, (string) ($json['sub'] ?? ''))) {
            throw new \RuntimeException('UserInfo is for a different member.');
        }

        return $json;
    }

    /** Where to send somebody to end their Toutify session too. */
    public static function endSessionUrl(?string $idToken): string
    {
        $home = rtrim((string) Config::get('app_url', 'https://promomonster.com'), '/') . '/';

        $params = ['post_logout_redirect_uri' => $home, 'state' => self::randomString(16)];
        if ($idToken !== null && $idToken !== '') {
            $params['id_token_hint'] = $idToken;
        } else {
            $params['client_id'] = self::clientId();
        }

        return self::endpoint('end_session_endpoint') . '?' . http_build_query($params);
    }

    /* -------------------------------------------------------------- plumbing */

    public static function randomString(int $bytes): string
    {
        return ToutifyJwt::base64Encode(random_bytes($bytes));
    }

    /** @return array<string,mixed> */
    private static function getJson(string $url): array
    {
        [$status, $json] = self::http('GET', $url);
        if ($status !== 200) {
            throw new \RuntimeException('Toutify returned ' . $status . ' for ' . $url . '.');
        }

        return $json;
    }

    /**
     * @param  array<int,string> $headers
     * @return array{0:int, 1:array<string,mixed>}
     */
    private static function http(string $method, string $url, ?string $body = null, array $headers = []): array
    {
        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            // Never negotiable. The token exchange carries the client secret
            // and the response carries the member's identity.
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            // A redirect from the token endpoint would be somebody moving the
            // secret somewhere else.
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json'], $headers),
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST]       = true;
            $options[CURLOPT_POSTFIELDS] = (string) $body;
        }

        curl_setopt_array($ch, $options);

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException('Could not reach Toutify: ' . $error);
        }

        $json = json_decode((string) $raw, true);

        return [$status, is_array($json) ? $json : []];
    }
}
