<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Audit;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Database;
use App\Support\Request;
use App\Support\View;

final class AdminController
{
    private const AUDIT_STATUSES = ['new', 'in_progress', 'delivered', 'converted', 'declined'];
    private const LEAD_STATUSES  = ['new', 'contacted', 'approved', 'declined'];

    public function __construct()
    {
        Auth::requireAdmin();
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
               (SELECT COUNT(*) FROM suppressions)                        AS suppressions_total"
        ) ?? [];

        echo View::admin('admin/overview', [
            'title'  => 'Overview · Admin',
            'counts' => $counts,
            'recent' => Database::all(
                'SELECT id, business_name, email, vertical, status, created_at
                   FROM audits ORDER BY created_at DESC LIMIT 8'
            ),
        ]);
    }

    public function audits(): void
    {
        $filter = $_GET['status'] ?? '';
        $where = in_array($filter, self::AUDIT_STATUSES, true) ? 'WHERE a.status = :status' : '';
        $params = $where !== '' ? ['status' => $filter] : [];

        echo View::admin('admin/audits', [
            'title'  => 'Audit requests · Admin',
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
            Request::redirect('/admin/audits');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        $notes = trim((string) ($_POST['notes'] ?? ''));

        if ($id <= 0 || !in_array($status, self::AUDIT_STATUSES, true)) {
            Request::redirect('/admin/audits');
        }

        $before = Database::first('SELECT status, notes FROM audits WHERE id = :id', ['id' => $id]);
        if ($before === null) {
            Request::redirect('/admin/audits');
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
        Request::redirect('/admin/audits');
    }

    public function leads(): void
    {
        echo View::admin('admin/leads', [
            'title'    => 'Agency applications · Admin',
            'rows'     => Database::all(
                "SELECT * FROM waitlist WHERE role = 'agency' ORDER BY created_at DESC LIMIT 200"
            ),
            'statuses' => self::LEAD_STATUSES,
        ]);
    }

    public function updateLead(): void
    {
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            Request::redirect('/admin/leads');
        }
        $id = (int) ($_POST['id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        if ($id <= 0 || !in_array($status, self::LEAD_STATUSES, true)) {
            Request::redirect('/admin/leads');
        }

        $before = Database::first('SELECT status FROM waitlist WHERE id = :id', ['id' => $id]);
        Database::run('UPDATE waitlist SET status = :status WHERE id = :id',
            ['status' => $status, 'id' => $id]);
        Audit::log('lead.update', 'waitlist', $id, $before, ['status' => $status]);

        $_SESSION['admin_flash'] = 'Application updated.';
        Request::redirect('/admin/leads');
    }

    public function compliance(): void
    {
        echo View::admin('admin/compliance', [
            'title'        => 'Compliance · Admin',
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
        echo View::admin('admin/activity', [
            'title' => 'Activity · Admin',
            'rows'  => Database::all(
                'SELECT l.*, u.email AS actor_email
                   FROM audit_log l
              LEFT JOIN users u ON u.id = l.actor_user_id
               ORDER BY l.created_at DESC LIMIT 200'
            ),
        ]);
    }
}
