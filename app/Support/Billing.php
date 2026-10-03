<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Stripe subscriptions, by hand.
 *
 * Called Billing rather than Stripe because Stripe is one processor and this is
 * the one concept the rest of the app should know about: "can this account pay,
 * and what has it paid for". Nothing outside this file and BillingController
 * mentions Stripe at all.
 *
 * No SDK. There is no Composer on this host and no build step, so the official
 * library is not an option -- and it would not earn its keep here either. This
 * uses four endpoints and verifies one signature. The whole surface is below.
 *
 * Stripe's API is form-encoded, not JSON, which is the one way it differs from
 * Mailer::post(). http_build_query() produces exactly the bracket syntax Stripe
 * wants for nested parameters (line_items[0][price]=...), so there is nothing
 * to hand-roll.
 *
 * No Stripe-Version header. Pinning to a version I cannot test against is how
 * you find out six months later that every webhook has been rejected, so this
 * rides the account's own default version and reads the few fields it needs
 * defensively -- see renewalFrom(), which handles current_period_end having
 * moved from the subscription onto its items.
 *
 * What this deliberately does NOT do: decide what an account may use. That is
 * Plans and SendLimit, keyed off accounts.plan, exactly as before. Billing only
 * ever writes that column. If Stripe is switched off tomorrow every account
 * keeps working on whatever plan it was last on.
 */
final class Billing
{
    /** Stripe's API root. Not configurable: there is no other server to talk to. */
    private const API = 'https://api.stripe.com/v1/';

    /**
     * How far out of date a webhook's own timestamp may be before we refuse it.
     *
     * Stripe's own recommendation, and the reason the signature covers a
     * timestamp at all: without this, a signed body captured once is replayable
     * forever by anybody who ever saw it.
     */
    public const TOLERANCE = 300;

    /**
     * Columns on `accounts` that arrive with migration 025, and what to read
     * instead until it has been run.
     *
     * The same guard ReviewRequests uses, for the same reason: a member opening
     * Settings on a database that is one migration behind must see a page that
     * says so, not a 500. The product has been through that twice.
     */
    private const OPTIONAL = [
        'stripe_subscription_id' => '025',
        'stripe_price_id'        => '025',
        'stripe_status'          => '025',
    ];

    /** @var array<string,bool>|null */
    private static ?array $columns = null;

    /* ---------------------------------------------------------------- setup */

    /** The secret key. sk_live_... in production, sk_test_... while trying it. */
    public static function key(): string
    {
        return trim((string) Config::get('stripe.secret_key', ''));
    }

    /** The webhook signing secret, whsec_... from the endpoint's own page. */
    public static function webhookSecret(): string
    {
        return trim((string) Config::get('stripe.webhook_secret', ''));
    }

    /** The Price id for a plan, e.g. price_1Q... for Pro. */
    public static function price(string $plan): string
    {
        return trim((string) Config::get('stripe.prices.' . $plan, ''));
    }

    /**
     * Which plan a Price belongs to, or null.
     *
     * Reversed from config rather than stored, so there is one place prices are
     * written down. A Price we do not recognise must never be guessed at: see
     * applySubscription(), which leaves the plan alone instead.
     */
    public static function planForPrice(string $priceId): ?string
    {
        if ($priceId === '') {
            return null;
        }

        foreach (Plans::PAID as $plan) {
            if (self::price($plan) === $priceId) {
                return $plan;
            }
        }

        return null;
    }

    /**
     * True when a key and a Price exist for this plan. Configuration only --
     * it says nothing about whether the result could be written down.
     */
    public static function configured(string $plan): bool
    {
        return self::key() !== ''
            && Plans::isPaid($plan)
            && self::price($plan) !== '';
    }

    /**
     * True when this account can be charged for this plan today.
     *
     * Configured AND recordable. A database one migration behind cannot store
     * which subscription was bought, and taking $19 off somebody for a plan we
     * will have forgotten by the next page load is worse than not offering the
     * button -- so this fails closed on a schema it cannot read, and the
     * settings page goes back to recording a request.
     */
    public static function canCharge(string $plan): bool
    {
        return self::configured($plan) && self::missing() === [];
    }

    /** True when any paid plan can be bought, i.e. show the card buttons. */
    public static function live(): bool
    {
        foreach (Plans::PAID as $plan) {
            if (self::canCharge($plan)) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when the keys are test keys.
     *
     * Surfaced on the settings page and in diagnose.php, because the failure
     * this prevents is silent in both directions: taking real cards on a test
     * key takes nothing, and testing against a live key takes money.
     */
    public static function testMode(): bool
    {
        return str_starts_with(self::key(), 'sk_test_');
    }

    /**
     * True when Stripe is still billing this card.
     *
     * The guard on an instant downgrade. accounts.plan is ours to write, but
     * the charge is Stripe's, and setting plan = 'free' here would leave a
     * business on the Free allowance with $19 a month still leaving their
     * account -- which is the single worst bug this feature could ship. A
     * cancellation has to happen at Stripe, and then come back as an event.
     *
     * Deliberately keyed off the subscription on the row, not off whether
     * Stripe is configured right now. A key removed from config.php does not
     * stop a subscription; it only stops us hearing about it.
     *
     * @param array<string,mixed> $account
     */
    public static function hasLiveSubscription(array $account): bool
    {
        if (trim((string) ($account['stripe_subscription_id'] ?? '')) === '') {
            return false;
        }

        // Anything Stripe would still try to collect on. 'past_due' counts:
        // the card has failed once and Stripe is still retrying it.
        return in_array(
            (string) ($account['stripe_status'] ?? ''),
            ['active', 'trialing', 'past_due', 'unpaid', 'incomplete'],
            true,
        );
    }

    /**
     * Migrations the database is missing before a subscription can be recorded.
     *
     * @return list<string>
     */
    public static function missing(): array
    {
        $missing = [];
        foreach (self::OPTIONAL as $column => $migration) {
            if (!self::hasColumn($column) && !in_array($migration, $missing, true)) {
                $missing[] = $migration;
            }
        }

        return $missing;
    }

    /**
     * One plain sentence for the diagnostics page and the settings page.
     *
     * Leads with what works, because a half-configured state is the normal one
     * for a while: Pro can be taking money while Premium is still arranged by
     * hand, and a message that led with the gap would read as "broken".
     */
    public static function status(): string
    {
        if (self::key() === '') {
            return 'No Stripe secret key is set, so plan changes stay a request we handle by hand.';
        }

        if (self::missing() !== []) {
            return 'Stripe is connected, but migration ' . implode(' and ', self::missing())
                 . ' has not been run, so a subscription cannot be recorded -- and a plan we '
                 . 'cannot record is one we must not charge for. Nothing is offered until it is '
                 . 'applied.';
        }

        $can = [];
        $cannot = [];
        foreach (Plans::PAID as $plan) {
            if (self::configured($plan)) {
                $can[] = Plans::name($plan);
            } else {
                $cannot[] = Plans::name($plan);
            }
        }

        if ($can === []) {
            return 'Stripe is connected, but no Price id is set for any plan, so nothing can be '
                 . 'bought yet.';
        }

        $out = 'Stripe is connected in ' . (self::testMode() ? 'test mode' : 'live mode')
             . ' and ' . implode(' and ', $can) . ' can be bought by card.';

        if ($cannot !== []) {
            $out .= ' ' . implode(' and ', $cannot) . ' has no Price id, so it stays a request.';
        }

        if (self::webhookSecret() === '') {
            $out .= ' There is no webhook secret: a first payment is picked up on the return from '
                  . 'checkout, and a later cancellation or failed renewal is picked up by nothing.';
        }

        return $out;
    }

    /* ------------------------------------------------------------ buying it */

    /**
     * Starts a hosted Checkout session and hands back where to send them.
     *
     * The customer is created first, in its own call, and stored before the
     * session exists. That order matters: customer.subscription.* webhooks
     * carry a customer id and nothing else we would recognise, and one of them
     * can land while the member is still looking at Stripe's payment form. If
     * the mapping were written on the way back from checkout, that webhook
     * would arrive about an account we could not name.
     *
     * @param array<string,mixed> $account
     * @return array{url:?string, error:?string}
     */
    public static function checkout(array $account, string $plan, string $email): array
    {
        if (!self::canCharge($plan)) {
            return ['url' => null, 'error' => 'Card payments are not switched on for that plan.'];
        }

        $accountId = (int) ($account['id'] ?? 0);
        if ($accountId <= 0) {
            return ['url' => null, 'error' => 'We could not tell which account to bill.'];
        }

        $customer = self::customer($account, $email);
        if ($customer['id'] === null) {
            return ['url' => null, 'error' => $customer['error']];
        }

        $base = rtrim((string) Config::get('app_url', ''), '/');

        $params = [
            'mode'                 => 'subscription',
            'customer'             => $customer['id'],
            'client_reference_id'  => (string) $accountId,
            'line_items'           => [['price' => self::price($plan), 'quantity' => 1]],
            // Stripe's own page is the only place a card number is typed, so
            // nothing here ever touches a card and no part of this codebase is
            // in PCI scope. That is the whole reason for hosted checkout.
            'success_url'          => $base . '/members/billing/return?session={CHECKOUT_SESSION_ID}',
            'cancel_url'           => $base . '/members/settings',
            // Carried so a subscription event can still find its way home if
            // the customer mapping is ever lost or rebuilt.
            'subscription_data'    => ['metadata' => ['account_id' => (string) $accountId, 'plan' => $plan]],
        ];

        [$status, $body, $error] = self::call('POST', 'checkout/sessions', $params);

        if ($error !== null) {
            return ['url' => null, 'error' => $error];
        }

        // A customer id from a different mode, or one deleted in the dashboard,
        // fails here and would fail on every retry. Forget it and go round once
        // -- the alternative is an account that can never check out again.
        if ($status === 400 && self::noSuchCustomer($body)) {
            self::forgetCustomer($accountId);
            $retry = self::customer(['id' => $accountId, 'name' => $account['name'] ?? null], $email);
            if ($retry['id'] === null) {
                return ['url' => null, 'error' => $retry['error']];
            }
            $params['customer'] = $retry['id'];
            [$status, $body, $error] = self::call('POST', 'checkout/sessions', $params);
            if ($error !== null) {
                return ['url' => null, 'error' => $error];
            }
        }

        $url = (string) ($body['url'] ?? '');
        if ($status !== 200 || $url === '') {
            return ['url' => null, 'error' => self::messageFrom($status, $body)];
        }

        return ['url' => $url, 'error' => null];
    }

    /**
     * A link to Stripe's own billing portal: card on file, invoices, cancel.
     *
     * Everything that is not "start paying" lives there on purpose. Building a
     * cancellation flow would mean building dunning, proration and invoice
     * history to go with it, and getting any of them subtly wrong is a dispute.
     *
     * @param array<string,mixed> $account
     * @return array{url:?string, error:?string}
     */
    public static function portal(array $account): array
    {
        $customerId = trim((string) ($account['stripe_customer_id'] ?? ''));

        if ($customerId === '') {
            return ['url' => null, 'error' => 'There is no billing account to manage yet.'];
        }

        // A subscription that exists with no key to reach it. Said plainly,
        // because the member cannot fix it and must not be told their
        // subscription is gone -- it is not, and it is still being charged.
        if (self::key() === '') {
            return ['url' => null, 'error' =>
                'We cannot reach Stripe from here at the moment, so we cannot open your billing '
                . 'page. Your subscription is unaffected. Email us and we will sort it.'];
        }

        $base = rtrim((string) Config::get('app_url', ''), '/');

        [$status, $body, $error] = self::call('POST', 'billing_portal/sessions', [
            'customer'   => $customerId,
            'return_url' => $base . '/members/settings',
        ]);

        if ($error !== null) {
            return ['url' => null, 'error' => $error];
        }

        $url = (string) ($body['url'] ?? '');
        if ($status !== 200 || $url === '') {
            return ['url' => null, 'error' => self::messageFrom($status, $body)];
        }

        return ['url' => $url, 'error' => null];
    }

    /**
     * Reads back a finished Checkout session, for the return trip.
     *
     * The session id arrives in a query string, which means it arrives from
     * whoever typed the URL. It is checked against the account twice -- the
     * reference we set when creating it AND the customer we have on file -- so
     * a guessed or borrowed session id cannot upgrade somebody else's account.
     *
     * @param array<string,mixed> $account
     * @return array{plan:?string, error:?string}
     */
    public static function finish(array $account, string $sessionId): array
    {
        $accountId = (int) ($account['id'] ?? 0);

        if (self::key() === '' || $sessionId === '' || $accountId <= 0) {
            return ['plan' => null, 'error' => null];
        }

        [$status, $session, $error] = self::call(
            'GET',
            'checkout/sessions/' . rawurlencode($sessionId),
            ['expand' => ['subscription']],
        );

        if ($error !== null || $status !== 200) {
            return ['plan' => null, 'error' => $error ?? self::messageFrom($status, $session)];
        }

        if ((string) ($session['client_reference_id'] ?? '') !== (string) $accountId) {
            return ['plan' => null, 'error' => null];
        }

        $onFile = trim((string) ($account['stripe_customer_id'] ?? ''));
        if ($onFile !== '' && self::idOf($session['customer'] ?? null) !== $onFile) {
            return ['plan' => null, 'error' => null];
        }

        $subscription = is_array($session['subscription'] ?? null) ? $session['subscription'] : null;
        if ($subscription === null) {
            return ['plan' => null, 'error' => null];
        }

        return ['plan' => self::applySubscription($accountId, $subscription), 'error' => null];
    }

    /* --------------------------------------------------------- the webhook */

    /**
     * Checks a Stripe-Signature header against the raw body.
     *
     * This is the whole authorisation on the webhook endpoint. There is no CSRF
     * token and no secret in the path: Stripe posts it, Stripe signs it, and a
     * body that does not carry our signature is not from Stripe. Same reasoning
     * as the unsubscribe POST, where the signed token is the authorisation.
     *
     * Returns null when the body is genuine, or a short reason when it is not.
     * The reason is for the log, never for the response: telling a caller which
     * part of its forgery failed is free help.
     */
    public static function verify(string $payload, ?string $header, string $secret, ?int $now = null): ?string
    {
        if ($secret === '') {
            return 'no signing secret is configured';
        }
        if ($header === null || trim($header) === '') {
            return 'no signature header';
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) {
                continue;
            }
            [$name, $value] = $pair;
            if ($name === 't') {
                $timestamp = ctype_digit($value) ? (int) $value : null;
            } elseif ($name === 'v1') {
                // Plural on purpose. During a secret roll Stripe signs one body
                // with both secrets and sends two v1 values.
                $signatures[] = $value;
            }
        }

        if ($timestamp === null) {
            return 'no timestamp in signature';
        }
        if ($signatures === []) {
            return 'no v1 signature';
        }

        $now ??= time();
        if (abs($now - $timestamp) > self::TOLERANCE) {
            return 'signature is outside the ' . self::TOLERANCE . ' second window';
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

        foreach ($signatures as $candidate) {
            // hash_equals, not ===. A timing-variable comparison of an HMAC is
            // the textbook way to let somebody forge one a byte at a time.
            if (hash_equals($expected, $candidate)) {
                return null;
            }
        }

        return 'signature does not match';
    }

    /**
     * Acts on one verified event. Returns a one-line note for the log.
     *
     * Every branch is safe to run twice. Stripe retries for three days on
     * anything that is not a 2xx, events can arrive out of order, and the
     * return from checkout handles the same first payment this does -- so
     * "already applied" is the normal case, not an error.
     *
     * @param array<string,mixed> $event
     */
    public static function handle(array $event): string
    {
        $type   = (string) ($event['type'] ?? '');
        $object = is_array($event['data']['object'] ?? null) ? $event['data']['object'] : [];

        switch ($type) {
            case 'checkout.session.completed':
                $accountId = self::accountFor($object, (int) ($object['client_reference_id'] ?? 0));
                if ($accountId === null) {
                    return 'checkout session for an account we do not have';
                }

                // The session carries only the subscription's id, so fetch it:
                // the status and the period end are what we actually store, and
                // guessing "they just paid, so active" would be wrong for a
                // card that needs 3-D Secure after the fact.
                $subscriptionId = self::idOf($object['subscription'] ?? null);
                if ($subscriptionId === '') {
                    return 'checkout session with no subscription';
                }

                [$status, $subscription] = self::call('GET', 'subscriptions/' . rawurlencode($subscriptionId));
                if ($status !== 200) {
                    return 'could not read subscription ' . $subscriptionId;
                }

                $plan = self::applySubscription($accountId, $subscription);

                return 'account ' . $accountId . ' -> ' . ($plan ?? 'unchanged');

            case 'customer.subscription.created':
            case 'customer.subscription.updated':
            case 'customer.subscription.deleted':
            case 'customer.subscription.paused':
            case 'customer.subscription.resumed':
                $accountId = self::accountFor($object, (int) ($object['metadata']['account_id'] ?? 0));
                if ($accountId === null) {
                    return $type . ' for an account we do not have';
                }

                // A delete event's own status can still read "active" in the
                // body Stripe sends, so the event type decides here.
                if ($type === 'customer.subscription.deleted') {
                    $object['status'] = 'canceled';
                }

                $plan = self::applySubscription($accountId, $object);

                return 'account ' . $accountId . ' -> ' . ($plan ?? 'unchanged');

            case 'invoice.payment_failed':
                $accountId = self::accountFor($object, 0);
                if ($accountId === null) {
                    return 'failed payment for an account we do not have';
                }

                // Marked, not downgraded. Stripe retries a failed renewal for
                // up to three weeks, and taking somebody's plan away on the
                // first failure would stop their review requests over a card
                // that expired on a Tuesday. The subscription event does the
                // downgrade if it finally gives up.
                self::write($accountId, ['status' => 'past_due']);

                return 'account ' . $accountId . ' marked past_due';

            default:
                return 'ignored ' . ($type === '' ? 'event with no type' : $type);
        }
    }

    /**
     * True the first time an event id is seen, false afterwards.
     *
     * The INSERT is the lock. Checking for the row and then writing it would
     * leave the window two of Stripe's own retries fit through, and the
     * duplicate key is what closes it.
     */
    public static function firstSighting(string $eventId, string $type): bool
    {
        if ($eventId === '') {
            return true;
        }

        try {
            Database::run(
                'INSERT INTO stripe_events (id, type) VALUES (:id, :type)',
                ['id' => $eventId, 'type' => $type],
            );

            return true;
        } catch (\PDOException $e) {
            // 23000 is a duplicate key: we have handled this one already.
            if (($e->errorInfo[0] ?? '') === '23000') {
                return false;
            }

            // No table yet, or anything else. Handling the event is the safer
            // failure -- every branch of handle() is idempotent, so the cost of
            // doing it twice is nothing, and the cost of dropping a
            // cancellation is billing somebody who cancelled.
            return true;
        }
    }

    /* ---------------------------------------------------------- the writing */

    /**
     * Writes one Stripe subscription onto an account. Returns the plan it ended
     * up on, or null when nothing was changed.
     *
     * The only place accounts.plan moves for a paid reason. Everything above
     * funnels through here so there is one table of status-to-plan and one set
     * of rules about what a status means.
     *
     * @param array<string,mixed> $subscription
     */
    public static function applySubscription(int $accountId, array $subscription): ?string
    {
        $state = (string) ($subscription['status'] ?? '');
        $price = self::priceOf($subscription);
        $plan  = self::planForPrice($price);

        $fields = [
            'stripe_subscription_id' => self::idOf($subscription['id'] ?? null) ?: null,
            'stripe_price_id'        => $price !== '' ? $price : null,
            'stripe_status'          => $state !== '' ? $state : null,
            'subscription_renews_at' => self::renewalFrom($subscription),
        ];

        $customerId = self::idOf($subscription['customer'] ?? null);
        if ($customerId !== '') {
            $fields['stripe_customer_id'] = $customerId;
        }

        switch ($state) {
            case 'active':
            case 'trialing':
                // An unrecognised Price is the one case where we have been paid
                // and cannot say for what. Record it and leave the plan alone:
                // inventing one would either give away Premium or take Pro off
                // somebody who is paying for it.
                if ($plan !== null) {
                    $fields['plan']   = $plan;
                    $fields['status'] = 'active';
                }
                break;

            case 'past_due':
            case 'unpaid':
                // Plan untouched. See invoice.payment_failed.
                $fields['status'] = 'past_due';
                break;

            case 'canceled':
            case 'incomplete_expired':
                $fields['plan']   = Plans::FREE;
                // Not 'cancelled': the account is not cancelled, the
                // subscription is. They keep their reviews and their login on
                // the Free allowance.
                $fields['status'] = 'active';
                $fields['subscription_renews_at'] = null;
                break;

            case 'paused':
                $fields['plan']   = Plans::FREE;
                $fields['status'] = 'active';
                break;

            case 'incomplete':
                // Checkout finished but the charge has not. Nothing granted.
                break;
        }

        self::write($accountId, $fields);

        return $fields['plan'] ?? null;
    }

    /**
     * Writes whichever of those fields this database actually has.
     *
     * @param array<string,mixed> $fields
     */
    private static function write(int $accountId, array $fields): void
    {
        $sets   = [];
        $params = ['id' => $accountId];

        foreach ($fields as $column => $value) {
            if (!self::hasColumn($column)) {
                continue;
            }
            $sets[]           = $column . ' = :' . $column;
            $params[$column]  = $value;
        }

        if ($sets === []) {
            return;
        }

        // Clearing the hand-run upgrade queue is part of the same write. A
        // request Stripe has just fulfilled must not still be sitting in
        // superadmin waiting to be set up by hand.
        if (array_key_exists('plan', $fields)) {
            $sets[] = 'requested_plan = NULL';
            $sets[] = 'requested_plan_at = NULL';
            $sets[] = 'plan_changed_at = NOW()';
        }

        Database::run(
            'UPDATE accounts SET ' . implode(', ', $sets) . ' WHERE id = :id',
            $params,
        );
    }

    /**
     * Which account an event is about.
     *
     * The customer id is the authority, because it is the one we wrote
     * ourselves before any session existed. The id out of the event body is
     * only a fallback, and it is checked against a real row rather than
     * trusted -- an attacker who could post a forged event would otherwise
     * nominate the account to upgrade. (They cannot: verify() runs first. It is
     * checked anyway.)
     *
     * @param array<string,mixed> $object
     */
    private static function accountFor(array $object, int $claimed): ?int
    {
        $customerId = self::idOf($object['customer'] ?? null);

        if ($customerId !== '') {
            $row = Database::first(
                'SELECT id FROM accounts WHERE stripe_customer_id = :c LIMIT 1',
                ['c' => $customerId],
            );
            if ($row !== null) {
                return (int) $row['id'];
            }
        }

        if ($claimed > 0) {
            $row = Database::first('SELECT id FROM accounts WHERE id = :id', ['id' => $claimed]);
            if ($row !== null) {
                return (int) $row['id'];
            }
        }

        return null;
    }

    /* ------------------------------------------------------------ customers */

    /**
     * The Stripe customer for an account, created on first need.
     *
     * @param array<string,mixed> $account
     * @return array{id:?string, error:?string}
     */
    private static function customer(array $account, string $email): array
    {
        $existing = trim((string) ($account['stripe_customer_id'] ?? ''));
        if ($existing !== '') {
            return ['id' => $existing, 'error' => null];
        }

        $accountId = (int) ($account['id'] ?? 0);

        [$status, $body, $error] = self::call('POST', 'customers', [
            'email'    => $email,
            'name'     => (string) ($account['name'] ?? ''),
            'metadata' => ['account_id' => (string) $accountId],
        ]);

        if ($error !== null) {
            return ['id' => null, 'error' => $error];
        }

        $id = self::idOf($body['id'] ?? null);
        if ($status !== 200 || $id === '') {
            return ['id' => null, 'error' => self::messageFrom($status, $body)];
        }

        // Stored before the session is created, so a webhook that arrives while
        // they are still typing a card number can be matched.
        Database::run(
            'UPDATE accounts SET stripe_customer_id = :c WHERE id = :id',
            ['c' => $id, 'id' => $accountId],
        );

        return ['id' => $id, 'error' => null];
    }

    private static function forgetCustomer(int $accountId): void
    {
        Database::run(
            'UPDATE accounts SET stripe_customer_id = NULL WHERE id = :id',
            ['id' => $accountId],
        );
    }

    /** @param array<string,mixed> $body */
    private static function noSuchCustomer(array $body): bool
    {
        return str_contains((string) ($body['error']['message'] ?? ''), 'No such customer');
    }

    /* -------------------------------------------------------------- reading */

    /**
     * When the subscription next renews, as a UTC string, or null.
     *
     * Two places to look. current_period_end sat on the subscription for years
     * and then moved onto each subscription item, and both shapes are in the
     * wild depending on an account's API version -- so this reads whichever is
     * there rather than pinning a version and hoping.
     *
     * gmdate, not date. PHP here runs on America/Phoenix and MySQL runs on UTC,
     * seven hours apart; a local string written into a DATETIME that is later
     * compared against NOW() is how a subscription renews in the past.
     *
     * @param array<string,mixed> $subscription
     */
    public static function renewalFrom(array $subscription): ?string
    {
        $end = $subscription['current_period_end'] ?? null;

        if (!is_int($end) && !(is_string($end) && ctype_digit($end))) {
            $item = $subscription['items']['data'][0] ?? null;
            $end  = is_array($item) ? ($item['current_period_end'] ?? null) : null;
        }

        if (!is_int($end) && !(is_string($end) && ctype_digit($end))) {
            return null;
        }

        return gmdate('Y-m-d H:i:s', (int) $end);
    }

    /**
     * The Price id on a subscription's first item.
     *
     * One item per subscription here, because one plan is one Price. A second
     * item would be an add-on we do not sell.
     *
     * @param array<string,mixed> $subscription
     */
    public static function priceOf(array $subscription): string
    {
        $item = $subscription['items']['data'][0] ?? null;
        if (!is_array($item)) {
            return '';
        }

        return self::idOf($item['price'] ?? null) ?: self::idOf($item['plan'] ?? null);
    }

    /**
     * An id out of a field Stripe may send expanded or not.
     *
     * Every reference in the API is either "cus_123" or {"id":"cus_123",...}
     * depending on what was expanded, and a comparison against the wrong one of
     * those silently fails rather than erroring.
     */
    public static function idOf(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_array($value) && is_string($value['id'] ?? null)) {
            return $value['id'];
        }

        return '';
    }

    /* --------------------------------------------------------------- plumbing */

    /**
     * One call to Stripe. Form-encoded, authenticated, bounded.
     *
     * @param array<string,mixed> $params
     * @return array{0:int, 1:array<string,mixed>, 2:?string}
     */
    private static function call(string $method, string $path, array $params = []): array
    {
        $key = self::key();
        if ($key === '') {
            return [0, [], 'Card payments are not switched on.'];
        }

        $url  = self::API . $path;
        $form = $params === [] ? '' : http_build_query($params);

        $ch = curl_init();
        $options = [
            CURLOPT_URL            => $method === 'GET' && $form !== '' ? $url . '?' . $form : $url,
            CURLOPT_RETURNTRANSFER => true,
            // A member is waiting on a redirect, so this cannot hang. Checkout
            // and portal sessions are cheap calls; fifteen seconds is already
            // generous and a timeout here shows them an apology, not a spinner.
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $key,
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST]       = true;
            $options[CURLOPT_POSTFIELDS] = $form;
        }

        curl_setopt_array($ch, $options);

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return [0, [], 'Could not reach Stripe: ' . $error];
        }

        $decoded = json_decode((string) $raw, true);

        return [$status, is_array($decoded) ? $decoded : [], null];
    }

    /**
     * Stripe's own words for a failure, when they are safe to repeat.
     *
     * card_declined and the rest are written for the person holding the card
     * and are better than anything we would write. An api_error is about us,
     * so it gets a generic line and the detail goes nowhere near the screen.
     *
     * @param array<string,mixed> $body
     */
    private static function messageFrom(int $status, array $body): string
    {
        $type    = (string) ($body['error']['type'] ?? '');
        $message = trim((string) ($body['error']['message'] ?? ''));

        if ($message !== '' && in_array($type, ['card_error', 'invalid_request_error'], true)) {
            return $message;
        }

        return 'Stripe could not start the payment just now (' . ($status ?: 'no response') . '). '
             . 'Nothing has been charged. Please try again in a minute.';
    }

    /* ------------------------------------------------------ column guarding */

    private static function hasColumn(string $column): bool
    {
        if (!array_key_exists($column, self::OPTIONAL)) {
            return true;
        }

        return in_array($column, self::columns(), true);
    }

    /** @return list<string> */
    private static function columns(): array
    {
        if (self::$columns !== null) {
            return self::$columns;
        }

        try {
            $rows = Database::all(
                "SELECT COLUMN_NAME AS c FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accounts'",
            );
            self::$columns = array_map(static fn (array $r): string => (string) $r['c'], $rows);
        } catch (\Throwable) {
            self::$columns = [];
        }

        return self::$columns;
    }

    /** Tests only: drop the memoised column list. */
    public static function forgetColumns(): void
    {
        self::$columns = null;
    }
}
