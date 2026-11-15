<?php

declare(strict_types=1);

/**
 * Review alerts and the monthly report, against a real schema.
 *
 *   PM_TEST_DB_HOST=127.0.0.1 PM_TEST_DB_NAME=promomonster_test \
 *       php tests/premium-tools-test.php
 *
 * Both jobs are periodic and both send mail, which is the combination that
 * goes wrong quietly. The things pinned here:
 *
 *   A review is announced once. alerted_at is set only after a send actually
 *   succeeds, so a provider having a bad minute means trying again in five
 *   rather than a review nobody is ever told about -- and a crash mid-batch
 *   resumes where it stopped rather than telling everybody twice.
 *
 *   A report that fails still moves its own clock on. Without that, one
 *   account whose audit throws is picked up again on the very next run,
 *   forever, spending an API call each time and starving every account
 *   behind it.
 *
 *   Due-ness is decided by the database against its own column. PHP runs on
 *   America/Phoenix here and MySQL on UTC -- seven hours apart -- so this
 *   suite sets that timezone deliberately, the way the other database suites
 *   do, and a PHP-side comparison cannot pass by accident.
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
use App\Support\DraftStore;
use App\Support\MonthlyReport;
use App\Support\Plans;
use App\Support\ReviewAlerts;

date_default_timezone_set('America/Phoenix');

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

try {
    Database::first('SELECT 1');
} catch (Throwable $e) {
    echo 'SKIP  cannot reach the test database: ' . $e->getMessage() . "\n";
    exit(0);
}

// Migration 028 has to be applied for any of this to mean anything. Saying so
// is the point: a suite that skips itself looks exactly like one that passed.
foreach ([
    'review_reply_drafts' => DraftStore::ready(),
    'report_competitors'  => MonthlyReport::ready(),
    'hosted_reviews.alerted_at' => ReviewAlerts::ready(),
] as $what => $there) {
    if (!$there) {
        echo "SKIP  migration 028 has not been applied ({$what} is missing)\n";
        exit(0);
    }
}

/* ---- A world to test against ------------------------------------------ */

function wipe(): void
{
    foreach (['review_reply_drafts', 'report_competitors', 'hosted_reviews',
              'account_users', 'accounts', 'users'] as $table) {
        Database::run('DELETE FROM ' . $table);
    }
}

function makeAccount(string $plan = 'premium', int $alertsOn = 1): int
{
    Database::run(
        'INSERT INTO accounts (name, plan, alerts_on) VALUES (:n, :p, :a)',
        ['n' => 'Acme Pools', 'p' => $plan, 'a' => $alertsOn],
    );
    $id = (int) Database::connection()->lastInsertId();

    Database::run(
        'INSERT INTO users (email, password_hash, first_name, last_name, status)
         VALUES (:e, :h, :f, :l, \'active\')',
        ['e' => 'mike@acmepools.test', 'h' => password_hash('x', PASSWORD_DEFAULT),
         'f' => 'Mike', 'l' => 'Doyle'],
    );
    $userId = (int) Database::connection()->lastInsertId();
    Database::run(
        'INSERT INTO account_users (account_id, user_id, role) VALUES (:a, :u, \'owner\')',
        ['a' => $id, 'u' => $userId],
    );

    return $id;
}

function makeReview(int $accountId, int $rating, string $body, string $who = 'Dave Patterson'): int
{
    // source is an ENUM. 'direct' is not one of its values and MySQL in
    // non-strict mode truncates rather than refusing, which is how a test
    // fixture ends up writing an empty string and nobody notices.
    Database::run(
        'INSERT INTO hosted_reviews (account_id, source, author_name, rating, body)
         VALUES (:a, \'public_link\', :w, :r, :b)',
        ['a' => $accountId, 'w' => $who, 'r' => $rating, 'b' => $body],
    );

    return (int) Database::connection()->lastInsertId();
}

/* ---- Alerts: who is waiting to be told -------------------------------- */

wipe();
$account = makeAccount();
$good = makeReview($account, 5, 'Turned up on time and the pool has never looked better.');
$bad  = makeReview($account, 2, 'Waited in all day and nobody came.');

$pending = ReviewAlerts::pending();
check('both reviews are waiting', count($pending), 2);
check('oldest first', (int) $pending[0]['id'], $good);
ok('the business name comes with it', ($pending[0]['business'] ?? '') === 'Acme Pools');

ReviewAlerts::markSent($good);
check('one left after marking', count(ReviewAlerts::pending()), 1);
check('and it is the other one', (int) ReviewAlerts::pending()[0]['id'], $bad);

// Marking is idempotent. "It does not come back in pending()" is not enough
// to prove that -- it would not come back either way. The timestamp is the
// thing: a second mark that moved it would rewrite when the member was
// actually told, which is the only record there is of that.
$stamp = (string) Database::first(
    'SELECT alerted_at FROM hosted_reviews WHERE id = :id', ['id' => $good]
)['alerted_at'];
ok('a marked review has a timestamp', $stamp !== '');

ReviewAlerts::markSent($good);
check('marking twice changes nothing', count(ReviewAlerts::pending()), 1);

// Backdated first. Two marks in the same second both write the same NOW(),
// so comparing them proves nothing -- the first version of this check passed
// happily with the "AND alerted_at IS NULL" guard removed. An old value is
// the only way to see the second write actually happen.
Database::run(
    'UPDATE hosted_reviews SET alerted_at = \'2020-01-01 00:00:00\' WHERE id = :id',
    ['id' => $good],
);
ReviewAlerts::markSent($good);
check('and does not overwrite when it was told', (string) Database::first(
    'SELECT alerted_at FROM hosted_reviews WHERE id = :id', ['id' => $good]
)['alerted_at'], '2020-01-01 00:00:00');

ReviewAlerts::markSent($bad);
check('nothing waiting once both are told', count(ReviewAlerts::pending()), 0);

/* ---- Alerts: an account that asked not to hear ------------------------- */

wipe();
$quiet = makeAccount('premium', 0);
makeReview($quiet, 5, 'Great job.');
check('alerts off means nothing is queued', count(ReviewAlerts::pending()), 0);

// Off is a choice, not a default. An account row with no preference recorded
// still hears about its reviews.
ok('no preference means yes', ReviewAlerts::wanted(['id' => 1]));
ok('1 means yes', ReviewAlerts::wanted(['alerts_on' => 1]));
ok('0 means no', !ReviewAlerts::wanted(['alerts_on' => 0]));
ok('"0" from a form means no', !ReviewAlerts::wanted(['alerts_on' => '0']));

/* ---- Alerts: on every plan -------------------------------------------- */

foreach (['free', 'pro', 'premium'] as $plan) {
    wipe();
    $id = makeAccount($plan);
    makeReview($id, 4, 'Good work.');
    check("{$plan}: hears about its reviews", count(ReviewAlerts::pending()), 1);
}

/* ---- Alerts: who gets the mail ---------------------------------------- */

wipe();
$account = makeAccount();
check('the owner is a recipient', ReviewAlerts::recipients($account), ['mike@acmepools.test']);

Database::run('UPDATE users SET status = \'suspended\'');
check('a suspended user is not', ReviewAlerts::recipients($account), []);

/* ---- Alerts: what the mail says ---------------------------------------- */

$poor = ReviewAlerts::compose([
    'rating' => 2, 'business' => 'Acme Pools', 'author_name' => 'Dave Patterson',
    'body' => 'Waited in all day and nobody came.',
], 'https://promomonster.test');

ok('a poor review says so in the subject', str_contains($poor['subject'], 'needs an answer'));
ok('and names the rating', str_contains($poor['subject'], '2-star'));
ok('the review itself is in the mail', str_contains($poor['text'], 'Waited in all day'));
ok('and the reviewer is named', str_contains($poor['text'], 'Dave'));
ok('with a link to reply', str_contains($poor['text'], '/members/promomonster-reviews'));
ok('and a way to stop them', str_contains(mb_strtolower($poor['text']), 'settings'));

$fine = ReviewAlerts::compose([
    'rating' => 5, 'business' => 'Acme Pools', 'author_name' => 'Dave Patterson',
    'body' => 'Spotless.',
], 'https://promomonster.test');
ok('a good review does not cry wolf', !str_contains($fine['subject'], 'needs an answer'));
ok('but still says what it is', str_contains($fine['subject'], '5 out of 5'));

// A rating with no words still has to produce a readable email.
$silent = ReviewAlerts::compose(
    ['rating' => 5, 'business' => 'Acme Pools', 'author_name' => '', 'body' => ''],
    'https://promomonster.test',
);
ok('a wordless review still sends', trim($silent['text']) !== '');
ok('and does not say "Thanks ,"', !str_contains($silent['text'], ' ,'));

/* ---- The report: saving competitors ------------------------------------ */

wipe();
$account = makeAccount();

check('two saved', MonthlyReport::save($account, [
    ['name' => 'Blue Water Pools', 'rating' => 4.6, 'review_count' => 130],
    ['name' => 'Sunstate Pools',   'rating' => 4.1, 'review_count' => 58],
]), 2);

$saved = MonthlyReport::competitors($account);
check('both come back', count($saved), 2);
check('in the order given', (string) $saved[0]['name'], 'Blue Water Pools');
check('with the rating', (float) $saved[0]['rating'], 4.6);
check('and the count', (int) $saved[1]['review_count'], 58);

// Replace, not merge. The form shows both rows, so what is submitted IS the
// list -- a merge would leave a competitor the member deleted on screen still
// in next month's email.
check('saving one replaces both', MonthlyReport::save($account, [
    ['name' => 'Blue Water Pools', 'rating' => 4.6, 'review_count' => 130],
]), 1);
check('only one is left', count(MonthlyReport::competitors($account)), 1);

// Counted in the table, not through competitors(), which has a LIMIT on it
// and would report "1" over a row that had quietly become four.
check('and only one row exists', (int) Database::first(
    'SELECT COUNT(*) AS n FROM report_competitors WHERE account_id = :a', ['a' => $account]
)['n'], 1);

// A blank name is not a competitor, and a rating that is not a rating is a
// typo -- feeding either to the audit produces arithmetic nobody can defend.
check('blank rows are dropped', MonthlyReport::save($account, [
    ['name' => '  ', 'rating' => 4.0, 'review_count' => 10],
    ['name' => 'Real Pools', 'rating' => null, 'review_count' => null],
]), 1);
$saved = MonthlyReport::competitors($account);
check('the real one survived', (string) $saved[0]['name'], 'Real Pools');
check('a missing rating stays missing', $saved[0]['rating'], null);

foreach ([['6.0', null], ['0.5', null], ['-2', null], ['4.4', 4.4], ['5', 5.0]] as [$given, $want]) {
    MonthlyReport::save($account, [['name' => 'X', 'rating' => $given, 'review_count' => 1]]);
    $got = MonthlyReport::competitors($account)[0]['rating'];
    check("rating {$given} becomes " . var_export($want, true),
        $got === null ? null : (float) $got, $want);
}

// More than the audit takes is cut, not spread over two emails.
check('the list is capped', MonthlyReport::save($account, [
    ['name' => 'A', 'rating' => 4.0, 'review_count' => 1],
    ['name' => 'B', 'rating' => 4.0, 'review_count' => 1],
    ['name' => 'C', 'rating' => 4.0, 'review_count' => 1],
]), MonthlyReport::MAX_COMPETITORS);

/* ---- The report: the schedule ------------------------------------------ */

wipe();
$account = makeAccount();
check('nothing is due with no competitors', MonthlyReport::nextDue(), null);

MonthlyReport::save($account, [['name' => 'Blue Water Pools', 'rating' => 4.6, 'review_count' => 130]]);
$due = MonthlyReport::nextDue();
ok('naming somebody makes one due now', $due !== null && (int) $due['id'] === $account);

MonthlyReport::reschedule($account, true);
check('a sent report is not due again', MonthlyReport::nextDue(), null);

$row = Database::first('SELECT report_last_at, report_next_at FROM accounts WHERE id = :a', ['a' => $account]);
ok('and the send is recorded', $row['report_last_at'] !== null);

// The one that matters: a FAILED report still moves its own clock. Without
// this, an account whose audit throws is picked up on every run forever.
MonthlyReport::save($account, [['name' => 'Blue Water Pools', 'rating' => 4.6, 'review_count' => 130]]);
ok('due again after saving', MonthlyReport::nextDue() !== null);
// Captured first. A failed run must not move report_last_at -- but it must
// not clear it either: the last report that DID go out really did go out
// then, and a failure today does not unsend it. The first version of this
// check expected null and was simply wrong about what the column means.
$before = (string) Database::first(
    'SELECT report_last_at FROM accounts WHERE id = :a', ['a' => $account]
)['report_last_at'];

MonthlyReport::reschedule($account, false);
check('a failed report waits its turn too', MonthlyReport::nextDue(), null);

$row = Database::first('SELECT report_last_at FROM accounts WHERE id = :a', ['a' => $account]);
check('and does not count as a send', (string) $row['report_last_at'], $before);

// An account that has never had one still reads as never having had one.
wipe();
$virgin = makeAccount();
MonthlyReport::save($virgin, [['name' => 'Blue Water Pools', 'rating' => 4.6, 'review_count' => 130]]);
MonthlyReport::reschedule($virgin, false);
$row = Database::first('SELECT report_last_at FROM accounts WHERE id = :a', ['a' => $virgin]);
check('a first run that failed records no send', $row['report_last_at'], null);
$account = $virgin;

// Clearing the list stops the report rather than sending an empty one.
MonthlyReport::save($account, []);
check('no competitors, no schedule', MonthlyReport::nextDue(), null);
$row = Database::first('SELECT report_next_at FROM accounts WHERE id = :a', ['a' => $account]);
check('and the date is cleared', $row['report_next_at'], null);

/* ---- The report: what the email says ----------------------------------- */

$audit = [
    'headline' => 'Acme Pools is rated higher than both, on far fewer reviews.',
    'standing' => 'behind',
    'rating_gap' => '4.8 from 24 reviews against 4.6 from 130 and 4.1 from 58.',
    'five_star_reviews_needed' => 11,
    'their_strengths' => ['Turning up when they said they would'],
    'competitor_strengths' => ['Weekend availability'],
    'weaknesses' => ['Two reviews mention slow callbacks'],
    'actions' => [
        ['action' => 'Ask the eight customers from last month', 'why' => 'Volume is the gap, not rating.'],
    ],
    'data_limits' => 'This saw only the reviews on your PromoMonster page, and the figures you typed for the other two.',
];

$mail = MonthlyReport::compose($audit, 'Acme Pools', [
    ['name' => 'Blue Water Pools'], ['name' => 'Sunstate Pools'],
], 'https://promomonster.test');

ok('the subject says where they stand', str_contains($mail['subject'], 'behind'));
ok('and names who against', str_contains($mail['subject'], 'Blue Water Pools'));
ok('the headline leads', str_contains($mail['text'], 'rated higher than both'));
ok('the numbers are there', str_contains($mail['text'], '4.8 from 24 reviews'));
ok('the gap is spelled out', str_contains($mail['text'], '11 more five-star reviews'));
ok('what to do is there', str_contains($mail['text'], 'Ask the eight customers'));
ok('and why', str_contains($mail['text'], 'Volume is the gap'));

// Never trimmed. This is the line that says what the report could not see,
// and a report that drops its own caveats is one people act on wrongly.
ok('the caveats survive', str_contains($mail['text'], 'only the reviews on your PromoMonster page'));
ok('under a heading that says so', str_contains($mail['text'], 'COULD NOT SEE'));

// An audit that found nothing must still produce a sendable email rather than
// a blank one with three empty headings.
$thin = MonthlyReport::compose([
    'headline' => 'Not enough to compare yet.',
    'standing' => 'not_enough_data',
    'rating_gap' => 'You have no reviews on your page yet.',
    'five_star_reviews_needed' => null,
    'their_strengths' => [], 'competitor_strengths' => [], 'weaknesses' => [],
    'actions' => [], 'data_limits' => 'No review text was available.',
], 'Acme Pools', [['name' => 'Blue Water Pools']], 'https://promomonster.test');

ok('a thin audit still sends', trim($thin['text']) !== '');
ok('with no empty headings', !str_contains($thin['text'], "WHAT COMES UP AS A PROBLEM"));
ok('and no null in the text', !str_contains(mb_strtolower($thin['text']), 'null'));

/* ---- Who gets any of this ---------------------------------------------- */

check('free has no report', Plans::can(Plans::FREE, Plans::MONTHLY_REPORT), false);
check('pro has no report',  Plans::can(Plans::PRO, Plans::MONTHLY_REPORT), false);
check('premium does',       Plans::can(Plans::PREMIUM, Plans::MONTHLY_REPORT), true);
check('premium is the cheapest that does',
    Plans::cheapestWith(Plans::MONTHLY_REPORT), Plans::PREMIUM);

/* ---- Drafts: the meter ------------------------------------------------- */

wipe();
$account = makeAccount();
$review  = makeReview($account, 2, 'Nobody came.');

$month = DraftStore::month($account, 'premium');
check('nothing used yet', $month['used'], 0);
ok('and a draft is allowed', $month['allowed']);
check('the cap is the plan\'s',
    $month['limit'], (int) Plans::limit(Plans::PREMIUM, 'reply_drafts_per_month'));

DraftStore::save($account, $review, 'Sorry about that, Dave.', 'claude-sonnet-5');
check('one used', DraftStore::month($account, 'premium')['used'], 1);
check('and it comes back', DraftStore::latest($account, $review), 'Sorry about that, Dave.');

DraftStore::save($account, $review, 'A second try.', 'claude-sonnet-5');
check('the newest wins', DraftStore::latest($account, $review), 'A second try.');
check('and both count', DraftStore::month($account, 'premium')['used'], 2);

check('one query gets them all',
    DraftStore::forReviews($account, [$review]), [$review => 'A second try.']);
check('another account sees nothing', DraftStore::latest($account + 999, $review), null);

// A plan that cannot draft is refused before anything is counted or spent.
$free = DraftStore::month($account, 'free');
ok('free is refused', !$free['allowed']);
check('with nothing to spend', $free['limit'], 0);
ok('and told where it lives', str_contains((string) $free['reason'], 'Premium'));

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
