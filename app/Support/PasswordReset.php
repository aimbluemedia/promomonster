<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Forgotten-password links.
 *
 * The whole flow lives here rather than in the controller, because almost none
 * of it is about HTTP. What makes a reset safe is when a token dies, who is
 * allowed one, and what a stranger can learn by asking -- all of which is
 * testable without a request, and none of which should be spread across a
 * controller where the next person to touch the form can quietly undo it.
 *
 * The rules
 * ---------
 *  - Asking looks identical whether or not the address exists. An account-
 *    recovery form is the one place on a site that will happily confirm who has
 *    an account, and a list of confirmed customer addresses is worth money to
 *    whoever is compiling it.
 *  - The token is 32 random bytes. Only its sha256 is stored, so the database
 *    cannot be read for live links.
 *  - One hour, one use. Asking again kills the previous link.
 *  - Staff logins are out of scope: see member() below.
 */
final class PasswordReset
{
    /** Matches PasswordController, so the two password forms agree. */
    public const MIN_LENGTH = 12;

    /**
     * Long enough that the link is not worth guessing, and the reason no
     * throttle is needed on a correct-looking token: 2^256 is not a space
     * anybody searches.
     */
    private const TOKEN_BYTES = 32;

    /**
     * An hour. Short enough that a link sitting in an unattended mailbox stops
     * mattering quickly; long enough for somebody who asked, went to lunch and
     * came back.
     */
    private const LIFETIME_MINUTES = 60;

    /** Requests allowed per address, and per asking IP, in an hour. */
    private const PER_ADDRESS = 3;
    private const PER_IP = 12;
    private const WINDOW = 3600;

    /** Spent and expired rows are kept a week for forensics, then dropped. */
    private const KEEP_DAYS = 7;

    /** Cached answer from ready(), so one request asks at most once. */
    private static ?bool $ready = null;

    /**
     * A temporary password somebody has to read down a phone line.
     *
     * Which is the whole specification. The alphabet drops every character
     * that sounds or looks like another one -- no i or l or 1, no o or 0, no
     * s next to 5 -- because the failure mode here is not somebody guessing
     * it, it is the owner saying "e" and the customer hearing "b" and then
     * both of them deciding the product is broken. Grouped in fours for the
     * same reason: you can read a group, pause, and be believed.
     *
     * Still 16 characters from a 31-character alphabet, which is about 79 bits.
     * Being easy to say is not the same as being easy to guess.
     */
    public static function temporaryPassword(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        $last     = strlen($alphabet) - 1;
        $groups   = [];

        for ($g = 0; $g < 4; $g++) {
            $group = '';
            for ($i = 0; $i < 4; $i++) {
                // random_int, not rand(): this is a credential, and modulo bias
                // on a 31-character alphabet is a real skew, not a rounding
                // error.
                $group .= $alphabet[random_int(0, $last)];
            }
            $groups[] = $group;
        }

        return implode('-', $groups);
    }

    /**
     * Kills any outstanding reset links for a user.
     *
     * Called when their password is set by hand: the account has just been
     * recovered by another route, and a link issued before that should not
     * still open it. Safe before the migration has run.
     */
    public static function revokeFor(int $userId): void
    {
        if (!self::ready()) {
            return;
        }

        Database::run(
            'DELETE FROM password_resets WHERE user_id = :uid AND used_at IS NULL',
            ['uid' => $userId],
        );
    }

    public static function lifetimeMinutes(): int
    {
        return self::LIFETIME_MINUTES;
    }

    /**
     * Whether the table this feature lives in is actually there.
     *
     * It is checked rather than assumed because of how this site is deployed:
     * files go up by FTP and migrations are run separately, by hand, through
     * phpMyAdmin. Between those two steps every member can see a "Forgot your
     * password?" link that leads to a form that cannot work -- and the people
     * following that link are, by definition, the ones already locked out. An
     * unhandled query error there is a white error page for somebody who
     * already cannot get in.
     *
     * So the page asks first and says plainly that the feature is not switched
     * on yet, which is the same thing the Get reviews page does about sending.
     * diagnose.php names the pending migration for whoever has to fix it.
     */
    public static function ready(): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }

        try {
            $row = Database::first(
                'SELECT 1 AS present FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = \'password_resets\'
                  LIMIT 1',
            );
        } catch (\PDOException $e) {
            // Cannot reach the database at all. Not this feature's problem to
            // report, and not a reason to throw from a status check.
            return self::$ready = false;
        }

        return self::$ready = $row !== null;
    }

    /**
     * Ask for a link.
     *
     * Returns nothing, on purpose. There is no outcome a caller could report
     * without reporting whether the address has an account, so there is nothing
     * to return -- the page above says "if that address has an account, the
     * link is on its way" either way, and means it.
     */
    public static function request(string $email): void
    {
        if (!self::ready()) {
            self::skipped('the password_resets table does not exist yet');
            return;
        }

        $email = mb_strtolower(trim($email));
        if ($email === '' || !Mailer::isSendableAddress($email)) {
            self::skipped('that was not a usable email address');
            return;
        }

        // The IP bucket first. Somebody working through a list of addresses
        // burns it on their second or third guess, before any of those guesses
        // reach the address bucket.
        if (RateLimiter::tooManyAttempts('pwreset-ip:' . Request::ip(), self::PER_IP, self::WINDOW)) {
            self::skipped(sprintf(
                'this IP has asked %d times in the last hour, which is the limit',
                self::PER_IP,
            ));
            return;
        }
        // And per address, so one person's mailbox cannot be used as a way to
        // send them a dozen emails from us.
        if (RateLimiter::tooManyAttempts('pwreset-address:' . $email, self::PER_ADDRESS, self::WINDOW)) {
            self::skipped(sprintf(
                'that address has asked %d times in the last hour, which is the limit',
                self::PER_ADDRESS,
            ));
            return;
        }

        self::purge();

        $user = self::member($email);
        if ($user === null) {
            self::skipped(
                'no active, non-staff member account with that address (check the spelling, '
                . 'and that the account has a business attached to it)',
            );
            return;
        }

        $userId = (int) $user['id'];

        // Anything outstanding is dead the moment a new one is issued. Deleted
        // rather than marked used, because it was never used: used_at is
        // evidence that somebody clicked, and it should not also mean "we
        // replaced this".
        Database::run(
            'DELETE FROM password_resets WHERE user_id = :uid AND used_at IS NULL',
            ['uid' => $userId],
        );

        $token = bin2hex(random_bytes(self::TOKEN_BYTES));

        Database::run(
            'INSERT INTO password_resets (user_id, token_hash, requested_ip, expires_at)
             VALUES (:uid, :hash, :ip, :expires)',
            [
                'uid'     => $userId,
                'hash'    => self::hash($token),
                'ip'      => Request::ip(),
                'expires' => date('Y-m-d H:i:s', time() + (self::LIFETIME_MINUTES * 60)),
            ],
        );

        Audit::log('auth.password_reset_requested', 'user', $userId);

        self::send($user, $token);
    }

    /**
     * The live reset this token names, or null.
     *
     * Read-only: finding a token is not spending it. The form has to be shown
     * before it can be filled in, and a link that died on being looked at would
     * be a link nobody could use.
     *
     * @return array{id:int, user_id:int, email:string, first_name:string}|null
     */
    public static function find(string $token): ?array
    {
        // Shape first. A token is 64 hex characters; anything else is somebody
        // probing, and it can be refused without touching the database.
        if (strlen($token) !== self::TOKEN_BYTES * 2 || !ctype_xdigit($token)) {
            return null;
        }

        // No table, no live links: nothing to find, and nothing to crash on.
        if (!self::ready()) {
            return null;
        }

        $row = Database::first(
            'SELECT pr.id, pr.user_id, u.email, u.first_name
               FROM password_resets pr
               JOIN users u ON u.id = pr.user_id
              WHERE pr.token_hash = :hash
                AND pr.used_at IS NULL
                AND pr.expires_at > NOW()
                AND u.status = \'active\'
                AND u.is_admin = 0
                AND EXISTS (SELECT 1 FROM account_users au WHERE au.user_id = u.id)
              LIMIT 1',
            ['hash' => self::hash($token)],
        );

        if ($row === null) {
            return null;
        }

        return [
            'id'         => (int) $row['id'],
            'user_id'    => (int) $row['user_id'],
            'email'      => (string) $row['email'],
            'first_name' => (string) $row['first_name'],
        ];
    }

    /**
     * Spend the link and set the password.
     *
     * False means the link was already spent between the form being shown and
     * this being called -- a double submit, or two tabs. The UPDATE is the
     * check: it matches on used_at IS NULL, so whichever request gets there
     * first is the only one that changes a row, and the loser is told the link
     * has gone rather than both of them setting a password.
     *
     * @param array{id:int, user_id:int, email:string, first_name:string} $reset
     */
    public static function complete(array $reset, string $password): bool
    {
        $spent = Database::run(
            'UPDATE password_resets SET used_at = NOW() WHERE id = :id AND used_at IS NULL',
            ['id' => $reset['id']],
        );

        if ($spent->rowCount() !== 1) {
            return false;
        }

        Auth::setPassword($reset['user_id'], $password);

        // Any other link for this account dies too. Two outstanding links
        // cannot normally exist -- request() clears the old one -- but if one
        // ever does, the account has just been recovered and nothing older
        // should still open it.
        Database::run(
            'DELETE FROM password_resets WHERE user_id = :uid AND used_at IS NULL',
            ['uid' => $reset['user_id']],
        );

        // The failed sign-ins that sent them here. Left in place they keep
        // Auth::lockedOut() true for fifteen minutes, so the person would set a
        // new password and then be refused for using it.
        Database::run(
            'DELETE FROM login_attempts WHERE successful = 0 AND email = :email',
            ['email' => mb_strtolower($reset['email'])],
        );

        Audit::log('auth.password_reset_completed', 'user', $reset['user_id']);

        return true;
    }

    // -- Internals ---------------------------------------------------------

    /**
     * The member this address belongs to, or null.
     *
     * Two exclusions, both deliberate.
     *
     * is_admin = 0, because this is the members form and staff do not sign in
     * through it -- the members login refuses them by design. A public form
     * that can retarget a staff password is a much larger thing to leave on the
     * internet than one that can retarget a customer's, and staff have
     * bin/create-admin.php and direct database access instead.
     *
     * The account_users check, because a user with no account cannot reach
     * anything behind the members login anyway. Sending them a link to a door
     * that will not open for them is worse than sending nothing.
     *
     * @return array<string,mixed>|null
     */
    private static function member(string $email): ?array
    {
        return Database::first(
            'SELECT u.id, u.email, u.first_name
               FROM users u
               JOIN account_users au ON au.user_id = u.id
              WHERE u.email = :email
                AND u.status = \'active\'
                AND u.is_admin = 0
           ORDER BY au.created_at
              LIMIT 1',
            ['email' => $email],
        );
    }

    /** @param array<string,mixed> $user */
    private static function send(array $user, string $token): void
    {
        $first = trim((string) ($user['first_name'] ?? ''));
        $greeting = $first === '' ? 'Hello,' : 'Hi ' . $first . ',';
        $minutes = self::LIFETIME_MINUTES;

        $text = implode("\n", [
            $greeting,
            '',
            'Somebody asked to reset the password for the ' . Config::get('app_name', 'PromoMonster')
                . ' account at ' . $user['email'] . '. If that was you, this link sets a new one:',
            '',
            self::url($token),
            '',
            'It works once, and it stops working in ' . $minutes . ' minutes.',
            '',
            'If it was not you, you can ignore this email. Nothing has changed and',
            'your current password still works.',
            '',
            '-- ' . Config::get('app_name', 'PromoMonster'),
        ]);

        // No List-Unsubscribe on this one. It is not a message anybody opted in
        // to and not one they can opt out of: offering to unsubscribe somebody
        // from their own account recovery would be a way to lock them out.
        $result = Mailer::send([
            'to'      => (string) $user['email'],
            'subject' => 'Reset your ' . Config::get('app_name', 'PromoMonster') . ' password',
            'text'    => $text,
            'tag'     => 'password-reset',
            // Account mail, not bulk. Its own address and its own stream, so
            // the spam complaints a business's review requests collect cannot
            // take the one email a locked-out customer actually needs with
            // them. See Mailer::transactionalFrom().
            'from_name' => Mailer::transactionalHeader(),
            'from'      => Mailer::transactionalFrom(),
            'stream'    => Mailer::transactionalStream(),
            'driver'    => Mailer::transactionalDriver(),
        ]);

        if (!$result['ok']) {
            // Nothing is shown to the person asking: telling them the send
            // failed would confirm the address exists. But it has to be visible
            // to whoever is wondering why no email arrived, and error_log()
            // was not that -- it goes to the server's log rather than the one
            // diagnose.php reads, so the page everybody checks stayed empty
            // while every send failed.
            ErrorHandler::note(
                'Password reset not sent',
                'driver "' . $result['driver'] . '" refused it: ' . (string) $result['error'],
            );
        }
    }

    /**
     * Records why no email went out, for staff eyes only.
     *
     * The page above says the same thing whichever of these happened, and it
     * has to: an account recovery form that distinguishes "no such address"
     * from "sent" is a way to test addresses against our customer list. But
     * that leaves an operator with one message for five different causes, and
     * "I updated everything and no emails arrive" has no next step.
     *
     * So the reason goes in the log diagnose.php reads. No address is recorded
     * with it -- whoever is debugging knows what they typed, and a log slowly
     * accumulating the addresses of people who are NOT customers is not
     * something to keep.
     */
    private static function skipped(string $reason): void
    {
        ErrorHandler::note('Password reset not sent', $reason);
    }

    private static function url(string $token): string
    {
        $base = rtrim((string) Config::get('app_url', 'https://promomonster.com'), '/');

        return $base . '/members/reset/' . $token;
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Drops rows whose link expired more than KEEP_DAYS ago. */
    private static function purge(): void
    {
        Database::run(
            'DELETE FROM password_resets WHERE expires_at < :cutoff',
            ['cutoff' => date('Y-m-d H:i:s', time() - (self::KEEP_DAYS * 86400))],
        );
    }
}
