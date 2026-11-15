<?php

declare(strict_types=1);

/**
 * Stripe subscriptions.
 *
 * Two halves. The first needs nothing: signature verification, the plan/Price
 * mapping and the field reading are pure functions and are tested as such. The
 * second needs a database, because what a subscription status DOES is a write
 * to accounts and the only honest way to check a write is to read it back.
 *
 * Nothing here talks to Stripe. Every fixture below is a Stripe payload shape,
 * typed out, which is the point: the failures this feature can have are all in
 * what we do with a body, not in whether cURL works.
 *
 *   PM_TEST_DB_HOST=127.0.0.1 PM_TEST_DB_PORT=3399 \
 *       PM_TEST_DB_NAME=pmtest php tests/billing-test.php
 *
 * The database half skips cleanly when no database is configured; the first
 * half always runs.
 */

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

use App\Support\Billing;
use App\Support\Config;
use App\Support\Database;
use App\Support\Plans;

/**
 * Deliberately not UTC. The live host runs PHP on Phoenix and MySQL on UTC,
 * seven hours apart, and a renewal date computed on the wrong one of those is
 * a subscription that renews in the past. Running skewed is what notices.
 */
date_default_timezone_set('America/Phoenix');

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

const PRO_PRICE     = 'price_test_pro_19';
const PREMIUM_PRICE = 'price_test_premium_49';

/** @param array<string,mixed> $extra */
function configure(array $extra = []): void
{
    Config::load(array_merge([
        'app_url' => 'https://promomonster.test',
        'stripe'  => [
            'secret_key'     => 'sk_test_abc123',
            'webhook_secret' => 'whsec_shhh',
            'prices'         => ['pro' => PRO_PRICE, 'premium' => PREMIUM_PRICE],
        ],
    ], $extra));
    Billing::forgetColumns();
}

configure();

// =====================================================================
// Webhook signatures
// =====================================================================
//
// This is the whole authorisation on /webhooks/stripe. There is no CSRF token
// and no secret in the path, so every one of these cases is the difference
// between "only Stripe can change a plan" and "anyone can".

$body   = '{"id":"evt_1","type":"customer.subscription.updated"}';
$secret = 'whsec_shhh';
$now    = 1767225600;

/** Builds the header Stripe would send. */
function signature(string $body, string $secret, int $t): string
{
    return 't=' . $t . ',v1=' . hash_hmac('sha256', $t . '.' . $body, $secret);
}

check('a genuine signature verifies',
    Billing::verify($body, signature($body, $secret, $now), $secret, $now), null);

check('the wrong secret is refused',
    Billing::verify($body, signature($body, 'whsec_other', $now), $secret, $now),
    'signature does not match');

// The one thing a signature over the body alone could not stop. Without the
// timestamp in the signed payload, a body captured once is replayable forever.
check('a replay from an hour ago is refused',
    Billing::verify($body, signature($body, $secret, $now - 3600), $secret, $now),
    'signature is outside the ' . Billing::TOLERANCE . ' second window');

check('a timestamp from the future is refused',
    Billing::verify($body, signature($body, $secret, $now + 3600), $secret, $now),
    'signature is outside the ' . Billing::TOLERANCE . ' second window');

ok('a timestamp just inside the window is accepted',
    Billing::verify($body, signature($body, $secret, $now - (Billing::TOLERANCE - 1)), $secret, $now) === null);

// A body changed by one byte, re-signed by nobody. This is the forgery the
// endpoint actually has to survive: the header is a real one, lifted from a
// real delivery, and the payload underneath it has been edited.
$tampered = '{"id":"evt_1","type":"customer.subscription.deleted"}';
check('a tampered body with a real header is refused',
    Billing::verify($tampered, signature($body, $secret, $now), $secret, $now),
    'signature does not match');

// Rotation. Stripe signs one body with the old and the new secret during a
// roll, and sends both; either matching has to be enough or the roll is an
// outage.
$rolled = 't=' . $now
    . ',v1=' . hash_hmac('sha256', $now . '.' . $body, 'whsec_previous')
    . ',v1=' . hash_hmac('sha256', $now . '.' . $body, $secret);
ok('either signature of a rotating pair is enough',
    Billing::verify($body, $rolled, $secret, $now) === null);

// An unset secret must close the door, not open it. The email webhook had
// exactly this hole described in its own comment, and it is the same mistake.
check('no configured secret refuses everything',
    Billing::verify($body, signature($body, '', $now), '', $now),
    'no signing secret is configured');

check('a missing header is refused', Billing::verify($body, null, $secret, $now), 'no signature header');
check('an empty header is refused',  Billing::verify($body, '   ', $secret, $now), 'no signature header');
check('a header with no timestamp is refused',
    Billing::verify($body, 'v1=deadbeef', $secret, $now), 'no timestamp in signature');
check('a header with no v1 is refused',
    Billing::verify($body, 't=' . $now, $secret, $now), 'no v1 signature');
check('a non-numeric timestamp is refused',
    Billing::verify($body, 't=yesterday,v1=deadbeef', $secret, $now), 'no timestamp in signature');
check('rubbish is refused',
    Billing::verify($body, 'nonsense', $secret, $now), 'no timestamp in signature');

// An empty body is still a body: it must verify if correctly signed and fail
// if not, rather than taking a shortcut on either side.
ok('an empty body signs and verifies', Billing::verify('', signature('', $secret, $now), $secret, $now) === null);
check('an empty body with the wrong signature is refused',
    Billing::verify('', 't=' . $now . ',v1=' . str_repeat('0', 64), $secret, $now),
    'signature does not match');

// =====================================================================
// Which plan is being paid for
// =====================================================================

check('the $19 Price is Pro',     Billing::planForPrice(PRO_PRICE), Plans::PRO);
check('the $49 Price is Premium', Billing::planForPrice(PREMIUM_PRICE), Plans::PREMIUM);
check('an unknown Price is nobody', Billing::planForPrice('price_someone_elses'), null);
check('an empty Price is nobody',   Billing::planForPrice(''), null);

// The reverse lookup must never answer Free or Partner, whatever is in config:
// Free is not bought and Partner is arranged by hand, so a Price mapping to
// either would be a subscription nobody can explain.
configure(['stripe' => [
    'secret_key' => 'sk_test_abc123',
    'webhook_secret' => 'whsec_shhh',
    'prices' => ['free' => 'price_free', 'partner' => 'price_partner', 'pro' => PRO_PRICE],
]]);
check('a Price on Free is ignored',    Billing::planForPrice('price_free'), null);
check('a Price on Partner is ignored', Billing::planForPrice('price_partner'), null);
configure();

// =====================================================================
// Whether a card can be taken
// =====================================================================

ok('Pro is configured',     Billing::configured(Plans::PRO));
ok('Premium is configured', Billing::configured(Plans::PREMIUM));
ok('Free can never be charged',    !Billing::configured(Plans::FREE));
ok('Partner can never be charged', !Billing::configured(Plans::PARTNER));
ok('a test key is recognised',      Billing::testMode());

// Configured is not the same as chargeable, and this is the half of that which
// matters: with no database to read, the schema cannot be confirmed, so no
// button is offered. Taking $19 for a subscription we would have forgotten by
// the next page load is worse than not offering to take it.
ok('an unreadable database takes no money', !Billing::canCharge(Plans::PRO));
ok('and nothing is live',                   !Billing::live());

// Pro live while Premium is still arranged by hand. The whole point of keying
// off the Price rather than a single on/off flag.
configure(['stripe' => [
    'secret_key' => 'sk_live_real', 'webhook_secret' => 'whsec_shhh',
    'prices' => ['pro' => PRO_PRICE, 'premium' => ''],
]]);
ok('Pro alone can be configured',    Billing::configured(Plans::PRO));
ok('Premium without a Price cannot', !Billing::configured(Plans::PREMIUM));
ok('a live key is not test mode',    !Billing::testMode());

// No key at all: the feature is off, and nothing may claim otherwise.
configure(['stripe' => ['secret_key' => '', 'webhook_secret' => '', 'prices' => ['pro' => PRO_PRICE]]]);
ok('no key means nothing can be charged', !Billing::configured(Plans::PRO));
ok('no key means not live',               !Billing::live());
ok('no key says so plainly',  str_contains(Billing::status(), 'No Stripe secret key'));

// A key with no webhook secret still takes a first payment, and will never
// hear about the cancellation. Pro is configured; whether status() says so is
// asserted in the database half, because with no schema to read status() has a
// more urgent thing to report.
configure(['stripe' => [
    'secret_key' => 'sk_live_real', 'webhook_secret' => '',
    'prices' => ['pro' => PRO_PRICE, 'premium' => PREMIUM_PRICE],
]]);
ok('Pro is configured without a webhook secret', Billing::configured(Plans::PRO));
ok('and an unreadable schema is the louder problem',
    str_contains(Billing::status(), 'migration'));
configure();

// A missing key must stop a call before it is made, not be rejected by Stripe.
configure(['stripe' => ['secret_key' => '', 'webhook_secret' => '', 'prices' => []]]);
$attempt = Billing::checkout(['id' => 1, 'name' => 'Acme'], Plans::PRO, 'mike@acme.test');
check('checkout with no key goes nowhere', $attempt['url'], null);
ok('and says why', is_string($attempt['error']) && $attempt['error'] !== '');
$attempt = Billing::portal(['id' => 1, 'stripe_customer_id' => 'cus_1']);
check('the portal with no key goes nowhere', $attempt['url'], null);
configure();

// An account with no customer on file has no portal to send them to, and must
// not invent one.
$attempt = Billing::portal(['id' => 1]);
check('the portal needs a customer', $attempt['url'], null);
ok('and says there is nothing to manage',
    str_contains((string) $attempt['error'], 'no billing account'));

// =====================================================================
// The downgrade that must not happen here
// =====================================================================
//
// accounts.plan is ours to write; the charge is Stripe's. Writing 'free' on
// this side while Stripe is still collecting would put a business on the Free
// allowance with $19 a month still leaving their account -- and they would have
// pressed the button that did it. This is the guard, and
// MembersController::requestPlan() refuses on it.

ok('an active subscription is live',
    Billing::hasLiveSubscription(['stripe_subscription_id' => 'sub_1', 'stripe_status' => 'active']));
ok('a trial is live',
    Billing::hasLiveSubscription(['stripe_subscription_id' => 'sub_1', 'stripe_status' => 'trialing']));
// Still being retried. A card that failed once is not a cancelled subscription.
ok('past_due is still live',
    Billing::hasLiveSubscription(['stripe_subscription_id' => 'sub_1', 'stripe_status' => 'past_due']));
ok('unpaid is still live',
    Billing::hasLiveSubscription(['stripe_subscription_id' => 'sub_1', 'stripe_status' => 'unpaid']));
ok('a cancelled subscription is not live',
    !Billing::hasLiveSubscription(['stripe_subscription_id' => 'sub_1', 'stripe_status' => 'canceled']));
ok('a paused subscription is not live',
    !Billing::hasLiveSubscription(['stripe_subscription_id' => 'sub_1', 'stripe_status' => 'paused']));
ok('no subscription id is not live',
    !Billing::hasLiveSubscription(['stripe_status' => 'active']));
ok('an empty subscription id is not live',
    !Billing::hasLiveSubscription(['stripe_subscription_id' => '  ', 'stripe_status' => 'active']));
ok('an account that has never paid is not live', !Billing::hasLiveSubscription([]));

// Keyed off the row, not off config. A secret key deleted from config.php does
// not stop a subscription; it only stops us hearing about it -- so the
// downgrade must still be refused.
configure(['stripe' => ['secret_key' => '', 'webhook_secret' => '', 'prices' => []]]);
ok('pulling the keys does not make a subscription safe to drop',
    Billing::hasLiveSubscription(['stripe_subscription_id' => 'sub_1', 'stripe_status' => 'active']));
// And the portal says so honestly rather than claiming there is nothing there.
$attempt = Billing::portal(['id' => 1, 'stripe_customer_id' => 'cus_1']);
ok('and the portal does not claim the subscription is gone',
    str_contains((string) $attempt['error'], 'unaffected'));
configure();

// A return trip with no session id does nothing at all -- in particular it
// does not reach the network, which is what makes this assertable here.
$finish = Billing::finish(['id' => 1], '');
check('an empty session grants nothing', $finish['plan'], null);
check('and is not an error',             $finish['error'], null);

// =====================================================================
// Reading a Stripe payload
// =====================================================================

check('a plain reference is an id',    Billing::idOf('cus_123'), 'cus_123');
check('an expanded object is an id',   Billing::idOf(['id' => 'cus_123', 'email' => 'a@b.c']), 'cus_123');
check('null is not an id',             Billing::idOf(null), '');
check('a number is not an id',         Billing::idOf(42), '');
check('an object with no id is not one', Billing::idOf(['email' => 'a@b.c']), '');

$subscription = [
    'id'       => 'sub_123',
    'status'   => 'active',
    'customer' => 'cus_123',
    'items'    => ['data' => [['price' => ['id' => PRO_PRICE]]]],
];
check('the Price comes off the first item', Billing::priceOf($subscription), PRO_PRICE);
check('an unexpanded Price still reads',
    Billing::priceOf(['items' => ['data' => [['price' => PRO_PRICE]]]]), PRO_PRICE);
// Very old payloads call it `plan`.
check('a legacy plan field still reads',
    Billing::priceOf(['items' => ['data' => [['plan' => PRO_PRICE]]]]), PRO_PRICE);
check('no items means no Price', Billing::priceOf(['id' => 'sub_1']), '');

// current_period_end sat on the subscription for years and then moved onto the
// items. Both shapes are in the wild depending on an account's API version,
// which is why nothing here pins a version.
$when = 1767225600; // 2026-01-01 00:00:00 UTC
check('a subscription-level period end reads',
    Billing::renewalFrom(['current_period_end' => $when]), '2026-01-01 00:00:00');
check('an item-level period end reads',
    Billing::renewalFrom(['items' => ['data' => [['current_period_end' => $when]]]]),
    '2026-01-01 00:00:00');
check('a numeric string reads',
    Billing::renewalFrom(['current_period_end' => (string) $when]), '2026-01-01 00:00:00');
check('a missing period end is null', Billing::renewalFrom(['status' => 'active']), null);
check('a null period end is null',
    Billing::renewalFrom(['current_period_end' => null]), null);
check('rubbish is null',
    Billing::renewalFrom(['current_period_end' => 'next Tuesday']), null);

// The assertion that makes the one above worth making. PHP is on Phoenix here,
// the database is on UTC, and a renewal written in local time is seven hours
// wrong in the direction that matters: it renews before it should.
ok('the renewal is UTC, not local time',
    Billing::renewalFrom(['current_period_end' => $when]) !== date('Y-m-d H:i:s', $when));
check('and it is exactly what the database would call now',
    Billing::renewalFrom(['current_period_end' => $when]), gmdate('Y-m-d H:i:s', $when));

// =====================================================================
// Migration 025 agrees with the code
// =====================================================================
//
// The templates suite learned this the hard way: a drift test that cuts the
// file in the wrong place passes while the two halves disagree. So this reads
// the column names out of the SQL and asserts each one is actually there.

$sql025 = (string) file_get_contents(dirname(__DIR__) . '/database/migrations/025_stripe_subscriptions.sql');
ok('025 exists', $sql025 !== '');

foreach (['stripe_subscription_id', 'stripe_price_id', 'stripe_status'] as $column) {
    ok("025 adds accounts.{$column}",
        str_contains($sql025, 'ADD COLUMN ' . $column . ' '));
    // The guard is what makes it safe to paste twice, which is how it will be
    // applied: by hand, into phpMyAdmin, possibly twice.
    ok("025 guards accounts.{$column}",
        str_contains($sql025, "COLUMN_NAME = '" . $column . "'"));
}
ok('025 creates the event table', str_contains($sql025, 'CREATE TABLE IF NOT EXISTS stripe_events'));

// phpMyAdmin pastes a file as one blob. A quoted string broken across lines
// comes back with the newline inside it, and the statement fails on the
// server and nowhere else.
foreach (explode("\n", $sql025) as $i => $line) {
    if (str_starts_with(trim($line), '--')) {
        continue;
    }
    ok('025 line ' . ($i + 1) . ' has balanced quotes',
        substr_count($line, "'") % 2 === 0);
}
check('025 is ASCII only', preg_match('/[^\x00-\x7F]/', $sql025), 0);

// =====================================================================
// What a status DOES -- against a real accounts table
// =====================================================================

$host = getenv('PM_TEST_DB_HOST') ?: '';
$name = getenv('PM_TEST_DB_NAME') ?: '';

if ($name === '' || $host === '') {
    echo "\n  (skipping the database half: set PM_TEST_DB_HOST and PM_TEST_DB_NAME)\n";
    echo "\n{$passed} passed, {$failed} failed\n";
    exit($failed === 0 ? 0 : 1);
}

configure(['db' => [
    'host'     => $host,
    'port'     => (int) (getenv('PM_TEST_DB_PORT') ?: 3306),
    'database' => $name,
    'username' => getenv('PM_TEST_DB_USER') ?: 'root',
    'password' => getenv('PM_TEST_DB_PASS') ?: '',
    'charset'  => 'utf8mb4',
]]);

// Apply 025 the way it will actually be applied -- split by the real migration
// runner, statement by statement -- and then again, to prove the guards hold.
//
// Migrator::statements() rather than explode(';'), because a semicolon inside
// a comment or a quoted string splits a statement in half. The naive version
// of this function broke on the word "plan; this" in 025's own header.
function apply025(string $sql): void
{
    foreach (App\Support\Migrator::statements($sql) as $statement) {
        Database::run($statement);
    }
}

apply025($sql025);
apply025($sql025);
Billing::forgetColumns();
ok('025 is safe to paste twice', true);
check('and the database is no longer missing anything', Billing::missing(), []);
// The other half of the fail-closed assertion above: with the schema readable,
// the button appears.
ok('Pro can be charged once 025 is applied', Billing::canCharge(Plans::PRO));
ok('and something is live',                  Billing::live());
ok('a connected Stripe says so', str_contains(Billing::status(), 'connected'));
ok('and names both plans', str_contains(Billing::status(), 'Pro and Premium can be bought'));

// Pro live while Premium is still a request: the message has to lead with what
// works, because that half-configured state is the normal one for a while and
// a message that led with the gap would read as "broken".
$bothPrices = Config::get('stripe');
configure(['db' => Config::get('db'), 'stripe' => [
    'secret_key' => 'sk_live_real', 'webhook_secret' => 'whsec_shhh',
    'prices' => ['pro' => PRO_PRICE, 'premium' => ''],
]]);
$partial = Billing::status();
ok('a partial setup leads with Pro',          str_contains($partial, 'Pro can be bought'));
ok('and names Premium as still a request',    str_contains($partial, 'Premium has no Price'));
ok('Pro is chargeable',                       Billing::canCharge(Plans::PRO));
ok('Premium is not',                          !Billing::canCharge(Plans::PREMIUM));
configure(['db' => Config::get('db'), 'stripe' => $bothPrices]);

// Now that the schema is readable, the missing webhook secret is the thing
// worth saying. It has to be said out loud: the product looks entirely fine in
// that state, right up to the first month billed after a cancellation.
$withSecret = Config::get('stripe');
configure(['db' => Config::get('db'), 'stripe' => [
    'secret_key' => 'sk_live_real', 'webhook_secret' => '',
    'prices' => ['pro' => PRO_PRICE, 'premium' => PREMIUM_PRICE],
]]);
ok('a missing webhook secret is reported', str_contains(Billing::status(), 'webhook'));
ok('and Pro is still chargeable without it', Billing::canCharge(Plans::PRO));
configure(['db' => Config::get('db'), 'stripe' => $withSecret]);

function seedAccount(string $plan = 'free', ?string $customer = 'cus_acme'): int
{
    Database::run('DELETE FROM accounts WHERE id = 90');
    Database::run(
        "INSERT INTO accounts (id, name, plan, status, stripe_customer_id, requested_plan, requested_plan_at)
         VALUES (90, 'Acme Pools', :plan, 'active', :cus, 'pro', NOW())",
        ['plan' => $plan, 'cus' => $customer],
    );

    return 90;
}

/** @return array<string,mixed> */
function account(int $id = 90): array
{
    return Database::first('SELECT * FROM accounts WHERE id = :id', ['id' => $id]) ?? [];
}

/** @return array<string,mixed> */
function subscription(string $status, string $price = PRO_PRICE, ?int $end = 1767225600): array
{
    $sub = [
        'id'       => 'sub_acme',
        'status'   => $status,
        'customer' => 'cus_acme',
        'items'    => ['data' => [['price' => ['id' => $price]]]],
    ];
    if ($end !== null) {
        $sub['current_period_end'] = $end;
    }

    return $sub;
}

// --- The thing the user actually asked for -------------------------------
$id = seedAccount();
check('an active $19 subscription grants Pro',
    Billing::applySubscription($id, subscription('active')), Plans::PRO);
$a = account();
check('and the column says so',          $a['plan'], Plans::PRO);
check('the account is active',           $a['status'], 'active');
check('the subscription id is recorded', $a['stripe_subscription_id'], 'sub_acme');
check('the Price is recorded',           $a['stripe_price_id'], PRO_PRICE);
check('Stripe\'s own word is recorded',  $a['stripe_status'], 'active');
check('the renewal is stored in UTC',    $a['subscription_renews_at'], '2026-01-01 00:00:00');
// Stripe has now done by card what superadmin was queued up to do by hand.
// Leaving the request would mean somebody sets the same plan up twice.
check('the hand-run upgrade request is cleared', $a['requested_plan'], null);
check('and its timestamp with it',               $a['requested_plan_at'], null);
ok('the change is dated',                        $a['plan_changed_at'] !== null);

// Running the same event again is the normal case, not an error: Stripe retries
// for three days and the return from checkout handles the same payment.
check('applying the same subscription twice is the same answer',
    Billing::applySubscription($id, subscription('active')), Plans::PRO);
check('and leaves the same plan', account()['plan'], Plans::PRO);

// --- A trial is a paid plan ---------------------------------------------
$id = seedAccount();
check('a trialing subscription grants the plan',
    Billing::applySubscription($id, subscription('trialing')), Plans::PRO);
check('and the trial status is visible', account()['stripe_status'], 'trialing');

// --- Premium -------------------------------------------------------------
$id = seedAccount();
check('the $49 Price grants Premium',
    Billing::applySubscription($id, subscription('active', PREMIUM_PRICE)), Plans::PREMIUM);
check('and the column says so', account()['plan'], Plans::PREMIUM);

// --- A Price we cannot name ---------------------------------------------
// We have been paid and cannot say for what. Record it, change nothing:
// guessing would either give away Premium or take Pro off somebody paying.
$id = seedAccount(Plans::PRO);
check('an unknown Price grants nothing',
    Billing::applySubscription($id, subscription('active', 'price_someone_elses')), null);
$a = account();
check('the plan is untouched',    $a['plan'], Plans::PRO);
check('but the Price is recorded so it can be looked up',
    $a['stripe_price_id'], 'price_someone_elses');

// --- A failed renewal ----------------------------------------------------
// Marked, never downgraded. Stripe retries for weeks; taking the plan away on
// the first failure would stop a business's review requests over a card that
// expired on a Tuesday.
$id = seedAccount(Plans::PRO);
check('past_due grants no new plan',
    Billing::applySubscription($id, subscription('past_due')), null);
$a = account();
check('the plan survives a failed payment', $a['plan'], Plans::PRO);
check('and the account is flagged',         $a['status'], 'past_due');

$id = seedAccount(Plans::PRO);
Billing::applySubscription($id, subscription('unpaid'));
check('unpaid also keeps the plan', account()['plan'], Plans::PRO);
check('and also flags the account', account()['status'], 'past_due');

// --- Cancelling ----------------------------------------------------------
$id = seedAccount(Plans::PRO);
check('a cancelled subscription drops to Free',
    Billing::applySubscription($id, subscription('canceled')), Plans::FREE);
$a = account();
check('the plan is Free',  $a['plan'], Plans::FREE);
// Not 'cancelled'. The subscription is cancelled; the account is not. They
// keep their login, their reviews and the Free allowance.
check('the account is still active', $a['status'], 'active');
check('and there is no renewal date left', $a['subscription_renews_at'], null);

$id = seedAccount(Plans::PRO);
Billing::applySubscription($id, subscription('incomplete_expired'));
check('an expired incomplete also drops to Free', account()['plan'], Plans::FREE);

$id = seedAccount(Plans::PRO);
Billing::applySubscription($id, subscription('paused'));
check('a paused subscription drops to Free', account()['plan'], Plans::FREE);
check('and keeps the account active',       account()['status'], 'active');

// --- Checkout finished, payment did not ---------------------------------
$id = seedAccount();
check('an incomplete subscription grants nothing',
    Billing::applySubscription($id, subscription('incomplete')), null);
$a = account();
check('the plan stays Free',  $a['plan'], Plans::FREE);
check('but it is on record',  $a['stripe_status'], 'incomplete');
// The queue is only cleared when a plan actually moves, so this is still
// waiting for superadmin -- correctly, because nobody has paid.
check('and the hand-run request is still there', $a['requested_plan'], 'pro');

// --- Events arriving twice ----------------------------------------------
Database::run("DELETE FROM stripe_events WHERE id LIKE 'evt_test_%'");
ok('an event is new the first time',
    Billing::firstSighting('evt_test_1', 'customer.subscription.updated'));
ok('and not the second',
    !Billing::firstSighting('evt_test_1', 'customer.subscription.updated'));
ok('a different event is still new',
    Billing::firstSighting('evt_test_2', 'customer.subscription.updated'));
// An event with no id cannot be deduplicated, so it must be handled rather
// than dropped: every branch is safe twice, and dropping a cancellation is not.
ok('an event with no id is handled', Billing::firstSighting('', 'whatever'));

// =====================================================================
// Whole events
// =====================================================================

$id = seedAccount();
check('a subscription event finds the account by its customer id',
    Billing::handle([
        'id'   => 'evt_x',
        'type' => 'customer.subscription.updated',
        'data' => ['object' => subscription('active')],
    ]),
    'account 90 -> ' . Plans::PRO);
check('and grants the plan', account()['plan'], Plans::PRO);

// A delete event's body can still read "active" in the status field. The event
// type is the authority there, not the status.
$id = seedAccount(Plans::PRO);
Billing::handle([
    'id'   => 'evt_y',
    'type' => 'customer.subscription.deleted',
    'data' => ['object' => subscription('active')],
]);
check('a deleted event cancels whatever its body says', account()['plan'], Plans::FREE);

// A failed invoice marks and does not downgrade.
$id = seedAccount(Plans::PRO);
Billing::handle([
    'id'   => 'evt_z',
    'type' => 'invoice.payment_failed',
    'data' => ['object' => ['customer' => 'cus_acme', 'id' => 'in_1']],
]);
$a = account();
check('a failed invoice flags the account', $a['status'], 'past_due');
check('and does not take the plan away',    $a['plan'], Plans::PRO);

// An event about somebody we do not have must change nothing and say so,
// rather than falling through to a default account.
$id = seedAccount(Plans::FREE);
$note = Billing::handle([
    'id'   => 'evt_w',
    'type' => 'customer.subscription.updated',
    'data' => ['object' => [
        'id' => 'sub_other', 'status' => 'active', 'customer' => 'cus_nobody',
        'items' => ['data' => [['price' => ['id' => PRO_PRICE]]]],
    ]],
]);
ok('an unknown customer changes nothing', str_contains($note, 'do not have'));
check('and no account is upgraded', account()['plan'], Plans::FREE);

// A forged account_id in metadata, for an account that does not exist. (A
// forged event cannot get past verify() in the first place; this is the second
// lock, checked anyway.)
$note = Billing::handle([
    'id'   => 'evt_v',
    'type' => 'customer.subscription.updated',
    'data' => ['object' => [
        'id' => 'sub_other', 'status' => 'active', 'customer' => 'cus_nobody',
        'metadata' => ['account_id' => '999999'],
        'items' => ['data' => [['price' => ['id' => PRO_PRICE]]]],
    ]],
]);
ok('a claimed account that does not exist is refused', str_contains($note, 'do not have'));

// Metadata is the fallback when the customer mapping is missing -- but only to
// an account that really exists.
$id = seedAccount(Plans::FREE, null);
Billing::handle([
    'id'   => 'evt_u',
    'type' => 'customer.subscription.updated',
    'data' => ['object' => [
        'id' => 'sub_meta', 'status' => 'active', 'customer' => 'cus_unmapped',
        'metadata' => ['account_id' => '90'],
        'current_period_end' => 1767225600,
        'items' => ['data' => [['price' => ['id' => PRO_PRICE]]]],
    ]],
]);
$a = account();
check('metadata finds an account with no customer on file', $a['plan'], Plans::PRO);
// And the mapping is repaired on the way through, so the next event does not
// need the fallback.
check('and the customer mapping is written', $a['stripe_customer_id'], 'cus_unmapped');

// Anything we do not act on is ignored out loud, and acted on by nothing.
$id = seedAccount(Plans::FREE);
$note = Billing::handle(['id' => 'evt_t', 'type' => 'charge.succeeded', 'data' => ['object' => []]]);
ok('an event we do not handle is ignored', str_contains($note, 'ignored charge.succeeded'));
check('and changes nothing', account()['plan'], Plans::FREE);

$note = Billing::handle(['id' => 'evt_s']);
ok('an event with no type at all is ignored', str_contains($note, 'ignored'));

// A checkout session with no subscription on it is not a subscription.
$note = Billing::handle([
    'id'   => 'evt_r',
    'type' => 'checkout.session.completed',
    'data' => ['object' => ['client_reference_id' => '90', 'customer' => 'cus_acme']],
]);
ok('a session with no subscription grants nothing', str_contains($note, 'no subscription'));
check('and the plan is untouched', account()['plan'], Plans::FREE);

Database::run('DELETE FROM accounts WHERE id = 90');
Database::run("DELETE FROM stripe_events WHERE id LIKE 'evt_test_%'");

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
