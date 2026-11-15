<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Csrf;
use App\Support\Database;
use App\Support\RateLimiter;
use App\Support\Request;
use App\Support\Validator;
use PDOException;

final class LeadController
{
    private const ROLES = ['business', 'agency'];

    public function store(): void
    {
        $role = $_POST['role'] ?? '';
        $back = $role === 'agency' ? '/agencies#apply' : '/audit#start';

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $this->fail($back, 'Your session expired. Please try again.');
        }

        // Honeypot: report success so a bot learns nothing from the response.
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
            if (RateLimiter::tooManyAttempts('leads:' . Request::ip(), 5, 3600)) {
                $this->fail($back, 'Too many submissions from this connection. Please try again later.');
            }

            if ($role === 'business') {
                // An audit request is a lead for the acquisition engine, so it
                // belongs in `audits` rather than the waitlist.
                Database::run(
                    'INSERT INTO audits (business_name, email, vertical, results, ip)
                     VALUES (:business_name, :email, :vertical, :results, :ip)',
                    [
                        'business_name' => $validator->value('company', 200) ?? '(not given)',
                        'email'         => $email,
                        'vertical'      => $validator->value('vertical', 60),
                        'results'       => json_encode([
                            'requested_by' => $validator->value('name', 120),
                            'website'      => $validator->value('website', 300),
                            'source'       => $validator->value('source', 80),
                        ], JSON_THROW_ON_ERROR),
                        'ip'            => Request::ip(),
                    ],
                );
            } else {
                Database::run(
                    'INSERT INTO waitlist
                        (email, role, name, company, website, goal, source, referrer, ip, user_agent)
                     VALUES
                        (:email, :role, :name, :company, :website, :goal, :source, :referrer, :ip, :user_agent)
                     ON DUPLICATE KEY UPDATE id = id',
                    [
                        'email'      => $email,
                        'role'       => $role,
                        'name'       => $validator->value('name', 120),
                        'company'    => $validator->value('company', 160),
                        'website'    => $validator->value('website', 300),
                        'goal'       => $validator->value('goal', 2000),
                        'source'     => $validator->value('source', 80),
                        'referrer'   => Request::referer(),
                        'ip'         => Request::ip(),
                        'user_agent' => Request::userAgent(),
                    ],
                );
            }
        } catch (PDOException $e) {
            error_log('leads: ' . $e->getMessage());
            $this->fail($back, 'Something went wrong on our end. Please try again.');
        }

        $this->succeed($back);
    }

    private function succeed(string $back): never
    {
        $_SESSION['lead_done'] = true;
        Request::redirect($back);
    }

    private function fail(string $back, string $message): never
    {
        $_SESSION['lead_error'] = $message;
        Request::redirect($back);
    }
}
