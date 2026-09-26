<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Mailer;
use App\Support\PasswordReset;
use App\Support\RateLimiter;
use App\Support\Request;
use App\Support\View;

/**
 * Forgotten password, for the members login.
 *
 * Four routes: ask for a link, and use one. The decisions that matter are all
 * in PasswordReset -- this is the HTTP around them.
 *
 * Not wired up for /superadmin. Staff passwords are set from the command line,
 * and a public form that can send a reset link for a staff login is a bigger
 * thing to have on the internet than one that can do it for a customer's.
 */
final class PasswordResetController
{
    /**
     * Bad tokens per IP per hour, after which the dead-link page is all anybody
     * gets. Not a defence against guessing -- a 256-bit token needs none -- but
     * it stops this route being a free oracle to hammer.
     */
    private const BAD_TOKENS = 20;
    private const BAD_TOKEN_WINDOW = 3600;

    /** GET /members/forgot */
    public function showForgot(): void
    {
        if (Auth::account() !== null) {
            Request::redirect('/members');
        }

        $error = $_SESSION['forgot_error'] ?? null;
        $sent  = $_SESSION['forgot_sent'] ?? null;
        unset($_SESSION['forgot_error'], $_SESSION['forgot_sent']);

        echo View::render('members/forgot', [
            'title'   => 'Reset your password',
            'error'   => is_string($error) ? $error : null,
            'sent'    => is_string($sent) ? $sent : null,
            'minutes' => PasswordReset::lifetimeMinutes(),
            // The files can be on the server before the migration has been run
            // through phpMyAdmin, and in that window this page is the one thing
            // a locked-out member will try. Say so rather than fail on submit.
            'ready'   => PasswordReset::ready(),
            // Said out loud rather than left as a mystery. Until Postmark is
            // connected a reset email goes to storage/logs/mail.log, which is
            // no use to the person on this page, and "check your inbox" would
            // be a straight lie.
            'sending' => Mailer::transactionalIsLive(),
        ]);
    }

    /** POST /members/forgot */
    public function sendLink(): void
    {
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $this->failForgot('Your session expired. Please try again.');
        }

        // Honeypot, same as signup.
        if (trim((string) ($_POST['website_url'] ?? '')) !== '') {
            // Answer exactly as a success would, so a bot learns nothing about
            // having been caught.
            $_SESSION['forgot_sent'] = '';
            Request::redirect('/members/forgot');
        }

        if (!PasswordReset::ready()) {
            $this->failForgot(
                'Password reset is not switched on yet. Get in touch and we will sort it out by hand.',
            );
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        if ($email === '') {
            $this->failForgot('Enter the email address you signed up with.');
        }

        PasswordReset::request($email);

        // Identical whether or not that address has an account. This is the
        // whole point: the alternative turns the form into a way to test
        // addresses against our customer list.
        //
        // Truncated before it goes in the session, because this field is echoed
        // back on the confirmation page and nothing stops somebody posting a
        // hundred kilobytes into it. Escaped on the way out either way; this is
        // so the page stays a page. A real address fits in 254.
        $_SESSION['forgot_sent'] = mb_substr($email, 0, 254);
        Request::redirect('/members/forgot');
    }

    /** GET /members/reset/{token} */
    public function showReset(string $token): void
    {
        $reset = $this->lookUp($token);

        $error = $_SESSION['reset_error'] ?? null;
        unset($_SESSION['reset_error']);

        echo View::render('members/reset', [
            'title' => $reset === null ? 'That link has expired' : 'Choose a new password',
            'reset' => $reset,
            // Echoed back into the form so the POST does not need the token in
            // its URL. A path shows up in access logs and in a Referer header;
            // a form field does neither.
            'token' => $reset === null ? '' : $token,
            'error' => is_string($error) ? $error : null,
            'min'   => PasswordReset::MIN_LENGTH,
        ]);
    }

    /** POST /members/reset */
    public function reset(): void
    {
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $this->failForgot('Your session expired. Ask for a new link and try again.');
        }

        $token = (string) ($_POST['token'] ?? '');
        $reset = $this->lookUp($token);

        if ($reset === null) {
            $this->failForgot('That link has expired or has already been used. Here is a fresh one.');
        }

        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['password_confirm'] ?? '');

        // Back to the same link on a mistake, not to the start. The token is
        // still good -- it is only spent by complete() -- so a typo in the
        // confirmation must not cost somebody their reset.
        $back = '/members/reset/' . rawurlencode($token);

        if (strlen($password) < PasswordReset::MIN_LENGTH) {
            $this->failReset($back, 'Use at least ' . PasswordReset::MIN_LENGTH . ' characters.');
        }
        if ($password !== $confirm) {
            $this->failReset($back, 'Those two passwords do not match.');
        }

        if (!PasswordReset::complete($reset, $password)) {
            // Lost the race with another submit of the same form. The password
            // is already set, so send them to sign in rather than claim failure.
            $this->failForgot('That link has already been used. Sign in, or ask for a new link.');
        }

        // Straight in, rather than back to the login form. They have just proved
        // control of the address on the account and set the password themselves,
        // and the form they would land on is the one that had been refusing
        // them -- for up to fifteen minutes more, if the failed attempts that
        // sent them here had tripped the lockout.
        Auth::signIn($reset['user_id'], 'auth.password_reset_login');
        $_SESSION['members_flash'] = 'Password updated. You are signed in.';
        Request::redirect('/members');
    }

    // -- Internals ---------------------------------------------------------

    /**
     * A token lookup with a throttle on the misses only.
     *
     * atLimit()/record() rather than tooManyAttempts(), because a correct token
     * must never consume allowance: somebody who reloads the reset form six
     * times is not attacking anything, and charging them for it would lock them
     * out of their own recovery.
     *
     * @return array{id:int, user_id:int, email:string, first_name:string}|null
     */
    private function lookUp(string $token): ?array
    {
        $bucket = 'pwreset-token:' . Request::ip();

        if (RateLimiter::atLimit($bucket, self::BAD_TOKENS, self::BAD_TOKEN_WINDOW)) {
            return null;
        }

        $reset = PasswordReset::find($token);
        if ($reset === null) {
            RateLimiter::record($bucket);
        }

        return $reset;
    }

    private function failForgot(string $message): never
    {
        $_SESSION['forgot_error'] = $message;
        Request::redirect('/members/forgot');
    }

    private function failReset(string $back, string $message): never
    {
        $_SESSION['reset_error'] = $message;
        Request::redirect($back);
    }
}
