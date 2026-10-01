<?php

declare(strict_types=1);

/**
 * The review-request sender, end to end, against a real MySQL schema.
 *
 * Not mocked. Every migration runs, every query is the query that runs in
 * production, and the assertions are made by reading rows back. Half the
 * failure modes in this feature — a reminder queued twice, an opt-out that
 * does not cancel what is already in flight, a click that overwrites when it
 * first happened — only exist in the database, so a test that stubs the
 * database cannot see them.
 *
 * Point it at a scratch database. It TRUNCATES, so never at a real one:
 *
 *   PM_TEST_DB_HOST=127.0.0.1 PM_TEST_DB_PORT=3399 \
 *       PM_TEST_DB_NAME=promomonster_test php tests/sending-test.php
 *
 * Skips cleanly with exit 0 when no database is configured, so it can sit in
 * the same run as the pure unit suites.
 */

$host = getenv('PM_TEST_DB_HOST') ?: '';
$name = getenv('PM_TEST_DB_NAME') ?: '';

if ($name === '' || $host === '') {
    echo "SKIP  no test database configured (set PM_TEST_DB_HOST and PM_TEST_DB_NAME)\n";
    exit(0);
}

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
use App\Support\Database;
use App\Support\Plans;
use App\Support\ReviewRequests;
use App\Support\SendLimit;
use App\Support\Template;
use App\Support\Tokens;

/**
 * Deliberately NOT UTC, so this suite can see a clock mismatch.
 *
 * With PHP on the same timezone as the test database, any value PHP computes
 * and the database compares looks correct no matter which clock produced it.
 * A follow-up scheduled by PHP and made due by MySQL's NOW() is exactly that
 * shape, and on the live host the two are seven hours apart -- which made
 * reminders due seven hours early. Running skewed is what notices.
 */
date_default_timezone_set('America/Phoenix');

Config::load([
    'app_name' => 'PromoMonster',
    'app_url'  => 'https://promomonster.test',
    'app_key'  => 'integration-test-key-0123456789abcdef',
    'db' => [
        'host'     => $host,
        'port'     => (int) (getenv('PM_TEST_DB_PORT') ?: 3306),
        'database' => $name,
        'username' => getenv('PM_TEST_DB_USER') ?: 'root',
        'password' => getenv('PM_TEST_DB_PASS') ?: '',
        'charset'  => 'utf8mb4',
    ],
    // Nothing leaves the building.
    'mail' => ['driver' => 'null', 'from' => 'reviews@notify.promomonster.test'],
]);

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

// =====================================================================
// Fixtures
// =====================================================================

/** @return array{account:int, location:int, contact:int} */
function seed(string $plan = 'free'): array
{
    foreach (['message_events', 'review_requests', 'suppressions', 'consent_records',
              'contacts', 'locations', 'account_users', 'accounts'] as $table) {
        Database::run('DELETE FROM ' . $table);
    }

    Database::run(
        "INSERT INTO accounts (id, name, plan, status) VALUES (1, 'Acme Pools', :plan, 'active')",
        ['plan' => $plan],
    );
    Database::run(
        "INSERT INTO locations
            (id, account_id, name, google_review_url, reply_to_email,
             address_line1, city, region, postal_code, country_code, timezone)
         VALUES
            (1, 1, 'Acme Pools', 'https://g.page/r/acme/review', 'mike@acmepools.test',
             '12 Canal Street', 'Mesa', 'AZ', '85201', 'US', 'America/Phoenix')",
    );
    Database::run(
        "INSERT INTO contacts (id, location_id, first_name, last_name, email, source)
         VALUES (1, 1, 'Dana', 'Reyes', 'dana@example.test', 'manual')",
    );

    return ['account' => 1, 'location' => 1, 'contact' => 1];
}

/** @return array<string,mixed> */
function location(): array
{
    return Database::first('SELECT * FROM locations WHERE id = 1') ?? [];
}

/** @return array<string,mixed> */
function contact(): array
{
    return Database::first('SELECT * FROM contacts WHERE id = 1') ?? [];
}

/** @return array<string,mixed>|null */
function request(int $id): ?array
{
    return Database::first('SELECT * FROM review_requests WHERE id = :id', ['id' => $id]);
}

try {
    Database::connection();
} catch (Throwable $e) {
    echo 'SKIP  cannot reach the test database: ' . $e->getMessage() . "\n";
    exit(0);
}

// =====================================================================
// Queueing
// =====================================================================
seed();

$queued = ReviewRequests::queue(location(), contact(), null);
ok('queues a request', $queued['ok']);
$firstId = (int) $queued['id'];

$row = request($firstId);
check('starts queued', $row['status'], 'queued');
check('is not a follow-up', (int) $row['is_follow_up'], 0);
check('has no attempts yet', (int) $row['attempts'], 0);
ok('has a click token', preg_match('~^[0-9a-f-]{36}$~', (string) $row['click_token']) === 1);
ok('logged a queued event', Database::first(
    "SELECT id FROM message_events WHERE request_id = :id AND type = 'queued'",
    ['id' => $firstId],
) !== null);

// Tokens must be unique per request, or two sends share attribution.
ReviewRequests::queue(location(), contact(), null);
$tokens = Database::all('SELECT DISTINCT click_token FROM review_requests');
check('every request gets its own token', count($tokens), 2);

// --- Refusals ---------------------------------------------------------
seed();
$bad = ReviewRequests::queue(location(), ['id' => 1, 'email' => 'not-an-address'], null);
check('refuses a malformed address', $bad['ok'], false);

$noUrl = ReviewRequests::queue(['id' => 1, 'name' => 'X', 'google_review_url' => ''], contact(), null);
check('refuses a location with no review link', $noUrl['ok'], false);
ok('and says why', str_contains((string) $noUrl['error'], 'review link'));

$optedOut = ReviewRequests::queue(location(), ['id' => 1, 'email' => 'dana@example.test', 'email_opted_out' => 1], null);
check('refuses an opted-out contact', $optedOut['ok'], false);

ReviewRequests::suppress('blocked@example.test', 'manual');
$suppressed = ReviewRequests::queue(location(), ['id' => 1, 'email' => 'blocked@example.test'], null);
check('refuses a suppressed address', $suppressed['ok'], false);

// =====================================================================
// Composing
// =====================================================================
seed();
$id = (int) ReviewRequests::queue(location(), contact(), null)['id'];
$due = ReviewRequests::due(10);
check('due() finds it', count($due), 1);

$message = ReviewRequests::compose($due[0]);

check('merges the first name into the subject', $message['subject'], 'How did we do, Dana?');
ok('greets by first name', str_contains($message['text'], 'Hi Dana,'));
ok('names the business in the body', str_contains($message['text'], 'Acme Pools'));
// The footer line and the greeting come from different places, and an earlier
// version rendered the greeting correctly while leaving the footer blank —
// the row from due() is not a location, it only looks like one.
ok('says who the email is from', str_contains($message['text'], 'This email is from Acme Pools.'));
ok('signs off as the business', str_contains($message['text'], "Thanks,\nAcme Pools"));
ok('links through our redirect, not straight to Google', str_contains(
    $message['text'],
    'https://promomonster.test/r/' . $due[0]['click_token'],
));
ok('does not leak the raw Google URL into the body',
    !str_contains($message['text'], 'https://g.page/r/acme/review'));
ok('carries the postal address CAN-SPAM requires',
    str_contains($message['text'], '12 Canal Street, Mesa, AZ, 85201'));
ok('carries an unsubscribe link', str_contains($message['text'], 'https://promomonster.test/u/'));
ok('sets the unsubscribe header for the mail client', str_starts_with(
    (string) $message['unsubscribe_url'],
    'https://promomonster.test/u/',
));
ok('replies go to the business, not to us', $message['reply_to'] === 'mike@acmepools.test');
ok('no merge field is left unrendered', !str_contains($message['text'], '{{'));
ok('tagged as a request', $message['tag'] === 'review-request');

// --- Free carries the branding, paid does not -------------------------
ok('free says who sent it', str_contains($message['text'], 'Sent with PromoMonster'));
ok('free From carries the suffix', str_contains((string) $message['from_name'], '(via PromoMonster)'));

seed('pro');
$proId  = (int) ReviewRequests::queue(location(), contact(), null)['id'];
$proMsg = ReviewRequests::compose(ReviewRequests::due(10)[0]);
ok('paid drops the branding', !str_contains($proMsg['text'], 'Sent with PromoMonster'));
ok('paid From is just the business', !str_contains((string) $proMsg['from_name'], '(via'));
ok('paid still carries the unsubscribe link', str_contains($proMsg['text'], '/u/'));
ok('paid still carries the postal address', str_contains($proMsg['text'], '12 Canal Street'));

// =====================================================================
// Sending
// =====================================================================
seed();
$id  = (int) ReviewRequests::queue(location(), contact(), null)['id'];
$due = ReviewRequests::due(10);
$sent = ReviewRequests::send($due[0]);

ok('sends', $sent['ok']);
$row = request($id);
check('is marked sent', $row['status'], 'sent');
ok('records when', $row['sent_at'] !== null);
ok('counted the attempt', (int) $row['attempts'] === 1);
ok('stores what was actually sent', str_contains((string) $row['sent_body'], 'Hi Dana,'));
check('stores the subject too', $row['sent_subject'], 'How did we do, Dana?');
ok('stamped the contact', contact()['last_requested_at'] !== null);

// --- The one reminder -------------------------------------------------
// Sending no longer schedules anything. It used to queue a reminder three days
// out, and the only thing that could have sent it was a cron job created by
// hand in a control panel -- so the promise of a follow-up was conditional on a
// step most installs never took. The member now sends it from the list, once
// they can see the first one went unopened.
check('sending schedules nothing by itself', count(Database::all(
    'SELECT id FROM review_requests WHERE parent_request_id = :id', ['id' => $id])), 0);

// Nor does sending the same row twice, which is the bug that would turn "we
// never send a third message" into a lie.
ReviewRequests::send($due[0]);
check('and a re-send still schedules nothing', count(Database::all(
    'SELECT id FROM review_requests WHERE parent_request_id = :id', ['id' => $id])), 0);

// The member chases it. The first one went out above, so backdate it past the
// day the product makes them wait.
Database::run('UPDATE review_requests SET sent_at = NOW() - INTERVAL 2 DAY WHERE id = :id',
    ['id' => $id]);
$made = ReviewRequests::remind(1, $id);
ok('a reminder can be sent by hand', $made['ok']);

$followUps = Database::all(
    'SELECT * FROM review_requests WHERE parent_request_id = :id', ['id' => $id]);
check('which produces exactly one', count($followUps), 1);
check('marked as a follow-up', (int) $followUps[0]['is_follow_up'], 1);

// Due now, not three days out: there is nothing left to wait for it.
$dueNow = (int) Database::first(
    'SELECT scheduled_for <= NOW() AS d FROM review_requests WHERE id = :id',
    ['id' => (int) $followUps[0]['id']])['d'];
check('and due immediately', $dueNow, 1);

check('a second reminder is refused', ReviewRequests::remind(1, $id)['ok'], false);

// The reminder wording is the reminder's, not the request's.
$reminder = Database::first(
    'SELECT r.*, c.email AS contact_email, c.first_name, c.last_name, c.email_opted_out,
            l.name AS location_name, l.google_review_url, l.reply_to_email,
            l.address_line1, l.city, l.region, l.postal_code, a.plan,
            t.subject AS template_subject, t.body AS template_body
       FROM review_requests r
       JOIN contacts c ON c.id = r.contact_id
       JOIN locations l ON l.id = r.location_id
       JOIN accounts a ON a.id = l.account_id
       JOIN templates t ON t.id = r.template_id
      WHERE r.id = :id',
    ['id' => (int) $followUps[0]['id']],
);
$reminderMessage = ReviewRequests::compose($reminder);
check('the reminder uses the reminder subject', $reminderMessage['subject'], 'A quick reminder, Dana');
ok('and promises it is the only one',
    str_contains($reminderMessage['text'], 'only reminder'));
ok('and is tagged separately', $reminderMessage['tag'] === 'review-reminder');
ok('the reminder has its own click token',
    $reminder['click_token'] !== $due[0]['click_token']);

// =====================================================================
// Clicks
// =====================================================================
$token = (string) $due[0]['click_token'];
check('a click returns the review URL', ReviewRequests::click($token), 'https://g.page/r/acme/review');

$clicked = request($id);
ok('records the click', $clicked['first_clicked_at'] !== null);
check('moves the status on', $clicked['status'], 'clicked');

$firstClickAt = $clicked['first_clicked_at'];
sleep(1);
ReviewRequests::click($token);
check('a second click does not overwrite the first', request($id)['first_clicked_at'], $firstClickAt);
check('an unknown token goes nowhere', ReviewRequests::click('not-a-token'), null);

// =====================================================================
// Unsubscribing
// =====================================================================
seed();
$liveId = (int) ReviewRequests::queue(location(), contact(), null)['id'];
ReviewRequests::send(ReviewRequests::due(10)[0]);

// Something still waiting, for the opt-out to cancel. This used to be the
// reminder that sending scheduled automatically; nothing schedules now, so the
// waiting row is the ordinary case instead -- a request that did not get away
// and is sitting there for somebody to try again.
$pending = (int) ReviewRequests::queue(location(), contact(), null)['id'];
check('it is waiting to begin with', request($pending)['status'], 'queued');

$unsubToken = Tokens::unsubscribe(1);
check('the token resolves to the contact', Tokens::readUnsubscribe($unsubToken), 1);
ok('and finds them', ReviewRequests::findContactForUnsubscribe(1) !== null);

ReviewRequests::suppress('dana@example.test', 'unsubscribe');

ok('the address is suppressed', ReviewRequests::isSuppressed('dana@example.test'));
ok('case does not matter', ReviewRequests::isSuppressed('DANA@Example.Test'));
check('the contact is flagged', (int) contact()['email_opted_out'], 1);
check('anything still waiting is cancelled', request($pending)['status'], 'cancelled');
check('the already-sent one is left alone', request($liveId)['status'], 'sent');

ok('the address is not stored in the clear', Database::first(
    'SELECT id FROM suppressions WHERE address_hash = :h',
    ['h' => 'dana@example.test'],
) === null);
ok('it is stored as a hash', Database::first(
    'SELECT id FROM suppressions WHERE address_hash = :h',
    ['h' => Tokens::addressHash('dana@example.test')],
) !== null);

// Unsubscribing twice must not blow up on the unique key.
ReviewRequests::suppress('dana@example.test', 'unsubscribe');
check('suppressing twice keeps one row', count(Database::all(
    'SELECT id FROM suppressions WHERE channel = \'email\'',
)), 1);

// A request that survives to send() while the recipient has opted out in the
// meantime must be cancelled, not sent.
seed();
$raceId = (int) ReviewRequests::queue(location(), contact(), null)['id'];
$raceRow = ReviewRequests::due(10)[0];
ReviewRequests::suppress('dana@example.test', 'unsubscribe');
$raced = ReviewRequests::send($raceRow);
check('an opt-out between queue and send stops the send', $raced['ok'], false);
check('and cancels the row', request($raceId)['status'], 'cancelled');

// =====================================================================
// Send limits
// =====================================================================
seed();
$limit = SendLimit::check(1, Plans::FREE);
ok('a fresh account may send', $limit['allowed']);
check('nothing used yet', $limit['month_used'], 0);
check('the month allowance is the plan\'s', $limit['month_limit'], 4);
check('so is the burst', $limit['burst_limit'], 1);

$countedId = (int) ReviewRequests::queue(location(), contact(), null)['id'];
$after = SendLimit::check(1, Plans::FREE);
check('a queued request counts', $after['month_used'], 1);
check('and trips the one-a-week pace', $after['allowed'], false);
ok('with a sentence an owner can act on', str_contains((string) $after['reason'], 'week'));

// The reminder must not eat the allowance. It is the second half of one ask.
ReviewRequests::send(ReviewRequests::due(10)[0]);
check('the reminder does not count', SendLimit::check(1, Plans::FREE)['month_used'], 1);

// Nor may a failure. Charging for a bounce is how the old limiter locked
// someone out with nothing to delete.
Database::run("UPDATE review_requests SET status = 'failed' WHERE id = :id", ['id' => $countedId]);
check('a failed request does not count', SendLimit::check(1, Plans::FREE)['month_used'], 0);
ok('so the account may send again', SendLimit::check(1, Plans::FREE)['allowed']);

Database::run("UPDATE review_requests SET status = 'cancelled' WHERE id = :id", ['id' => $countedId]);
check('nor does a cancelled one', SendLimit::check(1, Plans::FREE)['month_used'], 0);

// Pro's allowance is bigger and its pace is per-day.
seed('pro');
check('pro month allowance', SendLimit::check(1, Plans::PRO)['month_limit'], 60);
check('pro burst', SendLimit::check(1, Plans::PRO)['burst_limit'], 2);
ReviewRequests::queue(location(), contact(), null);
ok('one send leaves pro under its pace', SendLimit::check(1, Plans::PRO)['allowed']);
ReviewRequests::queue(location(), contact(), null);
check('two trips it', SendLimit::check(1, Plans::PRO)['allowed'], false);
ok('and says so in days', str_contains((string) SendLimit::check(1, Plans::PRO)['reason'], 'day'));

// A request from last month must not count against this month.
seed();
ReviewRequests::queue(location(), contact(), null);
Database::run(
    "UPDATE review_requests SET created_at = DATE_SUB(:start, INTERVAL 1 DAY)",
    ['start' => SendLimit::monthStart()],
);
check('last month does not count against this one', SendLimit::check(1, Plans::FREE)['month_used'], 0);

// =====================================================================
// Events are idempotent, because webhooks retry
// =====================================================================
seed();
$eventId = (int) ReviewRequests::queue(location(), contact(), null)['id'];
ReviewRequests::event($eventId, 'delivered', 'provider-ref-1', []);
ReviewRequests::event($eventId, 'delivered', 'provider-ref-1', []);
check('the same receipt is recorded once', count(Database::all(
    "SELECT id FROM message_events WHERE request_id = :id AND type = 'delivered'",
    ['id' => $eventId],
)), 1);

ReviewRequests::event($eventId, 'delivered', 'provider-ref-2', []);
check('a different receipt is a different event', count(Database::all(
    "SELECT id FROM message_events WHERE request_id = :id AND type = 'delivered'",
    ['id' => $eventId],
)), 2);

// =====================================================================
// Retries stop
// =====================================================================
seed();
$stuckId = (int) ReviewRequests::queue(location(), contact(), null)['id'];
Config::load(array_merge(
    ['app_name' => 'PromoMonster', 'app_url' => 'https://promomonster.test',
     'app_key' => 'integration-test-key-0123456789abcdef'],
    ['db' => Config::get('db'), 'mail' => ['driver' => 'nonsense']],
));

for ($i = 0; $i < 4; $i++) {
    $rows = ReviewRequests::due(10);
    if ($rows === []) {
        break;
    }
    ReviewRequests::send($rows[0]);
}

$stuck = request($stuckId);
check('a permanently failing send is marked failed', $stuck['status'], 'failed');
ok('with the reason on the row', (string) $stuck['failure_reason'] !== '');
ok('and stops being picked up', ReviewRequests::due(10) === []);

// =====================================================================
// A reminder goes when the member sends it, not on a timer
// =====================================================================
// This used to assert a row scheduled three days out. Nothing drained that row
// but a cron job created by hand in a control panel, so in practice it was not
// a reminder, it was a row that said "queued" for ever. remind() replaces it:
// the member presses a button on a request that went out and was not opened.
seed();
$first = (int) ReviewRequests::queue(location(), contact(), null)['id'];

// Sent, but only just: too soon to chase somebody who has not had time to look.
Database::run("UPDATE review_requests SET status = 'sent', sent_at = NOW() WHERE id = {$first}");
$tooSoon = ReviewRequests::remind(1, $first);
ok('a reminder on the same day is refused', !$tooSoon['ok']);
ok('and says to give them a day', str_contains((string) $tooSoon['error'], 'day'));

// A day later it is allowed, and goes now rather than at some future date.
Database::run("UPDATE review_requests SET sent_at = NOW() - INTERVAL 2 DAY WHERE id = {$first}");
$made = ReviewRequests::remind(1, $first);
ok('a day later the reminder is allowed', $made['ok']);

$row = Database::first(
    'SELECT is_follow_up, parent_request_id, scheduled_for <= NOW() AS due_now
       FROM review_requests WHERE id = :id',
    ['id' => (int) $made['id']],
);
check('it is marked as the follow-up', (int) $row['is_follow_up'], 1);
check('and points at the request it chases', (int) $row['parent_request_id'], $first);
check('it is due immediately, not in three days', (int) $row['due_now'], 1);

// One reminder means one.
$again = ReviewRequests::remind(1, $first);
ok('a second reminder is refused', !$again['ok']);
ok('and says one has already gone', str_contains((string) $again['error'], 'already'));

// Somebody who clicked is not chased.
seed();
$clicked = (int) ReviewRequests::queue(location(), contact(), null)['id'];
Database::run("UPDATE review_requests
                  SET status = 'sent', sent_at = NOW() - INTERVAL 2 DAY, first_clicked_at = NOW()
                WHERE id = {$clicked}");
ok('somebody who opened the link is not chased', !ReviewRequests::remind(1, $clicked)['ok']);

// Nor is one that never went out.
seed();
$unsent = (int) ReviewRequests::queue(location(), contact(), null)['id'];
ok('a request that never sent cannot be reminded about', !ReviewRequests::remind(1, $unsent)['ok']);

// And not another account's customer.
seed();
$mine = (int) ReviewRequests::queue(location(), contact(), null)['id'];
Database::run("UPDATE review_requests SET status = 'sent', sent_at = NOW() - INTERVAL 2 DAY WHERE id = {$mine}");
ok("another account cannot remind this account's customer", !ReviewRequests::remind(99, $mine)['ok']);

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
