<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Audit;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Database;
use App\Support\Mailer;
use App\Support\Plans;
use App\Support\ReviewLink;
use App\Support\ReviewRequests;
use App\Support\SendLimit;
use App\Support\Request;
use App\Support\View;

final class MembersController
{
    public function __construct()
    {
        Auth::requireMember();
    }

    public function overview(): void
    {
        $account = Auth::account() ?? [];
        $locations = Database::all(
            'SELECT * FROM locations WHERE account_id = :id ORDER BY id',
            ['id' => (int) $account['id']],
        );

        $stats = Database::first(
            'SELECT
               (SELECT COUNT(*) FROM contacts c
                  JOIN locations l ON l.id = c.location_id
                 WHERE l.account_id = :a1)                                AS contacts,
               (SELECT COUNT(*) FROM review_requests r
                  JOIN locations l ON l.id = r.location_id
                 WHERE l.account_id = :a2)                                AS requests,
               (SELECT COUNT(*) FROM reviews rv
                  JOIN locations l ON l.id = rv.location_id
                 WHERE l.account_id = :a3)                                AS reviews,
               (SELECT COUNT(*) FROM reviews rv
                  JOIN locations l ON l.id = rv.location_id
                 WHERE l.account_id = :a4 AND rv.replied_at IS NULL)      AS unanswered',
            ['a1' => (int) $account['id'], 'a2' => (int) $account['id'],
             'a3' => (int) $account['id'], 'a4' => (int) $account['id']],
        ) ?? [];

        echo View::members('members/overview', [
            'title'     => 'Dashboard · PromoMonster',
            'account'   => $account,
            'locations' => $locations,
            'stats'     => $stats,
        ]);
    }

    /**
     * "Get reviews" — the one screen where the work happens.
     *
     * Was a list of reviews synced from Google, which needs Business Profile
     * API access we have not applied for, so it showed nothing and always
     * would. It is now the place a member sets their review link and asks a
     * customer, which is the only thing the product can actually do today.
     */
    public function reviews(): void
    {
        $account  = Auth::account() ?? [];
        $location = $this->primaryLocation((int) $account['id']);

        echo View::members('members/reviews', [
            'title'    => 'Get reviews · PromoMonster',
            'account'  => $account,
            'location' => $location,
            'limit'    => SendLimit::check((int) $account['id'], (string) ($account['plan'] ?? Plans::FREE)),
            'replyTo'  => $this->replyToAddress($location),
            'stuck'    => $this->queueLooksStuck((int) $account['id']),
            'sending'  => Mailer::isLive(),
            'recent'   => Database::all(
                'SELECT r.status, r.sent_at, r.created_at, r.first_clicked_at,
                        r.is_follow_up, r.failure_reason,
                        c.first_name, c.last_name, c.email
                   FROM review_requests r
                   JOIN contacts c ON c.id = r.contact_id
                   JOIN locations l ON l.id = r.location_id
                  WHERE l.account_id = :id AND r.is_follow_up = 0
               ORDER BY r.created_at DESC LIMIT 8',
                ['id' => (int) $account['id']],
            ),
        ]);
    }

    /** Saves the Google review link, which nothing could set before this. */
    public function saveReviewLink(): void
    {
        $account = Auth::account() ?? [];
        $this->guard();

        $location = $this->primaryLocation((int) $account['id']);
        if ($location === null) {
            $this->back('We could not find a location on your account. Please get in touch.');
        }

        $checked = ReviewLink::check((string) ($_POST['review_url'] ?? ''));
        if (!$checked['ok']) {
            $this->back((string) $checked['error']);
        }

        // Replies belong to the business, not to us, and the owner's login
        // address is the one we already know is theirs. Set once, and never
        // overwritten, so changing the link later cannot silently redirect
        // replies somewhere they have since moved away from.
        $replyTo = trim((string) ($location['reply_to_email'] ?? ''));
        if ($replyTo === '') {
            $replyTo = trim((string) ((Auth::user() ?? [])['email'] ?? ''));
        }

        Database::run(
            'UPDATE locations SET google_review_url = :url, reply_to_email = :reply WHERE id = :id',
            [
                'url'   => $checked['url'],
                'reply' => Mailer::isSendableAddress($replyTo) ? $replyTo : null,
                'id'    => (int) $location['id'],
            ],
        );

        Audit::log('location.review_link', 'location', (int) $location['id']);
        $this->back('Saved. You can send your first review request now.');
    }

    /**
     * Adds one customer and queues the ask.
     *
     * The allowance is checked here rather than only in the sender, so somebody
     * over their limit is told before a row exists rather than watching a
     * request sit in a queue that will never send it.
     */
    public function ask(): void
    {
        $account = Auth::account() ?? [];
        $this->guard();

        $location = $this->primaryLocation((int) $account['id']);
        if ($location === null || trim((string) ($location['google_review_url'] ?? '')) === '') {
            $this->back('Save your Google review link first — there is nowhere to send them yet.');
        }

        $first = trim((string) ($_POST['first_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));

        if ($first === '') {
            $this->back('Who is it for? A first name is enough.');
        }
        if (!Mailer::isSendableAddress($email)) {
            $this->back('That does not look like an email address we can send to.');
        }

        $limit = SendLimit::check((int) $account['id'], (string) ($account['plan'] ?? Plans::FREE));
        if (!$limit['allowed']) {
            $this->back((string) $limit['reason']);
        }

        $contact = $this->upsertContact((int) $location['id'], $first,
            trim((string) ($_POST['last_name'] ?? '')), $email);

        $queued = ReviewRequests::queue($location, $contact, (int) ((Auth::user() ?? [])['id'] ?? 0) ?: null);

        if (!$queued['ok']) {
            $this->back((string) $queued['error']);
        }

        Audit::log('request.queued', 'review_request', (int) $queued['id']);
        $this->back(sprintf(
            'On its way to %s. We will remind them once in %d days, then stop.',
            $first,
            ReviewRequests::FOLLOW_UP_DAYS,
        ));
    }

    // -- Helpers -----------------------------------------------------------

    /**
     * One contact per address per location, which the schema already enforces.
     * Re-asking somebody must reuse their row, or their opt-out stops applying.
     *
     * @return array<string,mixed>
     */
    private function upsertContact(int $locationId, string $first, string $last, string $email): array
    {
        $existing = Database::first(
            'SELECT * FROM contacts WHERE location_id = :l AND email = :e',
            ['l' => $locationId, 'e' => $email],
        );

        if ($existing !== null) {
            Database::run(
                'UPDATE contacts SET first_name = :f, last_name = :n WHERE id = :id',
                ['f' => $first, 'n' => $last !== '' ? $last : null, 'id' => (int) $existing['id']],
            );

            return array_merge($existing, ['first_name' => $first, 'last_name' => $last]);
        }

        Database::run(
            "INSERT INTO contacts (location_id, first_name, last_name, email, source)
             VALUES (:l, :f, :n, :e, 'manual')",
            ['l' => $locationId, 'f' => $first, 'n' => $last !== '' ? $last : null, 'e' => $email],
        );

        return Database::first(
            'SELECT * FROM contacts WHERE id = :id',
            ['id' => (int) Database::connection()->lastInsertId()],
        ) ?? [];
    }

    /** @return array<string,mixed>|null */
    private function primaryLocation(int $accountId): ?array
    {
        return Database::first(
            'SELECT * FROM locations WHERE account_id = :id ORDER BY id LIMIT 1',
            ['id' => $accountId],
        );
    }

    /** @param array<string,mixed>|null $location */
    private function replyToAddress(?array $location): string
    {
        $saved = trim((string) ($location['reply_to_email'] ?? ''));

        return $saved !== '' ? $saved : trim((string) ((Auth::user() ?? [])['email'] ?? ''));
    }

    /**
     * Has anything been sitting in the queue longer than a cron run should take?
     *
     * The single most likely reason a member presses Send and nothing happens
     * is that the cron job was never set up, and from inside the app that looks
     * exactly like everything working. Fifteen minutes is three misses of a
     * five-minute schedule, so it is late rather than merely unlucky.
     */
    private function queueLooksStuck(int $accountId): int
    {
        $row = Database::first(
            'SELECT COUNT(*) AS n
               FROM review_requests r
               JOIN locations l ON l.id = r.location_id
              WHERE l.account_id = :id
                AND r.status IN (\'queued\', \'scheduled\')
                AND r.scheduled_for <= DATE_SUB(NOW(), INTERVAL 15 MINUTE)',
            ['id' => $accountId],
        );

        return (int) ($row['n'] ?? 0);
    }

    private function guard(): void
    {
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $this->back('Your session expired. Please try again.');
        }
    }

    private function back(string $message): never
    {
        $_SESSION['members_flash'] = $message;
        Request::redirect('/members/reviews');
    }

    public function requests(): void
    {
        $account = Auth::account() ?? [];
        echo View::members('members/requests', [
            'title'   => 'Review requests · PromoMonster',
            'account' => $account,
            'rows'    => Database::all(
                'SELECT r.*, l.name AS location_name, c.first_name, c.last_name
                   FROM review_requests r
                   JOIN locations l ON l.id = r.location_id
                   JOIN contacts c ON c.id = r.contact_id
                  WHERE l.account_id = :id
               ORDER BY r.created_at DESC LIMIT 100',
                ['id' => (int) $account['id']],
            ),
        ]);
    }

    public function playbook(): void
    {
        $account = Auth::account() ?? [];
        $vertical = Database::first(
            'SELECT vertical FROM locations WHERE account_id = :id AND vertical IS NOT NULL LIMIT 1',
            ['id' => (int) $account['id']],
        );

        echo View::members('members/playbook', [
            'title'    => 'Your playbook · PromoMonster',
            'account'  => $account,
            'playbook' => $vertical === null ? null : Database::first(
                'SELECT * FROM playbooks WHERE vertical = :v',
                ['v' => $vertical['vertical']],
            ),
        ]);
    }

    public function settings(): void
    {
        $account = Auth::account() ?? [];

        // members/layout.php renders and clears members_flash for every page.
        echo View::members('members/settings', [
            'title'   => 'Settings · PromoMonster',
            'account' => $account,
            'plans'   => Plans::selectable(),
            'user'    => Auth::user() ?? [],
            'team'    => Database::all(
                'SELECT u.first_name, u.last_name, u.email, au.role
                   FROM account_users au JOIN users u ON u.id = au.user_id
                  WHERE au.account_id = :id ORDER BY au.created_at',
                ['id' => (int) $account['id']],
            ),
        ]);
    }

    /**
     * Records that a member wants a different plan.
     *
     * Downgrading to Free takes effect immediately — it costs nothing and
     * refusing it would be holding someone's account hostage. Moving up is a
     * request, because there is no payment processor to charge yet; superadmin
     * sees the queue and sets the subscription up by hand.
     */
    public function requestPlan(): void
    {
        $account = Auth::account() ?? [];
        $accountId = (int) ($account['id'] ?? 0);

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $this->flashBack('Your session expired. Please try again.');
        }

        $wanted = (string) ($_POST['plan'] ?? '');
        if (!in_array($wanted, Plans::SELECTABLE, true)) {
            $this->flashBack('That is not a plan we offer.');
        }

        $current = (string) ($account['plan'] ?? Plans::FREE);

        // Partner accounts are arranged directly and are not on the self-serve
        // ladder. Letting one "downgrade to Free" here would quietly cancel a
        // reseller agreement from a stray click.
        if (!in_array($current, Plans::SELECTABLE, true)) {
            $this->flashBack(
                'Your account is on ' . Plans::name($current) . ', which we arrange directly. '
                . 'Email us and a person will sort any change.'
            );
        }

        if ($wanted === $current && ($account['requested_plan'] ?? null) === null) {
            $this->flashBack('You are already on ' . Plans::name($current) . '.');
        }

        if ($wanted === Plans::FREE) {
            Database::run(
                "UPDATE accounts
                    SET plan = 'free', requested_plan = NULL, requested_plan_at = NULL,
                        plan_changed_at = NOW()
                  WHERE id = :id",
                ['id' => $accountId],
            );
            Audit::log('account.plan_downgraded', 'account', $accountId,
                ['plan' => $current], ['plan' => Plans::FREE]);

            $this->flashBack('You are on the Free plan now. Nothing further is owed.');
        }

        Database::run(
            'UPDATE accounts SET requested_plan = :plan, requested_plan_at = NOW() WHERE id = :id',
            ['plan' => $wanted, 'id' => $accountId],
        );
        Audit::log('account.plan_requested', 'account', $accountId,
            ['plan' => $current], ['requested_plan' => $wanted]);

        $this->flashBack(
            'Thanks — we have your request for ' . Plans::name($wanted) . '. We will be in '
            . 'touch to set the subscription up. Nothing is charged until you agree to it.'
        );
    }

    private function flashBack(string $message): never
    {
        $_SESSION['members_flash'] = $message;
        Request::redirect('/members/settings');
    }
}
