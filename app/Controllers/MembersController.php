<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Auth;
use App\Support\Database;
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
        echo View::members('members/settings', [
            'title'   => 'Settings · PromoMonster',
            'account' => $account,
            'user'    => Auth::user() ?? [],
            'team'    => Database::all(
                'SELECT u.first_name, u.last_name, u.email, au.role
                   FROM account_users au JOIN users u ON u.id = au.user_id
                  WHERE au.account_id = :id ORDER BY au.created_at',
                ['id' => (int) $account['id']],
            ),
        ]);
    }
}
