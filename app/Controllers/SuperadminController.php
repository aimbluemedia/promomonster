<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Audit;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Claude;
use App\Support\Database;
use App\Support\ReviewComparison;
use App\Support\Plans;
use App\Support\RateLimiter;
use App\Support\Request;
use App\Support\View;
use Throwable;

final class SuperadminController
{
    private const AUDIT_STATUSES = ['new', 'in_progress', 'delivered', 'converted', 'declined'];
    private const LEAD_STATUSES  = ['new', 'contacted', 'approved', 'declined'];

    public function __construct()
    {
        Auth::requireStaff();
    }

    public function overview(): void
    {
        $counts = Database::first(
            "SELECT
               (SELECT COUNT(*) FROM audits)                              AS audits_total,
               (SELECT COUNT(*) FROM audits WHERE status = 'new')         AS audits_new,
               (SELECT COUNT(*) FROM audits WHERE status = 'converted')   AS audits_converted,
               (SELECT COUNT(*) FROM audits WHERE created_at > NOW() - INTERVAL 7 DAY) AS audits_week,
               (SELECT COUNT(*) FROM waitlist WHERE role = 'agency')      AS agencies_total,
               (SELECT COUNT(*) FROM waitlist WHERE role = 'agency' AND status = 'new') AS agencies_new,
               (SELECT COUNT(*) FROM accounts)                            AS accounts_total,
               (SELECT COUNT(*) FROM accounts WHERE requested_plan IS NOT NULL) AS upgrades_pending,
               (SELECT COUNT(*) FROM suppressions)                        AS suppressions_total"
        ) ?? [];

        echo View::superadmin('superadmin/overview', [
            'title'  => 'Overview · Superadmin',
            'counts' => $counts,
            'recent' => Database::all(
                'SELECT id, business_name, email, vertical, status, created_at
                   FROM audits ORDER BY created_at DESC LIMIT 8'
            ),
            // Plan requests go nowhere unless somebody sees them. Until billing
            // exists, this list IS the billing system.
            'upgrades' => Database::all(
                "SELECT a.id, a.name, a.plan, a.requested_plan, a.requested_plan_at,
                        u.email AS owner_email
                   FROM accounts a
              LEFT JOIN account_users au ON au.account_id = a.id AND au.role = 'owner'
              LEFT JOIN users u ON u.id = au.user_id
                  WHERE a.requested_plan IS NOT NULL
               ORDER BY a.requested_plan_at"
            ),
        ]);
    }

    /**
     * Applies or dismisses a member's plan request.
     *
     * This is the manual stand-in for billing. 'apply' moves the account onto
     * the plan it asked for — do it once payment is actually arranged, because
     * nothing here takes money.
     */
    public function updatePlan(): void
    {
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $this->flash('/superadmin', 'Your session expired. Please try again.');
        }

        $accountId = (int) ($_POST['account_id'] ?? 0);
        $decision  = (string) ($_POST['decision'] ?? '');

        $account = Database::first(
            'SELECT id, name, plan, requested_plan FROM accounts WHERE id = :id',
            ['id' => $accountId],
        );

        if ($account === null || $account['requested_plan'] === null) {
            $this->flash('/superadmin', 'That account has no plan request outstanding.');
        }
        if (!in_array($decision, ['apply', 'dismiss'], true)) {
            $this->flash('/superadmin', 'Unknown action.');
        }

        $wanted = (string) $account['requested_plan'];

        if ($decision === 'apply') {
            Database::run(
                'UPDATE accounts
                    SET plan = :plan, requested_plan = NULL, requested_plan_at = NULL,
                        plan_changed_at = NOW()
                  WHERE id = :id',
                ['plan' => $wanted, 'id' => $accountId],
            );
            Audit::log('account.plan_applied', 'account', $accountId,
                ['plan' => $account['plan']], ['plan' => $wanted]);

            $this->flash('/superadmin', $account['name'] . ' is now on ' . Plans::name($wanted) . '.');
        }

        Database::run(
            'UPDATE accounts SET requested_plan = NULL, requested_plan_at = NULL WHERE id = :id',
            ['id' => $accountId],
        );
        Audit::log('account.plan_request_dismissed', 'account', $accountId,
            ['requested_plan' => $wanted], null);

        $this->flash('/superadmin', 'Cleared the ' . Plans::name($wanted) . ' request for ' . $account['name'] . '.');
    }

    private function flash(string $to, string $message): never
    {
        $_SESSION['admin_flash'] = $message;
        Request::redirect($to);
    }

    /** One audit request, with the competitor comparison tool. */
    public function audit(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $audit = Database::first('SELECT * FROM audits WHERE id = :id', ['id' => $id]);

        if ($audit === null) {
            $this->flash('/superadmin/audits', 'No audit with that id.');
        }

        $results = null;
        if (!empty($audit['results'])) {
            $decoded = json_decode((string) $audit['results'], true);
            $results = is_array($decoded) ? $decoded : null;
        }

        echo View::superadmin('superadmin/audit', [
            'title'      => 'Audit #' . $id . ' · Superadmin',
            'audit'      => $audit,
            'results'    => $results,
            'aiReady'    => Claude::isConfigured(),
            'modelLabel' => Claude::modelInfo()['label'],
            'costNote'   => Claude::costNote(),
            'statuses'   => self::AUDIT_STATUSES,
        ]);
    }

    /**
     * Runs the review comparison and stores it on the audit.
     *
     * Deliberately synchronous: this is a person pressing a button and waiting,
     * not a background job, and one audit takes seconds. A queue here would be
     * infrastructure with nothing to do.
     */
    public function compareAudit(): void
    {
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $this->flash('/superadmin/audits', 'Your session expired. Please try again.');
        }

        $id = (int) ($_POST['audit_id'] ?? 0);
        $back = '/superadmin/audit?id=' . $id;
        $audit = Database::first('SELECT id, business_name FROM audits WHERE id = :id', ['id' => $id]);

        if ($audit === null) {
            $this->flash('/superadmin/audits', 'No audit with that id.');
        }

        $subject = $this->readBusiness('subject', (string) $audit['business_name']);
        $competitors = [];
        foreach (['c1', 'c2', 'c3'] as $key) {
            $competitor = $this->readBusiness($key, '');
            if ($competitor['name'] !== '') {
                $competitors[] = $competitor;
            }
        }

        if ($competitors === []) {
            $this->flash($back, 'Add at least one competitor before running the comparison.');
        }

        try {
            $results = ReviewComparison::run($subject, $competitors);
        } catch (Throwable $e) {
            // The message is written for the operator — an API key problem and a
            // refusal need different responses, so say which happened.
            $this->flash($back, 'Comparison failed: ' . $e->getMessage());
        }

        Database::run(
            'UPDATE audits SET results = :results, handled_by_user_id = :user, handled_at = NOW(),
                    status = CASE WHEN status = \'new\' THEN \'in_progress\' ELSE status END
              WHERE id = :id',
            [
                'results' => json_encode($results, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'user'    => (int) (Auth::user()['id'] ?? 0),
                'id'      => $id,
            ],
        );

        Audit::log('audit.comparison_run', 'audit', $id);
        $this->flash($back, 'Comparison ready.');
    }

    /**
     * @return array{name:string,rating:?float,review_count:?int,reviews:array<int,string>}
     */
    private function readBusiness(string $prefix, string $fallbackName): array
    {
        $name = trim((string) ($_POST[$prefix . '_name'] ?? ''));
        $rating = trim((string) ($_POST[$prefix . '_rating'] ?? ''));
        $count  = trim((string) ($_POST[$prefix . '_count'] ?? ''));

        // One review per blank-line-separated block: pasting from a profile
        // gives you paragraphs, and splitting on single newlines would shred
        // every multi-line review into fragments.
        $raw = trim((string) ($_POST[$prefix . '_reviews'] ?? ''));
        $reviews = $raw === '' ? [] : (preg_split('/\n\s*\n/', $raw) ?: []);
        $reviews = array_values(array_filter(array_map('trim', $reviews)));

        return [
            'name'         => $name !== '' ? $name : $fallbackName,
            'rating'       => is_numeric($rating) ? (float) $rating : null,
            'review_count' => is_numeric($count) ? (int) $count : null,
            'reviews'      => array_slice($reviews, 0, 25),
        ];
    }

    /** Free Review Score submissions, newest first. */
    public function scores(): void
    {
        $perPage = 25;
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $total = (int) (Database::first(
            "SELECT COUNT(*) AS n FROM audits WHERE source = 'score'"
        )['n'] ?? 0);

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        // LIMIT/OFFSET cannot be bound as parameters on every driver, so they
        // are cast to int and interpolated. Both come from max()/min() over
        // integers above, so neither can carry anything but a number.
        $rows = Database::all(
            "SELECT id, business_name, website, email, score, results, created_at
               FROM audits
              WHERE source = 'score'
           ORDER BY created_at DESC, id DESC
              LIMIT " . (int) $perPage . " OFFSET " . (int) $offset
        );

        echo View::superadmin('superadmin/scores', [
            'title'   => 'Review scores · Superadmin',
            'rows'    => $rows,
            'page'    => $page,
            'pages'   => $pages,
            'total'   => $total,
            'perPage' => $perPage,
        ]);
    }

    /**
     * Everyone who has signed up, by plan.
     *
     * Listed by ACCOUNT rather than by user. A plan belongs to an account and
     * an account can have several logins, so a per-user list would show the
     * same business three times on three different rows and count it three
     * times in the totals.
     */
    public function users(): void
    {
        $perPage = 25;
        $page    = max(1, (int) ($_GET['page'] ?? 1));

        // The filter is matched against a known list before it reaches SQL, so
        // it can only ever be one of these strings.
        $filters = array_merge(['all', 'wants_upgrade'], Plans::SELECTABLE, [Plans::PARTNER]);
        $filter  = in_array((string) ($_GET['plan'] ?? 'all'), $filters, true)
            ? (string) ($_GET['plan'] ?? 'all')
            : 'all';

        [$where, $params] = match ($filter) {
            'all'           => ['1 = 1', []],
            'wants_upgrade' => ['a.requested_plan IS NOT NULL', []],
            default         => ['a.plan = :plan', ['plan' => $filter]],
        };

        $total = (int) (Database::first(
            'SELECT COUNT(*) AS n FROM accounts a WHERE ' . $where,
            $params,
        )['n'] ?? 0);

        $pages  = max(1, (int) ceil($total / $perPage));
        $page   = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        // LIMIT/OFFSET are interpolated because they cannot be bound on every
        // driver. Both come from max()/min() over integers, so neither can
        // carry anything but a number.
        $rows = Database::all(
            'SELECT a.id, a.name, a.plan, a.requested_plan, a.requested_plan_at,
                    a.status, a.created_at, a.signup_ip,
                    u.first_name, u.last_name, u.email, u.last_login_at,
                    (SELECT COUNT(*) FROM locations l
                      WHERE l.account_id = a.id
                        AND l.google_review_url IS NOT NULL
                        AND l.google_review_url <> \'\')                       AS linked,
                    (SELECT COUNT(*) FROM locations l WHERE l.account_id = a.id) AS locations,
                    (SELECT COUNT(*) FROM review_requests r
                       JOIN locations l ON l.id = r.location_id
                      WHERE l.account_id = a.id AND r.is_follow_up = 0)       AS asks,
                    (SELECT MAX(r.created_at) FROM review_requests r
                       JOIN locations l ON l.id = r.location_id
                      WHERE l.account_id = a.id)                              AS last_ask
               FROM accounts a
          LEFT JOIN account_users au ON au.account_id = a.id AND au.role = \'owner\'
          LEFT JOIN users u ON u.id = au.user_id
              WHERE ' . $where . '
           ORDER BY a.created_at DESC, a.id DESC
              LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
            $params,
        );

        echo View::superadmin('superadmin/users', [
            'title'   => 'Users · Superadmin',
            'rows'    => $rows,
            'counts'  => $this->planCounts(),
            'filter'  => $filter,
            'page'    => $page,
            'pages'   => $pages,
            'total'   => $total,
            'perPage' => $perPage,
        ]);
    }

    /**
     * Sets an account's plan outright.
     *
     * Different from updatePlan(), which answers a request the member made.
     * This is for the case with no request behind it: somebody paid by invoice,
     * or a plan needs winding back. Both are recorded, because "who moved this
     * account to Premium and when" is a question that gets asked.
     */
    public function setPlan(): void
    {
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $this->flash('/superadmin/users', 'Your session expired. Please try again.');
        }

        $accountId = (int) ($_POST['account_id'] ?? 0);
        $wanted    = (string) ($_POST['plan'] ?? '');

        if (!Plans::exists($wanted)) {
            $this->flash('/superadmin/users', 'That is not a plan.');
        }

        $account = Database::first(
            'SELECT id, name, plan FROM accounts WHERE id = :id',
            ['id' => $accountId],
        );
        if ($account === null) {
            $this->flash('/superadmin/users', 'No such account.');
        }
        if ((string) $account['plan'] === $wanted) {
            $this->flash('/superadmin/users', $account['name'] . ' is already on ' . Plans::name($wanted) . '.');
        }

        Database::run(
            'UPDATE accounts
                SET plan = :plan, requested_plan = NULL, requested_plan_at = NULL,
                    plan_changed_at = NOW()
              WHERE id = :id',
            ['plan' => $wanted, 'id' => $accountId],
        );
        Audit::log('account.plan_set', 'account', $accountId,
            ['plan' => $account['plan']], ['plan' => $wanted]);

        $this->flash('/superadmin/users', sprintf(
            '%s moved from %s to %s.',
            $account['name'],
            Plans::name((string) $account['plan']),
            Plans::name($wanted),
        ));
    }

    /**
     * How many accounts sit on each plan, for the filter row.
     *
     * Every plan is present in the result even at zero, so a tab does not
     * vanish the moment nobody is on that tier.
     *
     * @return array<string,int>
     */
    private function planCounts(): array
    {
        $counts = ['all' => 0, 'wants_upgrade' => 0];
        foreach (array_merge(Plans::SELECTABLE, [Plans::PARTNER]) as $plan) {
            $counts[$plan] = 0;
        }

        foreach (Database::all('SELECT plan, COUNT(*) AS n FROM accounts GROUP BY plan') as $row) {
            $counts[(string) $row['plan']] = (int) $row['n'];
            $counts['all'] += (int) $row['n'];
        }

        $counts['wants_upgrade'] = (int) (Database::first(
            'SELECT COUNT(*) AS n FROM accounts WHERE requested_plan IS NOT NULL'
        )['n'] ?? 0);

        return $counts;
    }

    /**
     * Deletes one score so the same site and address can be tested again.
     *
     * Deleting the row alone is not enough. The per-email and per-IP limiters
     * live in their own table and would still refuse the retest, which makes
     * the button look broken — so the buckets that submission filled are
     * cleared too. Scoped to source='score': a delete button that could reach
     * a real audit request or a paying lead is a different thing entirely.
     */
    public function deleteScore(): void
    {
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $this->flash('/superadmin/scores', 'Your session expired. Please try again.');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $row = Database::first(
            "SELECT id, business_name, email, ip, score FROM audits WHERE id = :id AND source = 'score'",
            ['id' => $id],
        );

        if ($row === null) {
            $this->flash('/superadmin/scores', 'No score with that id — it may already be gone.');
        }

        Database::run('DELETE FROM audits WHERE id = :id', ['id' => $id]);

        // Same keys ScoreController limits on.
        RateLimiter::forget('score:email:' . (string) $row['email']);
        if (!empty($row['ip'])) {
            RateLimiter::forget('score:ip:' . (string) $row['ip']);
        }

        Audit::log('score.deleted', 'audit', $id,
            ['website' => $row['business_name'], 'score' => $row['score']], null);

        $back = '/superadmin/scores' . (isset($_POST['page']) ? '?page=' . (int) $_POST['page'] : '');
        $this->flash($back, 'Deleted. ' . $row['email'] . ' can be scored again now.');
    }

    /**
     * Clears every rate-limit bucket.
     *
     * The escape hatch for the case the delete button cannot reach: an attempt
     * that failed before it produced anything still filled a bucket, so there
     * is no row to delete and no way back. Bucket keys are hashed, so they
     * cannot be looked up by address either — clearing all of them is the only
     * move available. Cheap to do: the worst case is that a handful of people
     * get their daily allowance back.
     */
    public function clearLimits(): void
    {
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $this->flash('/superadmin/scores', 'Your session expired. Please try again.');
        }

        $cleared = RateLimiter::forgetAll();
        Audit::log('ratelimits.cleared', null, null, ['cleared' => $cleared], null);

        $this->flash('/superadmin/scores',
            $cleared === 0
                ? 'There were no rate limits to clear.'
                : 'Cleared ' . $cleared . ' rate limit(s). Everyone can try again now.');
    }

    public function audits(): void
    {
        $filter = $_GET['status'] ?? '';
        $where = in_array($filter, self::AUDIT_STATUSES, true) ? 'WHERE a.status = :status' : '';
        $params = $where !== '' ? ['status' => $filter] : [];

        echo View::superadmin('superadmin/audits', [
            'title'  => 'Audit requests · Superadmin',
            'filter' => $where !== '' ? $filter : '',
            'rows'   => Database::all(
                "SELECT a.*, u.first_name AS handler_first, u.last_name AS handler_last
                   FROM audits a
              LEFT JOIN users u ON u.id = a.handled_by_user_id
                   {$where}
               ORDER BY a.created_at DESC LIMIT 200",
                $params,
            ),
            'statuses' => self::AUDIT_STATUSES,
        ]);
    }

    public function updateAudit(): void
    {
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            Request::redirect('/superadmin/audits');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        $notes = trim((string) ($_POST['notes'] ?? ''));

        if ($id <= 0 || !in_array($status, self::AUDIT_STATUSES, true)) {
            Request::redirect('/superadmin/audits');
        }

        $before = Database::first('SELECT status, notes FROM audits WHERE id = :id', ['id' => $id]);
        if ($before === null) {
            Request::redirect('/superadmin/audits');
        }

        Database::run(
            'UPDATE audits
                SET status = :status, notes = :notes,
                    handled_by_user_id = :handler, handled_at = NOW()
              WHERE id = :id',
            [
                'status'  => $status,
                'notes'   => $notes !== '' ? mb_substr($notes, 0, 5000) : null,
                'handler' => (int) (Auth::user()['id'] ?? 0),
                'id'      => $id,
            ],
        );

        Audit::log('audit.update', 'audit', $id, $before, ['status' => $status, 'notes' => $notes]);
        $_SESSION['admin_flash'] = 'Audit request updated.';
        Request::redirect('/superadmin/audits');
    }

    public function leads(): void
    {
        echo View::superadmin('superadmin/leads', [
            'title'    => 'Agency applications · Superadmin',
            'rows'     => Database::all(
                "SELECT * FROM waitlist WHERE role = 'agency' ORDER BY created_at DESC LIMIT 200"
            ),
            'statuses' => self::LEAD_STATUSES,
        ]);
    }

    public function updateLead(): void
    {
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            Request::redirect('/superadmin/leads');
        }
        $id = (int) ($_POST['id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        if ($id <= 0 || !in_array($status, self::LEAD_STATUSES, true)) {
            Request::redirect('/superadmin/leads');
        }

        $before = Database::first('SELECT status FROM waitlist WHERE id = :id', ['id' => $id]);
        Database::run('UPDATE waitlist SET status = :status WHERE id = :id',
            ['status' => $status, 'id' => $id]);
        Audit::log('lead.update', 'waitlist', $id, $before, ['status' => $status]);

        $_SESSION['admin_flash'] = 'Application updated.';
        Request::redirect('/superadmin/leads');
    }

    public function compliance(): void
    {
        echo View::superadmin('superadmin/compliance', [
            'title'        => 'Compliance · Superadmin',
            'suppressions' => Database::all(
                'SELECT channel, reason, created_at FROM suppressions ORDER BY created_at DESC LIMIT 100'
            ),
            'brands'       => Database::all(
                'SELECT b.*, a.name AS account_name FROM messaging_brands b
                   JOIN accounts a ON a.id = b.account_id
               ORDER BY b.created_at DESC LIMIT 100'
            ),
        ]);
    }

    public function activity(): void
    {
        echo View::superadmin('superadmin/activity', [
            'title' => 'Activity · Superadmin',
            'rows'  => Database::all(
                'SELECT l.*, u.email AS actor_email
                   FROM audit_log l
              LEFT JOIN users u ON u.id = l.actor_user_id
               ORDER BY l.created_at DESC LIMIT 200'
            ),
        ]);
    }
}
