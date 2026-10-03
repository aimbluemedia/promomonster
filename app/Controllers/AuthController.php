<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Request;
use App\Support\View;

/**
 * Shared login for both signed-in areas. `$area` is 'superadmin' (PromoMonster
 * staff) or 'members' (customers) — same credential check, different guard
 * afterwards.
 */
final class AuthController
{
    private const AREAS = [
        'superadmin' => [
            'view'  => 'superadmin/login',
            'title' => 'Sign in · PromoMonster',
        ],
        'members' => [
            'view'  => 'members/login',
            'title' => 'Sign in · PromoMonster',
        ],
    ];

    public function showLogin(string $area): void
    {
        $this->assertArea($area);

        // Already signed in and entitled? Go straight through.
        if ($area === 'superadmin' && Auth::isStaff()) {
            Request::redirect('/superadmin');
        }
        if ($area === 'members' && Auth::account() !== null) {
            Request::redirect('/members');
        }

        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);

        echo View::render(self::AREAS[$area]['view'], [
            'title' => self::AREAS[$area]['title'],
            'error' => $error,
        ]);
    }

    public function login(string $area): void
    {
        $this->assertArea($area);
        $back = "/{$area}/login";

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $this->fail($back, 'Your session expired. Please try again.');
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $this->fail($back, 'Enter your email and password.');
        }
        if (Auth::lockedOut($email)) {
            $this->fail($back, 'Too many failed attempts. Try again in 15 minutes.');
        }
        if (!Auth::attempt($email, $password)) {
            // Identical for an unknown address and a wrong password, so this
            // form cannot be used to enumerate accounts.
            $this->fail($back, 'Those details did not match.');
        }

        // Credentials were right but this login does not belong to the area.
        // Ending the session here is what stops the guard bouncing the user
        // back to this same form forever.
        $entitled = $area === 'superadmin' ? Auth::isStaff() : Auth::account() !== null;
        if (!$entitled) {
            Auth::logout();
            $this->fail($back, $area === 'superadmin'
                ? 'That login does not have staff access.'
                : 'That login is not linked to a business account.');
        }

        Request::redirect("/{$area}");
    }

    public function logout(string $area): void
    {
        $this->assertArea($area);
        if (Csrf::check($_POST['_csrf'] ?? null)) {
            Auth::logout();
        }
        Request::redirect("/{$area}/login");
    }

    private function assertArea(string $area): void
    {
        if (!isset(self::AREAS[$area])) {
            throw new \InvalidArgumentException("Unknown area: {$area}");
        }
    }

    private function fail(string $back, string $message): never
    {
        $_SESSION['login_error'] = $message;
        Request::redirect($back);
    }
}
