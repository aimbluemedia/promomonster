<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Audit;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Database;
use App\Support\Plans;
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

    public function reviews(): void
    {
        $account = Auth::account() ?? [];
        echo View::members('members/reviews', [
            'title'   => 'Reviews · PromoMonster',
            'account' => $account,
            'rows'    => Database::all(
                'SELECT rv.*, l.name AS location_name
                   FROM reviews rv JOIN locations l ON l.id = rv.location_id
                  WHERE l.account_id = :id
               ORDER BY rv.posted_at DESC LIMIT 100',
                ['id' => (int) $account['id']],
            ),
        ]);
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
