<?php

declare(strict_types=1);

/**
 * One page, one copy, one paste.
 *
 * Written after too many rounds of "still no email" answered with "read this
 * row and tell me what it says". That loop does not converge: each round
 * reports one fact, and the fact that mattered was never the one asked for.
 *
 * This prints everything at once, as plain text, in the order it would be
 * diagnosed: what is actually deployed, what the config resolves to, what the
 * forgot-password page will therefore do, and what the mail server says when
 * something really connects to it and logs in.
 *
 *   1. Upload to public_html, beside diagnose.php.
 *   2. Open https://your-domain/support.php
 *   3. Select all, copy, paste it back.
 *   4. DELETE IT from the server.
 *
 * No secret is printed. Passwords and tokens appear as a length and nothing
 * else -- this page has no login on it, and a support dump that leaks the
 * mailbox password is worse than the fault it is diagnosing.
 */

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$here = __DIR__;
$base = is_file($here . '/app/bootstrap.php') ? $here
      : (is_file(dirname($here) . '/app/bootstrap.php') ? dirname($here) : null);

$out = [];
$say = static function (string $line = '') use (&$out): void { $out[] = $line; };

$say('=== PROMOMONSTER SUPPORT DUMP ===');
$say('generated  ' . date('Y-m-d H:i:s T'));
$say('php        ' . PHP_VERSION);
$say('host       ' . (string) ($_SERVER['HTTP_HOST'] ?? '?'));
$say('this file  ' . $here);
$say('app root   ' . ($base ?? 'NOT FOUND - app/bootstrap.php is not here or one level up'));
$say('docroot    ' . (string) ($_SERVER['DOCUMENT_ROOT'] ?? '?'));
$say();

if ($base === null) {
    $say('Cannot continue: the application was not found from here.');
    echo implode("\n", $out), "\n";
    exit;
}

/* ---- 1. What is actually deployed -------------------------------------- */
/* The marker is a string only the current version of each file contains. Every
   other check reads config, so a stale file reports a flawless setup. */
$say('--- FILES (marker = a string only the current version contains) ---');
foreach ([
    'app/Controllers/PasswordResetController.php' => 'transactionalIsLive',
    'app/Support/PasswordReset.php'               => 'transactionalDriver',
    'app/Support/Mailer.php'                      => 'transactionalIsLive',
    'app/Support/Smtp.php'                        => 'STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT',
    'app/Support/ErrorHandler.php'                => 'function note',
    'app/Support/Auth.php'                        => 'setTemporaryPassword',
    'app/Views/members/forgot.php'                => 'ready',
    'app/Views/members/reset.php'                 => 'data-eye',
    'app/.htaccess'                               => null,
    'storage/.htaccess'                           => null,
] as $rel => $marker) {
    $full = $base . '/' . $rel;
    if (!is_file($full)) {
        $say(sprintf('  %-46s MISSING', $rel));
        continue;
    }
    $body = (string) @file_get_contents($full);
    $state = $marker === null ? 'present'
        : (str_contains($body, $marker) ? 'current' : '*** STALE - OLD COPY ***');
    $say(sprintf('  %-46s %6s  %s  %s', $rel, strlen($body), date('Y-m-d H:i', (int) filemtime($full)), $state));
}
$say();

/* ---- 2. What the config resolves to ------------------------------------ */
$config = is_file($base . '/app/config.php') ? require $base . '/app/config.php' : null;
if (!is_array($config)) {
    $say('--- CONFIG --- app/config.php is missing or does not return an array.');
    echo implode("\n", $out), "\n";
    exit;
}

$mail = is_array($config['mail'] ?? null) ? $config['mail'] : [];
$smtp = is_array($mail['smtp'] ?? null) ? $mail['smtp'] : [];
$len  = static fn (mixed $v): string => trim((string) $v) === '' ? '(empty)' : strlen(trim((string) $v)) . ' chars';

$say('--- CONFIG (secrets shown as a length only) ---');
$say('  app_key                   ' . $len($config['app_key'] ?? ''));
$say('  mail.driver               ' . var_export($mail['driver'] ?? null, true));
$say('  mail.token                ' . $len($mail['token'] ?? ''));
$say('  mail.from                 ' . var_export($mail['from'] ?? null, true));
$say('  mail.transactional_driver ' . var_export($mail['transactional_driver'] ?? null, true));
$say('  mail.transactional_from   ' . var_export($mail['transactional_from'] ?? null, true));
$say('  mail.smtp.host            ' . var_export($smtp['host'] ?? null, true));
$say('  mail.smtp.port            ' . var_export($smtp['port'] ?? null, true));
$say('  mail.smtp.encryption      ' . var_export($smtp['encryption'] ?? null, true));
$say('  mail.smtp.username        ' . var_export($smtp['username'] ?? null, true));
$say('  mail.smtp.password        ' . $len($smtp['password'] ?? ''));
$say();

/* ---- 3. What the page will therefore do -------------------------------- */
define('APP_ROOT', $base . '/app');
define('BASE_PATH', $base);
spl_autoload_register(static function (string $class) use ($base): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $f = $base . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($f)) {
        require $f;
    }
});

$say('--- WHAT THE PAGE WILL DO ---');
try {
    App\Support\Config::load($config);
    $lane = App\Support\Mailer::transactionalDriver();
    $live = App\Support\Mailer::transactionalIsLive();
    $say('  account lane resolves to  ' . $lane);
    $say('  transactionalIsLive()     ' . var_export($live, true));
    $say('  transactionalFrom()       ' . App\Support\Mailer::transactionalFrom());
    $say('  /members/forgot will      ' . ($live
        ? 'show the form and try to send'
        : 'show "Email sending is not switched on yet"'));

    // The bulk lane is a separate question with a separate answer, and the
    // Google reviews page shows the same wording for it. Reading only the
    // account lane here is how "email works" and "the page still says sending
    // is off" both end up true with nothing to explain the gap.
    $bulk     = App\Support\Mailer::driver();
    $bulkLive = App\Support\Mailer::isLive();
    $say('  bulk lane resolves to     ' . $bulk);
    $say('  isLive()                  ' . var_export($bulkLive, true));
    $say('  from()                    ' . App\Support\Mailer::from());
    $say('  /members/reviews will     ' . ($bulkLive
        ? 'send review requests (once the cron job is running)'
        : 'show "Email sending is not switched on yet"'));
    $say('  why                       ' . App\Support\Mailer::status());
} catch (Throwable $e) {
    $say('  FAILED: ' . get_class($e) . ': ' . $e->getMessage());
}
$say();

/* ---- 4. What the mail server actually says ----------------------------- */
/* Connect, start TLS, log in, hang up. Nothing is sent. This is the step no
   configuration check can stand in for, and the one never yet reported. */
$say('--- LIVE SMTP TEST (connect + log in, no message sent) ---');
$host = trim((string) ($smtp['host'] ?? ''));
$user = trim((string) ($smtp['username'] ?? ''));
$pass = (string) ($smtp['password'] ?? '');
$port = (int) ($smtp['port'] ?? 465);
$enc  = strtolower(trim((string) ($smtp['encryption'] ?? ''))) ?: ($port === 587 ? 'tls' : 'ssl');

if ($host === '' || $user === '' || $pass === '') {
    $say('  skipped: host, username or password is empty.');
} else {
    $read = static function ($fp): string {
        $all = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $all .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        return rtrim($all);
    };
    $send = static function ($fp, string $cmd): void { fwrite($fp, $cmd . "\r\n"); };
    $code = static fn (string $r): int => (int) substr(ltrim($r), 0, 3);

    $ctx = stream_context_create(['ssl' => [
        'verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false,
        'peer_name' => $host,
        'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
    ]]);

    $target = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $say('  connecting to ' . $target . ' (' . $enc . ')');
    $fp = @stream_socket_client($target, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);

    if ($fp === false) {
        $say('  *** COULD NOT CONNECT: ' . ($errstr !== '' ? $errstr : 'error ' . $errno));
        $say('      Wrong host or port, TLS refused, or the server blocks outbound mail ports.');
    } else {
        stream_set_timeout($fp, 15);
        $say('  greeting   ' . $read($fp));
        $me = (string) (parse_url((string) ($config['app_url'] ?? 'https://example.com'), PHP_URL_HOST) ?: 'example.com');

        $send($fp, 'EHLO ' . $me);
        $ehlo = $read($fp);
        $say('  EHLO       ' . str_replace("\r\n", ' | ', $ehlo));

        if ($enc === 'tls') {
            $send($fp, 'STARTTLS');
            $say('  STARTTLS   ' . $read($fp));
            $ok = @stream_socket_enable_crypto($fp, true,
                STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
            $say('  TLS        ' . ($ok === true ? 'established' : '*** FAILED (certificate or version)'));
            if ($ok === true) {
                $send($fp, 'EHLO ' . $me);
                $ehlo = $read($fp);
                $say('  EHLO again ' . str_replace("\r\n", ' | ', $ehlo));
            }
        }

        // The password is base64 on the wire, which is encoding and not
        // secrecy: the command is named, never echoed.
        $send($fp, 'AUTH LOGIN');
        $say('  AUTH       ' . $read($fp));
        $send($fp, base64_encode($user));
        $say('  username   ' . $read($fp));
        $send($fp, base64_encode($pass));
        $authReply = $read($fp);
        $say('  password   ' . $authReply);
        $say('  RESULT     ' . ($code($authReply) === 235
            ? 'LOGGED IN. The mailbox works; anything missing after this is delivery.'
            : '*** LOGIN REFUSED. The mailbox password or username is wrong.'));

        $send($fp, 'QUIT');
        fclose($fp);
    }
}
$say();

/* ---- 5. The database and the log --------------------------------------- */
$say('--- DATABASE ---');
try {
    $db = is_array($config['db'] ?? null) ? $config['db'] : [];
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $db['host'] ?? 'localhost', (int) ($db['port'] ?? 3306), $db['database'] ?? ''),
        (string) ($db['username'] ?? ''), (string) ($db['password'] ?? ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
    );
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $say('  connected, ' . count($tables) . ' tables');
    $say('  password_resets table     ' . (in_array('password_resets', $tables, true) ? 'yes' : 'NO'));
    if (in_array('password_resets', $tables, true)) {
        $r = $pdo->query('SELECT COUNT(*) n, MAX(created_at) newest FROM password_resets')->fetch(PDO::FETCH_ASSOC);
        $say('  reset links ever issued   ' . (int) ($r['n'] ?? 0) . ', newest ' . ((string) ($r['newest'] ?? '-')));
    }
    $applied = in_array('migrations', $tables, true)
        ? $pdo->query('SELECT filename FROM migrations')->fetchAll(PDO::FETCH_COLUMN) : [];
    $files = array_map('basename', glob($base . '/database/migrations/*.sql') ?: []);
    $pending = array_values(array_diff($files, $applied));
    $say('  migrations pending        ' . ($pending === [] ? 'none' : implode(', ', $pending)));
} catch (Throwable $e) {
    $say('  FAILED: ' . $e->getMessage());
}
$say();

$say('--- LAST 8 LOG ENTRIES ---');
$log = $base . '/storage/logs/error.log';
if (!is_file($log)) {
    $say('  no error.log');
} else {
    $blocks = array_filter(
        preg_split('/\n(?=\[\d{4}-)/', trim((string) @file_get_contents($log))) ?: [],
        static fn (string $b): bool => trim($b) !== '',
    );
    foreach (array_slice(array_reverse(array_values($blocks)), 0, 8) as $b) {
        $say('  ' . substr(explode("\n", trim($b))[0], 0, 200));
    }
    if ($blocks === []) {
        $say('  empty');
    }
}
$say();
$say('=== END. Copy everything above, then DELETE support.php from the server. ===');

echo implode("\n", $out), "\n";
