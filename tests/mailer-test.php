<?php

declare(strict_types=1);

/**
 * Mailer, Tokens and SendLimit — the parts that do not need a database.
 *
 * Header injection and token forgery are the two failure modes here that are
 * not merely bugs, so they get the most cases.
 */

require __DIR__ . '/../app/Support/Config.php';
require __DIR__ . '/../app/Support/Mailer.php';
require __DIR__ . '/../app/Support/Tokens.php';
require __DIR__ . '/../app/Support/Plans.php';

use App\Support\Config;
use App\Support\Mailer;
use App\Support\Tokens;

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

Config::load([
    'app_name' => 'PromoMonster',
    'app_key'  => 'test-key-not-the-live-one-0123456789abcdef',
    'mail'     => ['driver' => 'null', 'from' => 'reviews@notify.promomonster.com'],
]);

// =====================================================================
// Addresses
// =====================================================================
foreach ([
    'mike@acmepools.com',
    'first.last+tag@example.co.uk',
] as $good) {
    ok("accepts {$good}", Mailer::isSendableAddress($good));
}

foreach ([
    ''                                  => 'empty',
    'not-an-address'                    => 'no @',
    'mike@'                             => 'no domain',
    "mike@example.com\nBcc: b@evil.com" => 'a newline (header injection)',
    "mike@example.com\rBcc: b@evil.com" => 'a carriage return',
    "mike\x00@example.com"              => 'a null byte',
] as $bad => $why) {
    check("rejects {$why}", Mailer::isSendableAddress((string) $bad), false);
}

// 254 is the longest a mailbox may be. Built from legal parts — the local half
// caps at 64 and each domain label at 63, so a single long run of letters is
// rejected for being malformed rather than for being long, and would prove
// nothing about the length check.
$longest = str_repeat('a', 64) . '@'
    . str_repeat('b', 61) . '.' . str_repeat('c', 61) . '.' . str_repeat('d', 61) . '.com';
check('the fixture really is 254 characters', strlen($longest), 254);
ok('accepts a 254-character address', Mailer::isSendableAddress($longest));
check('rejects one character more',
    Mailer::isSendableAddress(str_repeat('a', 65) . substr($longest, 64)), false);

// =====================================================================
// Display names — the other half of header injection
// =====================================================================
check('quotes a plain name', Mailer::encodeName('Acme Pools'), '"Acme Pools"');
check('escapes a quote', Mailer::encodeName('Bob\'s "Best" Plumbing'), '"Bob\'s \\"Best\\" Plumbing"');
check('escapes a backslash', Mailer::encodeName('A\\B'), '"A\\\\B"');
check('flattens a newline', Mailer::encodeName("Acme\nBcc: b@evil.com"), '"Acme Bcc: b@evil.com"');
check('flattens a CRLF', Mailer::encodeName("Acme\r\nX-Evil: 1"), '"Acme X-Evil: 1"');
check('collapses runs of space', Mailer::encodeName("Acme    Pools  "), '"Acme Pools"');

// No encoded name may contain a bare CR or LF, whatever went in.
foreach (["a\nb", "a\rb", "a\r\nb", "a\tb"] as $i => $nasty) {
    ok("encoded name {$i} has no line break",
        preg_match('/[\r\n]/', Mailer::encodeName($nasty)) === 0);
}

// =====================================================================
// The From line
// =====================================================================
check('free plan carries the suffix',
    Mailer::fromHeader('Acme Pools', true),
    '"Acme Pools (via PromoMonster)" <reviews@notify.promomonster.com>');
check('paid plan does not',
    Mailer::fromHeader('Acme Pools', false),
    '"Acme Pools" <reviews@notify.promomonster.com>');
check('no business name falls back to ours',
    Mailer::fromHeader(null, true),
    '"PromoMonster" <reviews@notify.promomonster.com>');
check('an empty business name does too',
    Mailer::fromHeader('   ', true),
    '"PromoMonster" <reviews@notify.promomonster.com>');

// =====================================================================
// Driver selection
// =====================================================================
Config::load(['mail' => ['driver' => '', 'token' => '']]);
check('no token means the log driver', Mailer::driver(), 'log');
check('and that is not live', Mailer::isLive(), false);

Config::load(['mail' => ['driver' => '', 'token' => 'a-server-token']]);
check('a token means postmark', Mailer::driver(), 'postmark');
ok('and that is live', Mailer::isLive());

Config::load(['mail' => ['driver' => 'null', 'token' => 'a-server-token']]);
check('an explicit driver wins', Mailer::driver(), 'null');

// =====================================================================
// send() — the null driver, so nothing leaves and nothing is written
// =====================================================================
Config::load([
    'app_name' => 'PromoMonster',
    'mail'     => ['driver' => 'null', 'from' => 'reviews@notify.promomonster.com'],
]);

$sent = Mailer::send(['to' => 'mike@acmepools.com', 'subject' => 'Hi', 'text' => 'Body']);
ok('a good message is accepted', $sent['ok']);
check('and names its driver', $sent['driver'], 'null');

$bad = Mailer::send(['to' => "mike@acmepools.com\nBcc: b@evil.com", 'subject' => 'Hi', 'text' => 'Body']);
check('an injected recipient is refused', $bad['ok'], false);
ok('with a reason', is_string($bad['error']) && $bad['error'] !== '');

$long = Mailer::send(['to' => 'mike@acmepools.com', 'subject' => str_repeat('x', 999), 'text' => 'Body']);
check('an absurd subject is refused', $long['ok'], false);

foreach (['to', 'subject', 'text'] as $field) {
    $message = ['to' => 'mike@acmepools.com', 'subject' => 'Hi', 'text' => 'Body'];
    $message[$field] = '  ';
    $threw = false;
    try {
        Mailer::send($message);
    } catch (RuntimeException) {
        $threw = true;
    }
    ok("a blank '{$field}' is a programming error, not a send", $threw);
}

Config::load(['mail' => ['driver' => 'nonsense']]);
$unknown = Mailer::send(['to' => 'mike@acmepools.com', 'subject' => 'Hi', 'text' => 'Body']);
check('an unknown driver fails rather than throwing', $unknown['ok'], false);

// =====================================================================
// Tokens
// =====================================================================
Config::load(['app_key' => 'test-key-not-the-live-one-0123456789abcdef']);

ok('a key is configured', Tokens::configured());

$token = Tokens::unsubscribe(42);
check('round-trips', Tokens::readUnsubscribe($token), 42);
ok('is stable across calls', $token === Tokens::unsubscribe(42));
ok('differs per contact', $token !== Tokens::unsubscribe(43));
ok('is URL-safe', preg_match('~^[A-Za-z0-9_-]+$~', $token) === 1);

foreach ([
    ''                       => 'empty',
    '42'                     => 'unsigned',
    '42-'                    => 'empty signature',
    '-abc'                   => 'no value',
    '43-' . explode('-', $token, 2)[1] => 'another id with a stolen signature',
    $token . 'x'             => 'a tampered signature',
    'x' . $token             => 'a tampered prefix',
] as $forged => $why) {
    check("refuses {$why}", Tokens::readUnsubscribe((string) $forged), null);
}

// A different key must not validate the old token, or rotating the key would
// silently keep honouring links it can no longer sign.
Config::load(['app_key' => 'a-completely-different-key-aaaaaaaaaaaaaaaa']);
check('refuses a token signed with another key', Tokens::readUnsubscribe($token), null);

Config::load(['app_key' => '']);
check('reports a missing key', Tokens::configured(), false);
$threw = false;
try {
    Tokens::unsubscribe(1);
} catch (RuntimeException) {
    $threw = true;
}
ok('and refuses to sign without one', $threw);

// =====================================================================
// Address hashing for the suppression list
// =====================================================================
check('hashes case-insensitively',
    Tokens::addressHash('Mike@Acme.com'), Tokens::addressHash('mike@acme.com'));
check('hashes whitespace-insensitively',
    Tokens::addressHash('  mike@acme.com '), Tokens::addressHash('mike@acme.com'));
ok('is a sha256 hex digest',
    preg_match('~^[0-9a-f]{64}$~', Tokens::addressHash('mike@acme.com')) === 1);
ok('does not contain the address',
    !str_contains(Tokens::addressHash('mike@acme.com'), 'mike'));
ok('differs for different addresses',
    Tokens::addressHash('a@b.com') !== Tokens::addressHash('c@d.com'));
// The hash must not depend on app_key: suppressions have to survive a key
// rotation, because an opt-out is forever.
Config::load(['app_key' => 'yet-another-key']);
check('does not depend on the signing key',
    Tokens::addressHash('mike@acme.com'),
    hash('sha256', 'mike@acme.com'));

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
