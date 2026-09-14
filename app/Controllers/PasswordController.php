<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Request;
use App\Support\View;

/**
 * Forced password change. Reached only while must_change_password is set — the
 * area guards send every other route here until it is cleared.
 */
final class PasswordController
{
    private const MIN_LENGTH = 12;
    private const AREAS = ['superadmin', 'members'];

    public function show(string $area): void
    {
        $this->guard($area);

        $error = $_SESSION['password_error'] ?? null;
        unset($_SESSION['password_error']);

        echo View::render('auth/password', [
            'title' => 'Choose a password · PromoMonster',
            'area'  => $area,
            'error' => $error,
            'min'   => self::MIN_LENGTH,
        ]);
    }

    public function update(string $area): void
    {
        $this->guard($area);
        $back = "/{$area}/password";

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $this->fail($back, 'Your session expired. Please try again.');
        }

        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['password_confirm'] ?? '');
        $user = Auth::user();

        if ($user === null) {
            Request::redirect("/{$area}/login");
        }
        if (strlen($password) < self::MIN_LENGTH) {
            $this->fail($back, 'Use at least ' . self::MIN_LENGTH . ' characters.');
        }
        if ($password !== $confirm) {
            $this->fail($back, 'Those two passwords do not match.');
        }
        // Re-using the temporary password would defeat the point: it was
        // transmitted in the clear to hand the account over.
        if (Auth::attemptPasswordOnly((int) $user['id'], $password)) {
            $this->fail($back, 'Choose a different password from the temporary one.');
        }

        Auth::setPassword((int) $user['id'], $password);
        $_SESSION[$area === 'superadmin' ? 'admin_flash' : 'members_flash'] = 'Password updated.';
        Request::redirect("/{$area}");
    }

    /**
     * Signed in, entitled to this area, and actually required to change. Anyone
     * else has no business on this form.
     */
    private function guard(string $area): void
    {
        if (!in_array($area, self::AREAS, true)) {
            throw new \InvalidArgumentException("Unknown area: {$area}");
        }
        $entitled = $area === 'superadmin' ? Auth::isStaff() : Auth::account() !== null;
        if (!$entitled) {
            Request::redirect("/{$area}/login");
        }
        if (!Auth::mustChangePassword()) {
            Request::redirect("/{$area}");
        }
    }

    private function fail(string $back, string $message): never
    {
        $_SESSION['password_error'] = $message;
        Request::redirect($back);
    }
}
