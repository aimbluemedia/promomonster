<?php

declare(strict_types=1);

namespace App\Support;

use PDOException;
use Throwable;

/**
 * Queueing and sending review requests.
 *
 * The rule the whole file is built around: every customer gets the same
 * message and the same link. There is no branch here that asks how happy
 * somebody is, and no column to put the answer in. That is review gating —
 * Google prohibits it outright and the FTC treats it as deceptive — so the
 * absence is structural, not a policy somebody could quietly relax.
 *
 * Sending is two steps on purpose. queue() writes a row and returns; send()
 * talks to the provider. A web request should never wait on a third party, and
 * a send that fails needs a row to record the failure against.
 */
final class ReviewRequests
{
    /** Give up after this many tries. A fourth would be the provider's problem, not ours. */
    private const MAX_ATTEMPTS = 3;

    /** The playbook's one reminder. Not two, and never three. */
    public const FOLLOW_UP_DAYS = 3;

    // =====================================================================
    // Queueing
    // =====================================================================

    /**
     * Queue one request. Returns why not, rather than throwing.
     *
     * @param array<string,mixed> $location
     * @param array<string,mixed> $contact
     * @return array{ok:bool, id:?int, error:?string}
     */
    public static function queue(array $location, array $contact, ?int $askedByUserId = null): array
    {
        $email = trim((string) ($contact['email'] ?? ''));

        if (!Mailer::isSendableAddress($email)) {
            return self::no('That is not an email address we can send to.');
        }
        if (trim((string) ($location['google_review_url'] ?? '')) === '') {
            return self::no('This location has no Google review link yet, so there is nothing to send them to.');
        }
        if (self::isSuppressed($email)) {
            return self::no('That address has opted out of review requests. We will not email it again.');
        }
        if ((int) ($contact['email_opted_out'] ?? 0) === 1) {
            return self::no('That customer has opted out.');
        }

        $template = self::systemTemplate('request');
        if ($template === null) {
            return self::no('No request template is installed. Run the migrations.');
        }

        try {
            Database::run(
                'INSERT INTO review_requests
                    (location_id, contact_id, template_id, channel, status,
                     asked_by_user_id, is_follow_up, scheduled_for, click_token)
                 VALUES
                    (:location, :contact, :template, \'email\', \'queued\',
                     :asked_by, 0, NOW(), :token)',
                [
                    'location' => (int) $location['id'],
                    'contact'  => (int) $contact['id'],
                    'template' => (int) $template['id'],
                    'asked_by' => $askedByUserId,
                    'token'    => self::newToken(),
                ],
            );
        } catch (PDOException $e) {
            return self::no('Could not queue that request: ' . $e->getMessage());
        }

        $id = (int) Database::connection()->lastInsertId();
        self::event($id, 'queued', null, []);

        return ['ok' => true, 'id' => $id, 'error' => null];
    }

    /**
     * Queue the one reminder, three days out.
     *
     * Deliberately not counted against the plan allowance — see SendLimit. It
     * is the second half of one ask.
     */
    public static function queueFollowUp(int $parentId): ?int
    {
        $parent = Database::first(
            'SELECT * FROM review_requests WHERE id = :id',
            ['id' => $parentId],
        );

        if ($parent === null || (int) $parent['is_follow_up'] === 1) {
            return null;
        }

        // One reminder means one. If a row already points at this parent, the
        // job is done — a retry of the sender must not add a second.
        $existing = Database::first(
            'SELECT id FROM review_requests WHERE parent_request_id = :id LIMIT 1',
            ['id' => $parentId],
        );
        if ($existing !== null) {
            return null;
        }

        $template = self::systemTemplate('follow_up');
        if ($template === null) {
            return null;
        }

        Database::run(
            'INSERT INTO review_requests
                (location_id, contact_id, template_id, channel, status,
                 asked_by_user_id, is_follow_up, parent_request_id, scheduled_for, click_token)
             VALUES
                (:location, :contact, :template, \'email\', \'scheduled\',
                 :asked_by, 1, :parent, :when, :token)',
            [
                'location' => (int) $parent['location_id'],
                'contact'  => (int) $parent['contact_id'],
                'template' => (int) $template['id'],
                'asked_by' => $parent['asked_by_user_id'],
                'parent'   => $parentId,
                'when'     => date('Y-m-d H:i:s', time() + (self::FOLLOW_UP_DAYS * 86400)),
                'token'    => self::newToken(),
            ],
        );

        return (int) Database::connection()->lastInsertId();
    }

    // =====================================================================
    // Sending
    // =====================================================================

    /**
     * Requests that are due, oldest first.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function due(int $limit = 25): array
    {
        return Database::all(
            'SELECT r.*,
                    c.email AS contact_email, c.first_name, c.last_name, c.email_opted_out,
                    l.name AS location_name, l.google_review_url, l.reply_to_email,
                    l.address_line1, l.city, l.region, l.postal_code,
                    a.id AS account_id, a.plan,
                    t.subject AS template_subject, t.body AS template_body
               FROM review_requests r
               JOIN contacts  c ON c.id = r.contact_id
               JOIN locations l ON l.id = r.location_id
               JOIN accounts  a ON a.id = l.account_id
          LEFT JOIN templates t ON t.id = r.template_id
              WHERE r.status IN (\'queued\', \'scheduled\')
                AND r.scheduled_for <= NOW()
                AND r.attempts < :max
           ORDER BY r.scheduled_for ASC, r.id ASC
              LIMIT ' . max(1, min(200, $limit)),
            ['max' => self::MAX_ATTEMPTS],
        );
    }

    /**
     * Send one request.
     *
     * @param array<string,mixed> $row a row from due()
     * @return array{ok:bool, error:?string}
     */
    public static function send(array $row): array
    {
        $id    = (int) $row['id'];
        $email = trim((string) ($row['contact_email'] ?? ''));

        // Re-checked at send time, not only at queue time. A request sits in
        // the queue for minutes or days, and somebody can opt out in between —
        // in fact the reminder is exactly when they are most likely to.
        if (self::isSuppressed($email) || (int) ($row['email_opted_out'] ?? 0) === 1) {
            self::cancel($id, 'The recipient opted out before this was sent.');
            return ['ok' => false, 'error' => 'Opted out.'];
        }

        if (!Mailer::isSendableAddress($email)) {
            self::fail($id, 'Not a sendable address.', true);
            return ['ok' => false, 'error' => 'Not a sendable address.'];
        }

        try {
            $message = self::compose($row);
        } catch (Throwable $e) {
            // A missing app_key lands here. Permanent until a human fixes it,
            // so do not burn the retries on it.
            self::fail($id, 'Could not build the email: ' . $e->getMessage(), true);
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        Database::run(
            'UPDATE review_requests SET attempts = attempts + 1 WHERE id = :id',
            ['id' => $id],
        );

        $result = Mailer::send($message);

        if (!$result['ok']) {
            $attempts = (int) ($row['attempts'] ?? 0) + 1;
            self::fail($id, (string) $result['error'], $attempts >= self::MAX_ATTEMPTS);
            return ['ok' => false, 'error' => (string) $result['error']];
        }

        Database::run(
            'UPDATE review_requests
                SET status = \'sent\', sent_at = NOW(), failure_reason = NULL,
                    sent_subject = :subject, sent_body = :body, provider_ref = :ref
              WHERE id = :id',
            [
                'id'      => $id,
                'subject' => $message['subject'],
                'body'    => $message['text'],
                'ref'     => $result['id'],
            ],
        );
        self::event($id, 'sent', $result['id'], ['driver' => $result['driver']]);

        Database::run(
            'UPDATE contacts SET last_requested_at = NOW() WHERE id = :id',
            ['id' => (int) $row['contact_id']],
        );

        // The reminder is scheduled once the first one is actually away, not
        // when it was queued — otherwise a request stuck in the queue for two
        // days gets a reminder almost on top of it.
        if ((int) $row['is_follow_up'] === 0) {
            self::queueFollowUp($id);
        }

        return ['ok' => true, 'error' => null];
    }

    /**
     * Build the message. Public so a preview screen can show exactly this.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public static function compose(array $row): array
    {
        $appUrl = rtrim((string) Config::get('app_url', 'https://promomonster.com'), '/');
        $plan   = (string) ($row['plan'] ?? Plans::FREE);
        // Free carries the footer. It is the price of the plan, and it is the
        // only marketing this product does.
        $branded = !Plans::isPaid($plan);

        // due() joins the location in with its name aliased, so the raw row is
        // NOT a location and must not be handed to Template as one. It was, and
        // every email went out with an empty business name in the greeting and
        // no "this email is from" line in the footer — which is precisely the
        // sort of thing that reads as spam.
        $location = [
            'name'          => $row['location_name'] ?? null,
            'address_line1' => $row['address_line1'] ?? null,
            'city'          => $row['city'] ?? null,
            'region'        => $row['region'] ?? null,
            'postal_code'   => $row['postal_code'] ?? null,
        ];

        $fields = Template::fields($location, [
            'first_name' => $row['first_name'] ?? null,
            'last_name'  => $row['last_name'] ?? null,
        ], $appUrl . '/r/' . $row['click_token']);

        $unsubscribe = $appUrl . '/u/' . Tokens::unsubscribe((int) $row['contact_id']);

        $body = Template::render((string) ($row['template_body'] ?? ''), $fields)
            . "\n\n"
            . Template::footer($location, $unsubscribe, $branded, $appUrl);

        return [
            'to'              => trim((string) $row['contact_email']),
            'subject'         => Template::render((string) ($row['template_subject'] ?? ''), $fields),
            'text'            => $body,
            'from_name'       => Mailer::fromHeader((string) ($row['location_name'] ?? ''), $branded),
            'reply_to'        => self::replyTo($row),
            'unsubscribe_url' => $unsubscribe,
            'tag'             => (int) $row['is_follow_up'] === 1 ? 'review-reminder' : 'review-request',
        ];
    }

    // =====================================================================
    // Clicks and opt-outs
    // =====================================================================

    /**
     * Record a click and hand back where to send them.
     *
     * Idempotent on first_clicked_at: a mail client that prefetches links, or a
     * customer who opens the email twice, must not overwrite when they first
     * showed interest.
     */
    public static function click(string $token): ?string
    {
        $row = Database::first(
            'SELECT r.id, r.status, r.first_clicked_at, l.google_review_url
               FROM review_requests r
               JOIN locations l ON l.id = r.location_id
              WHERE r.click_token = :token',
            ['token' => $token],
        );

        if ($row === null) {
            return null;
        }

        Database::run(
            'UPDATE review_requests
                SET first_clicked_at = COALESCE(first_clicked_at, NOW()),
                    status = CASE WHEN status IN (\'sent\', \'delivered\') THEN \'clicked\' ELSE status END
              WHERE id = :id',
            ['id' => (int) $row['id']],
        );
        self::event((int) $row['id'], 'clicked', null, []);

        $url = trim((string) ($row['google_review_url'] ?? ''));

        return $url === '' ? null : $url;
    }

    public static function isSuppressed(string $email): bool
    {
        $row = Database::first(
            'SELECT id FROM suppressions WHERE channel = \'email\' AND address_hash = :hash',
            ['hash' => Tokens::addressHash($email)],
        );

        return $row !== null;
    }

    /**
     * Opt an address out, everywhere, for good.
     *
     * Global rather than per-account on purpose: once somebody says stop, they
     * are done with the platform, not with one of its customers. Cancels any
     * request already queued to them, including the reminder — which is the
     * whole point of unsubscribing after the first email.
     */
    public static function suppress(string $email, string $reason = 'unsubscribe'): void
    {
        Database::run(
            'INSERT INTO suppressions (channel, address_hash, reason)
             VALUES (\'email\', :hash, :reason)
             ON DUPLICATE KEY UPDATE id = id',
            ['hash' => Tokens::addressHash($email), 'reason' => $reason],
        );

        Database::run(
            'UPDATE contacts SET email_opted_out = 1 WHERE LOWER(email) = :email',
            ['email' => mb_strtolower(trim($email))],
        );

        Database::run(
            'UPDATE review_requests r
               JOIN contacts c ON c.id = r.contact_id
                SET r.status = \'cancelled\',
                    r.failure_reason = \'Recipient unsubscribed.\'
              WHERE LOWER(c.email) = :email
                AND r.status IN (\'queued\', \'scheduled\')',
            ['email' => mb_strtolower(trim($email))],
        );
    }

    /** @return array<string,mixed>|null */
    public static function findContactForUnsubscribe(int $contactId): ?array
    {
        return Database::first(
            'SELECT c.id, c.email, c.email_opted_out, l.name AS location_name
               FROM contacts c
               JOIN locations l ON l.id = c.location_id
              WHERE c.id = :id',
            ['id' => $contactId],
        );
    }

    // =====================================================================
    // Events
    // =====================================================================

    /**
     * Append to the message log.
     *
     * Webhooks retry, so the same delivery receipt arrives more than once. The
     * table has a unique key across (request, type, provider_ref) for exactly
     * that, and the duplicate is swallowed here rather than raised.
     *
     * @param array<string,mixed> $detail
     */
    public static function event(int $requestId, string $type, ?string $ref, array $detail = []): void
    {
        try {
            Database::run(
                'INSERT INTO message_events (request_id, type, provider_ref, detail)
                 VALUES (:request, :type, :ref, :detail)
                 ON DUPLICATE KEY UPDATE id = id',
                [
                    'request' => $requestId,
                    'type'    => $type,
                    'ref'     => $ref,
                    'detail'  => $detail === [] ? null : json_encode($detail, JSON_UNESCAPED_SLASHES),
                ],
            );
        } catch (PDOException) {
            // A missing event is not worth failing a send over.
        }
    }

    // =====================================================================
    // Internals
    // =====================================================================

    /** @return array<string,mixed>|null */
    public static function systemTemplate(string $kind): ?array
    {
        return Database::first(
            'SELECT * FROM templates
              WHERE is_system = 1 AND channel = \'email\' AND kind = :kind AND vertical IS NULL
              ORDER BY id ASC LIMIT 1',
            ['kind' => $kind],
        );
    }

    /** @param array<string,mixed> $row */
    private static function replyTo(array $row): ?string
    {
        $reply = trim((string) ($row['reply_to_email'] ?? ''));

        return Mailer::isSendableAddress($reply) ? $reply : null;
    }

    /**
     * Record a failure, and stop retrying once it is clearly permanent.
     *
     * Two statements rather than one with a CASE, because the CASE version
     * needed the same named placeholder twice and PDO's behaviour there is not
     * something to bet a send queue on with native prepares turned on.
     */
    private static function fail(int $id, string $reason, bool $permanent): void
    {
        $reason = mb_substr($reason, 0, 255);

        if ($permanent) {
            Database::run(
                'UPDATE review_requests
                    SET status = \'failed\', failure_reason = :reason, attempts = :attempts
                  WHERE id = :id',
                ['id' => $id, 'reason' => $reason, 'attempts' => self::MAX_ATTEMPTS],
            );
        } else {
            Database::run(
                'UPDATE review_requests SET failure_reason = :reason WHERE id = :id',
                ['id' => $id, 'reason' => $reason],
            );
        }

        self::event($id, 'failed', null, ['reason' => $reason]);
    }

    private static function cancel(int $id, string $reason): void
    {
        Database::run(
            'UPDATE review_requests SET status = \'cancelled\', failure_reason = :reason WHERE id = :id',
            ['id' => $id, 'reason' => mb_substr($reason, 0, 255)],
        );
    }

    /** A v4 UUID, which is what the click_token column is shaped for. */
    private static function newToken(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /** @return array{ok:false, id:null, error:string} */
    private static function no(string $error): array
    {
        return ['ok' => false, 'id' => null, 'error' => $error];
    }
}
