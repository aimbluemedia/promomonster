<?php

declare(strict_types=1);

/**
 * The forgotten-password flow, end to end, against a real MySQL schema.
 *
 * Not mocked, for the same reason as sending-test.php: almost every way this
 * feature can be wrong is a database fact. A link that survives being used, a
 * second request that leaves the first one live, a reset that does not lift the
 * lockout that sent somebody here -- none of those are visible to a test that
 * stubs out the rows.
 *
 * The token is read back out of the mail log rather than out of the database,
 * because the database only ever holds its hash. That is the property under
 * test as much as anything else here: if a plaintext token ever appears in
 * password_resets, one of these assertions fails.
 *
 * Point it at a scratch database. It DELETEs, so never at a real one:
 *
 *   PM_TEST_DB_HOST=127.0.0.1 PM_TEST_DB_PORT=3399 \
 *       PM_TEST_DB_NAME=promomonster_test php tests/reset-test.php
 *
 * Skips cleanly with exit 0 when no database is configured.
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

use App\Support\Auth;
use App\Support\Config;
use App\Support\Database;
use App\Support\Mailer;
use App\Support\PasswordReset;
use App\Support\RateLimiter;

date_default_timezone_set('UTC');

// Auth::setPassword() and Auth::signIn() regenerate the session id, so there
// has to be one.
@session_start();

// A fixed client address, so the per-IP bucket is something the test controls
// rather than something it inherits.
$_SERVER['REMOTE_ADDR'] = '198.51.100.7';

$logFile = BASE_PATH . '/storage/logs/mail.log';

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
    // The log driver, not null: this suite has to read the link that was sent.
    'mail' => ['driver' => 'log', 'from' => 'reviews@promomonster.test'],
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

const OLD_PASSWORD = 'the-old-one-they-forgot';
const NEW_PASSWORD = 'a-brand-new-passphrase';

/**
 * Three logins that the flow has to treat differently:
 *   1 a member, the only one entitled to a reset
 *   2 staff, who must not be resettable from a public form
 *   3 a user with no account row, who has nothing to sign in to
 */
function seed(): void
{
    foreach (['password_resets', 'login_attempts', 'audit_log', 'account_users',
              'locations', 'accounts', 'users'] as $table) {
        Database::run('DELETE FROM ' . $table);
    }
    RateLimiter::forgetAll();

    Database::run("INSERT INTO accounts (id, name, plan, status) VALUES (1, 'Acme Pools', 'free', 'active')");

    $hash = password_hash(OLD_PASSWORD, PASSWORD_DEFAULT);

    Database::run(
        "INSERT INTO users (id, email, password_hash, first_name, last_name, is_admin, status)
         VALUES (1, 'dana@acmepools.test', :h, 'Dana', 'Okafor', 0, 'active')",
        ['h' => $hash],
    );
    Database::run("INSERT INTO account_users (account_id, user_id, role) VALUES (1, 1, 'owner')");

    Database::run(
        "INSERT INTO users (id, email, password_hash, first_name, last_name, is_admin, status)
         VALUES (2, 'staff@promomonster.test', :h, 'Sam', 'Staff', 1, 'active')",
        ['h' => $hash],
    );
    // Staff get an account row as well, on purpose. Without one the JOIN in
    // member() would exclude them anyway and the staff assertions below would
    // pass whether or not the is_admin rule existed -- which is exactly what
    // happened the first time this suite was written.
    Database::run("INSERT INTO account_users (account_id, user_id, role) VALUES (1, 2, 'manager')");

    Database::run(
        "INSERT INTO users (id, email, password_hash, first_name, last_name, is_admin, status)
         VALUES (3, 'orphan@nowhere.test', :h, 'Ora', 'Phan', 0, 'active')",
        ['h' => $hash],
    );

    clearMail();
}

function clearMail(): void
{
    global $logFile;
    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents($logFile, '');
}

function mailLog(): string
{
    global $logFile;
    return is_file($logFile) ? (string) file_get_contents($logFile) : '';
}

/** The token out of the emailed link, or null if no link was sent. */
function emailedToken(): ?string
{
    return preg_match('~/members/reset/([0-9a-f]{64})~', mailLog(), $m) === 1 ? $m[1] : null;
}

/** Every row, live or not. */
function rows(): int
{
    $row = Database::first('SELECT COUNT(*) AS n FROM password_resets');
    return (int) ($row['n'] ?? 0);
}

/**
 * Rows a link could still be redeemed from.
 *
 * Distinct from rows() because superseding a link expires it rather than
 * deleting it: a row that is gone cannot be told apart from a token that was
 * never issued, and the reset route throttles the ones it has never seen. So
 * "how many links work" and "how many rows exist" stopped being the same
 * question, and most of these assertions mean the first one.
 */
function liveRows(): int
{
    $row = Database::first(
        'SELECT COUNT(*) AS n FROM password_resets WHERE used_at IS NULL AND expires_at > NOW()'
    );
    return (int) ($row['n'] ?? 0);
}

/**
 * Clears PasswordReset's memo of whether its table exists.
 *
 * It caches per request, which is right in a web process and wrong in a test
 * that moves the table out from under it. Done by binding into the class scope
 * rather than by adding a reset() to the class, because a method that exists
 * only so a test can call it is a method somebody will eventually call in
 * anger.
 */
/**
 * Swaps the mail settings, keeping everything else. Config::load() replaces the
 * whole array, so the rest has to be carried across by hand.
 *
 * @param array<string,mixed> $mail
 */
function reloadMail(array $mail): void
{
    Config::load([
        'app_name' => 'PromoMonster',
        'app_url'  => 'https://promomonster.test',
        'app_key'  => 'integration-test-key-0123456789abcdef',
        'db'       => Config::get('db'),
        'mail'     => $mail,
    ]);
}

function forgetReadyCache(): void
{
    (Closure::bind(
        static function (): void { PasswordReset::$ready = null; },
        null,
        PasswordReset::class,
    ))();
}

try {
    Database::connection();
} catch (Throwable $e) {
    echo 'SKIP  cannot reach the test database: ' . $e->getMessage() . "\n";
    exit(0);
}

// =====================================================================
// Asking for a link
// =====================================================================
seed();
PasswordReset::request('dana@acmepools.test');

check('a member gets exactly one reset row', rows(), 1);
$token = emailedToken();
ok('and an email carrying a 64-character token', $token !== null);
ok('addressed to them', str_contains(mailLog(), 'to=dana@acmepools.test'));
ok('on the app_url, not a relative path', str_contains(mailLog(), 'https://promomonster.test/members/reset/'));
ok('and says how long it lasts', str_contains(mailLog(), '60 minutes'));

// The property the whole design rests on.
$stored = Database::first('SELECT * FROM password_resets LIMIT 1') ?? [];
check('the row stores the sha256 of the token', (string) $stored['token_hash'], hash('sha256', (string) $token));
ok('and nowhere holds the token itself', !str_contains(implode('|', array_map('strval', $stored)), (string) $token));
ok('the asking address is recorded', (string) $stored['requested_ip'] === '198.51.100.7');
ok('it starts unused', $stored['used_at'] === null);

ok('the token resolves to the member', PasswordReset::find((string) $token)['user_id'] === 1);

// =====================================================================
// Who does NOT get one
// =====================================================================
seed();
PasswordReset::request('nobody@example.test');
check('an address with no account creates no row', rows(), 0);
ok('and no email at all', emailedToken() === null);

seed();
PasswordReset::request('staff@promomonster.test');
check('a staff login cannot be reset from the members form', rows(), 0);
ok('and is sent nothing', emailedToken() === null);

// The same rule on the other side of the flow. A link issued while somebody was
// a customer must stop working if they are made staff, so the two halves cannot
// drift apart and leave a live link into a staff login.
seed();
Database::run(
    "INSERT INTO password_resets (user_id, token_hash, expires_at)
     VALUES (2, :hash, NOW() + INTERVAL 1 HOUR)",
    ['hash' => hash('sha256', str_repeat('b', 64))],
);
ok('a token pointing at a staff login does not resolve', PasswordReset::find(str_repeat('b', 64)) === null);
Database::run('UPDATE users SET is_admin = 0 WHERE id = 2');
ok('and resolves once that login is not staff', PasswordReset::find(str_repeat('b', 64)) !== null);

seed();
PasswordReset::request('orphan@nowhere.test');
check('nor can a user with no business account', rows(), 0);

seed();
Database::run("UPDATE users SET status = 'suspended' WHERE id = 1");
PasswordReset::request('dana@acmepools.test');
check('nor a suspended member', rows(), 0);

seed();
PasswordReset::request('DANA@AcmePools.TEST');
check('the address is matched case-insensitively', rows(), 1);

seed();
PasswordReset::request('not an email at all');
check('a malformed address is refused before any work', rows(), 0);

// =====================================================================
// A second request kills the first link
// =====================================================================
seed();
PasswordReset::request('dana@acmepools.test');
$first = (string) emailedToken();
clearMail();
PasswordReset::request('dana@acmepools.test');
$second = (string) emailedToken();

ok('a second request issues a different token', $first !== $second && $second !== '');
check('and leaves only one live row', liveRows(), 1);
check('the superseded one is kept, expired', rows(), 2);
ok('the first link is dead', PasswordReset::find($first) === null);
ok('the second link works', PasswordReset::find($second) !== null);

// =====================================================================
// What find() refuses
// =====================================================================
seed();
PasswordReset::request('dana@acmepools.test');
$token = (string) emailedToken();

ok('an empty token', PasswordReset::find('') === null);
ok('a short token', PasswordReset::find(substr($token, 0, 32)) === null);
ok('a token with a non-hex character', PasswordReset::find(str_repeat('z', 64)) === null);
ok('a token of the right shape that was never issued', PasswordReset::find(str_repeat('a', 64)) === null);
ok('SQL in the token slot', PasswordReset::find("' OR 1=1 -- ") === null);

// Expiry is a column, so move it rather than waiting an hour.
Database::run('UPDATE password_resets SET expires_at = NOW() - INTERVAL 1 MINUTE');
ok('an expired token', PasswordReset::find($token) === null);
Database::run('UPDATE password_resets SET expires_at = NOW() + INTERVAL 1 HOUR');
ok('and works again once it has not expired', PasswordReset::find($token) !== null);

// Being suspended between asking and clicking has to stop the link.
Database::run("UPDATE users SET status = 'suspended' WHERE id = 1");
ok('a member suspended after asking', PasswordReset::find($token) === null);
Database::run("UPDATE users SET status = 'active' WHERE id = 1");

// So does losing the account the link would sign them in to.
Database::run('DELETE FROM account_users WHERE user_id = 1');
ok('a member whose account went away', PasswordReset::find($token) === null);
Database::run("INSERT INTO account_users (account_id, user_id, role) VALUES (1, 1, 'owner')");

// =====================================================================
// Using it
// =====================================================================
seed();
PasswordReset::request('dana@acmepools.test');
$token = (string) emailedToken();
$reset = PasswordReset::find($token);

ok('completing the reset succeeds', PasswordReset::complete($reset, NEW_PASSWORD) === true);
ok('the new password now works', Auth::attemptPasswordOnly(1, NEW_PASSWORD));
ok('the old one does not', !Auth::attemptPasswordOnly(1, OLD_PASSWORD));

$spent = Database::first('SELECT * FROM password_resets WHERE id = :id', ['id' => $reset['id']]);
ok('the row is marked used', $spent !== null && $spent['used_at'] !== null);
ok('the link no longer resolves', PasswordReset::find($token) === null);
ok('and a second completion is refused', PasswordReset::complete($reset, 'something-else-entirely') === false);
ok('so the second password was never set', !Auth::attemptPasswordOnly(1, 'something-else-entirely'));

// must_change_password is cleared by Auth::setPassword(), which matters: a
// member handed a temporary password who resets instead must not land back on
// the forced-change form.
seed();
Database::run('UPDATE users SET must_change_password = 1 WHERE id = 1');
PasswordReset::request('dana@acmepools.test');
PasswordReset::complete(PasswordReset::find((string) emailedToken()), NEW_PASSWORD);
$user = Database::first('SELECT must_change_password FROM users WHERE id = 1');
check('a reset clears the forced-change flag', (int) $user['must_change_password'], 0);

// =====================================================================
// The lockout that sent them here
// =====================================================================
seed();
for ($i = 0; $i < 6; $i++) {
    Database::run(
        "INSERT INTO login_attempts (email, ip, successful) VALUES ('dana@acmepools.test', '203.0.113.9', 0)",
    );
}
ok('six failed sign-ins lock the address out', Auth::lockedOut('dana@acmepools.test'));

PasswordReset::request('dana@acmepools.test');
PasswordReset::complete(PasswordReset::find((string) emailedToken()), NEW_PASSWORD);
ok('a completed reset lifts that lockout', !Auth::lockedOut('dana@acmepools.test'));

// But it must not clear the throttle for anybody else.
seed();
for ($i = 0; $i < 6; $i++) {
    Database::run(
        "INSERT INTO login_attempts (email, ip, successful) VALUES ('someone.else@example.test', '203.0.113.9', 0)",
    );
}
PasswordReset::request('dana@acmepools.test');
PasswordReset::complete(PasswordReset::find((string) emailedToken()), NEW_PASSWORD);
ok('and leaves another address still locked', Auth::lockedOut('someone.else@example.test'));

// =====================================================================
// Rate limits
// =====================================================================
seed();
for ($i = 0; $i < 3; $i++) {
    clearMail();
    PasswordReset::request('dana@acmepools.test');
}
ok('the third request in the window still sends', emailedToken() !== null);

clearMail();
PasswordReset::request('dana@acmepools.test');
ok('the fourth sends nothing', emailedToken() === null);
check('and issues no new live row', liveRows(), 1);

// The IP bucket is wider, and is what an address-guessing run runs into.
seed();
for ($i = 0; $i < 12; $i++) {
    PasswordReset::request('guess' . $i . '@example.test');
}
clearMail();
PasswordReset::request('dana@acmepools.test');
ok('a run of guesses from one address exhausts its IP bucket', emailedToken() === null);
check('so the real member gets no row either', rows(), 0);

// A different client is unaffected by that.
$_SERVER['REMOTE_ADDR'] = '198.51.100.8';
PasswordReset::request('dana@acmepools.test');
ok('a different client is not caught by it', emailedToken() !== null);
$_SERVER['REMOTE_ADDR'] = '198.51.100.7';

// =====================================================================
// Housekeeping
// =====================================================================
seed();
PasswordReset::request('dana@acmepools.test');
Database::run('UPDATE password_resets SET expires_at = NOW() - INTERVAL 30 DAY, used_at = NOW()');
RateLimiter::forgetAll();
PasswordReset::request('dana@acmepools.test');
check('long-expired rows are purged on the next request', rows(), 1);

seed();
PasswordReset::request('dana@acmepools.test');
Database::run('UPDATE password_resets SET expires_at = NOW() - INTERVAL 2 DAY, used_at = NOW()');
RateLimiter::forgetAll();
PasswordReset::request('dana@acmepools.test');
check('but a recent one is kept for forensics', rows(), 2);

// Deleting the user takes their links with them.
seed();
PasswordReset::request('dana@acmepools.test');
Database::run('DELETE FROM users WHERE id = 1');
check('deleting a user cascades to their reset rows', rows(), 0);

// =====================================================================
// The email itself
// =====================================================================
seed();
PasswordReset::request('dana@acmepools.test');
$body = mailLog();
ok('the email greets them by name', str_contains($body, 'Hi Dana,'));
ok('names the account it is for', str_contains($body, 'dana@acmepools.test'));
ok('says it works once', str_contains($body, 'works once'));
ok('tells somebody who did not ask that nothing changed', str_contains($body, 'Nothing has changed'));
// A reset is not a mailing list. An unsubscribe link on it would be a way to
// stop somebody recovering their own account.
ok('carries no unsubscribe link', !str_contains($body, '/u/'));
ok('and no password', !str_contains($body, OLD_PASSWORD));

// =====================================================================
// A deploy where the files went up but the migration did not
// =====================================================================
// Not hypothetical -- it is what happened the first time this shipped. Files go
// up by FTP and migrations are run separately by hand through phpMyAdmin, so in
// between, every member can see a "Forgot your password?" link pointing at a
// form with no table behind it. The people following that link are the ones
// already locked out, which makes it the worst possible place for a raw query
// error.
seed();
Database::run('RENAME TABLE password_resets TO password_resets_parked');
forgetReadyCache();

ok('with no table, the feature reports itself not ready', PasswordReset::ready() === false);

$threw = false;
try {
    PasswordReset::request('dana@acmepools.test');
} catch (Throwable $e) {
    $threw = true;
}
ok('asking for a link does not throw', !$threw);

$threw = false;
try {
    $found = PasswordReset::find(str_repeat('a', 64));
} catch (Throwable $e) {
    $threw = true;
    $found = false;
}
ok('nor does redeeming one', !$threw && $found === null);

Database::run('RENAME TABLE password_resets_parked TO password_resets');
forgetReadyCache();

ok('it reports ready once the migration has run', PasswordReset::ready() === true);
PasswordReset::request('dana@acmepools.test');
check('and starts working with no restart', rows(), 1);

// =====================================================================
// Account email must not ride the bulk stream
// =====================================================================
// A review request is sent in bulk on behalf of a business, to somebody who
// never asked us for anything, and a share of those people press "report spam".
// A password reset is the one message that has to arrive, to somebody already
// locked out. Sharing an address and a stream means the first slowly poisons
// the second, and the support queue that breaks is the one you cannot answer by
// email.
$bulk = [
    'driver' => 'log',
    'from'   => 'reviews@notify.promomonster.test',
    'stream' => 'broadcast',
    'transactional_from'   => 'logins@promomonster.test',
    'transactional_stream' => 'outbound',
];

seed();
reloadMail($bulk);
PasswordReset::request('dana@acmepools.test');
$line = mailLog();

ok('a reset comes from the account address', str_contains($line, '<logins@promomonster.test>'));
ok('not from the one the review requests use', !str_contains($line, 'reviews@notify.promomonster.test'));
ok('and goes on the transactional stream', str_contains($line, 'stream=outbound'));
ok('never on the broadcast one', !str_contains($line, 'stream=broadcast'));

// A review request, for contrast: it SHOULD be on the bulk settings.
check('the bulk address is still what Mailer::from() means', Mailer::from(), 'reviews@notify.promomonster.test');

// Half-configured: no separate address yet. It still has to send, because a
// fallback that silently fails is worse than one that shares a reputation.
seed();
reloadMail(['driver' => 'log', 'from' => 'reviews@notify.promomonster.test', 'stream' => 'broadcast']);
PasswordReset::request('dana@acmepools.test');
$line = mailLog();

ok('with no account address set it falls back and still sends', str_contains($line, '<reviews@notify.promomonster.test>'));
ok('but stays off the broadcast stream even then', str_contains($line, 'stream=outbound'));
check('and the fallback is visible to a caller', Mailer::transactionalFrom(), 'reviews@notify.promomonster.test');

// =====================================================================
// Handing an account back by hand
// =====================================================================
// The route that needs no mail provider: staff generate a password, read it
// down the phone, and the member is made to change it. It is what makes the
// "get in touch and we will sort it out" line on the reset page true.
$samples = [];
for ($i = 0; $i < 400; $i++) {
    $samples[] = PasswordReset::temporaryPassword();
}
$joined = implode('', $samples);

ok('a temporary password is four groups of four',
    count(array_filter($samples, static fn ($p) => preg_match('/^[a-z2-9]{4}(-[a-z2-9]{4}){3}$/', $p) === 1)) === 400);

// The failure mode is not guessing, it is the owner saying "e" and the customer
// hearing "b". Every character that reads or sounds like another one is out.
foreach (['i', 'l', 'o', '0', '1'] as $confusable) {
    ok("never contains '{$confusable}'", !str_contains($joined, $confusable));
}

check('400 of them are 400 different passwords', count(array_unique($samples)), 400);

seed();
$temp = PasswordReset::temporaryPassword();
Auth::setTemporaryPassword(1, $temp);

ok('the temporary password works', Auth::attemptPasswordOnly(1, $temp));
ok('and the old one does not', !Auth::attemptPasswordOnly(1, OLD_PASSWORD));
$user = Database::first('SELECT must_change_password FROM users WHERE id = 1');
check('it forces a change at the next sign-in', (int) $user['must_change_password'], 1);

// The opposite of setPassword(), which clears the flag because the member chose
// that one themselves. Worth asserting both ways round: these two methods
// differ by one column and it is the column that matters.
Auth::setPassword(1, NEW_PASSWORD);
$user = Database::first('SELECT must_change_password FROM users WHERE id = 1');
check('choosing their own clears it again', (int) $user['must_change_password'], 0);

// Handing an account over must kill any link still sitting in an inbox.
seed();
PasswordReset::request('dana@acmepools.test');
check('a live link exists', rows(), 1);
PasswordReset::revokeFor(1);
check('handing the account over revokes it', liveRows(), 0);
check('but the row stays, so a stale click is not mistaken for guessing', rows(), 1);

// Spent rows are evidence and stay.
seed();
PasswordReset::request('dana@acmepools.test');
Database::run('UPDATE password_resets SET used_at = NOW()');
PasswordReset::revokeFor(1);
check('but a spent one is kept', rows(), 1);

// And it has to be safe before the migration has run, like everything else.
seed();
Database::run('RENAME TABLE password_resets TO password_resets_parked');
forgetReadyCache();
$threw = false;
try {
    PasswordReset::revokeFor(1);
} catch (Throwable $e) {
    $threw = true;
}
ok('revoking is safe with no table', !$threw);
Database::run('RENAME TABLE password_resets_parked TO password_resets');
forgetReadyCache();

// =====================================================================
// A stale link must not lock somebody out of the live one
// =====================================================================
// Asking again supersedes the previous link, so anybody who clicks the older
// of two emails gets the dead-link page. That is fine. What is not fine is
// that the route throttles tokens it does not recognise -- so if a superseded
// row were deleted, those clicks would look exactly like guessing, and twenty
// of them would deny the live link too. The person most likely to do that is
// the one who just asked for several resets because none seemed to work.
seed();
PasswordReset::request('dana@acmepools.test');
$stale = (string) emailedToken();
RateLimiter::forgetAll();
clearMail();
PasswordReset::request('dana@acmepools.test');
$live = (string) emailedToken();

ok('the superseded link no longer resolves', PasswordReset::find($stale) === null);
ok('but it is still recognised as one of ours', PasswordReset::everExisted($stale));
ok('while a token never issued is not', !PasswordReset::everExisted(str_repeat('c', 64)));
ok('and the live link still works', PasswordReset::find($live) !== null);

// A spent link has to survive the same way, for the same reason.
seed();
PasswordReset::request('dana@acmepools.test');
$spent = (string) emailedToken();
PasswordReset::complete(PasswordReset::find($spent), NEW_PASSWORD);
ok('a spent link is still recognised as one of ours', PasswordReset::everExisted($spent));

// Shape is checked before the database, so rubbish never reaches a query.
ok('a malformed token is not "one of ours"', !PasswordReset::everExisted('not-a-token'));
ok('nor is an empty one', !PasswordReset::everExisted(''));

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
