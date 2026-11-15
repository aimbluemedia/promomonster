<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Audit;
use App\Support\Auth;
use App\Support\Billing;
use App\Support\Csrf;
use App\Support\Plans;
use App\Support\Request;

/**
 * Paying for a plan.
 *
 * Three member actions and one webhook. No auth in the constructor, because the
 * webhook has no session and never will: requireMember() there would redirect
 * Stripe to the login form and then retry for three days. Each member action
 * asks for itself instead.
 */
final class BillingController
{
    /**
     * Starts checkout for a paid plan.
     *
     * Deliberately a POST with a CSRF token, even though it only redirects. A
     * GET that creates a Stripe customer and a session is one an image tag on
     * another site can fire.
     */
    public function start(): void
    {
        Auth::requireMember();

        $account = Auth::account() ?? [];
        $user    = Auth::user() ?? [];

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $this->back('Your session expired. Please try again.');
        }

        $plan = (string) ($_POST['plan'] ?? '');
        if (!Plans::isPaid($plan)) {
            $this->back('That is not a plan you can subscribe to.');
        }

        $current = (string) ($account['plan'] ?? Plans::FREE);

        // Partner is arranged directly and is not on the ladder. Letting one
        // buy Pro here would replace a reseller agreement with a $19 charge.
        if (!in_array($current, Plans::SELECTABLE, true)) {
            $this->back(
                'Your account is on ' . Plans::name($current) . ', which we arrange directly. '
                . 'Email us and a person will sort any change.'
            );
        }

        // Already subscribed: the portal is where a plan is swapped, so Stripe
        // handles the proration rather than us charging twice for one month.
        if (Plans::isPaid($current) && trim((string) ($account['stripe_customer_id'] ?? '')) !== '') {
            $this->manage();
        }

        if (!Billing::canCharge($plan)) {
            $this->back(Billing::status());
        }

        $email = trim((string) ($user['email'] ?? ''));
        if ($email === '') {
            $this->back('We need an email address on your login before we can bill you.');
        }

        $result = Billing::checkout($account, $plan, $email);

        if ($result['url'] === null) {
            $this->back((string) $result['error']);
        }

        Audit::log('account.checkout_started', 'account', (int) ($account['id'] ?? 0),
            ['plan' => $current], ['plan' => $plan]);

        // Nothing is written about the plan here. The member has not paid yet,
        // and an account marked Pro on the way to a payment form they abandon
        // is one that got Pro for free.
        Request::redirect($result['url']);
    }

    /** Hands them to Stripe's billing portal: card, invoices, cancel. */
    public function manage(): void
    {
        Auth::requireMember();

        $account = Auth::account() ?? [];

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $this->back('Your session expired. Please try again.');
        }

        $result = Billing::portal($account);

        if ($result['url'] === null) {
            $this->back((string) $result['error']);
        }

        Request::redirect($result['url']);
    }

    /**
     * The return trip from a finished checkout.
     *
     * Reads the session back from Stripe and applies it, rather than waiting
     * for the webhook. Two reasons: the webhook may not be configured at all
     * yet, and even when it is, it races the browser -- a member who paid and
     * lands on a page still saying "Free" assumes it failed and pays again.
     *
     * finish() is what makes that safe. The session id is in a query string, so
     * it is checked against both the reference we set when creating it and the
     * customer we have on file; a borrowed id upgrades nothing.
     */
    public function finish(): void
    {
        Auth::requireMember();

        $account = Auth::account() ?? [];
        $result  = Billing::finish($account, (string) ($_GET['session'] ?? ''));

        if ($result['error'] !== null) {
            $this->back(
                'Your payment went through, but we could not read it back just now. '
                . 'Give it a minute and reload -- nothing is lost, and nothing will be charged twice.'
            );
        }

        if ($result['plan'] === null) {
            $this->back(
                'Thanks. Stripe has not confirmed the payment yet, which usually takes a few '
                . 'seconds. Reload this page and your plan will be here.'
            );
        }

        Audit::log('account.subscribed', 'account', (int) ($account['id'] ?? 0),
            ['plan' => $account['plan'] ?? null], ['plan' => $result['plan']]);

        $this->back(
            'You are on ' . Plans::name($result['plan']) . '. Your receipt is on its way from '
            . 'Stripe, and you can change or cancel any time from this page.'
        );
    }

    /**
     * Stripe posting an event.
     *
     * The signature is the authorisation. There is no CSRF token and no secret
     * in the path: only Stripe can sign a body with the endpoint's secret, so a
     * body that carries our signature is from Stripe and a body that does not
     * is from somebody else. Same reasoning as the signed unsubscribe link.
     *
     * Always 200 once the signature checks out, whatever we then decide about
     * the event. Stripe retries a non-2xx for three days, and retrying an event
     * we have deliberately ignored helps nobody.
     */
    public function webhook(): void
    {
        header('Content-Type: text/plain; charset=utf-8');

        // Read before anything else touches it. The signature covers the exact
        // bytes, so a re-encoded body verifies against nothing.
        $raw    = file_get_contents('php://input') ?: '';
        $header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? null;

        $why = Billing::verify($raw, is_string($header) ? $header : null, Billing::webhookSecret());

        if ($why !== null) {
            // 400 and nothing else. Naming which part of the check failed would
            // be free help to whoever is probing, and the detail is in the log
            // where it is useful.
            error_log('Stripe webhook rejected: ' . $why);
            http_response_code(400);
            echo "No\n";

            return;
        }

        $event = json_decode($raw, true);
        if (!is_array($event)) {
            http_response_code(400);
            echo "Expected JSON\n";

            return;
        }

        $id   = (string) ($event['id'] ?? '');
        $type = (string) ($event['type'] ?? '');

        if (!Billing::firstSighting($id, $type)) {
            echo "Already handled\n";

            return;
        }

        try {
            $note = Billing::handle($event);
        } catch (\Throwable $e) {
            // 500 on purpose, and the only place this endpoint returns one: a
            // genuine failure to record a cancellation is exactly what Stripe's
            // retries are for. The event id is left recorded, so the retry is
            // dropped as a duplicate -- which is the honest trade, because
            // handling it twice is harmless and the alternative is a loop.
            error_log('Stripe webhook ' . $id . ' (' . $type . ') failed: ' . $e->getMessage());
            http_response_code(500);
            echo "Retry\n";

            return;
        }

        Audit::log('billing.webhook', 'account', null, null, ['type' => $type, 'result' => $note]);

        echo "OK\n";
    }

    private function back(string $message): never
    {
        $_SESSION['members_flash'] = $message;
        Request::redirect('/members/settings');
    }
}
