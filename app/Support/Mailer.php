<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Transactional email, over raw cURL.
 *
 * No Composer on this host, so there is no SDK and no PHPMailer — which is
 * fine, because a modern provider's send API is one JSON POST.
 *
 * What this deliberately does NOT use is PHP's mail(). Shared hosting sends it
 * through a shared IP with no DKIM signature of ours, and a review request that
 * lands in spam is worse than one never sent: the customer never sees it, the
 * business blames us, and the complaints train the filter against the next one.
 *
 * Drivers
 * -------
 *   postmark  the real one
 *   log       writes the message to storage/logs/mail.log and reports success.
 *             This is the default when no token is configured, so a local or
 *             half-configured install exercises every code path around sending
 *             without silently posting nothing — and without mailing a real
 *             customer from a developer's laptop.
 *   null      accepts and discards. For tests that only care about the caller.
 *
 * Failure is returned, never thrown, except for a programming error. A send is
 * one row in a queue: the runner needs to record why it failed and move to the
 * next one, not unwind.
 */
final class Mailer
{
    private const ENDPOINT = 'https://api.postmarkapp.com/email';

    /** Postmark's own limit; a longer subject is a mistake, not a feature. */
    private const MAX_SUBJECT = 998;

    /**
     * Send one message.
     *
     * @param array{
     *     to:string, subject:string, text:string, html?:?string,
     *     from_name?:?string, reply_to?:?string, unsubscribe_url?:?string,
     *     tag?:?string, stream?:?string, driver?:?string, from?:?string,
     *     headers?:array<string,string>
     * } $message
     * @return array{ok:bool, id:?string, error:?string, driver:string}
     */
    public static function send(array $message): array
    {
        // Per message, because the two kinds of mail this app sends can come
        // from different places entirely: review requests through a bulk
        // provider, account email through an ordinary authenticated mailbox.
        $driver = (string) ($message['driver'] ?? self::driver());

        foreach (['to', 'subject', 'text'] as $required) {
            if (trim((string) ($message[$required] ?? '')) === '') {
                throw new RuntimeException("Mailer: '{$required}' is required.");
            }
        }

        if (!self::isSendableAddress($message['to'])) {
            return self::fail($driver, 'Not a sendable address.');
        }

        if (mb_strlen($message['subject']) > self::MAX_SUBJECT) {
            return self::fail($driver, 'Subject is too long.');
        }

        return match ($driver) {
            'postmark' => self::postmark($message),
            'smtp'     => self::smtp($message),
            'log'      => self::log($message),
            'null'     => ['ok' => true, 'id' => null, 'error' => null, 'driver' => 'null'],
            default    => self::fail($driver, "Unknown mail driver '{$driver}'."),
        };
    }

    /**
     * Which driver is in play, so a caller can say so on screen.
     *
     * Falls back to `log` rather than `postmark` when no token is set: a
     * missing token should make sending visibly local, not fail every send
     * with an authentication error nobody reads.
     */
    public static function driver(): string
    {
        $configured = (string) Config::get('mail.driver', '');
        if ($configured !== '') {
            return $configured;
        }

        return self::token() === '' ? 'log' : 'postmark';
    }

    /**
     * True when mail actually leaves the building.
     *
     * The token is part of the question, not just the driver name. Setting
     * mail.driver to 'postmark' with no token satisfies driver() and then fails
     * every single send with "No Postmark server token configured" -- and the
     * two screens that ask this (the Get reviews page and the forgot-password
     * form) would both have stopped warning about it, so a customer gets told
     * their link is on its way while nothing is being sent at all. Whether we
     * are live means whether a send can succeed.
     */
    public static function isLive(): bool
    {
        return self::driver() === 'postmark' && self::token() !== '';
    }

    /** The address every review request is sent from. */
    public static function from(): string
    {
        return (string) Config::get('mail.from', 'reviews@promomonster.com');
    }

    /**
     * The address account email comes from, and the stream it rides on.
     *
     * These exist because review requests and password resets are not the same
     * kind of mail and must not share a reputation. Review requests go out in
     * bulk on behalf of businesses whose customers did not ask us for anything;
     * some of those customers will press "report spam", and that lands on the
     * sending domain. A password reset is the one message that absolutely has
     * to arrive, to somebody who is already locked out and asked for it thirty
     * seconds ago.
     *
     * Send both from one address and the first eventually poisons the second,
     * which is a support queue you cannot answer by email. config.example.php
     * has said so since the review sender was written; the reset flow shipped
     * using the bulk address anyway, and this is the correction.
     *
     * transactional_from falls back to mail.from, because a Postmark server can
     * only send from a domain it has verified and a fallback that fails to send
     * is worse than one that sends from the wrong place. diagnose.php says out
     * loud when the two are the same.
     */
    public static function transactionalFrom(): string
    {
        $address = trim((string) Config::get('mail.transactional_from', ''));

        return $address !== '' ? $address : self::from();
    }

    /**
     * Postmark's default transactional stream is 'outbound' and exists on every
     * server, so this is safe before anything has been configured. Review
     * requests belong on a broadcast stream; this one must not be on it.
     */
    /**
     * Which driver account email goes out through.
     *
     * Separate from driver() because the two lanes can reasonably be on
     * different providers, and for a small site they usually should be. Review
     * requests need a bulk provider: complaint feedback, bounce webhooks, a
     * reputation of their own. A password reset needs none of that -- it is one
     * message an hour to somebody who asked for it thirty seconds ago -- and an
     * ordinary authenticated mailbox at the host does it, today, with no
     * account to open and no DNS to wait on.
     *
     * Falls back to the bulk driver when unset, so an install that only ever
     * configures one thing still sends.
     */
    public static function transactionalDriver(): string
    {
        $driver = trim((string) Config::get('mail.transactional_driver', ''));

        return $driver !== '' ? $driver : self::driver();
    }

    /**
     * Whether account email can actually be delivered.
     *
     * Asked by the forgot-password page, which must not promise a link it
     * cannot send. Deliberately not the same question as isLive(): sending
     * review requests and being able to let somebody back into their account
     * are now two switches, and either can be on without the other.
     */
    public static function transactionalIsLive(): bool
    {
        return match (self::transactionalDriver()) {
            'postmark' => self::token() !== '',
            'smtp'     => trim((string) Config::get('mail.smtp.host', '')) !== ''
                && trim((string) Config::get('mail.smtp.username', '')) !== ''
                && (string) Config::get('mail.smtp.password', '') !== '',
            default    => false,
        };
    }

    public static function transactionalStream(): string
    {
        $stream = trim((string) Config::get('mail.transactional_stream', ''));

        return $stream !== '' ? $stream : 'outbound';
    }

    /** The full From header for account email. */
    public static function transactionalHeader(): string
    {
        return self::encodeName((string) Config::get('app_name', 'PromoMonster'))
            . ' <' . self::transactionalFrom() . '>';
    }

    /**
     * The display name on the envelope.
     *
     * A review request from a name the customer has never heard of gets
     * ignored and reported. It goes out as "Acme Pools (via PromoMonster)" so
     * the customer sees who is actually asking, while the domain — and
     * therefore SPF, DKIM and the reputation — stays ours. Paid plans drop the
     * suffix; that is one of the things they are paying for.
     */
    public static function fromHeader(?string $businessName, bool $withSuffix = true): string
    {
        $name = trim((string) $businessName);
        if ($name === '') {
            $name = (string) Config::get('app_name', 'PromoMonster');
        } elseif ($withSuffix) {
            $name .= ' (via ' . Config::get('app_name', 'PromoMonster') . ')';
        }

        return self::encodeName($name) . ' <' . self::from() . '>';
    }

    /**
     * A display name safe to put in a header.
     *
     * A quote or a backslash in a business name would otherwise end the quoted
     * string early and let the rest of the name be read as header syntax. A
     * newline would end the header outright, which is header injection — and
     * business names come from a signup form.
     */
    public static function encodeName(string $name): string
    {
        $name = str_replace(["\r", "\n", "\t"], ' ', $name);
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        $name = addcslashes($name, '"\\');

        return '"' . $name . '"';
    }

    /**
     * A first-pass sanity check on an address.
     *
     * FILTER_VALIDATE_EMAIL plus an explicit refusal of anything with a control
     * character in it, for the same header-injection reason as above.
     */
    public static function isSendableAddress(string $address): bool
    {
        $address = trim($address);

        if ($address === '' || mb_strlen($address) > 254) {
            return false;
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $address) === 1) {
            return false;
        }

        return filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
    }

    // -- Drivers -----------------------------------------------------------

    /** @param array<string,mixed> $message @return array{ok:bool,id:?string,error:?string,driver:string} */
    private static function postmark(array $message): array
    {
        $token = self::token();
        if ($token === '') {
            return self::fail('postmark', 'No Postmark server token configured.');
        }

        $body = array_filter([
            'From'          => $message['from_name'] ?? self::fromHeader(null),
            'To'            => $message['to'],
            'Subject'       => $message['subject'],
            'TextBody'      => $message['text'],
            'HtmlBody'      => $message['html'] ?? null,
            'ReplyTo'       => $message['reply_to'] ?? null,
            'Tag'           => $message['tag'] ?? null,
            // Per message, so a password reset does not ride the broadcast
            // stream the review requests use.
            'MessageStream' => (string) ($message['stream'] ?? Config::get('mail.stream', 'outbound')),
            // Postmark rewrites links for click tracking. We do our own, on our
            // own redirect, so theirs would only add a second hop and a second
            // domain for a spam filter to weigh up.
            'TrackLinks'    => 'None',
            'TrackOpens'    => false,
        ], static fn ($v) => $v !== null);

        $headers = self::listHeaders($message);
        if ($headers !== []) {
            $body['Headers'] = $headers;
        }

        [$status, $decoded, $error] = self::post(self::ENDPOINT, $body, [
            'X-Postmark-Server-Token: ' . $token,
        ]);

        if ($error !== null) {
            return self::fail('postmark', $error);
        }

        if ($status === 200) {
            return [
                'ok'     => true,
                'id'     => isset($decoded['MessageID']) ? (string) $decoded['MessageID'] : null,
                'error'  => null,
                'driver' => 'postmark',
            ];
        }

        // Postmark's ErrorCode is the useful part: 406 means the address is on
        // their suppression list, 300 means the payload was wrong. Keep both,
        // and never let a token reach the message.
        $reason = (string) ($decoded['Message'] ?? 'HTTP ' . $status);
        $code   = isset($decoded['ErrorCode']) ? ' (code ' . (int) $decoded['ErrorCode'] . ')' : '';

        return self::fail('postmark', $reason . $code);
    }

    /** @param array<string,mixed> $message @return array{ok:bool,id:?string,error:?string,driver:string} */
    private static function smtp(array $message): array
    {
        $from = trim((string) ($message['from'] ?? self::from()));

        $result = Smtp::send([
            'to'          => (string) $message['to'],
            'from'        => $from,
            'from_header' => (string) ($message['from_name'] ?? self::fromHeader(null)),
            'subject'     => (string) $message['subject'],
            'text'        => (string) $message['text'],
            'reply_to'    => $message['reply_to'] ?? null,
            'headers'     => self::namedHeaders($message),
        ]);

        return $result + ['driver' => 'smtp'];
    }

    /**
     * The extra headers, as a name => value map.
     *
     * listHeaders() builds Postmark's shape, which is a list of {Name, Value}
     * objects. SMTP wants neither that nor its own translation layer, so the
     * common part lives here and each driver takes what it needs.
     *
     * @param array<string,mixed> $message
     * @return array<string,string>
     */
    private static function namedHeaders(array $message): array
    {
        $headers = [];

        foreach (self::listHeaders($message) as $header) {
            $headers[$header['Name']] = $header['Value'];
        }

        return $headers;
    }

    /** @param array<string,mixed> $message @return array{ok:bool,id:?string,error:?string,driver:string} */
    private static function log(array $message): array
    {
        $line = sprintf(
            "[%s] to=%s from=%s reply-to=%s via=%s stream=%s subject=%s\n%s\n%s\n",
            gmdate('c'),
            $message['to'],
            $message['from_name'] ?? self::fromHeader(null),
            $message['reply_to'] ?? '-',
            // Recorded because it is otherwise invisible until something is
            // sent for real, and sending account mail on the bulk stream is
            // exactly the mistake worth catching before that.
            // Which driver a real send would have used, and on which stream.
            // Both are otherwise invisible until something goes out for real,
            // and account mail on the bulk settings is exactly the mistake
            // worth catching before that.
            $message['driver'] ?? self::driver(),
            $message['stream'] ?? Config::get('mail.stream', 'outbound'),
            $message['subject'],
            str_repeat('-', 60),
            $message['text'],
        );

        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return self::fail('log', 'storage/logs is not writable.');
        }

        $written = @file_put_contents($dir . '/mail.log', $line, FILE_APPEND | LOCK_EX);
        if ($written === false) {
            return self::fail('log', 'Could not write storage/logs/mail.log.');
        }

        return [
            'ok'     => true,
            // A fake id, marked as one, so it can never be mistaken for a
            // provider reference when reading message_events later.
            'id'     => 'log-' . bin2hex(random_bytes(8)),
            'error'  => null,
            'driver' => 'log',
        ];
    }

    // -- Plumbing ----------------------------------------------------------

    /**
     * List-Unsubscribe, which is not decoration.
     *
     * Gmail and Outlook show a native unsubscribe control when it is present,
     * and someone who uses it is not pressing "report spam" — which is the
     * signal that actually costs a sending domain its reputation.
     *
     * @param array<string,mixed> $message
     * @return array<int,array{Name:string,Value:string}>
     */
    private static function listHeaders(array $message): array
    {
        $headers = [];

        foreach (($message['headers'] ?? []) as $name => $value) {
            $headers[] = ['Name' => (string) $name, 'Value' => (string) $value];
        }

        $url = $message['unsubscribe_url'] ?? null;
        if ($url !== null && $url !== '') {
            $headers[] = ['Name' => 'List-Unsubscribe', 'Value' => '<' . $url . '>'];
            // Tells the mail client the URL accepts a POST, so its own button
            // works without opening a browser.
            $headers[] = ['Name' => 'List-Unsubscribe-Post', 'Value' => 'List-Unsubscribe=One-Click'];
        }

        return $headers;
    }

    private static function token(): string
    {
        return trim((string) Config::get('mail.token', ''));
    }

    /** @return array{ok:false, id:null, error:string, driver:string} */
    private static function fail(string $driver, string $error): array
    {
        return ['ok' => false, 'id' => null, 'error' => $error, 'driver' => $driver];
    }

    /**
     * @param array<string,mixed> $body
     * @param array<int,string> $headers
     * @return array{0:int, 1:array<string,mixed>, 2:?string}
     */
    private static function post(string $url, array $body, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            // Short on purpose. A send that has not answered in fifteen seconds
            // is one the cron run should give up on and retry next time, rather
            // than holding the lock while the rest of the queue waits.
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER     => array_merge([
                'Accept: application/json',
                'Content-Type: application/json',
            ], $headers),
            CURLOPT_POSTFIELDS => json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ]);

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return [0, [], 'Could not reach the mail provider: ' . $error];
        }

        $decoded = json_decode((string) $raw, true);

        return [$status, is_array($decoded) ? $decoded : [], null];
    }
}
