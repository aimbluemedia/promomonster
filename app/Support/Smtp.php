<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A small SMTP client, spoken over a socket.
 *
 * Here because the alternatives are worse on this host. There is no Composer,
 * so there is no PHPMailer; and PHP's own mail() hands the message to a local
 * sendmail with no authentication, no TLS and no control over the envelope --
 * which on shared hosting means a shared IP, no DKIM signature of ours, and a
 * message that lands in spam. Submitting it to an authenticated mailbox over
 * TLS is a different thing entirely: the mail leaves as that mailbox, signed by
 * whatever the provider signs with.
 *
 * Scope is deliberately narrow. One recipient, one plain-text body, a handful
 * of headers. It is for account email -- password resets -- and not for bulk.
 *
 * Two things this does NOT do, on purpose:
 *
 *   It never disables certificate verification. An SMTP session carries a
 *   mailbox password in the clear inside the TLS tunnel, so an unverified
 *   tunnel is a credential handed to whoever answered the connection. There is
 *   no config flag to turn this off, because a flag like that gets switched on
 *   the first time a certificate looks inconvenient and never switched back.
 *
 *   It never returns a partly-sent state. Either the server accepted the
 *   message at the final dot, or this reports failure -- the queue above
 *   retries on failure, and "possibly sent" would mean possibly sending twice.
 */
final class Smtp
{
    /** Long enough for a slow provider, short enough not to hang a web request. */
    private const TIMEOUT = 20;

    /** Base64 wraps at 76, which is well inside the 998-octet line limit. */
    private const WRAP = 76;

    /**
     * TLS 1.2 and above, and nothing older.
     *
     * PHP's STREAM_CRYPTO_METHOD_TLS_CLIENT is every version including 1.0 and
     * 1.1, both long deprecated and both removed from every mail provider worth
     * using. Accepting them would mean a downgrade is available to anyone who
     * can sit in the middle of this connection -- and what crosses it is a
     * mailbox password. Saying the certificate must verify and then accepting
     * TLS 1.0 to carry the credential is half a position.
     */
    public const CRYPTO = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;

    /** @var resource|null */
    private $socket = null;

    /** @var array<int,string> Lines of the conversation, for the error message. */
    private array $trace = [];

    private function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $encryption,
        private readonly string $username,
        private readonly string $password,
    ) {
    }

    /**
     * Sends one message.
     *
     * @param array{
     *     to:string, from:string, from_header:string, subject:string, text:string,
     *     reply_to?:?string, headers?:array<string,string>
     * } $message
     * @return array{ok:bool, id:?string, error:?string}
     */
    public static function send(array $message): array
    {
        $host = trim((string) Config::get('mail.smtp.host', ''));
        $user = trim((string) Config::get('mail.smtp.username', ''));
        $pass = (string) Config::get('mail.smtp.password', '');

        if ($host === '' || $user === '' || $pass === '') {
            return ['ok' => false, 'id' => null, 'error' => 'SMTP is not configured (needs mail.smtp.host, username and password).'];
        }

        // 465 is implicit TLS, 587 is STARTTLS. Defaulting from the port rather
        // than making somebody state both is one fewer thing to get wrong, and
        // getting it wrong here means either a hang or a plaintext password.
        $port = (int) Config::get('mail.smtp.port', 465);
        $encryption = strtolower(trim((string) Config::get('mail.smtp.encryption', '')));
        if ($encryption === '') {
            $encryption = $port === 587 ? 'tls' : 'ssl';
        }

        $client = new self($host, $port, $encryption, $user, $pass);

        try {
            return $client->deliver($message);
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'id' => null, 'error' => $e->getMessage()];
        } finally {
            $client->close();
        }
    }

    /**
     * @param array<string,mixed> $message
     * @return array{ok:bool, id:?string, error:?string}
     */
    private function deliver(array $message): array
    {
        $this->connect();
        $capabilities = $this->hello();

        if ($this->encryption === 'tls') {
            $this->starttls();
            // A second EHLO after STARTTLS, because the capability list before
            // the tunnel is not binding and AUTH is usually only advertised
            // inside it.
            $capabilities = $this->hello();
        }

        $this->authenticate($capabilities);

        // The envelope sender is the authenticated mailbox, not the From
        // header. A provider that lets you authenticate as one address and post
        // as another is unusual, and being refused at MAIL FROM with no
        // explanation is a confusing afternoon.
        $this->command('MAIL FROM:<' . $this->username . '>', [250]);
        $this->command('RCPT TO:<' . $message['to'] . '>', [250, 251]);
        $this->command('DATA', [354]);

        $id = $this->messageId();
        $this->write($this->compose($message, $id) . "\r\n.\r\n");
        $this->expect([250]);

        $this->command('QUIT', [221]);

        return ['ok' => true, 'id' => $id, 'error' => null];
    }

    private function connect(): void
    {
        $transport = $this->encryption === 'ssl' ? 'ssl://' : 'tcp://';

        $context = stream_context_create(['ssl' => [
            // Not configurable. See the class comment.
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'allow_self_signed' => false,
            'SNI_enabled'       => true,
            'peer_name'         => $this->host,
            // Applies to ssl:// (implicit TLS). STARTTLS passes the same set
            // to stream_socket_enable_crypto() below.
            'crypto_method'     => self::CRYPTO,
        ]]);

        $socket = @stream_socket_client(
            $transport . $this->host . ':' . $this->port,
            $errno,
            $errstr,
            self::TIMEOUT,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if ($socket === false) {
            throw new \RuntimeException(sprintf(
                'Could not reach %s:%d - %s',
                $this->host,
                $this->port,
                $errstr !== '' ? $errstr : 'error ' . $errno,
            ));
        }

        $this->socket = $socket;
        stream_set_timeout($socket, self::TIMEOUT);

        $this->expect([220]);
    }

    /** @return string The EHLO response, for reading capabilities out of. */
    private function hello(): string
    {
        // A dotted name, because some servers reject a bare hostname. What it
        // says does not matter much; that it parses does.
        $me = (string) (parse_url((string) Config::get('app_url', 'https://promomonster.com'), PHP_URL_HOST)
            ?: 'promomonster.com');

        return $this->command('EHLO ' . $me, [250]);
    }

    private function starttls(): void
    {
        $this->command('STARTTLS', [220]);

        $ok = @stream_socket_enable_crypto($this->socket, true, self::CRYPTO);

        if ($ok !== true) {
            throw new \RuntimeException(
                'STARTTLS failed on ' . $this->host . '. The certificate did not verify, or the '
                . 'server does not offer TLS 1.2 or better on this port.',
            );
        }
    }

    private function authenticate(string $capabilities): void
    {
        $offered = '';
        foreach (preg_split('/\r?\n/', $capabilities) ?: [] as $line) {
            if (preg_match('/^\d{3}[ -]AUTH\s+(.*)$/i', trim($line), $m) === 1) {
                $offered = strtoupper($m[1]);
                break;
            }
        }

        // LOGIN first: it is what every shared host offers, and PLAIN is the
        // fallback rather than the other way round only because a handful of
        // servers advertise PLAIN and then refuse it.
        if ($offered === '' || str_contains($offered, 'LOGIN')) {
            $this->command('AUTH LOGIN', [334], 'authentication');
            $this->command(base64_encode($this->username), [334], 'authentication');
            $this->command(base64_encode($this->password), [235], 'authentication');
            return;
        }

        if (str_contains($offered, 'PLAIN')) {
            $this->command(
                'AUTH PLAIN ' . base64_encode("\0" . $this->username . "\0" . $this->password),
                [235],
                'authentication',
            );
            return;
        }

        throw new \RuntimeException(
            'The server offers no authentication method this understands (it offers: ' . $offered . ').',
        );
    }

    /**
     * The message itself.
     *
     * The body is base64, which is not about hiding anything. It sidesteps two
     * whole classes of SMTP bug at once: a line of the body that happens to
     * start with a full stop would end the message early unless it is doubled,
     * and a line over 998 octets is illegal. Base64's alphabet contains no full
     * stop and wraps at 76, so neither can happen. Somebody else's business
     * name or a long review URL would otherwise be enough to trigger both.
     *
     * @param array<string,mixed> $message
     */
    private function compose(array $message, string $id): string
    {
        $headers = [
            'Date'                      => date('r'),
            'Message-ID'                => '<' . $id . '>',
            'From'                      => (string) $message['from_header'],
            'To'                        => (string) $message['to'],
            'Subject'                   => self::encodeHeader((string) $message['subject']),
            'MIME-Version'              => '1.0',
            'Content-Type'              => 'text/plain; charset=utf-8',
            'Content-Transfer-Encoding' => 'base64',
            // Nothing here is a mailing list, and an auto-reply war with an
            // out-of-office is a real way to burn a sending reputation.
            'Auto-Submitted'            => 'auto-generated',
        ];

        if (!empty($message['reply_to'])) {
            $headers['Reply-To'] = (string) $message['reply_to'];
        }

        foreach (($message['headers'] ?? []) as $name => $value) {
            $headers[(string) $name] = (string) $value;
        }

        $lines = [];
        foreach ($headers as $name => $value) {
            // Belt and braces. Everything reaching here has already been
            // checked, but a header is one newline away from being two headers
            // and this is the last place to stop it.
            $lines[] = $name . ': ' . str_replace(["\r", "\n"], ' ', $value);
        }

        $body = rtrim(chunk_split(base64_encode((string) $message['text']), self::WRAP, "\r\n"), "\r\n");

        return implode("\r\n", $lines) . "\r\n\r\n" . $body;
    }

    /** RFC 2047, and only when it is needed. */
    private static function encodeHeader(string $value): string
    {
        $value = str_replace(["\r", "\n"], ' ', $value);

        if (preg_match('/[^\x20-\x7E]/', $value) !== 1) {
            return $value;
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function messageId(): string
    {
        $domain = substr($this->username, strpos($this->username, '@') + 1) ?: 'promomonster.com';

        return bin2hex(random_bytes(12)) . '@' . $domain;
    }

    /**
     * @param array<int,int> $expected
     * @param ?string $label What to call this step if it is refused. Passed
     *                       explicitly rather than derived from the line,
     *                       because the two halves of AUTH LOGIN are bare
     *                       base64 with no keyword in front of them -- so a
     *                       rule like "hide it if it starts with AUTH" misses
     *                       the line that actually carries the password, and
     *                       the refusal quotes the credential straight into
     *                       the error log. It did exactly that once.
     */
    private function command(string $line, array $expected, ?string $label = null): string
    {
        $this->write($line . "\r\n");

        return $this->expect($expected, $label ?? explode(' ', $line)[0]);
    }

    private function write(string $data): void
    {
        if ($this->socket === null || @fwrite($this->socket, $data) === false) {
            throw new \RuntimeException('The connection closed while sending. ' . $this->context());
        }
    }

    /**
     * Reads one reply and checks its code.
     *
     * A reply can span lines: "250-FIRST" continues, "250 LAST" ends it. Read
     * only the first line and the next command lands in the middle of the
     * previous answer, which is the bug that makes an SMTP client work against
     * one server and not another.
     *
     * @param array<int,int> $expected
     * @param string $label Names the step in an error. Never the line itself.
     */
    private function expect(array $expected, string $label = 'the greeting'): string
    {
        $reply = '';

        while (true) {
            $line = $this->socket === null ? false : @fgets($this->socket, 1024);

            if ($line === false) {
                $meta = $this->socket === null ? ['timed_out' => false] : stream_get_meta_data($this->socket);
                throw new \RuntimeException(
                    (!empty($meta['timed_out'])
                        ? 'The server stopped responding after ' . self::TIMEOUT . ' seconds.'
                        : 'The connection closed unexpectedly.')
                    . ' ' . $this->context(),
                );
            }

            $reply .= $line;
            $this->trace[] = rtrim($line, "\r\n");

            // A space in the fourth character means this was the last line.
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }

        $code = (int) substr(ltrim($reply), 0, 3);

        if (!in_array($code, $expected, true)) {
            // What we sent is never quoted back, only named. The password goes
            // over this socket base64-encoded, which is transport encoding and
            // not secrecy, and an error message ends up in storage/logs and on
            // the diagnostics screen.
            throw new \RuntimeException(sprintf(
                'The mail server refused %s: %s',
                $label,
                trim(preg_replace('/\s+/', ' ', $reply) ?? $reply),
            ));
        }

        return $reply;
    }

    /**
     * The last few lines of what the SERVER said, for an error message.
     *
     * Only the server's side is kept. Our own lines include the base64 password
     * and have no business in a log file.
     */
    private function context(): string
    {
        $tail = array_slice($this->trace, -3);

        return $tail === [] ? '' : '(last heard: ' . implode(' | ', $tail) . ')';
    }

    private function close(): void
    {
        if ($this->socket !== null) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }
}
