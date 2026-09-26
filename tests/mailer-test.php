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
require __DIR__ . '/../app/Support/ReviewLink.php';

use App\Support\Config;
use App\Support\Mailer;
use App\Support\ReviewLink;
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
ok('is URL-safe', preg_match('~^[0-9]+\.[A-Za-z0-9_-]+$~', $token) === 1);

// Round-trip EVERY id in a wide range, not a couple of convenient ones.
//
// This is the test that should have existed first. The separator used to be a
// hyphen, split on the last one — and base64url's alphabet contains hyphens,
// so 27% of tokens split in the wrong place and failed to verify. Checking
// ids 42 and 43 passed happily while a quarter of real unsubscribe links
// answered "that link has expired". One id proves nothing about an encoding.
$brokenIds = [];
for ($id = 1; $id <= 5000; $id++) {
    if (Tokens::readUnsubscribe(Tokens::unsubscribe($id)) !== $id) {
        $brokenIds[] = $id;
    }
}
check('every id in 1..5000 round-trips', $brokenIds, []);

// And no token may contain a character that needs escaping in a URL path.
$dirty = [];
for ($id = 1; $id <= 5000; $id++) {
    $t = Tokens::unsubscribe($id);
    if (rawurlencode($t) !== $t) {
        $dirty[] = $id;
    }
}
check('and survives a URL path unescaped', $dirty, []);

foreach ([
    ''                       => 'empty',
    '42'                     => 'unsigned',
    '42.'                    => 'empty signature',
    '.abc'                   => 'no value',
    '43.' . explode('.', $token, 2)[1] => 'another id with a stolen signature',
    $token . 'x'             => 'a tampered signature',
    'x' . $token             => 'a tampered prefix',
    'not-a-number.' . explode('.', $token, 2)[1] => 'a non-numeric id',
    '0.' . explode('.', $token, 2)[1] => 'contact zero',
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

// =====================================================================
// Review links
//
// This one is a security boundary as well as a usability one: whatever is
// stored here becomes a Location header on /r/{token}, pointed at somebody
// else's customers. Unrestricted it is an open redirect on our domain.
// =====================================================================
foreach ([
    'https://g.page/r/CdAbCdEfGh/review',
    'https://g.page/r/CdAbCdEfGh/review/',
    'https://search.google.com/local/writereview?placeid=ChIJabc123',
    'https://maps.app.goo.gl/abc123',
    'https://www.google.com/local/writereview?placeid=ChIJabc',
] as $good) {
    $r = ReviewLink::check($good);
    ok('accepts ' . $good, $r['ok'] === true);
    check('and keeps it as typed', $r['url'], $good);
}

// --- Not Google at all: the open-redirect case ------------------------
foreach ([
    'https://evil.example.com/phish'        => 'another domain',
    'https://g.page.evil.com/r/x/review'    => 'a lookalike domain',
    'https://notgoogle.com/maps'            => 'a domain that merely mentions google',
] as $bad => $why) {
    $r = ReviewLink::check($bad);
    check("refuses {$why}", $r['ok'], false);
    ok('and says it is not a Google link', str_contains((string) $r['error'], 'not a Google link'));
}

// --- Wrong scheme -----------------------------------------------------
foreach ([
    'http://g.page/r/x/review'       => 'plain http',
    'javascript:alert(1)'            => 'javascript:',
    'data:text/html,<script>'        => 'data:',
    '//g.page/r/x/review'            => 'a protocol-relative URL',
] as $bad => $why) {
    check("refuses {$why}", ReviewLink::check($bad)['ok'], false);
}

// --- A listing URL is Google's, and still the wrong link --------------
foreach ([
    'https://www.google.com/maps/place/Acme+Pools/@33.4,-111.8,17z',
    'https://maps.google.com/maps/place/Acme',
    'https://www.google.com/maps/search/acme+pools',
] as $listing) {
    $r = ReviewLink::check($listing);
    check('refuses a listing URL: ' . $listing, $r['ok'], false);
    ok('and explains which link to get instead',
        str_contains((string) $r['error'], 'Ask for reviews'));
}

// --- Header injection -------------------------------------------------
// A newline here would end the Location header and let what follows be read
// as headers of its own.
foreach ([
    "https://g.page/r/x/review\nLocation: https://evil.com" => 'a newline',
    "https://g.page/r/x/review\r\nSet-Cookie: a=b"          => 'a CRLF',
    "https://g.page/r/x/\x00review"                         => 'a null byte',
] as $bad => $why) {
    check("refuses {$why}", ReviewLink::check((string) $bad)['ok'], false);
}

// No accepted URL may carry a line break, whatever was submitted.
foreach (['https://g.page/r/x/review', 'https://search.google.com/local/writereview?placeid=a'] as $u) {
    $r = ReviewLink::check($u);
    ok('accepted link has no line break', preg_match('/[\r\n]/', (string) $r['url']) === 0);
}

// --- Empty, whitespace, absurd length ---------------------------------
check('refuses empty', ReviewLink::check('')['ok'], false);
check('refuses whitespace', ReviewLink::check("   \t ")['ok'], false);
check('trims before checking', ReviewLink::check('  https://g.page/r/x/review  ')['url'],
    'https://g.page/r/x/review');
check('refuses something absurdly long',
    ReviewLink::check('https://g.page/r/' . str_repeat('a', 600) . '/review')['ok'], false);
check('refuses a bare word', ReviewLink::check('review link')['ok'], false);

// --- The short form shown back on screen ------------------------------
check('drops the scheme', ReviewLink::short('https://g.page/r/abc/review'), 'g.page/r/abc/review');
ok('truncates a long one', mb_strlen(ReviewLink::short('https://g.page/r/' . str_repeat('x', 200))) <= 44);
ok('marks that it truncated', str_ends_with(ReviewLink::short('https://g.page/r/' . str_repeat('x', 200)), '…'));

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
