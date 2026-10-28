<?php

declare(strict_types=1);

/**
 * Telling a person apart from a mail scanner.
 *
 *   PM_TEST_DB_HOST=127.0.0.1 PM_TEST_DB_PORT=3399 \
 *       PM_TEST_DB_NAME=promomonster_test php tests/click-test.php
 *
 * Corporate mail systems follow every link in every message before a human sees
 * it. Counting that as a click was not a cosmetic problem: it set
 * first_clicked_at, which is what the reminder button reads, so the business
 * lost the ability to chase the one customer who never saw the email.
 *
 * The two mistakes here are not equal, and the assertions are weighted to
 * match. Calling a person a scanner costs one unnecessary reminder offered.
 * Calling a scanner a person costs a customer. So every ordinary browser below
 * must pass, and anything carrying a positive sign of automation must not.
 */

$host = getenv('PM_TEST_DB_HOST') ?: '';
$name = getenv('PM_TEST_DB_NAME') ?: '';

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

use App\Support\ClickSource;
use App\Support\Config;
use App\Support\Database;
use App\Support\ReviewRequests;

date_default_timezone_set('America/Phoenix');
$_SERVER['REMOTE_ADDR'] = '198.51.100.9';

$passed = 0;
$failed = 0;
function check(string $what, mixed $got, mixed $want): void
{
    global $passed, $failed;
    if ($got === $want) { $passed++; return; }
    $failed++;
    echo "FAIL  {$what}\n      got:  " . var_export($got, true)
        . "\n      want: " . var_export($want, true) . "\n";
}
function ok(string $what, bool $got): void { check($what, $got, true); }

// =====================================================================
// Reading the request: no database needed
// =====================================================================

/** A plain top-level navigation, as a browser makes it. */
function browser(array $extra = []): array
{
    return array_merge([
        'REQUEST_METHOD'      => 'GET',
        'HTTP_USER_AGENT'     => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) '
                               . 'AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1',
        'HTTP_ACCEPT'         => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'HTTP_SEC_FETCH_MODE' => 'navigate',
        'HTTP_SEC_FETCH_DEST' => 'document',
    ], $extra);
}

// -- Real people, which must never be refused --------------------------
check('an iPhone following the link is a person', ClickSource::reason(browser()), null);

check('so is desktop Chrome', ClickSource::reason(browser([
    'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
                       . '(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
])), null);

check('so is Firefox', ClickSource::reason(browser([
    'HTTP_USER_AGENT' => 'Mozilla/5.0 (X11; Linux x86_64; rv:126.0) Gecko/20100101 Firefox/126.0',
])), null);

// An older browser sends no Sec-Fetch-* at all. Treating that as suspicion
// would quietly stop counting real people on older phones.
check('an older browser with no Sec-Fetch headers is still a person',
    ClickSource::reason(browser(['HTTP_SEC_FETCH_MODE' => '', 'HTTP_SEC_FETCH_DEST' => ''])), null);

check('and one that sends no Accept header is given the benefit of the doubt',
    ClickSource::reason(browser(['HTTP_ACCEPT' => ''])), null);

check('an in-app webview with a plain Accept passes',
    ClickSource::reason(browser(['HTTP_ACCEPT' => '*/*'])), null);

// -- Machines ----------------------------------------------------------
ok('a HEAD request is not a person',
    ClickSource::reason(browser(['REQUEST_METHOD' => 'HEAD'])) !== null);

ok('nor is a request with no user agent at all',
    ClickSource::reason(browser(['HTTP_USER_AGENT' => ''])) !== null);

foreach ([
    'curl/8.4.0',
    'Wget/1.21',
    'python-requests/2.31.0',
    'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) HeadlessChrome/124.0.0.0',
    'Barracuda Link Protect',
    'Mimecast-Link-Scanner/1.0',
] as $agent) {
    ok("an agent saying '{$agent}' is not a person",
        ClickSource::reason(browser(['HTTP_USER_AGENT' => $agent])) !== null);
}

// Sec-Fetch is set by the browser engine, so when it is present and says this
// was not a navigation, it is decisive.
ok('a prefetch is not a person',
    ClickSource::reason(browser(['HTTP_SEC_FETCH_MODE' => 'no-cors'])) !== null);
ok('nor is a request for an image',
    ClickSource::reason(browser(['HTTP_SEC_FETCH_DEST' => 'image'])) !== null);
ok('nor is one that asked only for JSON',
    ClickSource::reason(browser(['HTTP_ACCEPT' => 'application/json'])) !== null);

// The reason is for a human to read in the event log, so it has to say something.
$why = ClickSource::reason(browser(['REQUEST_METHOD' => 'HEAD']));
ok('and the reason explains itself', is_string($why) && str_contains($why, 'HEAD'));

// =====================================================================
// What that does to a request
// =====================================================================
if ($name === '' || $host === '') {
    echo "\nSKIP  no test database configured; the request-shape checks above ran\n";
    echo "\n{$passed} passed, {$failed} failed\n";
    exit($failed === 0 ? 0 : 1);
}

Config::load([
    'app_name' => 'PromoMonster',
    'app_url'  => 'https://promomonster.test',
    'app_key'  => 'integration-test-key-0123456789abcdef',
    'db' => [
        'host' => $host, 'port' => (int) (getenv('PM_TEST_DB_PORT') ?: 3306),
        'database' => $name, 'username' => getenv('PM_TEST_DB_USER') ?: 'root',
        'password' => getenv('PM_TEST_DB_PASS') ?: '', 'charset' => 'utf8mb4',
    ],
    'mail' => ['driver' => 'null'],
]);

try {
    Database::connection();
} catch (Throwable $e) {
    echo 'SKIP  cannot reach the test database: ' . $e->getMessage() . "\n";
    exit(0);
}

/** A request sent a while ago, so the settle window is not what is being tested. */
function sentRequest(int $minutesAgo = 30): array
{
    foreach (['message_events', 'review_requests', 'contacts', 'locations', 'account_users', 'accounts'] as $t) {
        Database::run("DELETE FROM {$t}");
    }
    Database::run("INSERT INTO accounts (id,name,plan,status) VALUES (1,'Acme Pools','free','active')");
    Database::run("INSERT INTO locations (id,account_id,name,google_review_url)
                   VALUES (1,1,'Acme Pools','https://g.page/r/ACME')");
    Database::run("INSERT INTO contacts (id,location_id,first_name,last_name,email,source)
                   VALUES (1,1,'Dana','Reyes','dana@example.test','manual')");

    $queued = ReviewRequests::queue(
        array_merge(Database::first('SELECT * FROM locations WHERE id = 1'),
            ['account_id' => 1, 'account_name' => 'Acme Pools']),
        Database::first('SELECT * FROM contacts WHERE id = 1'),
        null, null, 'google',
    );

    Database::run(
        "UPDATE review_requests SET status = 'sent', sent_at = NOW() - INTERVAL :m MINUTE WHERE id = :i",
        ['m' => $minutesAgo, 'i' => (int) $queued['id']],
    );

    return Database::first('SELECT * FROM review_requests WHERE id = :i', ['i' => (int) $queued['id']]);
}

function reload(int $id): array
{
    return Database::first('SELECT * FROM review_requests WHERE id = :i', ['i' => $id]) ?? [];
}

// -- A scanner fetch must not become an open ---------------------------
// Two days back, so the 24-hour wait before a reminder may be sent is already
// behind us and the only thing deciding the button is whether it was opened.
$row = sentRequest(2 * 24 * 60);
$url = ReviewRequests::click((string) $row['click_token'], 'the user agent says "scanner"');

check('the scanner still gets its redirect', $url, 'https://g.page/r/ACME');

$after = reload((int) $row['id']);
check('but nothing is recorded as opened', $after['first_clicked_at'], null);
check('and the status is untouched', (string) $after['status'], 'sent');

$listed = ReviewRequests::recent(1, null)[0];
ok('so the reminder is still on offer', ReviewRequests::canRemind($listed));
check('while the fetch itself is counted', (int) $listed['fetches'], 1);

$event = Database::first("SELECT detail FROM message_events WHERE type = 'clicked' ORDER BY id DESC LIMIT 1");
$detail = json_decode((string) $event['detail'], true);
check('the event says it was automated', $detail['automated'] ?? null, true);
ok('and records why', str_contains((string) ($detail['why'] ?? ''), 'scanner'));

// -- A person must become an open --------------------------------------
$row = sentRequest();
$url = ReviewRequests::click((string) $row['click_token'], null);

check('a person is redirected too', $url, 'https://g.page/r/ACME');
$after = reload((int) $row['id']);
ok('and it is recorded as opened', $after['first_clicked_at'] !== null);
check('with the status moved on', (string) $after['status'], 'clicked');
ok('so no reminder is offered', !ReviewRequests::canRemind(ReviewRequests::recent(1, null)[0]));

// -- A fetch seconds after sending is a machine, whatever it claims ----
$row = sentRequest(0);
ReviewRequests::click((string) $row['click_token'], null);
$after = reload((int) $row['id']);
check('a fetch in the same second as the send is not an open', $after['first_clicked_at'], null);

$detail = json_decode((string) Database::first(
    "SELECT detail FROM message_events WHERE type = 'clicked' ORDER BY id DESC LIMIT 1")['detail'], true);
ok('and the reason names the timing', str_contains((string) ($detail['why'] ?? ''), 'after sending'));

// The window is measured by the database, against a column the database wrote.
// A PHP-side comparison would be seven hours out on this host and would call
// every real click a scanner.
$row = sentRequest(30);
ReviewRequests::click((string) $row['click_token'], null);
ok('a click half an hour later is a person', reload((int) $row['id'])['first_clicked_at'] !== null);

// -- A scanner first, then the person --------------------------------
// The common real sequence, and the one that matters most: the scanner must not
// have spent the click the customer is about to make.
$row = sentRequest(2 * 24 * 60);
ReviewRequests::click((string) $row['click_token'], 'the user agent says "bot"');
check('after the scanner, still not opened', reload((int) $row['id'])['first_clicked_at'], null);

ReviewRequests::click((string) $row['click_token'], null);
ok('and the person who follows is counted', reload((int) $row['id'])['first_clicked_at'] !== null);
check('with both fetches recorded', (int) ReviewRequests::recent(1, null)[0]['fetches'], 2);

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
