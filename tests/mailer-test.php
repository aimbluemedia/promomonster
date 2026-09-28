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

// Naming the driver is not the same as being able to send. Without this, two
// screens stop warning that sending is off while every send still fails with
// "No Postmark server token configured" -- so a customer is told their reset
// link is on its way when nothing has left at all.
Config::load(['mail' => ['driver' => 'postmark', 'token' => '']]);
check('postmark named but no token is still postmark', Mailer::driver(), 'postmark');
check('and is NOT live', Mailer::isLive(), false);

Config::load(['mail' => ['driver' => 'postmark', 'token' => '   ']]);
check('whitespace is not a token either', Mailer::isLive(), false);

// =====================================================================
// Account email is kept apart from bulk
// =====================================================================
Config::load(['app_name' => 'PromoMonster', 'mail' => [
    'from'   => 'reviews@notify.promomonster.com',
    'stream' => 'broadcast',
]]);
check('with nothing set, account mail falls back to the bulk address',
    Mailer::transactionalFrom(), 'reviews@notify.promomonster.com');
check('but never to the bulk stream', Mailer::transactionalStream(), 'outbound');

Config::load(['app_name' => 'PromoMonster', 'mail' => [
    'from'                 => 'reviews@notify.promomonster.com',
    'stream'               => 'broadcast',
    'transactional_from'   => 'logins@promomonster.com',
    'transactional_stream' => 'transactional',
]]);
check('once set it is used', Mailer::transactionalFrom(), 'logins@promomonster.com');
check('with its own stream', Mailer::transactionalStream(), 'transactional');
check('and its own From header',
    Mailer::transactionalHeader(), '"PromoMonster" <logins@promomonster.com>');
check('while the bulk address is unchanged', Mailer::from(), 'reviews@notify.promomonster.com');

// =====================================================================
// Which driver account email works out as
// =====================================================================
// A filled-in mailbox is an unambiguous statement of intent, and needing to
// say so a second time in another key is a trap: fill in the whole smtp block,
// miss transactional_driver, and the lane silently falls back to the log
// driver -- which looks exactly like the mail settings not working. It did.
$mailbox = ['host' => 'smtp.example.com', 'username' => 'logins@example.com', 'password' => 's3cret'];

Config::load(['mail' => ['driver' => '', 'token' => '', 'smtp' => $mailbox]]);
check('a complete mailbox means smtp, with nothing else said', Mailer::transactionalDriver(), 'smtp');
ok('and account email is live', Mailer::transactionalIsLive());
ok('while the bulk lane is still only the log driver', !Mailer::isLive());
check('and bulk stays on log', Mailer::driver(), 'log');

foreach (['host', 'username', 'password'] as $missing) {
    Config::load(['mail' => ['driver' => '', 'token' => '',
        'smtp' => array_merge($mailbox, [$missing => ''])]]);
    check("a mailbox missing {$missing} does not count", Mailer::transactionalDriver(), 'log');
    ok("and account email is not live without {$missing}", !Mailer::transactionalIsLive());
}

// Whitespace is not a password.
Config::load(['mail' => ['driver' => '', 'token' => '',
    'smtp' => array_merge($mailbox, ['password' => '   '])]]);
ok('nor is a whitespace password', !Mailer::transactionalIsLive());

// An explicit setting always beats the inference, in both directions.
Config::load(['mail' => ['driver' => '', 'token' => '', 'transactional_driver' => 'log',
    'smtp' => $mailbox]]);
check('an explicit driver overrides a complete mailbox', Mailer::transactionalDriver(), 'log');
ok('so account email is off even with a mailbox set up', !Mailer::transactionalIsLive());

Config::load(['mail' => ['driver' => 'postmark', 'token' => 'a-token', 'transactional_driver' => 'smtp',
    'smtp' => $mailbox]]);
check('and it overrides the bulk driver too', Mailer::transactionalDriver(), 'smtp');

// The two lanes are genuinely independent: either can be on without the other.
Config::load(['mail' => ['driver' => 'postmark', 'token' => 'a-token']]);
ok('postmark alone: bulk is live', Mailer::isLive());
ok('and so is account email', Mailer::transactionalIsLive());

Config::load(['mail' => ['driver' => '', 'token' => '', 'smtp' => $mailbox]]);
ok('mailbox alone: account email is live', Mailer::transactionalIsLive());
ok('but bulk is not', !Mailer::isLive());

// What diagnose.php prints. Asked of the same function the page asks, so the
// two cannot drift apart and disagree about what the visitor is seeing.
Config::load(['mail' => ['driver' => '', 'token' => '', 'smtp' => $mailbox]]);
ok('the status names SMTP and the host', str_contains(Mailer::transactionalStatus(), 'SMTP via smtp.example.com'));
ok('and says it was worked out rather than set', str_contains(Mailer::transactionalStatus(), 'working out as'));

Config::load(['mail' => ['driver' => '', 'token' => '', 'transactional_driver' => 'smtp', 'smtp' => $mailbox]]);
ok('an explicit setting is described as set', str_contains(Mailer::transactionalStatus(), 'is set to'));

Config::load(['mail' => ['driver' => '', 'token' => '']]);
ok('with nothing configured it names the log driver', str_contains(Mailer::transactionalStatus(), 'log driver'));
ok('and says what to fill in', str_contains(Mailer::transactionalStatus(), 'mail.smtp'));

Config::load(['mail' => ['driver' => 'postmark', 'token' => '']]);
ok('postmark with no token says so', str_contains(Mailer::transactionalStatus(), 'mail.token is empty'));

// =====================================================================
// The bulk lane can use a mailbox too, but only when told to
// =====================================================================
// send() has dispatched 'smtp' since the mailbox driver was added, so review
// requests through a mailbox really do go out. isLive() said otherwise, which
// left the Google reviews page insisting sending was off at a server that was
// sending -- and the only visible conclusion was that the config had not taken.
Config::load(['mail' => ['driver' => 'smtp', 'token' => '', 'smtp' => $mailbox]]);
check('an explicit smtp driver is what driver() reports', Mailer::driver(), 'smtp');
ok('and the bulk lane is live', Mailer::isLive());
ok('so is account email, off the same mailbox', Mailer::transactionalIsLive());

foreach (['host', 'username', 'password'] as $missing) {
    Config::load(['mail' => ['driver' => 'smtp', 'token' => '',
        'smtp' => array_merge($mailbox, [$missing => ''])]]);
    ok("naming smtp without {$missing} is not live", !Mailer::isLive());
    ok("and the status says the block is incomplete for {$missing}",
        str_contains(Mailer::status(), 'incomplete'));
}

// The inference transactionalDriver() makes is deliberately NOT made here. A
// shared mailbox has an hourly cap; a batch of review requests walks into it,
// the rest of the run fails, and a suspension takes the password reset email
// down with it. Bulk through a mailbox has to be typed out.
Config::load(['mail' => ['driver' => '', 'token' => '', 'smtp' => $mailbox]]);
check('a complete mailbox alone leaves bulk on log', Mailer::driver(), 'log');
ok('and bulk is not live off it', !Mailer::isLive());
ok('while the status explains that it is a decision, not a default',
    str_contains(Mailer::status(), 'mail.driver is set to "smtp"'));

// =====================================================================
// What diagnose.php prints about the bulk lane
// =====================================================================
Config::load(['mail' => ['driver' => 'smtp', 'token' => '', 'smtp' => $mailbox,
    'from' => 'reviews@example.com']]);
ok('the bulk status names SMTP and the host', str_contains(Mailer::status(), 'SMTP via smtp.example.com'));
ok('and names the mailbox', str_contains(Mailer::status(), 'as logins@example.com'));
ok('and says it was set rather than worked out', str_contains(Mailer::status(), 'set to'));
ok('and warns about the hourly cap', str_contains(Mailer::status(), 'hourly cap'));

Config::load(['mail' => ['driver' => 'postmark', 'token' => 'a-token']]);
ok('postmark with a token reads as Postmark', str_contains(Mailer::status(), 'are set to Postmark.'));

Config::load(['mail' => ['driver' => '', 'token' => '']]);
ok('nothing configured names the log driver', str_contains(Mailer::status(), 'log driver'));
ok('and points at Postmark, which is what bulk is for', str_contains(Mailer::status(), 'mail.token'));

Config::load(['mail' => ['driver' => 'nonsense', 'token' => '']]);
ok('an unknown driver is not live', !Mailer::isLive());
ok('and is named in the status', str_contains(Mailer::status(), '"nonsense"'));

// -- The From header has to be aligned with the mailbox --------------------
// Smtp puts the authenticated mailbox in MAIL FROM, so a mismatched mail.from
// is never refused outright -- it just fails DMARC alignment and lands in spam,
// which is the kind of failure that looks like success from in here.
Config::load(['mail' => ['driver' => 'smtp', 'token' => '', 'smtp' => $mailbox,
    'from' => 'reviews@example.com']]);
ok('the same domain needs no warning', !str_contains(Mailer::status(), 'NOTE: mail.from'));

Config::load(['mail' => ['driver' => 'smtp', 'token' => '', 'smtp' => $mailbox,
    'from' => 'reviews@notify.example.com']]);
ok('nor does a subdomain, which DMARC relaxed still aligns',
    !str_contains(Mailer::status(), 'NOTE: mail.from'));

Config::load(['mail' => ['driver' => 'smtp', 'token' => '', 'smtp' => $mailbox,
    'from' => 'reviews@somewhere-else.test']]);
ok('an unrelated domain is warned about', str_contains(Mailer::status(), 'NOTE: mail.from'));
ok('and the mailbox domain is named as the fix', str_contains(Mailer::status(), 'Use an address on example.com'));

Config::load(['mail' => ['driver' => 'smtp', 'token' => '', 'smtp' => $mailbox,
    'from' => 'REVIEWS@EXAMPLE.COM']]);
ok('the comparison ignores case', !str_contains(Mailer::status(), 'NOTE: mail.from'));

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
