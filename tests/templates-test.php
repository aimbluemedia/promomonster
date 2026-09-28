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
if (ReviewRequests::systemTemplate('request') === null) {
    echo "SKIP  migration 018 has not seeded the system templates\n";
    exit(0);
}

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
ok('and the system template is the only one on offer',
    count(EmailTemplates::forAccount(1, 'request')) === 1);
check('the reminder has a default too',
    (int) (EmailTemplates::defaultFor(1, 'follow_up')['id'] ?? 0),
    (int) ReviewRequests::systemTemplate('follow_up')['id']);

// =====================================================================
// Writing one
// =====================================================================
seed();
$made = save(1);
ok('a valid template saves', $made['ok']);
ok('and now there are two to choose between', count(EmailTemplates::forAccount(1, 'request')) === 2);
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

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
