<?php

declare(strict_types=1);

/**
 * Member-owned email templates, against a real schema.
 *
 *   PM_TEST_DB_HOST=127.0.0.1 PM_TEST_DB_PORT=3399 \
 *       PM_TEST_DB_NAME=promomonster_test php tests/templates-test.php
 *
 * The one that matters most here is ownership. A template id arrives on a form,
 * and a template is the words that go out over a business's name to one of
 * their customers -- so "can account 2 send account 1's wording" is not a
 * hypothetical, it is the first thing anybody would try.
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
use App\Support\EmailTemplates;
use App\Support\ReviewRequests;

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

if (!EmailTemplates::ready()) {
    echo "SKIP  migration 021 has not been applied to the test database\n";
    exit(0);
}
// Deliberately not a skip any more: a missing system template is the state
// this is here to test, because it is the state the live site was in.
EmailTemplates::install();

/** How many request templates the product ships with, counted rather than typed. */
define('SHIPPED_REQUESTS', count(array_filter(
    EmailTemplates::SYSTEM,
    static fn (array $t): bool => $t['kind'] === 'request',
)));

/** Two accounts, so every ownership claim has somebody to steal from. */
function seed(): void
{
    Database::run('DELETE FROM templates WHERE account_id IS NOT NULL');
    foreach (['review_requests', 'contacts', 'locations', 'account_users', 'accounts'] as $t) {
        Database::run("DELETE FROM {$t}");
    }
    Database::run("INSERT INTO accounts (id,name,plan,status) VALUES (1,'Acme Pools','free','active')");
    Database::run("INSERT INTO accounts (id,name,plan,status) VALUES (2,'Rival Pools','free','active')");
    Database::run("INSERT INTO locations (id,account_id,name,google_review_url)
                   VALUES (1,1,'Acme Pools','https://g.page/r/ACME')");
    Database::run("INSERT INTO locations (id,account_id,name,google_review_url)
                   VALUES (2,2,'Rival Pools','https://g.page/r/RIVAL')");
    Database::run("INSERT INTO contacts (id,location_id,first_name,last_name,email,source)
                   VALUES (1,1,'Dana','Reyes','dana@example.test','manual')");
}

/** @param array<string,mixed> $extra */
function save(int $account, array $extra = []): array
{
    return EmailTemplates::save($account, array_merge([
        'kind' => 'request', 'name' => 'Mine', 'subject' => 'How did we do?',
        'body' => "Hi {{first_name}},\n\nPlease review us: {{review_url}}\n\nThanks",
    ], $extra));
}

// =====================================================================
// What the system ships with is the answer before anybody writes anything
// =====================================================================
seed();
$system = ReviewRequests::systemTemplate('request');
check('a brand new account defaults to the system template',
    (int) (EmailTemplates::defaultFor(1, 'request')['id'] ?? 0), (int) $system['id']);
check('and the shipped request templates are what is on offer',
    count(EmailTemplates::forAccount(1, 'request')), SHIPPED_REQUESTS);
check('the reminder has a default too',
    (int) (EmailTemplates::defaultFor(1, 'follow_up')['id'] ?? 0),
    (int) ReviewRequests::systemTemplate('follow_up')['id']);

// =====================================================================
// Writing one
// =====================================================================
seed();
$made = save(1);
ok('a valid template saves', $made['ok']);
check('and it joins the shipped ones on the list',
    count(EmailTemplates::forAccount(1, 'request')), SHIPPED_REQUESTS + 1);
check('but it is not the default until asked',
    (int) (EmailTemplates::defaultFor(1, 'request')['id'] ?? 0), (int) $system['id']);

ok('saving with make_default makes it the default',
    save(1, ['name' => 'Chosen', 'make_default' => true])['ok']);
check('and that is what defaultFor returns',
    (string) (EmailTemplates::defaultFor(1, 'request')['name'] ?? ''), 'Chosen');

// One default, not several.
seed();
$a = save(1, ['name' => 'First', 'make_default' => true]);
$b = save(1, ['name' => 'Second', 'make_default' => true]);
$flagged = Database::first('SELECT COUNT(*) AS n FROM templates WHERE account_id = 1 AND is_default = 1');
check('setting a second default clears the first', (int) $flagged['n'], 1);
check('and the second one is the one in force',
    (int) (EmailTemplates::defaultFor(1, 'request')['id'] ?? 0), (int) $b['id']);

// A default is per kind, not per account.
seed();
save(1, ['kind' => 'follow_up', 'name' => 'My reminder', 'make_default' => true]);
check('a reminder default does not become the request default',
    (int) (EmailTemplates::defaultFor(1, 'request')['id'] ?? 0), (int) $system['id']);
check('while the reminder default is the new one',
    (string) (EmailTemplates::defaultFor(1, 'follow_up')['name'] ?? ''), 'My reminder');

// Going back to the stock wording.
seed();
save(1, ['name' => 'Mine', 'make_default' => true]);
EmailTemplates::useSystemDefault(1, 'request');
check('clearing the flag hands the account back to the system template',
    (int) (EmailTemplates::defaultFor(1, 'request')['id'] ?? 0), (int) $system['id']);

// =====================================================================
// What will not save
// =====================================================================
seed();
ok('an empty name is refused', !save(1, ['name' => '  '])['ok']);
ok('an empty subject is refused', !save(1, ['subject' => ''])['ok']);
ok('an empty body is refused', !save(1, ['body' => '   '])['ok']);
ok('a name longer than the column is refused', !save(1, ['name' => str_repeat('x', 121)])['ok']);
ok('a subject longer than the column is refused', !save(1, ['subject' => str_repeat('x', 201)])['ok']);
ok('a body longer than the column is refused', !save(1, ['body' => str_repeat('x', 5001)])['ok']);

// The link is the whole point of the email.
$noLink = save(1, ['body' => 'Hi {{first_name}}, please leave us a review. Thanks!']);
ok('a body with no review link is refused', !$noLink['ok']);
ok('and says which field is missing', str_contains((string) $noLink['error'], '{{review_url}}'));

// A typo in a merge field is silently deleted at send time, so it is caught here.
$typo = save(1, ['body' => 'Hi {{firstname}}, review us: {{review_url}}']);
ok('an unknown merge field is refused', !$typo['ok']);
ok('and the typo is named back', str_contains((string) $typo['error'], 'firstname'));
check('unknownFields finds it', EmailTemplates::unknownFields('{{firstname}} {{review_url}}'), ['firstname']);
check('and finds nothing in a good one',
    EmailTemplates::unknownFields('{{first_name}} {{business_name}} {{review_url}}'), []);
check('a field is reported once however often it appears',
    EmailTemplates::unknownFields('{{nope}} {{nope}} {{review_url}}'), ['nope']);

// =====================================================================
// One account cannot touch another's wording
// =====================================================================
seed();
$mine = save(1, ['name' => 'Acme wording']);
$id   = (int) $mine['id'];

ok('the owner can read it', EmailTemplates::find(1, $id) !== null);
ok('a rival cannot', EmailTemplates::find(2, $id) === null);
ok('a rival cannot make it their default', !EmailTemplates::makeDefault(2, $id));
ok('a rival cannot delete it', !EmailTemplates::delete(2, $id));
ok('and it is still there afterwards', EmailTemplates::find(1, $id) !== null);
ok("a rival's list does not include it", EmailTemplates::forAccount(2, 'request') === array_values(
    array_filter(EmailTemplates::forAccount(2, 'request'), static fn ($r) => (int) $r['id'] !== $id)));

// resolve() is what the send form's id goes through.
check('resolve returns the chosen template for its owner',
    (int) (EmailTemplates::resolve(1, $id, 'request')['id'] ?? 0), $id);
check("resolve falls back rather than sending somebody else's",
    (int) (EmailTemplates::resolve(2, $id, 'request')['id'] ?? 0), (int) $system['id']);
check('an id that does not exist falls back too',
    (int) (EmailTemplates::resolve(1, 999999, 'request')['id'] ?? 0), (int) $system['id']);
check('so does no id at all',
    (int) (EmailTemplates::resolve(1, null, 'request')['id'] ?? 0), (int) $system['id']);

// A request template must not arrive as a reminder.
seed();
$reminder = save(1, ['kind' => 'follow_up', 'name' => 'My reminder']);
check('a template of the wrong kind is not used',
    (int) (EmailTemplates::resolve(1, (int) $reminder['id'], 'request')['id'] ?? 0), (int) $system['id']);

// =====================================================================
// The system templates are shared, so they are read-only
// =====================================================================
seed();
$before = (string) $system['body'];
$copy   = EmailTemplates::save(1, [
    'id' => (int) $system['id'], 'kind' => 'request', 'name' => 'My copy',
    'subject' => 'Changed', 'body' => 'Rewritten: {{review_url}}',
]);
ok('saving over a system template succeeds', $copy['ok']);
ok('but as a copy, not an edit', (int) $copy['id'] !== (int) $system['id']);
check('the shared row is untouched',
    (string) Database::first('SELECT body FROM templates WHERE id = :id',
        ['id' => (int) $system['id']])['body'], $before);
check('and the copy belongs to the account that made it',
    (int) Database::first('SELECT account_id FROM templates WHERE id = :id',
        ['id' => (int) $copy['id']])['account_id'], 1);

ok('a system template cannot be deleted', !EmailTemplates::delete(1, (int) $system['id']));
ok('nor flagged as one account\'s default', !EmailTemplates::makeDefault(1, (int) $system['id']));
ok('and it is still installed', ReviewRequests::systemTemplate('request') !== null);

// =====================================================================
// Deleting leaves queued requests sendable
// =====================================================================
// review_requests.template_id is ON DELETE SET NULL and the sender reads the
// subject and body off the joined row. Without the hand-off a queued request
// whose template was deleted would go out with no subject and no body.
seed();
$doomed   = save(1, ['name' => 'Doomed', 'make_default' => true]);
$location = Database::first('SELECT * FROM locations WHERE id = 1');
$contact  = Database::first('SELECT * FROM contacts WHERE id = 1');

$queued = ReviewRequests::queue($location, $contact, null, (int) $doomed['id']);
ok('a request queues on the chosen template', $queued['ok']);
check('and records which one',
    (int) Database::first('SELECT template_id FROM review_requests WHERE id = :id',
        ['id' => (int) $queued['id']])['template_id'], (int) $doomed['id']);

EmailTemplates::delete(1, (int) $doomed['id']);
$moved = Database::first('SELECT template_id FROM review_requests WHERE id = :id',
    ['id' => (int) $queued['id']]);
ok('deleting it moves the queued request onto the fallback', $moved['template_id'] !== null);
check('which is the system template now that the account has no default',
    (int) $moved['template_id'], (int) $system['id']);

// =====================================================================
// Queueing uses the account's default when nothing is picked
// =====================================================================
seed();
$chosen   = save(1, ['name' => 'House style', 'make_default' => true]);
$location = Database::first('SELECT * FROM locations WHERE id = 1');
$contact  = Database::first('SELECT * FROM contacts WHERE id = 1');

$q = ReviewRequests::queue($location, $contact);
ok('queueing with no template picked works', $q['ok']);
check('and uses the account default rather than the system one',
    (int) Database::first('SELECT template_id FROM review_requests WHERE id = :id',
        ['id' => (int) $q['id']])['template_id'], (int) $chosen['id']);

// The id on the form is not trusted.
seed();
$theirs   = save(2, ['name' => 'Rival wording', 'make_default' => true]);
$location = Database::first('SELECT * FROM locations WHERE id = 1');
$contact  = Database::first('SELECT * FROM contacts WHERE id = 1');

$sneaky = ReviewRequests::queue($location, $contact, null, (int) $theirs['id']);
ok('queueing with another account\'s template id still works', $sneaky['ok']);
check("but does not send that account's wording",
    (int) Database::first('SELECT template_id FROM review_requests WHERE id = :id',
        ['id' => (int) $sneaky['id']])['template_id'], (int) $system['id']);

// =====================================================================
// The preview is the email
// =====================================================================
seed();
$made = save(1, [
    'name' => 'Previewed',
    'subject' => 'How did we do, {{first_name}}?',
    'body' => "Hi {{first_name}},\n\n{{business_name}} here. {{review_url}}",
]);
$preview = EmailTemplates::preview(EmailTemplates::find(1, (int) $made['id']));
check('the subject is merged', $preview['subject'], 'How did we do, Dana?');
ok('the body has the sample name', str_contains($preview['body'], 'Hi Dana,'));
ok('and the sample business', str_contains($preview['body'], 'Acme Pools here.'));
ok('and a link that looks like a link', str_contains($preview['body'], 'https://'));
ok('with nothing left in braces', !str_contains($preview['body'], '{{'));

// =====================================================================
// The shipped wording installs itself when the seed never ran
// =====================================================================
// "No request template is installed. Run the migrations." was a dead end on
// the live site: the templates table was there, 018's INSERTs were not, and
// asking a customer for a review was impossible until somebody ran SQL by
// hand. The wording now lives in PHP as well, and a missing row is repaired
// on the read path.
seed();
Database::run("DELETE FROM templates WHERE is_system = 1 AND channel = 'email'");
EmailTemplates::forget();
ok('with the seed gone there is no system template', ReviewRequests::systemTemplate('request') === null);

$location = Database::first('SELECT * FROM locations WHERE id = 1');
$contact  = Database::first('SELECT * FROM contacts WHERE id = 1');
$rescued  = ReviewRequests::queue($location, $contact);
ok('but queueing a request still works', $rescued['ok']);
ok('because the template was put back', ReviewRequests::systemTemplate('request') !== null);

// Every one of them, not just the first: a repair that puts one template back
// and leaves the other three out is the bug this section exists for.
foreach (EmailTemplates::SYSTEM as $shipped) {
    $installed = Database::first(
        "SELECT * FROM templates
          WHERE is_system = 1 AND channel = 'email' AND kind = :kind AND name = :name",
        ['kind' => $shipped['kind'], 'name' => $shipped['name']],
    );
    ok("'{$shipped['name']}' was installed", $installed !== null);
    check("with its shipped subject", (string) ($installed['subject'] ?? ''), $shipped['subject']);
    check("and its shipped body", (string) ($installed['body'] ?? ''), $shipped['body']);
    // Not ?? here: account_id IS NULL on a shipped row, and ?? treats a null
    // value as an absent key, so the fallback fired on the correct answer.
    check("owned by nobody, so every account sees it",
        $installed === null ? 'no row at all' : $installed['account_id'], null);
    check("and flagged as ours", (int) ($installed['is_system'] ?? 0), 1);
    check("but not as anybody's default", (int) ($installed['is_default'] ?? 1), 0);
}

// Running it twice must not leave two.
EmailTemplates::forget();
check('installing again adds nothing', EmailTemplates::install(), 0);
check('so there is exactly one row per shipped template',
    (int) Database::first("SELECT COUNT(*) n FROM templates WHERE is_system = 1 AND channel = 'email'")['n'],
    count(EmailTemplates::SYSTEM));

// The reminder heals the same way.
seed();
Database::run("DELETE FROM templates WHERE is_system = 1 AND channel = 'email'");
EmailTemplates::forget();
ok('the reminder is reinstalled too',
    EmailTemplates::defaultFor(1, 'follow_up') !== null);

// And the page is not left with an empty list.
seed();
Database::run("DELETE FROM templates WHERE is_system = 1 AND channel = 'email'");
EmailTemplates::forget();
check('the templates page still has something to show',
    count(EmailTemplates::forAccount(1, 'request')), SHIPPED_REQUESTS);

// =====================================================================
// A template added after 018 still reaches a database that ran 018
// =====================================================================
// This is the case the first version of the repair got wrong. It asked whether
// a system row of the kind existed, and on any database that had run 018 the
// answer was yes -- so a request template added later would never have been
// installed on the one database that already worked. "Some template exists" is
// not the same question as "the ones we ship do".
seed();
Database::run("DELETE FROM templates WHERE is_system = 1 AND channel = 'email'");

// Exactly what 018 leaves behind, and nothing since.
foreach (EmailTemplates::SYSTEM as $shipped) {
    if (($shipped['migration'] ?? '') !== '018') {
        continue;
    }
    Database::run(
        'INSERT INTO templates (account_id, vertical, channel, kind, name, subject, body, is_system)
         VALUES (NULL, NULL, :channel, :kind, :name, :subject, :body, 1)',
        ['channel' => 'email', 'kind' => $shipped['kind'], 'name' => $shipped['name'],
         'subject' => $shipped['subject'], 'body' => $shipped['body']],
    );
}
check('a database at 018 has two system templates',
    (int) Database::first("SELECT COUNT(*) n FROM templates WHERE is_system = 1 AND channel = 'email'")['n'], 2);

EmailTemplates::forget();
$list = EmailTemplates::forAccount(1, 'request');
check('opening the page installs the ones added since', count($list), SHIPPED_REQUESTS);

$names = array_column($list, 'name');
foreach (EmailTemplates::SYSTEM as $shipped) {
    if ($shipped['kind'] !== 'request') {
        continue;
    }
    ok("'{$shipped['name']}' is on the list", in_array($shipped['name'], $names, true));
}

// And the one that was already the default stays the default -- a new template
// arriving must not move a business off the wording it has been using.
check('the standard request is still what a new account falls back to',
    (string) (EmailTemplates::defaultFor(1, 'request')['name'] ?? ''), 'Standard review request');

// Each one is sendable: the link is there and no field is left unmerged.
foreach ($list as $row) {
    $preview = EmailTemplates::preview($row);
    ok("'{$row['name']}' previews with a link", str_contains($preview['body'], 'https://'));
    ok("'{$row['name']}' leaves nothing in braces",
        !str_contains($preview['body'], '{{') && !str_contains($preview['subject'], '{{'));
    ok("'{$row['name']}' would pass the save rules",
        str_contains((string) $row['body'], '{{review_url}}')
        && EmailTemplates::unknownFields((string) $row['subject'] . (string) $row['body']) === []);
    ok("'{$row['name']}' has a subject within the column",
        mb_strlen((string) $row['subject']) <= EmailTemplates::MAX_SUBJECT);
    ok("'{$row['name']}' has a body within the column",
        mb_strlen((string) $row['body']) <= EmailTemplates::MAX_BODY);
    // No incentive wording, which is the one thing that would make the shipped
    // templates themselves a policy breach rather than a taste question.
    foreach (['discount', 'voucher', 'coupon', 'free ', 'gift', 'prize', 'draw', 'raffle', '% off'] as $bribe) {
        ok("'{$row['name']}' offers no {$bribe}",
            !str_contains(mb_strtolower((string) $row['body']), $bribe));
    }
}

// =====================================================================
// The PHP copy and the migration must not drift
// =====================================================================
// Two copies of the same words is the cost of the repair above. A test that
// reads the migration is what stops that cost turning into two DIFFERENT
// emails depending on which one ran first.
$sql = (string) file_get_contents(dirname(__DIR__) . '/database/migrations/018_email_sending.sql');

foreach (EmailTemplates::SYSTEM as $shipped) {
    // Only the two 018 seeds. The rest ship from PHP alone and deliberately
    // have no migration to drift from -- adding one would mean a third copy of
    // the same words.
    if (($shipped['migration'] ?? '') !== '018') {
        continue;
    }
    $kind = $shipped['kind'];

    // Each INSERT ... SELECT block, from the kind to its guard.
    $block = '';
    if (preg_match("/'email',\s*\n\s*'" . $kind . "',(.*?)WHERE NOT EXISTS/s", $sql, $m) === 1) {
        $block = $m[1];
    }
    ok("018 has a block for {$kind}", $block !== '');

    // The name and subject are the two bare quoted strings before CONCAT_WS.
    $head = substr($block, 0, strpos($block, 'CONCAT_WS') ?: 0);
    preg_match_all("/'((?:[^']|'')*)'/", $head, $heads);
    $parts = array_map(static fn (string $v): string => str_replace("''", "'", $v), $heads[1]);

    check("the {$kind} name matches the migration", $parts[0] ?? '', $shipped['name']);
    check("the {$kind} subject matches the migration", $parts[1] ?? '', $shipped['subject']);

    // The body is the quoted lines inside CONCAT_WS, joined with a newline.
    // Matched past the separator argument rather than cut at the first '),':
    // CONCAT_WS(CHAR(10 USING utf8mb4), ends in exactly that, so cutting there
    // threw the whole body away and compared '' to '' for both kinds -- a test
    // that passes because it is looking at nothing.
    $tail = '';
    if (preg_match('/CONCAT_WS\(CHAR\(10 USING utf8mb4\),(.*?)\n\s*\),/s', $block, $bm) === 1) {
        $tail = $bm[1];
    }
    ok("the {$kind} body is readable out of the migration", trim($tail) !== '');

    preg_match_all("/'((?:[^']|'')*)'/", $tail, $lines);
    $body = implode("\n", array_map(
        static fn (string $v): string => str_replace("''", "'", $v),
        $lines[1],
    ));

    check("the {$kind} body matches the migration character for character", $body, $shipped['body']);
}

// Whatever this file did to the shared seed, leave it as it found it.
EmailTemplates::forget();
EmailTemplates::install();

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
