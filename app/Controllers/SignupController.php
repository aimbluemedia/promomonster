<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Audit;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Database;
use App\Support\RateLimiter;
use App\Support\Request;
use App\Support\Validator;
use App\Support\View;
use Throwable;

/**
 * Self-serve signup for the members area. Free, and only Free.
 *
 * There is no plan to choose here and no plan read from the request. That is
 * the point, and it is deliberately enforced on this side rather than by the
 * absence of radio buttons on the form: a hidden field is a suggestion, and a
 * POST is whatever somebody sends. Nothing a visitor submits can put an
 * upgrade request on an account.
 *
 * Upgrades happen in members settings, after signup, which is also the only
 * place they make sense — you cannot usefully choose how much of a product you
 * want before using any of it.
 */
final class SignupController
{
    private const MIN_PASSWORD = 12;

    public function show(): void
    {
        // Already signed in? There is nothing to sign up for.
        if (Auth::account() !== null) {
            Request::redirect('/members');
        }

        $error = $_SESSION['signup_error'] ?? null;
        $old   = $_SESSION['signup_old'] ?? [];
        unset($_SESSION['signup_error'], $_SESSION['signup_old']);

        echo View::page('members/signup', [
            'title'       => 'Create your account · PromoMonster',
            'description' => 'Start free. Ask every customer for a review, the right way.',
            'min'         => self::MIN_PASSWORD,
            'error'       => $error,
            'old'         => $old,
        ]);
    }

    public function store(): void
    {
        if (Auth::account() !== null) {
            Request::redirect('/members');
        }

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $this->fail('Your session expired. Please try again.');
        }

        // Honeypot: behave as though it worked, so a bot learns nothing.
        if (trim((string) ($_POST['website_url'] ?? '')) !== '') {
            Request::redirect('/members/login');
        }

        $validator = new Validator($_POST);
        $business  = $validator->required('business', 'Tell us the name of your business.', 160);
        $name      = $validator->required('name', 'Tell us your name.', 120);
        $email     = $validator->email('email', 'Please enter a valid email address.');

        $password = (string) ($_POST['password'] ?? '');

        if ($validator->fails()) {
            $this->fail($validator->firstError() ?? 'Please check the form and try again.');
        }
        if (strlen($password) < self::MIN_PASSWORD) {
            $this->fail('Your password needs at least ' . self::MIN_PASSWORD . ' characters.');
        }
        // No terms checkbox to enforce. The form states plainly, next to the
        // button, that signing up is the agreement, and the links are right
        // there -- so leaving this check in would only have rejected every
        // signup for a field the form no longer sends.
        $email = mb_strtolower((string) $email);

        if (RateLimiter::tooManyAttempts('signup:' . Request::ip(), 5, 3600)) {
            $this->fail('Too many signups from this connection. Please try again later.');
        }

        // Signup has to say whether an address is taken — there is no way to
        // create an account without it. The sign-in page is what resists
        // enumeration; this one points you at it.
        if (Database::first('SELECT id FROM users WHERE email = :email', ['email' => $email]) !== null) {
            $this->fail('That address already has an account. Sign in instead, or use a different address.');
        }

        [$first, $last] = $this->splitName((string) $name);

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            Database::run(
                "INSERT INTO users (email, password_hash, first_name, last_name, is_admin, status,
                                    must_change_password, email_verified_at)
                 VALUES (:email, :hash, :first, :last, 0, 'active', 0, NULL)",
                [
                    'email' => $email,
                    'hash'  => password_hash($password, PASSWORD_DEFAULT),
                    'first' => $first,
                    'last'  => $last,
                ],
            );
            $userId = (int) $pdo->lastInsertId();

            // Free, with no requested_plan. Written as literals rather than
            // from a variable so there is no path from the request to either
            // column, however the form is submitted.
            Database::run(
                "INSERT INTO accounts (name, plan, requested_plan, requested_plan_at, signup_ip)
                 VALUES (:name, 'free', NULL, NULL, :ip)",
                ['name' => $business, 'ip' => Request::ip()],
            );
            $accountId = (int) $pdo->lastInsertId();

            Database::run(
                "INSERT INTO account_users (account_id, user_id, role) VALUES (:a, :u, 'owner')",
                ['a' => $accountId, 'u' => $userId],
            );
            Database::run(
                'INSERT INTO locations (account_id, name) VALUES (:a, :name)',
                ['a' => $accountId, 'name' => $business],
            );

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;   // ErrorHandler logs it with a reference the owner can look up.
        }

        Audit::log('account.signup', 'account', $accountId);

        Auth::signIn($userId);
        $_SESSION['members_flash'] =
            'Welcome to PromoMonster. Add your Google review link to get started.';

        Request::redirect('/members');
    }

    /** Splits "Dana Okafor" into first and last without losing a long surname. */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [];

        return [$parts[0] ?? $name, $parts[1] ?? ''];
    }

    private function fail(string $message): never
    {
        $_SESSION['signup_error'] = $message;
        $_SESSION['signup_old'] = [
            'business' => (string) ($_POST['business'] ?? ''),
            'name'     => (string) ($_POST['name'] ?? ''),
            'email'    => (string) ($_POST['email'] ?? ''),
        ];
        Request::redirect('/members/signup');
    }
}
