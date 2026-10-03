<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Audit;
use App\Support\Auth;
use App\Support\Billing;
use App\Support\Config;
use App\Support\Csrf;
use App\Support\Database;
use App\Support\EmailTemplates;
use App\Support\Heartbeat;
use App\Support\HostedReviews;
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
     * "Google reviews" — everything about the Google side, in one place.
     *
     * The form that asks a customer used to live here. It now sits on the
     * Review requests page, because there are two places a review can be left
     * and a form that only ever pointed at Google could not be the one that
     * offers the choice. What is left here is what is genuinely Google's: the
     * review link, and the requests that were sent to it.
     */
    public function reviews(): void
    {
        $account  = Auth::account() ?? [];
        $location = $this->primaryLocation((int) $account['id']);

        echo View::members('members/reviews', [
            'title'    => 'Google reviews · PromoMonster',
            'account'  => $account,
            'location' => $location,
            'stuck'    => $this->queueLooksStuck((int) $account['id']),
            'sending'  => Mailer::isLive(),
            'requests' => ReviewRequests::recent((int) $account['id'], 'google', 25),
        ]);
    }

    /**
     * Saves the Google review link, which nothing could set before this.
     *
     * Every exit goes back to the Google page, which is where this form is.
     */
    public function saveReviewLink(): void
    {
        $account = Auth::account() ?? [];
        $here    = '/members/reviews';
        $this->guard($here);

        $location = $this->primaryLocation((int) $account['id']);
        if ($location === null) {
            $this->backTo($here, 'We could not find a location on your account. Please get in touch.');
        }

        $checked = ReviewLink::check((string) ($_POST['review_url'] ?? ''));
        if (!$checked['ok']) {
            $this->backTo($here, (string) $checked['error']);
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
        $this->backTo($here, 'Saved. You can send a Google review request now, '
            . 'from the Review requests page.');
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

        // Where it points. Checked before anything else is validated, because
        // it decides what "set up" even means: a PromoMonster request needs no
        // Google link, and refusing one for the want of it -- which is what the
        // old guard here did for every request -- would make the new
        // destination impossible to use.
        $destination = ReviewRequests::destination((string) ($_POST['destination'] ?? 'promomonster'));

        $location = $this->primaryLocation((int) $account['id']);
        $target   = ReviewRequests::target($this->targetContext($account, $location), $destination);
        if ($location === null || $target['error'] !== null) {
            $this->back($target['error'] ?? 'There is nowhere to send them yet.');
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

        // Which wording. An id off a form is not trusted any further than
        // EmailTemplates::resolve(), which refuses one belonging to another
        // account and falls back to this account's default.
        $templateId = (int) ($_POST['template_id'] ?? 0);

        $queued = ReviewRequests::queue(
            $this->targetContext($account, $location),
            $contact,
            (int) ((Auth::user() ?? [])['id'] ?? 0) ?: null,
            $templateId > 0 ? $templateId : null,
            $destination,
        );

        if (!$queued['ok']) {
            $this->back((string) $queued['error']);
        }

        Audit::log('request.queued', 'review_request', (int) $queued['id']);

        // Pressing Send sends. One message to one address is an ordinary form
        // submit, and waiting five minutes for a scheduled job to notice -- or
        // for ever, if that job was never created -- is not what anybody means
        // by this button. The row is committed first, so a failure here is a
        // slower send rather than a lost one.
        $now = ReviewRequests::sendNow((int) $queued['id']);

        if ($now['sent']) {
            $this->back(sprintf(
                'Sent to %s, pointing at %s. We will remind them once in %d days, then stop.',
                $first,
                ReviewRequests::DESTINATIONS[$destination],
                ReviewRequests::FOLLOW_UP_DAYS,
            ));
        }

        // Not an error. The request is recorded and will go out; say which of
        // the two reasons it is waiting, because they need different fixes.
        $this->back(sprintf(
            'Queued for %s, pointing at %s. %s',
            $first,
            ReviewRequests::DESTINATIONS[$destination],
            $now['error'] === null
                ? 'It will go out shortly.'
                : 'It could not go out just now: ' . $now['error'],
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

    /**
     * The business's own reviews, hosted here.
     *
     * Separate from the Google page because they are separate jobs: that one
     * pushes customers to somebody else's platform, this one builds something
     * the business owns and can put on its own website.
     */
    public function promoReviews(): void
    {
        $account = Auth::account() ?? [];
        $id      = (int) $account['id'];

        $slug = HostedReviews::slug($id, (string) ($account['name'] ?? ''));
        $base = rtrim((string) Config::get('app_url', 'https://promomonster.com'), '/');

        echo View::members('members/promo-reviews', [
            'title'    => 'PromoMonster reviews · PromoMonster',
            'account'  => $account,
            'ready'    => HostedReviews::ready(),
            'slug'     => $slug,
            'pageUrl'  => $slug === null ? null : $base . '/reviews/' . $slug,
            'widgetJs' => $slug === null ? null : $base . '/widget/' . $slug . '.js',
            'summary'  => HostedReviews::summary($id),
            'reviews'  => HostedReviews::forAccount($id),
            'requests' => ReviewRequests::recent($id, 'promomonster', 25),
            'error'    => $this->takeFlash('promo_review_error'),
        ]);
    }

    /**
     * Send the one reminder, because the member decided to.
     *
     * There is no scheduled job behind this. The list shows which requests went
     * out and were never opened, which is the only thing a three-day timer was
     * ever standing in for, and the member picks who to chase and in what
     * words.
     */
    public function remindRequest(): void
    {
        $this->guard();
        $account = Auth::account() ?? [];

        $id         = (int) ($_POST['id'] ?? 0);
        $templateId = (int) ($_POST['template_id'] ?? 0);

        $made = ReviewRequests::remind((int) $account['id'], $id, $templateId > 0 ? $templateId : null);
        if (!$made['ok']) {
            $this->back((string) $made['error']);
        }

        Audit::log('request.reminded', 'review_request', (int) $made['id']);

        $now = ReviewRequests::sendNow((int) $made['id']);
        $this->back($now['sent']
            ? 'Reminder sent. That is the last one they will get from us.'
            : 'Reminder queued. It could not go out just now: '
              . ($now['error'] ?? 'no reason given') . '.');
    }

    /** Try again, for a request that never got away. */
    public function retryRequest(): void
    {
        $this->guard();
        $account = Auth::account() ?? [];

        $again = ReviewRequests::retry((int) $account['id'], (int) ($_POST['id'] ?? 0));
        if (!$again['ok']) {
            $this->back((string) $again['error']);
        }

        $now = ReviewRequests::sendNow((int) $again['id']);
        $this->back($now['sent']
            ? 'Sent this time.'
            : 'Still could not send: ' . ($now['error'] ?? 'no reason given') . '.');
    }

    // =====================================================================
    // Email templates
    // =====================================================================

    /**
     * The wording that goes out, and which of it is the default.
     *
     * Both kinds on one page rather than two. A member who rewrites the request
     * and leaves the stock reminder behind has written two emails in two
     * voices to the same customer, and the way to stop that happening is to
     * have them both on screen at once.
     */
    public function templates(): void
    {
        $account = Auth::account() ?? [];
        $id      = (int) $account['id'];

        $sets = [];
        foreach (EmailTemplates::KINDS as $kind => $label) {
            $default = EmailTemplates::defaultFor($id, $kind);
            $sets[$kind] = [
                'label'     => $label,
                'rows'      => EmailTemplates::forAccount($id, $kind),
                'defaultId' => $default === null ? null : (int) $default['id'],
            ];
        }

        // An id in the query string opens that one for editing. Loaded through
        // find(), so a guessed id belonging to another account opens nothing.
        $editing = EmailTemplates::find($id, (int) ($_GET['edit'] ?? 0));

        echo View::members('members/templates', [
            'title'    => 'Email templates · PromoMonster',
            'account'  => $account,
            'ready'    => EmailTemplates::ready(),
            // Writing templates and picking one per send need nothing from 021.
            // Remembering a favourite does, so the page hides those controls
            // rather than offering a button that cannot work.
            'defaults' => EmailTemplates::canRememberDefault(),
            'sets'     => $sets,
            'editing'  => $editing,
            'error'    => $this->takeFlash('template_error'),
        ]);
    }

    /** Creates or updates one of the account's own templates. */
    public function saveTemplate(): void
    {
        $this->guardTo('/members/templates');
        $account = Auth::account() ?? [];

        $result = EmailTemplates::save((int) $account['id'], [
            'id'           => (int) ($_POST['id'] ?? 0),
            'kind'         => (string) ($_POST['kind'] ?? 'request'),
            'name'         => (string) ($_POST['name'] ?? ''),
            'subject'      => (string) ($_POST['subject'] ?? ''),
            'body'         => (string) ($_POST['body'] ?? ''),
            'make_default' => !empty($_POST['make_default']),
        ]);

        if (!$result['ok']) {
            $_SESSION['template_error'] = (string) $result['error'];
            Request::redirect('/members/templates');
        }

        Audit::log('template.saved', 'template', (int) $result['id']);
        $_SESSION['members_flash'] = 'Saved. New requests will use it when you pick it.';
        Request::redirect('/members/templates');
    }

    /** Points this account's default at one of its own templates. */
    public function makeTemplateDefault(): void
    {
        $this->guardTo('/members/templates');
        $account = Auth::account() ?? [];

        // The stock wording is not a row this account owns, so choosing it is
        // clearing the flag rather than setting one. See EmailTemplates.
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === 0) {
            EmailTemplates::useSystemDefault((int) $account['id'], (string) ($_POST['kind'] ?? 'request'));
            $_SESSION['members_flash'] = 'Back to the wording we ship with.';
        } elseif (EmailTemplates::makeDefault((int) $account['id'], $id)) {
            Audit::log('template.default_set', 'template', $id);
            $_SESSION['members_flash'] = 'That is the one we will use from now on.';
        } else {
            $_SESSION['template_error'] = 'That template is not one we can set as your default.';
        }

        Request::redirect('/members/templates');
    }

    /** Deletes one of the account's own templates. */
    public function deleteTemplate(): void
    {
        $this->guardTo('/members/templates');
        $account = Auth::account() ?? [];

        $id = (int) ($_POST['id'] ?? 0);
        if (EmailTemplates::delete((int) $account['id'], $id)) {
            Audit::log('template.deleted', 'template', $id);
            $_SESSION['members_flash'] = 'Deleted. Anything already queued will go out in your default wording.';
        } else {
            $_SESSION['template_error'] = 'That one cannot be deleted. The wording we ship with stays put.';
        }

        Request::redirect('/members/templates');
    }

    /** Adds a review the business is entering on somebody's behalf. */
    public function addPromoReview(): void
    {
        $this->guardTo('/members/promomonster-reviews');
        $account = Auth::account() ?? [];

        $source = (string) ($_POST['source'] ?? 'entered_by_business');
        if (!in_array($source, ['entered_by_business', 'google'], true)) {
            // Only the two a business may claim. 'invited' means we sent the
            // link ourselves and is not something a form can assert.
            $source = 'entered_by_business';
        }

        $result = HostedReviews::add([
            'account'    => (int) $account['id'],
            'location'   => $this->primaryLocation((int) $account['id'])['id'] ?? null,
            'source'     => $source,
            'name'       => (string) ($_POST['author_name'] ?? ''),
            'city'       => (string) ($_POST['author_city'] ?? ''),
            'rating'     => (int) ($_POST['rating'] ?? 0),
            'body'       => (string) ($_POST['body'] ?? ''),
            'source_url' => (string) ($_POST['source_url'] ?? ''),
        ]);

        if (!$result['ok']) {
            $_SESSION['promo_review_error'] = (string) $result['error'];
            Request::redirect('/members/promomonster-reviews');
        }

        $_SESSION['members_flash'] = $source === 'google'
            ? 'Google review added. It shows with a G so readers know where it came from.'
            : 'Review added. It shows as added by you, which is the honest label.';
        Request::redirect('/members/promomonster-reviews');
    }

    /** The public answer to a review. The only thing that can be done to one. */
    public function replyPromoReview(): void
    {
        $this->guardTo('/members/promomonster-reviews');
        $account = Auth::account() ?? [];

        HostedReviews::reply(
            (int) $account['id'],
            (int) ($_POST['review_id'] ?? 0),
            (string) ($_POST['reply'] ?? ''),
        );

        $_SESSION['members_flash'] = 'Your reply is on the review.';
        Request::redirect('/members/promomonster-reviews');
    }

    /** @return ?string A one-shot session message. */
    private function takeFlash(string $key): ?string
    {
        $value = $_SESSION[$key] ?? null;
        unset($_SESSION[$key]);

        return is_string($value) ? $value : null;
    }

    /** guard(), but returning somewhere other than the Google page. */
    private function guardTo(string $back): void
    {
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $_SESSION['members_flash'] = 'Your session expired. Please try again.';
            Request::redirect($back);
        }
    }

    private function guard(string $back = '/members/requests'): void
    {
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $this->backTo($back, 'Your session expired. Please try again.');
        }
    }

    /**
     * Back to the Review requests page, where the send form is.
     *
     * It used to mean the Google page, which is where the form used to live.
     * The form moved and this had to move with it, or somebody who pressed Send
     * would land on a different screen with the result of their action left
     * behind on the one they came from. saveReviewLink() is the one caller that
     * belongs to the Google page still, and it uses backTo().
     */
    private function back(string $message): never
    {
        $this->backTo('/members/requests', $message);
    }

    private function backTo(string $path, string $message): never
    {
        $_SESSION['members_flash'] = $message;
        Request::redirect($path);
    }

    /**
     * "Review requests" — choose where, then ask.
     *
     * The one screen that sends. Where the review is left is the first
     * decision, not a setting buried in a form, because it changes what the
     * customer sees when they click and it is the thing a business actually
     * chooses between.
     */
    public function requests(): void
    {
        $account  = Auth::account() ?? [];
        $id       = (int) $account['id'];
        $location = $this->primaryLocation($id);

        // Whether each destination can be sent to at all, worked out once here
        // rather than guessed in the view. A card that cannot send says why.
        $available = [];
        foreach (array_keys(ReviewRequests::DESTINATIONS) as $destination) {
            $available[$destination] = ReviewRequests::target(
                $this->targetContext($account, $location),
                $destination,
            );
        }

        echo View::members('members/requests', [
            'title'       => 'Review requests · PromoMonster',
            'account'     => $account,
            'location'    => $location,
            'limit'       => SendLimit::check($id, (string) ($account['plan'] ?? Plans::FREE)),
            'replyTo'     => $this->replyToAddress($location),
            'stuck'       => $this->queueLooksStuck($id),
            'sending'     => Mailer::isLive(),
            // Migrations the sender needs and has not got. Reading a request is
            // made to work without them; sending one cannot be, so the page
            // says so rather than letting somebody queue requests that will
            // never leave.
            'blocked'     => ReviewRequests::missingForSending(),
            // What the queue runner is actually doing, measured from its own
            // runs rather than from what the crontab is supposed to say.
            'runner'      => Heartbeat::status(Heartbeat::SEND_QUEUE),
            'available'   => $available,
            // The wording to offer. One entry means there is nothing to choose
            // between, and the picker is not drawn.
            'templates'      => EmailTemplates::forAccount($id, 'request'),
            // The wording offered when chasing somebody who did not open the
            // first one. A different kind of message, so a different list.
            'reminders'      => EmailTemplates::forAccount($id, 'follow_up'),
            'templateChosen' => (function () use ($id): ?int {
                $row = EmailTemplates::defaultFor($id, 'request');

                return $row === null ? null : (int) $row['id'];
            })(),
            'requests'    => ReviewRequests::recent($id, null, 100),
        ]);
    }

    /**
     * The row target() needs: the location, plus who owns it.
     *
     * target() resolves a PromoMonster link from the account's slug and a
     * Google link from the location's, so it needs both sides. A location on
     * its own is missing half the answer, and a brand new account has no
     * location row at all.
     *
     * @param array<string,mixed> $account
     * @param array<string,mixed>|null $location
     * @return array<string,mixed>
     */
    private function targetContext(array $account, ?array $location): array
    {
        return array_merge($location ?? [], [
            'account_id'   => (int) $account['id'],
            'account_name' => (string) ($account['name'] ?? ''),
            'public_slug'  => $account['public_slug'] ?? null,
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

        // A card Stripe is still charging. Writing plan = 'free' here would put
        // the business on the Free allowance with $19 a month still leaving
        // their account, and they would have pressed the button that did it.
        // Cancelling has to happen at Stripe; the plan then comes back to Free
        // on the subscription event.
        if ($wanted === Plans::FREE && Billing::hasLiveSubscription($account)) {
            $this->flashBack(
                'Your subscription is live with Stripe, so cancelling it there is what stops the '
                . 'charge -- doing it here would drop your plan and keep billing you. Press '
                . '"Manage billing" and cancel on that page; your plan moves to Free as soon as '
                . 'Stripe confirms it.'
            );
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
