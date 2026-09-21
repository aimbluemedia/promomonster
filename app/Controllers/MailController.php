<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Config;
use App\Support\Database;
use App\Support\ReviewRequests;
use App\Support\Tokens;
use App\Support\View;

/**
 * The three endpoints a sent email points back at.
 *
 * All three are reached with no session: the visitor is a customer of a
 * customer, who has never heard of us and never will unless something goes
 * wrong. So nothing here needs a login, and nothing here may leak who else is
 * on the platform.
 */
final class MailController
{
    /**
     * /r/{token} — record the click, then get out of the way.
     *
     * A redirect rather than a landing page. Every extra tap between "I'll
     * leave them a review" and the review box costs reviews, and a page that
     * says "you are about to visit Google" helps nobody.
     *
     * An unknown token is a 404 page, not a redirect to somewhere guessed: a
     * link that quietly sends people to the wrong business would be worse than
     * one that plainly does not work.
     */
    public function click(string $token): void
    {
        $url = ReviewRequests::click($token);

        if ($url === null) {
            http_response_code(404);
            echo View::page('errors/404', ['title' => 'That link has expired']);
            return;
        }

        // 302, not 301. A permanent redirect would be cached by the browser and
        // the second click would never reach us to be counted.
        header('Location: ' . $url, true, 302);
        // Nothing about a review link should be stored by an intermediary.
        header('Cache-Control: no-store, private');
        header('Referrer-Policy: no-referrer');
    }

    /**
     * GET /u/{token} — ask before doing it.
     *
     * Not a one-click opt-out on GET, because corporate mail scanners follow
     * every link in every message. They would unsubscribe half a customer list
     * before a human ever read one. The confirmation POSTs.
     */
    public function unsubscribeForm(string $token): void
    {
        $contact = $this->contactFromToken($token);

        if ($contact === null) {
            http_response_code(404);
            echo View::page('errors/404', ['title' => 'That link has expired']);
            return;
        }

        echo View::page('unsubscribe', [
            'title'    => 'Unsubscribe · ' . Config::get('app_name', 'PromoMonster'),
            'token'    => $token,
            'business' => (string) ($contact['location_name'] ?? ''),
            'already'  => (int) ($contact['email_opted_out'] ?? 0) === 1,
            'done'     => false,
        ]);
    }

    /**
     * POST /u/{token} — do it.
     *
     * No CSRF token, deliberately. There is no session and nothing to protect:
     * the signed token IS the authorisation, and the worst an attacker who has
     * somehow obtained one can do is stop us emailing the person it belongs to.
     * Requiring a form token would also break the one-click unsubscribe button
     * that Gmail and Outlook render from the List-Unsubscribe-Post header, and
     * that button is worth far more than the theoretical risk — someone who
     * cannot find the opt-out presses "report spam" instead.
     */
    public function unsubscribe(string $token): void
    {
        $contact = $this->contactFromToken($token);

        if ($contact === null) {
            http_response_code(404);
            echo View::page('errors/404', ['title' => 'That link has expired']);
            return;
        }

        ReviewRequests::suppress((string) $contact['email'], 'unsubscribe');

        echo View::page('unsubscribe', [
            'title'    => 'Unsubscribed · ' . Config::get('app_name', 'PromoMonster'),
            'token'    => $token,
            'business' => (string) ($contact['location_name'] ?? ''),
            'already'  => false,
            'done'     => true,
        ]);
    }

    /**
     * POST /webhooks/email/{secret} — bounces and complaints from the provider.
     *
     * These are the two events that matter more than deliveries. A complaint
     * rate above a fraction of a percent gets a sending domain throttled and
     * then cut off, so both go straight onto the suppression list: the address
     * is never tried again, by anyone, on any plan.
     *
     * Authorised by a secret in the path, compared in constant time. The
     * provider has no way to sign a request we could verify without shipping
     * their public keys, and a long secret in a TLS-protected URL is what they
     * recommend.
     */
    public function webhook(string $secret): void
    {
        header('Content-Type: text/plain; charset=utf-8');

        $expected = trim((string) Config::get('mail.webhook_secret', ''));

        // An unset secret must close the door, not open it. Without this an
        // empty config would make the endpoint world-writable.
        if ($expected === '' || !hash_equals($expected, $secret)) {
            http_response_code(404);
            echo "Not found\n";
            return;
        }

        $raw     = file_get_contents('php://input') ?: '';
        $payload = json_decode($raw, true);

        if (!is_array($payload)) {
            http_response_code(400);
            echo "Expected JSON\n";
            return;
        }

        $type      = (string) ($payload['RecordType'] ?? '');
        $email     = trim((string) ($payload['Email'] ?? $payload['Recipient'] ?? ''));
        $messageId = trim((string) ($payload['MessageID'] ?? ''));

        // Always 200 from here on. A provider that gets an error retries, and
        // retrying an event we have decided to ignore helps nobody.
        $request = $messageId === '' ? null : Database::first(
            'SELECT id FROM review_requests WHERE provider_ref = :ref LIMIT 1',
            ['ref' => $messageId],
        );
        $requestId = $request === null ? null : (int) $request['id'];

        switch ($type) {
            case 'SpamComplaint':
                $this->suppressAndRecord($email, 'complaint', $requestId, 'complained', $messageId);
                break;

            case 'Bounce':
                // A soft bounce is a full mailbox or a server having a bad
                // afternoon; suppressing on that would throw away a good
                // address. Only a hard bounce means the address is wrong.
                $hard = (bool) ($payload['Inactive'] ?? false)
                    || in_array((string) ($payload['Type'] ?? ''), ['HardBounce', 'BadEmailAddress'], true);

                if ($hard) {
                    $this->suppressAndRecord($email, 'bounce', $requestId, 'bounced', $messageId);
                } elseif ($requestId !== null) {
                    ReviewRequests::event($requestId, 'bounced', $messageId, ['soft' => true]);
                }
                break;

            case 'SubscriptionChange':
                if ((bool) ($payload['SuppressSending'] ?? false)) {
                    $this->suppressAndRecord($email, 'unsubscribe', $requestId, 'stop', $messageId);
                }
                break;

            case 'Delivery':
                if ($requestId !== null) {
                    Database::run(
                        'UPDATE review_requests SET status = \'delivered\'
                          WHERE id = :id AND status = \'sent\'',
                        ['id' => $requestId],
                    );
                    ReviewRequests::event($requestId, 'delivered', $messageId, []);
                }
                break;
        }

        echo "OK\n";
    }

    // -- Internals ---------------------------------------------------------

    private function suppressAndRecord(
        string $email,
        string $reason,
        ?int $requestId,
        string $eventType,
        string $messageId,
    ): void {
        if ($email !== '') {
            ReviewRequests::suppress($email, $reason);
        }
        if ($requestId !== null) {
            ReviewRequests::event($requestId, $eventType, $messageId !== '' ? $messageId : null, []);
        }
    }

    /** @return array<string,mixed>|null */
    private function contactFromToken(string $token): ?array
    {
        $contactId = Tokens::readUnsubscribe($token);

        return $contactId === null ? null : ReviewRequests::findContactForUnsubscribe($contactId);
    }
}
