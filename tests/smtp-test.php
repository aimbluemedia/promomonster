<?php

declare(strict_types=1);

/**
 * The SMTP client, against a real SMTP server over a real TLS socket.
 *
 * Not mocked, and it could not usefully be. Every way this class goes wrong is
 * a protocol fact at the far end of a socket: desynchronising on a multi-line
 * reply, losing a line that begins with a full stop, emitting a bare LF where
 * CRLF is required, or -- the one that actually happened -- quoting the
 * base64-encoded password into an error message. A stub that returns "250 OK"
 * sees none of that.
 *
 * It starts its own server (tests/fixtures/smtp-server.py) on two ports, one
 * speaking implicit TLS like 465 and one STARTTLS like 587, with a throwaway
 * certificate generated per run.
 *
 * The certificate is trusted the way a real one is: by being a CA the process
 * trusts, passed with php -d openssl.cafile=... Nothing in the client is
 * relaxed for the test -- verify_peer stays on throughout, which is the point
 * of the last two assertions.
 *
 *   php tests/smtp-test.php
 *
 * Skips cleanly with exit 0 when python3 or openssl is missing.
 */

define('APP_ROOT', dirname(__DIR__) . '/app');
define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = APP_ROOT . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Support\Config;
use App\Support\Mailer;

date_default_timezone_set('UTC');

const MAILBOX  = 'logins@promomonster.test';
const PASSWORD = 'mailbox-secret';

foreach (['python3', 'openssl'] as $binary) {
    exec('command -v ' . escapeshellarg($binary), $out, $code);
    if ($code !== 0) {
        echo "SKIP  {$binary} is not available\n";
        exit(0);
    }
}

// The child is handed the parent's directory, and must not make a second one:
// it would be left behind, and the certificate in it would not be the one the
// parent told this process to trust.
$child = ($argv[1] ?? '') !== '';
$dir   = $child ? (string) $argv[1] : sys_get_temp_dir() . '/pm-smtp-test-' . bin2hex(random_bytes(4));

$cert = $dir . '/cert.pem';
$key  = $dir . '/key.pem';

if (!$child) {
    mkdir($dir, 0700, true);
    exec(sprintf(
    'openssl req -x509 -newkey rsa:2048 -keyout %s -out %s -days 1 -nodes '
    . '-subj "/CN=localhost" -addext "subjectAltName=DNS:localhost" 2>/dev/null',
        escapeshellarg($key),
        escapeshellarg($cert),
    ), $out, $code);

    if ($code !== 0 || !is_file($cert)) {
        echo "SKIP  could not generate a test certificate\n";
        exit(0);
    }
}

// The client verifies certificates and there is no way to ask it not to. So
// the test makes this one verifiable, rather than making the client lenient.
// Re-exec once with the CA trusted, if it is not already.
if (!$child) {
    $cmd = sprintf(
        '%s -d openssl.cafile=%s %s %s',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($cert),
        escapeshellarg(__FILE__),
        escapeshellarg($dir),
    );
    passthru($cmd, $code);
    exec('rm -rf ' . escapeshellarg($dir));
    exit($code);
}

/**
 * A port nothing is listening on.
 *
 * Asked for rather than hardcoded: a fixed port collides with whatever else is
 * on the machine, and the collision does not look like a collision. It looks
 * like every assertion failing at once, because the probe below finds SOMETHING
 * listening and hands the client a stranger's certificate. That cost a
 * confusing ten minutes.
 */
function freePort(): int
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if ($socket === false) {
        return 0;
    }
    $name = (string) stream_socket_get_name($socket, false);
    fclose($socket);

    return (int) substr($name, strrpos($name, ':') + 1);
}

$ports = ['ssl' => freePort(), 'starttls' => freePort()];
$pids  = [];

if (in_array(0, $ports, true) || $ports['ssl'] === $ports['starttls']) {
    echo "SKIP  could not reserve two free ports\n";
    exit(0);
}

foreach ($ports as $mode => $port) {
    $log = $dir . '/' . $mode . '.log';
    $cmd = sprintf(
        'SMTP_TEST_CERT=%s SMTP_TEST_KEY=%s nohup python3 %s %d %s %s > %s 2>&1 & echo $!',
        escapeshellarg($cert),
        escapeshellarg($key),
        escapeshellarg(BASE_PATH . '/tests/fixtures/smtp-server.py'),
        $port,
        escapeshellarg($mode),
        escapeshellarg($dir . '/' . $mode . '.json'),
        escapeshellarg($log),
    );
    $pids[] = (int) shell_exec($cmd);
}

register_shutdown_function(static function () use ($pids, $dir): void {
    foreach ($pids as $pid) {
        if ($pid > 0) {
            @posix_kill($pid, SIGTERM);
        }
    }
    exec('rm -rf ' . escapeshellarg($dir));
});

// Wait for both to be listening rather than sleeping a guessed amount.
$ready = false;
for ($i = 0; $i < 60 && !$ready; $i++) {
    $ready = true;
    foreach ($ports as $port) {
        $probe = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
        if ($probe === false) {
            $ready = false;
        } else {
            fclose($probe);
        }
    }
    if (!$ready) {
        usleep(200_000);
    }
}

if (!$ready) {
    echo "SKIP  the test SMTP server did not start\n";
    exit(0);
}

$passed = 0;
$failed = 0;

function check(string $what, mixed $got, mixed $want): void
{
    global $passed, $failed;
    if ($got === $want) {
        $passed++;
        return;
    }
    $failed++;
    echo "FAIL  {$what}\n      got:  " . var_export($got, true)
        . "\n      want: " . var_export($want, true) . "\n";
}

function ok(string $what, bool $got): void
{
    check($what, $got, true);
}

/** @param array<string,mixed> $smtp */
function configure(array $smtp): void
{
    Config::load([
        'app_name' => 'PromoMonster',
        'app_url'  => 'https://promomonster.test',
        'mail' => [
            'driver'               => 'postmark',
            'token'                => 'a-bulk-token',
            'from'                 => 'reviews@notify.promomonster.test',
            'stream'               => 'broadcast',
            'transactional_driver' => 'smtp',
            'transactional_from'   => MAILBOX,
            'smtp' => array_merge([
                'host'     => 'localhost',
                'username' => MAILBOX,
                'password' => PASSWORD,
            ], $smtp),
        ],
    ]);
}

/** @return array<string,mixed> What the server actually received. */
function received(string $dir, string $mode): array
{
    $file = $dir . '/' . $mode . '.json';

    return is_file($file)
        ? (array) json_decode((string) file_get_contents($file), true, 16, JSON_THROW_ON_ERROR)
        : [];
}

// A body carrying both hazards: a line that begins with a full stop (which
// would end the message early if it reached the wire raw) and a line far over
// the 998-octet limit. Either is reachable from a real business name or a long
// review URL.
$body = "Hi Dana,\n\n"
    . ".a line that starts with a full stop\n"
    . str_repeat('x', 1400) . "\n\n"
    . "Accents: \u{e9} \u{e0} \u{fc} \u{2014} done.\n";

// =====================================================================
// Both transports
// =====================================================================
foreach ($ports as $mode => $port) {
    configure(['port' => $port, 'encryption' => $mode === 'ssl' ? 'ssl' : 'tls']);

    $result = Mailer::send([
        'to'        => 'dana@acmepools.test',
        'subject'   => "R\u{e9}initialisez votre mot de passe",
        'text'      => $body,
        'from_name' => Mailer::transactionalHeader(),
        'from'      => Mailer::transactionalFrom(),
        'driver'    => Mailer::transactionalDriver(),
        'reply_to'  => 'support@promomonster.test',
    ]);

    ok("{$mode}: the send succeeds", $result['ok'] === true);
    check("{$mode}: reported as the smtp driver", $result['driver'], 'smtp');
    ok("{$mode}: carries a message id", is_string($result['id']) && $result['id'] !== '');

    $got = received($dir, $mode);
    [$head, $encoded] = array_pad(explode("\r\n\r\n", (string) ($got['data'] ?? ''), 2), 2, '');
    $text = base64_decode(str_replace("\r\n", '', $encoded), true);

    ok("{$mode}: the server required and got authentication", ($got['authed'] ?? false) === true);
    check("{$mode}: the envelope sender is the mailbox we authenticated as", $got['from'] ?? null, MAILBOX);
    check("{$mode}: one recipient", $got['rcpt'] ?? [], ['dana@acmepools.test']);

    // STARTTLS needs a second EHLO: the capabilities offered before the tunnel
    // are not binding, and AUTH is usually only advertised inside it.
    check("{$mode}: the right number of EHLOs", $got['ehlo'] ?? 0, $mode === 'starttls' ? 2 : 1);

    ok("{$mode}: the From header is the account address", str_contains($head, '<' . MAILBOX . '>'));
    ok("{$mode}: not the bulk one", !str_contains($head, 'reviews@notify.promomonster.test'));
    ok("{$mode}: Reply-To survives", str_contains($head, 'Reply-To: support@promomonster.test'));
    ok("{$mode}: declares utf-8", str_contains($head, 'text/plain; charset=utf-8'));

    // An auto-reply war with an out-of-office is a real way to burn a domain.
    ok("{$mode}: marked auto-generated", str_contains($head, 'Auto-Submitted: auto-generated'));

    // Non-ASCII cannot go in a header raw.
    ok("{$mode}: the subject is RFC 2047 encoded", str_contains($head, '=?UTF-8?B?'));
    ok(
        "{$mode}: and decodes back to what was asked for",
        preg_match('/=\?UTF-8\?B\?([^?]+)\?=/', $head, $m) === 1
            && base64_decode($m[1], true) === "R\u{e9}initialisez votre mot de passe",
    );

    ok("{$mode}: the body arrives byte for byte", $text === $body);
    ok("{$mode}: including the line starting with a full stop", str_contains((string) $text, "\n.a line that starts"));
    ok("{$mode}: including the 1400-character line", str_contains((string) $text, str_repeat('x', 1400)));
    ok("{$mode}: and the accents", str_contains((string) $text, "\u{e9} \u{e0} \u{fc} \u{2014}"));

    // Two things that only exist correctly on the wire.
    $lines = explode("\r\n", $encoded);
    ok("{$mode}: no encoded line is over 998 octets", max(array_map('strlen', $lines)) <= 998);
    ok("{$mode}: no encoded line begins with a full stop",
        array_filter($lines, static fn (string $l) => str_starts_with($l, '.')) === []);
    check("{$mode}: every newline is a CRLF",
        substr_count((string) $got['data'], "\n"), substr_count((string) $got['data'], "\r\n"));
}

// =====================================================================
// Failure, which is returned and never thrown
// =====================================================================
configure(['port' => $ports['ssl'], 'encryption' => 'ssl', 'password' => 'not-the-password']);
$result = Mailer::send(['to' => 'a@b.test', 'subject' => 'x', 'text' => 'y', 'driver' => 'smtp']);

ok('a wrong password fails', $result['ok'] === false);
ok('and says the server refused authentication', str_contains((string) $result['error'], 'refused authentication'));

// The one that actually happened. AUTH LOGIN sends the password as a bare
// base64 line with no keyword in front of it, so a rule like "hide the line if
// it starts with AUTH" misses precisely the line that carries the credential --
// and the refusal quoted it into storage/logs/error.log.
ok('the password is not in the error', !str_contains((string) $result['error'], 'not-the-password'));
ok('nor is it there base64-encoded',
    !str_contains((string) $result['error'], base64_encode('not-the-password')));
ok('nor is the real one', !str_contains((string) $result['error'], PASSWORD));

$dead = freePort();
configure(['port' => $dead, 'encryption' => 'ssl']);
$result = Mailer::send(['to' => 'a@b.test', 'subject' => 'x', 'text' => 'y', 'driver' => 'smtp']);
ok('an unreachable server fails rather than hanging', $result['ok'] === false);
ok('and names the host and port', str_contains((string) $result['error'], 'localhost:' . $dead));

configure(['port' => $ports['ssl'], 'encryption' => 'ssl', 'host' => '', 'username' => '', 'password' => '']);
$result = Mailer::send(['to' => 'a@b.test', 'subject' => 'x', 'text' => 'y', 'driver' => 'smtp']);
ok('an unconfigured install fails before opening a socket', $result['ok'] === false);
ok('and says what is missing', str_contains((string) $result['error'], 'mail.smtp.host'));

// =====================================================================
// Certificate verification, which has no off switch
// =====================================================================
// A certificate from a CA this process DOES trust, issued for a different
// name. Only the hostname check can reject it, so this is the assertion that
// says the check is really running.
$wrongCert = $dir . '/wrong-cert.pem';
$wrongKey  = $dir . '/wrong-key.pem';
exec(sprintf(
    'openssl req -x509 -newkey rsa:2048 -keyout %s -out %s -days 1 -nodes '
    . '-subj "/CN=not-our-host.example" -addext "subjectAltName=DNS:not-our-host.example" 2>/dev/null',
    escapeshellarg($wrongKey),
    escapeshellarg($wrongCert),
));

if (is_file($wrongCert)) {
    // Trusted as a CA for this child process, by appending it to the bundle
    // the parent passed in. If the hostname were not checked, this would now
    // succeed.
    file_put_contents($cert, file_get_contents($wrongCert), FILE_APPEND);

    $wrongPort = freePort();
    $pid = (int) shell_exec(sprintf(
        'SMTP_TEST_CERT=%s SMTP_TEST_KEY=%s nohup python3 %s %d ssl %s > %s 2>&1 & echo $!',
        escapeshellarg($wrongCert),
        escapeshellarg($wrongKey),
        escapeshellarg(BASE_PATH . '/tests/fixtures/smtp-server.py'),
        $wrongPort,
        escapeshellarg($dir . '/wrong.json'),
        escapeshellarg($dir . '/wrong.log'),
    ));

    for ($i = 0; $i < 40; $i++) {
        $probe = @fsockopen('127.0.0.1', $wrongPort, $errno, $errstr, 0.2);
        if ($probe !== false) {
            fclose($probe);
            break;
        }
        usleep(150_000);
    }

    configure(['port' => $wrongPort, 'encryption' => 'ssl']);
    $result = Mailer::send(['to' => 'a@b.test', 'subject' => 'x', 'text' => 'y', 'driver' => 'smtp']);

    ok('a certificate for the wrong hostname is refused', $result['ok'] === false);
    ok('and nothing was delivered', !is_file($dir . '/wrong.json'));

    if ($pid > 0) {
        @posix_kill($pid, SIGTERM);
    }
} else {
    echo "note: skipped the hostname-mismatch check (no second certificate)\n";
}

// And the other half of the same guarantee. The certificate above was refused
// on its NAME, which PHP checks independently of whether it trusts the issuer
// -- so that assertion alone passes even with verify_peer switched off, and a
// self-signed certificate for the right hostname would sail through. This one
// is for the right name and an issuer nothing trusts, so only verify_peer can
// reject it.
$untrustedCert = $dir . '/untrusted-cert.pem';
$untrustedKey  = $dir . '/untrusted-key.pem';
exec(sprintf(
    'openssl req -x509 -newkey rsa:2048 -keyout %s -out %s -days 1 -nodes '
    . '-subj "/CN=localhost" -addext "subjectAltName=DNS:localhost" 2>/dev/null',
    escapeshellarg($untrustedKey),
    escapeshellarg($untrustedCert),
));

if (is_file($untrustedCert)) {
    // Pointedly NOT appended to the trusted bundle.
    $untrustedPort = freePort();
    $pid = (int) shell_exec(sprintf(
        'SMTP_TEST_CERT=%s SMTP_TEST_KEY=%s nohup python3 %s %d ssl %s > %s 2>&1 & echo $!',
        escapeshellarg($untrustedCert),
        escapeshellarg($untrustedKey),
        escapeshellarg(BASE_PATH . '/tests/fixtures/smtp-server.py'),
        $untrustedPort,
        escapeshellarg($dir . '/untrusted.json'),
        escapeshellarg($dir . '/untrusted.log'),
    ));

    for ($i = 0; $i < 40; $i++) {
        $probe = @fsockopen('127.0.0.1', $untrustedPort, $errno, $errstr, 0.2);
        if ($probe !== false) {
            fclose($probe);
            break;
        }
        usleep(150_000);
    }

    configure(['port' => $untrustedPort, 'encryption' => 'ssl']);
    $result = Mailer::send(['to' => 'a@b.test', 'subject' => 'x', 'text' => 'y', 'driver' => 'smtp']);

    ok('a certificate from an untrusted issuer is refused', $result['ok'] === false);
    ok('and that message was not delivered either', !is_file($dir . '/untrusted.json'));

    if ($pid > 0) {
        @posix_kill($pid, SIGTERM);
    }
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
