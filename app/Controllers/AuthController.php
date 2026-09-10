<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Request;
use App\Support\View;

final class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            Request::redirect('/admin');
        }
        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);

        echo View::render('admin/login', [
            'title' => 'Sign in · PromoMonster',
            'error' => $error,
        ]);
    }

    public function login(): void
    {
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $this->fail('Your session expired. Please try again.');
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $this->fail('Enter your email and password.');
        }

        if (Auth::lockedOut($email)) {
            $this->fail('Too many failed attempts. Try again in 15 minutes.');
        }

        if (!Auth::attempt($email, $password)) {
            // Deliberately identical for an unknown address and a wrong
            // password, so this form cannot enumerate accounts.
            $this->fail('Those details did not match.');
        }

        Request::redirect('/admin');
    }

    public function logout(): void
    {
        if (Csrf::check($_POST['_csrf'] ?? null)) {
            Auth::logout();
        }
        Request::redirect('/admin/login');
    }

    private function fail(string $message): never
    {
        $_SESSION['login_error'] = $message;
        Request::redirect('/admin/login');
    }
}
