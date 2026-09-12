<?php

declare(strict_types=1);

/**
 * Browser-based superadmin password reset, for hosting with no shell access.
 *
 *   1. Open this file and set RESET_TOKEN to a long random string.
 *   2. Upload it into public_html.
 *   3. Visit  https://your-domain/reset-admin.php?token=YOUR-TOKEN
 *   4. DELETE IT from the server the moment you are signed in.
 *
 * bin/create-admin.php is deliberately CLI-only so that no web route can mint an
 * administrator. This file is the shared-hosting exception to that rule, and it
 * is narrowed to match:
 *
 *   - It does nothing at all until a token is set, and 404s on a wrong one.
 *   - It can only RESET an account that is already an admin. Creating one is
 *     possible only while the database contains no admin at all, so it cannot be
 *     used to add a second, quieter way in.
 *   - Every reset is written to the audit log.
 *
 * It is still a password reset sitting on a public URL. Delete it when done.
 */

const RESET_TOKEN = '';

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$here = __DIR__;
$base = is_file($here . '/app/bootstrap.php') ? $here
      : (is_file(dirname($here) . '/app/bootstrap.php') ? dirname($here) : null);

function page(string $title, string $body, string $tone = 'info'): never
{
    $colour = ['info' => '#0475a3', 'good' => '#1a7f4b', 'bad' => '#b3261e'][$tone] ?? '#0475a3';
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>' . htmlspecialchars($title) . '</title>'
       . '<style>body{font:15px/1.6 system-ui,-apple-system,Segoe UI,sans-serif;margin:0;background:#f6f8fa;color:#1c2024}'
       . 'main{max-width:680px;margin:0 auto;padding:32px 20px}'
       . 'h1{font-size:22px;margin:0 0 4px;color:' . $colour . '}'
       . 'p.lede{margin:0 0 24px;color:#5b646e}'
       . 'table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #dfe3e8;border-radius:8px;overflow:hidden}'
       . 'td,th{padding:10px 12px;border-bottom:1px solid #eef1f4;text-align:left;font-size:14px}'
       . 'th{background:#f0f3f6;font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#5b646e}'
       . 'tr:last-child td{border-bottom:0}'
       . 'code,pre{font:13px/1.5 ui-monospace,SFMono-Regular,Menlo,monospace}'
       . 'pre{white-space:pre-wrap;background:#fff;border:1px solid #dfe3e8;border-radius:8px;padding:12px}'
       . 'input[type=email]{font:15px system-ui;padding:10px 12px;border:1px solid #cbd2d9;border-radius:8px;width:100%;max-width:360px;box-sizing:border-box}'
       . 'button{font:600 15px system-ui;background:#0475a3;color:#fff;border:0;border-radius:8px;padding:12px 22px;cursor:pointer;margin-top:16px}'
       . '.pw{font:700 22px ui-monospace,SFMono-Regular,Menlo,monospace;letter-spacing:.06em;'
       . 'background:#fff;border:2px solid #1a7f4b;border-radius:8px;padding:16px;text-align:center;margin:16px 0;user-select:all}'
       . '.note{margin-top:24px;padding:12px 14px;background:#fff8e6;border:1px solid #f0dca8;border-radius:8px;font-size:14px}'
       . '</style><main><h1>' . htmlspecialchars($title) . '</h1>' . $body . '</main>';
    exit;
}

/** Readable one-time password; ambiguous characters left out so it survives being copied by hand. */
function temporaryPassword(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $groups = [];
    for ($g = 0; $g < 4; $g++) {
        $chunk = '';
        for ($i = 0; $i < 5; $i++) {
            $chunk .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $groups[] = $chunk;
    }

    return implode('-', $groups);
}

if (RESET_TOKEN === '') {
    page('Set a token first',
        '<p class="lede">This reset page is disabled until you give it a password.</p>'
        . '<p>Open <code>reset-admin.php</code>, change the line</p>'
        . '<pre>const RESET_TOKEN = \'\';</pre>'
        . '<p>to a long random string, re-upload it, then visit this page with '
        . '<code>?token=</code> followed by that string.</p>', 'bad');
}

if (!hash_equals(RESET_TOKEN, (string) ($_GET['token'] ?? $_POST['token'] ?? ''))) {
    http_response_code(404);
    page('Not found', '<p class="lede">No such page.</p>', 'bad');
}

if ($base === null) {
    page('Cannot find the application',
        '<p class="lede">Upload this file into <code>public_html</code>, alongside the '
        . '<code>app</code> folder.</p>', 'bad');
}

require $base . '/app/bootstrap.php';

use App\Support\Database;

try {
    Database::connection();
} catch (Throwable $e) {
    page('Cannot reach the database',
        '<p class="lede">Check the <code>db</code> settings in <code>app/config.php</code>.</p>'
        . '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>', 'bad');
}

$admins = Database::all(
    'SELECT id, email, status, must_change_password, password_changed_at
       FROM users WHERE is_admin = 1 ORDER BY id'
);
$tokenField = '<input type="hidden" name="token" value="' . htmlspecialchars(RESET_TOKEN) . '">';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        page('That is not an email address', '<p class="lede">Go back and try again.</p>', 'bad');
    }

    $user = Database::first('SELECT id, is_admin FROM users WHERE email = :email', ['email' => $email]);

    // Reset an existing admin, or bootstrap the very first one. Never add a
    // second admin to a database that already has one.
    if ($user === null && $admins !== []) {
        page('No such admin',
            '<p class="lede">There is no account with that address, and this page will not '
            . 'create a new administrator while one already exists.</p>'
            . '<p>Reset one of the accounts listed on the previous page instead.</p>', 'bad');
    }
    if ($user !== null && (int) $user['is_admin'] !== 1) {
        page('Not an administrator',
            '<p class="lede">That account exists but is not an admin, and this page will not '
            . 'promote it. Use <code>php bin/create-admin.php</code> for that.</p>', 'bad');
    }

    $password = temporaryPassword();
    $hash = password_hash($password, PASSWORD_DEFAULT);

    if ($user === null) {
        Database::run(
            "INSERT INTO users (email, password_hash, first_name, last_name, is_admin, status,
                                must_change_password, email_verified_at)
             VALUES (:email, :hash, 'Site', 'Owner', 1, 'active', 1, NOW())",
            ['email' => $email, 'hash' => $hash],
        );
        $userId = (int) Database::connection()->lastInsertId();
        $what = 'Created the first administrator';
    } else {
        Database::run(
            "UPDATE users
                SET password_hash = :hash, status = 'active', must_change_password = 1,
                    password_changed_at = NULL,
                    email_verified_at = COALESCE(email_verified_at, NOW())
              WHERE id = :id",
            ['hash' => $hash, 'id' => $user['id']],
        );
        $userId = (int) $user['id'];
        $what = 'Reset the password for';
    }

    // A lockout from the failed attempts that brought you here would otherwise
    // keep you out with the new password too.
    $cleared = 0;
    try {
        $stmt = Database::run('DELETE FROM login_attempts WHERE email = :email', ['email' => $email]);
        $cleared = $stmt->rowCount();
    } catch (Throwable) {
        // Table missing means migration 013 has not run. The reset still stands.
    }

    $logged = false;
    try {
        Database::run(
            "INSERT INTO audit_log (actor_user_id, action, target_type, target_id, after_state, ip)
             VALUES (:actor, 'admin.password_reset', 'user', :target, :state, :ip)",
            [
                'actor'  => $userId,
                'target' => $userId,
                'state'  => json_encode(['via' => 'reset-admin.php', 'email' => $email]),
                'ip'     => $_SERVER['REMOTE_ADDR'] ?? null,
            ],
        );
        $logged = true;
    } catch (Throwable) {
        // Never let logging swallow the reset itself — but say so below rather
        // than claiming an audit entry that was not written.
    }

    page('Temporary password created',
        '<p class="lede">' . htmlspecialchars($what) . ' <strong>'
        . htmlspecialchars($email) . '</strong>.</p>'
        . '<div class="pw">' . htmlspecialchars($password) . '</div>'
        . '<p>Sign in at <a href="/superadmin/login">/superadmin/login</a> with that address and '
        . 'this password. You will be asked to choose your own straight away.</p>'
        . '<p>It is shown here once and is not stored anywhere in plain text. If you lose it, '
        . 'run this page again.</p>'
        . ($cleared > 0 ? '<p>Cleared ' . $cleared . ' recent sign-in attempt(s), so any lockout is lifted.</p>' : '')
        . ($logged ? '' : '<p>Note: the audit_log entry could not be written, so this reset is '
            . 'not recorded there. The reset itself went through.</p>')
        . '<div class="note"><strong>Delete <code>reset-admin.php</code> from the server now.</strong> '
        . 'Leave it there and anyone who learns the token can take the account.</div>', 'good');
}

if ($admins === []) {
    page('No administrator yet',
        '<p class="lede">This database has no admin account. Enter the address you want to sign '
        . 'in with and one will be created.</p>'
        . '<form method="post">' . $tokenField
        . '<input type="email" name="email" required placeholder="you@promomonster.com" autofocus>'
        . '<button type="submit">Create administrator</button></form>');
}

$rows = '';
foreach ($admins as $admin) {
    $rows .= '<tr><td><code>' . htmlspecialchars((string) $admin['email']) . '</code></td>'
          . '<td>' . htmlspecialchars((string) $admin['status']) . '</td>'
          . '<td>' . ((int) $admin['must_change_password'] === 1
              ? 'Temporary — must change on sign-in'
              : ($admin['password_changed_at'] === null ? 'Never changed' : 'Set ' . htmlspecialchars((string) $admin['password_changed_at'])))
          . '</td></tr>';
}

page('Reset a superadmin password',
    '<p class="lede">These are the admin accounts on this database. Enter one of the addresses '
    . 'below to be given a new temporary password.</p>'
    . '<table><tr><th>Email</th><th>Status</th><th>Password</th></tr>' . $rows . '</table>'
    . '<form method="post">' . $tokenField
    . '<p style="margin:20px 0 8px;font-weight:600;">Reset which account?</p>'
    . '<input type="email" name="email" required placeholder="you@promomonster.com" autofocus>'
    . '<button type="submit">Generate a temporary password</button></form>'
    . '<div class="note">Delete this file from the server as soon as you are signed in.</div>');
