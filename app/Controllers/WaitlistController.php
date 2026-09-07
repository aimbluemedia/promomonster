<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Csrf;
use App\Support\Database;
use App\Support\RateLimiter;
use App\Support\Request;
use App\Support\Validator;
use App\Support\View;
use PDOException;

final class WaitlistController
{
    private const ROLES = ['business', 'panelist'];

    public function store(): void
    {
        $role = $_POST['role'] ?? '';
        $back = $role === 'business' ? '/business' : '/earn';

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $this->fail($back, 'Your session expired. Please try again.');
        }

        // Honeypot: a real person never fills a field they cannot see. Report
        // success so a bot learns nothing from the response.
        if (trim((string) ($_POST['website_url'] ?? '')) !== '') {
            $this->succeed($back);
        }

        $validator = new Validator($_POST);
        $email = $validator->email('email', 'Please enter a valid email address.');
        $role = $validator->inList('role', self::ROLES, 'Something went wrong. Please try again.');

        if ($validator->fails()) {
            $this->fail($back, $validator->firstError() ?? 'Please check the form and try again.');
        }

        try {
            if (RateLimiter::tooManyAttempts('waitlist:' . Request::ip(), 5, 3600)) {
                $this->fail($back, 'Too many signups from this connection. Please try again later.');
            }

            Database::run(
                'INSERT INTO waitlist
                    (email, role, name, company, website, goal, country, region, postal_code,
                     source, referrer, ip, user_agent)
                 VALUES
                    (:email, :role, :name, :company, :website, :goal, :country, :region, :postal_code,
                     :source, :referrer, :ip, :user_agent)
                 ON DUPLICATE KEY UPDATE id = id',
                [
                    'email'       => $email,
                    'role'        => $role,
                    'name'        => $validator->value('name', 120),
                    'company'     => $validator->value('company', 160),
                    'website'     => $validator->value('website', 300),
                    'goal'        => $validator->value('goal', 2000),
                    'country'     => $role === 'panelist' ? 'US' : null,
                    'region'      => $validator->value('region', 80),
                    'postal_code' => $validator->value('postalCode', 16),
                    'source'      => $validator->value('source', 80),
                    'referrer'    => Request::referer(),
                    'ip'          => Request::ip(),
                    'user_agent'  => Request::userAgent(),
                ],
            );
        } catch (PDOException $e) {
            error_log('waitlist: ' . $e->getMessage());
            $this->fail($back, 'Something went wrong on our end. Please try again.');
        }

        $this->succeed($back);
    }

    private function succeed(string $back): never
    {
        $_SESSION['waitlist_done'] = true;
        Request::redirect($back . '#start');
    }

    private function fail(string $back, string $message): never
    {
        $_SESSION['waitlist_error'] = $message;
        Request::redirect($back . '#start');
    }
}
