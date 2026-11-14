<?php

declare(strict_types=1);

/**
 * The Toutify sign-in: token verification, webhook signatures, the OIDC request.
 *
 * Needs no database and no network. Everything asserted here is a decision
 * PromoMonster makes about a token or a signature somebody else sent, which is
 * exactly the part that must not be wrong -- an identity layer that accepts
 * one forged token is worse than no identity layer.
 */

define('APP_ROOT', dirname(__DIR__) . '/app');
define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = APP_ROOT . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Support\Config;
use App\Support\ToutifyAuth;
use App\Support\ToutifyEvents;
use App\Support\ToutifyJwt;

$passed = 0;
$failed = 0;

function check(string $what, mixed $got, mixed $want): void
{
    global $passed, $failed;
    if ($got === $want) {
        $passed++;
        return;
    }
    $failed++;
    echo "FAIL  {$what}\n      got:  " . var_export($got, true)
        . "\n      want: " . var_export($want, true) . "\n";
}

function ok(string $what, bool $got): void
{
    check($what, $got, true);
}

/** Did this throw? Used because "refused" is the pass condition for most of these. */
function refused(callable $fn): bool
{
    try {
        $fn();
        return false;
    } catch (Throwable) {
        return true;
    }
}

Config::load([
    'app_url' => 'https://promomonster.com',
    'toutify' => [
        'issuer'         => 'https://toutify.com',
        'client_id'      => 'promomonster',
        'client_secret'  => 'test-secret',
        'webhook_secret' => 'whsec-test',
    ],
]);

// --- A real key pair, so the signatures below are real ------------------
$pair    = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
$details = openssl_pkey_get_details($pair);
$jwk = [
    'kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => 'k1',
    'n' => ToutifyJwt::base64Encode($details['rsa']['n']),
    'e' => ToutifyJwt::base64Encode($details['rsa']['e']),
];

/** @param array<string,mixed> $head @param array<string,mixed> $body */
function sign(array $head, array $body, $key): string
{
    $h = ToutifyJwt::base64Encode((string) json_encode($head));
    $b = ToutifyJwt::base64Encode((string) json_encode($body));
    openssl_sign("{$h}.{$b}", $sig, $key, OPENSSL_ALGO_SHA256);

    return "{$h}.{$b}." . ToutifyJwt::base64Encode($sig);
}

$good = ['iss' => 'https://toutify.com', 'aud' => 'promomonster', 'sub' => str_repeat('A', 26),
         'exp' => time() + 300, 'iat' => time(), 'nonce' => 'the-nonce',
         'sid' => str_repeat('f', 32)];
$token = sign(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => 'k1'], $good, $pair);

// =====================================================================
// The two classic JWT forgeries
// =====================================================================
//
// alg=none asks the verifier to skip the signature. HS256-with-the-public-key
// asks it to use the PUBLIC key as an HMAC secret -- which the attacker has
// too, because it is public. Both are why the algorithm comes from US and
// never from the token.

ok('a genuine token is accepted', !refused(fn () => ToutifyJwt::decode($token, [$jwk])));
check('and its subject reads back', ToutifyJwt::decode($token, [$jwk])['sub'], str_repeat('A', 26));

// The allow-list itself, not just the outcome.
//
// Found by mutation: adding 'none' and 'HS256' to ALGORITHMS did not fail this
// suite. The tokens were still refused -- but by openssl_verify choking on a
// nonsense algorithm id, not by the allow-list doing its job. A future edit
// that added HS256 with a working HMAC behind it would have sailed through.
// So the list is pinned directly.
$algorithms = (new ReflectionClass(ToutifyJwt::class))->getConstant('ALGORITHMS');
check('exactly one algorithm is accepted', array_keys((array) $algorithms), ['RS256']);
check('and it is RSA-SHA256', $algorithms['RS256'], OPENSSL_ALGO_SHA256);

$noneHead = ToutifyJwt::base64Encode((string) json_encode(['alg' => 'none', 'typ' => 'JWT']));
$noneBody = ToutifyJwt::base64Encode((string) json_encode($good));
ok('alg=none is refused', refused(fn () => ToutifyJwt::decode("{$noneHead}.{$noneBody}.", [$jwk])));

$h = ToutifyJwt::base64Encode((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT', 'kid' => 'k1']));
$b = ToutifyJwt::base64Encode((string) json_encode($good));
$hs = "{$h}.{$b}." . ToutifyJwt::base64Encode(hash_hmac('sha256', "{$h}.{$b}", $details['key'], true));
ok('HS256 signed with the public key is refused', refused(fn () => ToutifyJwt::decode($hs, [$jwk])));

// --- Tampering and wrong keys -------------------------------------------
$parts = explode('.', $token);
$swapped = $good; $swapped['sub'] = str_repeat('B', 26);
ok('a swapped payload is refused', refused(fn () => ToutifyJwt::decode(
    $parts[0] . '.' . ToutifyJwt::base64Encode((string) json_encode($swapped)) . '.' . $parts[2], [$jwk])));

$other = openssl_pkey_get_details(openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]));
$otherJwk = ['kty' => 'RSA', 'kid' => 'k1',
             'n' => ToutifyJwt::base64Encode($other['rsa']['n']),
             'e' => ToutifyJwt::base64Encode($other['rsa']['e'])];
ok('another key is refused',   refused(fn () => ToutifyJwt::decode($token, [$otherJwk])));
ok('an unknown kid is refused', refused(fn () => ToutifyJwt::decode($token, [['kty' => 'RSA', 'kid' => 'zz', 'n' => $jwk['n'], 'e' => $jwk['e']]])));
ok('no keys at all is refused', refused(fn () => ToutifyJwt::decode($token, [])));
ok('rubbish is refused',        refused(fn () => ToutifyJwt::decode('not.a.token', [$jwk])));
ok('two parts is refused',      refused(fn () => ToutifyJwt::decode('a.b', [$jwk])));

// --- Times ---------------------------------------------------------------
ok('an expired token is refused', refused(function () use ($pair, $jwk) {
    $old = ['iss' => 'https://toutify.com', 'exp' => time() - 3600, 'iat' => time() - 7200];
    ToutifyJwt::checkTimes(ToutifyJwt::decode(sign(['alg' => 'RS256', 'kid' => 'k1'], $old, $pair), [$jwk]));
}));
ok('a token from the future is refused', refused(function () use ($pair, $jwk) {
    $soon = ['iss' => 'https://toutify.com', 'iat' => time() + 3600, 'exp' => time() + 7200];
    ToutifyJwt::checkTimes(ToutifyJwt::decode(sign(['alg' => 'RS256', 'kid' => 'k1'], $soon, $pair), [$jwk]));
}));
// 60 seconds of skew is allowed, because two servers never agree exactly.
ok('a token one second past expiry still passes, within skew', !refused(function () use ($pair, $jwk) {
    $edge = ['iss' => 'https://toutify.com', 'exp' => time() - 1, 'iat' => time() - 300];
    ToutifyJwt::checkTimes(ToutifyJwt::decode(sign(['alg' => 'RS256', 'kid' => 'k1'], $edge, $pair), [$jwk]));
}));
check('the skew is the 60 seconds the spec asks for', ToutifyJwt::SKEW, 60);

// =====================================================================
// Webhook signatures
// =====================================================================

$body = '{"id":"evt_1","type":"member.updated"}';
$now  = 1800000000;
$sig  = static fn (string $b, string $secret, int $t): string
    => 't=' . $t . ',v1=' . hash_hmac('sha256', $t . '.' . $b, $secret);

check('a genuine signature verifies',
    ToutifyEvents::verify($body, $sig($body, 'whsec-test', $now), 'whsec-test', $now), null);
check('the wrong secret is refused',
    ToutifyEvents::verify($body, $sig($body, 'other', $now), 'whsec-test', $now), 'signature does not match');
check('a tampered body under a real header is refused',
    ToutifyEvents::verify('{"id":"evt_1","type":"member.closed"}', $sig($body, 'whsec-test', $now), 'whsec-test', $now),
    'signature does not match');
check('a six-minute-old delivery is refused',
    ToutifyEvents::verify($body, $sig($body, 'whsec-test', $now - 360), 'whsec-test', $now),
    'timestamp is more than five minutes out');
ok('four minutes old is still accepted',
    ToutifyEvents::verify($body, $sig($body, 'whsec-test', $now - 240), 'whsec-test', $now) === null);
// An unset secret must close the door, not open it.
check('no configured secret refuses everything',
    ToutifyEvents::verify($body, $sig($body, '', $now), '', $now), 'no webhook secret is configured');
check('no header is refused',    ToutifyEvents::verify($body, null, 'whsec-test', $now), 'no signature header');
check('no timestamp is refused', ToutifyEvents::verify($body, 'v1=abc', 'whsec-test', $now), 'no timestamp in signature');
check('no v1 is refused',        ToutifyEvents::verify($body, 't=' . $now, 'whsec-test', $now), 'no v1 signature');

// =====================================================================
// The authorize request
// =====================================================================

$begin = ToutifyAuth::begin(true, ['login_hint' => 'dana@example.com', 'wat' => 'nope']);
parse_str((string) parse_url($begin['url'], PHP_URL_QUERY), $q);

check('PKCE is S256, never plain', $q['code_challenge_method'], 'S256');
ok('the challenge is the SHA-256 of the verifier',
    $q['code_challenge'] === ToutifyJwt::base64Encode(hash('sha256', $begin['verifier'], true)));
check('the scope is the agreed one', $q['scope'], ToutifyAuth::SCOPE);
check('sign-up asks for the create prompt', $q['prompt'], 'create');
check('the documented hint goes through', $q['login_hint'], 'dana@example.com');
// A visitor must not be able to push arbitrary parameters into the authorize
// URL by adding them to PromoMonster's query string.
ok('an undocumented parameter is dropped', !isset($q['wat']));
check('the redirect is ours', $q['redirect_uri'], 'https://promomonster.com/auth/toutify/callback');

ok('state and nonce differ',       $begin['state'] !== $begin['nonce']);
ok('state is long enough to matter', strlen($begin['state']) >= 32);
ok('the verifier is long enough',    strlen($begin['verifier']) >= 43);
// Two sign-ins must never share them, or one member's callback would satisfy
// another's pending request.
$second = ToutifyAuth::begin();
ok('every attempt gets a fresh state',    $second['state'] !== $begin['state']);
ok('every attempt gets a fresh verifier', $second['verifier'] !== $begin['verifier']);
parse_str((string) parse_url($second['url'], PHP_URL_QUERY), $q2);
ok('a plain sign-in asks for no prompt', !isset($q2['prompt']));

// --- The sign-in window ---------------------------------------------------
// Sign-up means leaving to read an email and choosing a password on Toutify.
// A five-minute window would fail most first attempts.
ok('the pending window is at least 30 minutes', ToutifyAuth::PENDING_SECONDS >= 1800);

// --- Configuration --------------------------------------------------------
ok('configured with an id and a secret', ToutifyAuth::configured());
Config::load(['app_url' => 'https://promomonster.com',
              'toutify' => ['client_id' => 'promomonster', 'client_secret' => '']]);
ok('no secret means not configured', !ToutifyAuth::configured());

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
