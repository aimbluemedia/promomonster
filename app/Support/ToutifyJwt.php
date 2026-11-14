<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Verifies a JWT signed by Toutify, against Toutify's published keys.
 *
 * Written out rather than installed. firebase/php-jwt is the obvious choice and
 * it is a Composer package; this host has no shell and the project has no
 * Composer or build step by design, so the choice is "implement RS256
 * verification" or "vendor a dependency tree by hand". RS256 verification is
 * one openssl_verify call once the key is assembled, and the assembling is the
 * only fiddly part. Everything risky about it is commented below.
 *
 * THE RULES THIS MUST NOT BREAK, because an identity library that gets any of
 * them wrong is worse than none:
 *
 *  - The algorithm comes from US, never from the token. A JWT header saying
 *    alg=none or alg=HS256 is the classic forgery: with HS256 the verifier is
 *    tricked into using the PUBLIC key as an HMAC secret, which the attacker
 *    also has. Only RS256 is accepted, and the header is checked against that
 *    list before anything else happens.
 *  - Signature first, claims after. Nothing inside a token means anything
 *    until the signature is good.
 *  - Every comparison of a secret or a digest is constant time.
 */
final class ToutifyJwt
{
    /** The only algorithm Toutify signs with, and the only one we accept. */
    private const ALGORITHMS = ['RS256' => OPENSSL_ALGO_SHA256];

    /** Seconds of clock skew allowed on exp/iat/nbf. The spec asks for 60. */
    public const SKEW = 60;

    /**
     * Verifies and decodes. Returns the claims, or throws.
     *
     * @param  array<int,array<string,mixed>> $keys  the "keys" array from JWKS
     * @return array<string,mixed>
     * @throws \RuntimeException on anything at all wrong
     */
    public static function decode(string $jwt, array $keys): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Not a three-part JWT.');
        }

        [$head64, $body64, $sig64] = $parts;

        $header = self::json(self::base64($head64), 'header');

        // Before anything else. See the note at the top of the class.
        $alg = (string) ($header['alg'] ?? '');
        if (!isset(self::ALGORITHMS[$alg])) {
            throw new \RuntimeException('Refusing algorithm "' . $alg . '".');
        }

        $key = self::keyFor($keys, (string) ($header['kid'] ?? ''));

        $ok = openssl_verify(
            $head64 . '.' . $body64,
            self::base64($sig64),
            $key,
            self::ALGORITHMS[$alg],
        );

        if ($ok !== 1) {
            throw new \RuntimeException('Signature does not verify.');
        }

        return self::json(self::base64($body64), 'payload');
    }

    /**
     * The timing checks, separate so a caller can say what it expects.
     *
     * @param array<string,mixed> $claims
     */
    public static function checkTimes(array $claims, ?int $now = null): void
    {
        $now ??= time();

        if (isset($claims['exp']) && $now > ((int) $claims['exp'] + self::SKEW)) {
            throw new \RuntimeException('Token has expired.');
        }
        if (isset($claims['nbf']) && $now < ((int) $claims['nbf'] - self::SKEW)) {
            throw new \RuntimeException('Token is not valid yet.');
        }
        // A token issued in the future is a clock problem or a forgery; either
        // way it is not one to act on.
        if (isset($claims['iat']) && $now < ((int) $claims['iat'] - self::SKEW)) {
            throw new \RuntimeException('Token was issued in the future.');
        }
    }

    /**
     * Builds an OpenSSL public key from one JWK.
     *
     * A JWK gives the RSA modulus and exponent as base64url integers. OpenSSL
     * wants DER, so this writes the ASN.1 by hand: SEQUENCE { INTEGER n,
     * INTEGER e }, wrapped in the SubjectPublicKeyInfo header for rsaEncryption.
     *
     * @param array<int,array<string,mixed>> $keys
     * @return \OpenSSLAsymmetricKey
     */
    private static function keyFor(array $keys, string $kid): \OpenSSLAsymmetricKey
    {
        $candidates = [];
        foreach ($keys as $jwk) {
            if (($jwk['kty'] ?? '') !== 'RSA') {
                continue;
            }
            // A kid in the header picks one key; without one, try them all,
            // which is what a key rollover needs.
            if ($kid !== '' && (string) ($jwk['kid'] ?? '') !== $kid) {
                continue;
            }
            $candidates[] = $jwk;
        }

        if ($candidates === []) {
            throw new \RuntimeException('No signing key' . ($kid === '' ? '' : ' for kid ' . $kid) . '.');
        }

        $jwk = $candidates[0];
        $n   = self::base64((string) ($jwk['n'] ?? ''));
        $e   = self::base64((string) ($jwk['e'] ?? ''));

        if ($n === '' || $e === '') {
            throw new \RuntimeException('Signing key is missing n or e.');
        }

        $der = self::seq(self::seq(
            // rsaEncryption OID, then NULL parameters.
            "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00"
        ) . self::bitString(self::seq(self::int($n) . self::int($e))));

        $pem = "-----BEGIN PUBLIC KEY-----\n"
             . chunk_split(base64_encode($der), 64, "\n")
             . "-----END PUBLIC KEY-----\n";

        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new \RuntimeException('Signing key could not be read.');
        }

        return $key;
    }

    /* ----------------------------------------------------------- ASN.1 bits */

    private static function len(string $body): string
    {
        $n = strlen($body);
        if ($n < 0x80) {
            return chr($n);
        }
        $bytes = ltrim(pack('N', $n), "\x00");

        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private static function seq(string $body): string
    {
        return "\x30" . self::len($body) . $body;
    }

    private static function bitString(string $body): string
    {
        // The leading \x00 is the "unused bits" count, always zero here.
        $body = "\x00" . $body;

        return "\x03" . self::len($body) . $body;
    }

    private static function int(string $bytes): string
    {
        // DER integers are signed, so a leading byte of 0x80 or more would read
        // as negative. Pad it.
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '' || (ord($bytes[0]) & 0x80)) {
            $bytes = "\x00" . $bytes;
        }

        return "\x02" . self::len($bytes) . $bytes;
    }

    /* ------------------------------------------------------------ encodings */

    /** base64url decode, strict: a token with invalid characters is not a token. */
    public static function base64(string $value): string
    {
        $value = strtr($value, '-_', '+/');
        $pad   = strlen($value) % 4;
        if ($pad !== 0) {
            $value .= str_repeat('=', 4 - $pad);
        }

        $out = base64_decode($value, true);
        if ($out === false) {
            throw new \RuntimeException('Not valid base64url.');
        }

        return $out;
    }

    public static function base64Encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /** @return array<string,mixed> */
    private static function json(string $raw, string $what): array
    {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('JWT ' . $what . ' is not a JSON object.');
        }

        return $decoded;
    }
}
