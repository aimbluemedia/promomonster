<?php

declare(strict_types=1);

/**
 * Reviews hosted here, against a real schema.
 *
 *   PM_TEST_DB_HOST=127.0.0.1 PM_TEST_DB_PORT=3399 \
 *       PM_TEST_DB_NAME=promomonster_test php tests/hosted-reviews-test.php
 *
 * Runs on a timezone the database does not share, like the other two suites,
 * so a value computed by PHP and compared by MySQL cannot hide here.
 */

$host = getenv('PM_TEST_DB_HOST') ?: '';
$name = getenv('PM_TEST_DB_NAME') ?: '';
if ($name === '' || $host === '') {
    echo "SKIP  no test database configured\n";
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
use App\Support\HostedReviews;
use App\Support\RateLimiter;

date_default_timezone_set('America/Phoenix');
$_SERVER['REMOTE_ADDR'] = '198.51.100.9';

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
    Database::connection();
} catch (Throwable $e) {
    echo 'SKIP  cannot reach the test database: ' . $e->getMessage() . "\n";
    exit(0);
}

if (!HostedReviews::ready()) {
    echo "SKIP  migration 020 has not been applied to the test database\n";
    exit(0);
}

function seed(): void
{
    foreach (['hosted_reviews', 'contacts', 'locations', 'account_users', 'accounts'] as $t) {
        Database::run('DELETE FROM ' . $t);
    }
    RateLimiter::forgetAll();
    Database::run("INSERT INTO accounts (id,name,plan,status) VALUES (1,'Acme Pools','free','active')");
    Database::run("INSERT INTO locations (id,account_id,name,timezone) VALUES (1,1,'Acme Pools','UTC')");
    Database::run("INSERT INTO contacts (id,location_id,first_name,last_name,email,source)
                   VALUES (1,1,'Mira','Vance','mira@example.test','manual')");
}

/** @param array<string,mixed> $extra */
function add(array $extra = []): array
{
    return HostedReviews::add(array_merge([
        'account' => 1, 'source' => 'public_link',
        'name' => 'Sam Doyle', 'city' => 'Mesa, AZ', 'rating' => 4, 'body' => 'Good job.',
    ], $extra));
}

// =====================================================================
// The public address
// =====================================================================
seed();
check('the slug comes from the business name', HostedReviews::slug(1, 'Acme Pools'), 'acme-pools');
check('and is stable on a second call', HostedReviews::slug(1, 'Acme Pools'), 'acme-pools');

Database::run("INSERT INTO accounts (id,name,plan,status) VALUES (2,'Acme Pools','free','active')");
check('a clash gets a readable suffix, not a random string',
    HostedReviews::slug(2, 'Acme Pools'), 'acme-pools-1');

check('punctuation and case are stripped', HostedReviews::slugify("O'Brien & Sons, Ltd."), 'o-brien-sons-ltd');
check('a name with nothing usable still yields something', HostedReviews::slugify('***'), 'business');
ok('an unknown slug resolves to nothing', HostedReviews::bySlug('no-such-business') === null);
ok('and so does an injection attempt', HostedReviews::bySlug("' OR 1=1 -- ") === null);
check('a real one resolves to its account', (int) (HostedReviews::bySlug('acme-pools')['id'] ?? 0), 1);

// =====================================================================
// What may be recorded
// =====================================================================
seed();
ok('a plain review is accepted', add()['ok']);
ok('a rating of nought is refused', !add(['rating' => 0])['ok']);
ok('a rating of six is refused', !add(['rating' => 6])['ok']);
ok('an empty body is refused', !add(['body' => '   '])['ok']);
ok('an empty name is refused', !add(['name' => ''])['ok']);
ok('a body longer than the column is refused', !add(['body' => str_repeat('x', 4100)])['ok']);

// City and state is asked for, not suggested: required="" on the input is a
// courtesy to the browser, and a form posted around it has to be refused too.
ok('a missing city is refused', !add(['city' => null])['ok']);
ok('an empty city is refused', !add(['city' => ''])['ok']);
ok('a city of spaces is refused', !add(['city' => "  \t "])['ok']);
ok('a city longer than the column is refused', !add(['city' => str_repeat('x', 121)])['ok']);
seed();
add(['city' => '  Mesa, AZ  ']);
check('the city is stored, trimmed',
    (string) Database::first('SELECT author_city FROM hosted_reviews')['author_city'], 'Mesa, AZ');

// A form must not be able to claim the one label we vouch for.
seed();
add(['source' => 'invited']);
check('"invited" is not settable without a contact',
    (string) Database::first('SELECT source FROM hosted_reviews')['source'], 'invited');
seed();
add(['source' => 'not-a-real-source']);
check('an unknown source falls back to the weakest claim',
    (string) Database::first('SELECT source FROM hosted_reviews')['source'], 'public_link');

// =====================================================================
// Google rows
// =====================================================================
seed();
ok('a Google review with a Google link is accepted',
    add(['source' => 'google', 'source_url' => 'https://g.page/r/acme/review'])['ok']);
check('and the link is stored',
    (string) Database::first('SELECT source_url FROM hosted_reviews')['source_url'],
    'https://g.page/r/acme/review');

seed();
$bad = add(['source' => 'google', 'source_url' => 'https://evil.test/phish']);
ok('a non-Google link is refused', !$bad['ok']);
check('nothing was written', (int) Database::first('SELECT COUNT(*) n FROM hosted_reviews')['n'], 0);

seed();
ok('a Google review with no link is still fine', add(['source' => 'google', 'source_url' => ''])['ok']);

// The labels are the compliance surface, so they are asserted literally.
check('only an invited review is called verified',
    HostedReviews::sourceLabel('invited'), 'Verified customer');
check('a business-entered one says so',
    HostedReviews::sourceLabel('entered_by_business'), 'Added by the business');
check('and a Google one does not pretend we checked it',
    HostedReviews::sourceLabel('google'), 'From Google, added by the business');
check('the public-page one claims nothing',
    HostedReviews::sourceLabel('public_link'), 'Left on the review page');
check('only Google gets the G badge', HostedReviews::sourceBadge('google'), 'Google');
check('and an ordinary review gets none', HostedReviews::sourceBadge('public_link'), '');

// =====================================================================
// The summary is over everything
// =====================================================================
seed();
foreach ([5, 5, 1] as $r) {
    add(['rating' => $r]);
}
$summary = HostedReviews::summary(1);
check('every review counts', $summary['count'], 3);
check('including the bad one, in the average', $summary['average'], 3.7);
check('and the breakdown', $summary['stars'][5] . '/' . $summary['stars'][1], '2/1');

// =====================================================================
// A review cannot be hidden, and that is enforced by the schema
// =====================================================================
$columns = array_column(Database::all(
    "SELECT COLUMN_NAME AS c FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hosted_reviews'"
), 'c');
foreach (['hidden', 'is_hidden', 'approved', 'is_approved', 'published', 'is_published', 'status'] as $forbidden) {
    ok("there is no '{$forbidden}' column to hide behind", !in_array($forbidden, $columns, true));
}

// =====================================================================
// Replying, which is the only thing a business can do
// =====================================================================
seed();
add(['rating' => 1, 'body' => 'Late and rude.']);
$id = (int) Database::first('SELECT id FROM hosted_reviews')['id'];

HostedReviews::reply(1, $id, 'Sorry about that - we have spoken to the crew.');
$row = Database::first('SELECT reply_body, replied_at FROM hosted_reviews WHERE id = :id', ['id' => $id]);
check('the reply is saved', (string) $row['reply_body'], 'Sorry about that - we have spoken to the crew.');
ok('and stamped', $row['replied_at'] !== null);

HostedReviews::reply(2, $id, 'Nothing to see here.');
check('another business cannot answer this one',
    (string) Database::first('SELECT reply_body FROM hosted_reviews WHERE id = :id', ['id' => $id])['reply_body'],
    'Sorry about that - we have spoken to the crew.');

HostedReviews::reply(1, $id, '');
$row = Database::first('SELECT reply_body, replied_at FROM hosted_reviews WHERE id = :id', ['id' => $id]);
ok('an empty reply clears the answer', $row['reply_body'] === null);
ok('but the review is still there',
    (int) Database::first('SELECT COUNT(*) n FROM hosted_reviews')['n'] === 1);

// =====================================================================
// Throttling public submissions
// =====================================================================
seed();
$allowed = 0;
for ($i = 0; $i < 8; $i++) {
    if (!HostedReviews::throttled()) {
        $allowed++;
    }
}
check('five public submissions an hour from one address', $allowed, 5);

// =====================================================================
// A half-applied migration is a notice, not a 500
// =====================================================================
// 020 gained columns after it had already run on the live database, and the
// runner records a migration by filename and never replays a recorded name --
// so the edit reached nobody who needed it. ready() asked only whether the
// table existed, said yes, and add() went to the database with a column name
// it did not have. A member adding a review got an error page and a reference
// number for doing nothing wrong.
//
// Reproduced by dropping the column rather than by mocking, because what is
// under test is what MySQL does about it.
seed();
$probe = 'hosted_reviews_ready_probe';
Database::run("DROP TABLE IF EXISTS {$probe}");
Database::run("CREATE TABLE {$probe} LIKE hosted_reviews");

try {
    Database::run('ALTER TABLE hosted_reviews DROP COLUMN author_city');
    HostedReviews::forget();

    ok('a table missing a column the code writes is not ready', !HostedReviews::ready());

    $result = add();
    ok('so adding a review is refused rather than fatal', !$result['ok']);
    ok('and says the feature is not switched on',
        str_contains((string) $result['error'], 'not switched on'));
} finally {
    // Put it back however the assertions went, or every later run of this
    // suite starts against a broken table.
    Database::run('DROP TABLE hosted_reviews');
    Database::run("RENAME TABLE {$probe} TO hosted_reviews");
    HostedReviews::forget();
}

ok('and the table is whole again afterwards', HostedReviews::ready());
seed();
ok('with a working insert', add()['ok']);

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
